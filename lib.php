<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Core library functions for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Check if MS Graph Mailer is enabled and fully configured.
 *
 * @return bool True if enabled and all required settings are set.
 */
function local_msgraph_api_mailer_is_enabled() {
    if (!get_config('local_msgraph_api_mailer', 'enabled')) {
        return false;
    }
    return !empty(get_config('local_msgraph_api_mailer', 'tenant_id'))
        && !empty(get_config('local_msgraph_api_mailer', 'client_id'))
        && !empty(get_config('local_msgraph_api_mailer', 'client_secret'))
        && !empty(get_config('local_msgraph_api_mailer', 'sender_email'));
}

/**
 * Moodle PHPMailer hook — called by email_to_user() for EVERY outgoing email.
 *
 * Replaces PHPMailer/SMTP delivery with Microsoft Graph API.
 * Respects Moodle's "Email diverting" setting ($CFG->divertallemailsto).
 * All sends (success and failure) are logged unconditionally.
 *
 * @param \PHPMailer\PHPMailer\PHPMailer $mail Fully configured PHPMailer instance.
 */
function local_msgraph_api_mailer_phpmailer_init($mail) {
    if (!local_msgraph_api_mailer_is_enabled()) {
        return; // Plugin disabled or not configured — fall through to SMTP.
    }

    // Extract recipients, subject, and body from the PHPMailer object.
    $recipients = array_keys($mail->getAllRecipientAddresses());
    $subject    = $mail->Subject;
    // Prefer HTML body; fall back to plain-text body.
    $body       = !empty($mail->Body) ? $mail->Body : nl2br(htmlspecialchars($mail->AltBody ?? ''));

    if (empty($recipients) || $subject === '') {
        return; // Nothing to send — let PHPMailer proceed.
    }

    // Apply Moodle's email diverting setting ($CFG->divertallemailsto).
    // This mirrors Moodle core behaviour: all outgoing emails are redirected
    // to the configured divert address unless the recipient matches an exception.
    global $CFG;
    if (!empty($CFG->divertallemailsto)) {
        $divertto   = trim($CFG->divertallemailsto);
        $exceptions = [];
        if (!empty($CFG->divertallemailsexcept)) {
            $exceptions = preg_split('/[\s,]+/', $CFG->divertallemailsexcept, -1, PREG_SPLIT_NO_EMPTY);
        }
        $diverted = [];
        foreach ($recipients as $recipient) {
            $excepted = false;
            foreach ($exceptions as $pattern) {
                if (stripos($recipient, $pattern) !== false) {
                    $excepted = true;
                    break;
                }
            }
            $diverted[] = $excepted ? $recipient : $divertto;
        }
        $recipients = array_values(array_unique($diverted));
    }

    require_once(__DIR__ . '/classes/api/graph_client.php');

    // Extract attachments from the PHPMailer object.
    // Each attachment array has 8 fields: path/content (0), name (1), basename (2),
    // encoding (3), mimetype (4), isString (5), disposition (6), cid (7).
    $attachments = [];
    foreach ($mail->getAttachments() as $attach) {
        if (($attach[6] ?? 'attachment') === 'inline') {
            continue; // Skip embedded images (e.g. logo CIDs).
        }
        $attachments[] = [
            'filepath' => $attach[0], // File path OR raw string content when isstring=true.
            'filename' => $attach[2] ?: basename($attach[0]), // Display name or fallback to basename.
            'mimetype' => $attach[4] ?: 'application/octet-stream',
            'isstring' => !empty($attach[5]),
        ];
    }

    $hasattachment = !empty($attachments) ? 1 : 0;

    try {
        $client = new \local_msgraph_api_mailer\api\graph_client();
        $result = $client->send_email($recipients, $subject, $body, null, $attachments);

        if ($result['success']) {
            // Log successful send (always, when plugin is enabled).
            local_msgraph_api_mailer_log_record($recipients, $subject, 1, 'Sent via MS Graph API', $hasattachment);

            // Prevent PHPMailer from sending a duplicate via SMTP.
            // On Linux/Mac (Docker): switch to sendmail mode pointing at /bin/true.
            // /bin/true accepts any stdin and exits 0, so PHPMailer::send() returns
            // true — email_to_user() reports success AND Moodle's built-in
            // "Test outgoing mail configuration" also passes.
            // On Windows (no /bin/true): fall back to clearing recipients; PHPMailer
            // throws "no recipients" but the email WAS already delivered via Graph API.
            if (is_executable('/bin/true')) {
                $mail->isSendmail();
                $mail->Sendmail = '/bin/true';
            } else {
                $mail->clearAllRecipients();
            }
        } else {
            // Graph API returned non-202; log failure and fall through to SMTP.
            local_msgraph_api_mailer_log_record(
                $recipients,
                $subject,
                0,
                'HTTP ' . $result['http_code'] . ' -- ' . substr($result['response'], 0, 500),
                $hasattachment
            );
            // If fallback is disabled, also clear recipients to prevent SMTP send.
            if (!get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
                $mail->clearAllRecipients();
            }
        }
    } catch (Exception $e) {
        // Graph API threw an exception (e.g. token failure, network error).
        local_msgraph_api_mailer_log_record($recipients, $subject, 0, $e->getMessage(), $hasattachment);
        // If SMTP fallback is disabled, prevent PHPMailer from sending too.
        if (!get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
            $mail->clearAllRecipients();
        }
        // If SMTP fallback is enabled, fall through — PHPMailer will attempt SMTP.
    }
}

/**
 * Write an email send record to the log table.
 * Only writes when the 'log_emails' setting is enabled (default: yes).
 *
 * @param array  $recipients     Array of recipient email addresses.
 * @param string $subject        Email subject.
 * @param int    $status         1 = sent, 0 = failed.
 * @param string $response       API response or error message.
 * @param int    $hasattachment  1 if the email had attachments, 0 otherwise.
 */
function local_msgraph_api_mailer_log_record($recipients, $subject, $status, $response, $hasattachment = 0) {
    // Only skip when explicitly set to '0'. Treat missing (false) as enabled — the default.
    if (get_config('local_msgraph_api_mailer', 'log_emails') === '0') {
        return;
    }
    global $DB;
    try {
        $record                 = new stdClass();
        $record->recipients     = json_encode($recipients);
        $record->subject        = $subject;
        $record->status         = (int) $status;
        $record->has_attachment = (int) $hasattachment;
        $record->response       = substr((string) $response, 0, 2000);
        $record->timecreated    = time();
        $DB->insert_record('local_msgraph_api_mailer_log', $record, false);
    } catch (Exception $e) {
        // Ignore logging errors (e.g. table not yet installed on first run).
        unset($e);
    }
}

/**
 * Cron task placeholder for future queue processing.
 *
 * @return bool True always.
 */
function local_msgraph_api_mailer_cron() {
    return true;
}

// Moodle PHPMailer patch helpers.

/** @var string Relative path (from dirroot) to the file we patch. */
define('LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL', '/lib/phpmailer/moodle_phpmailer.php');

/** @var string Unique string present in our injected block — used to detect if already patched. */
define('LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER', "get_plugins_with_function('phpmailer_init')");

/**
 * The exact string in postSend()'s else-branch that we use as the injection point.
 */
define('LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR', "        } else {\n            return parent::postSend();");

/** @var string Our hook block — replaces the anchor above. */
define(
    'LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT',
    "        } else {\n" .
    "            // Call phpmailer_init hooks so local plugins can intercept outgoing email\n" .
    "            // (restored by local_msgraph_api_mailer — remove this plugin to undo).\n" .
    "            \$pluginswithfunction = get_plugins_with_function('phpmailer_init');\n" .
    "            foreach (\$pluginswithfunction as \$plugins) {\n" .
    "                foreach (\$plugins as \$function) {\n" .
    "                    \$function(\$this);\n" .
    "                }\n" .
    "            }\n" .
    "            return parent::postSend();"
);

/**
 * Write $newcontent to $filepath and invalidate OPcache.
 *
 * @param string $filepath   Absolute path to the file to write.
 * @param string $newcontent New file content to write.
 */
function local_msgraph_api_mailer_write_phpmailer(string $filepath, string $newcontent): void {
    file_put_contents($filepath, $newcontent);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($filepath, true);
    }
}

/**
 * Inject the phpmailer_init hook into moodle_phpmailer::postSend().
 * Safe to call multiple times — skips if already patched.
 *
 * @return bool True on success or already patched, false on failure.
 */
function local_msgraph_api_mailer_apply_phpmailer_patch() {
    global $CFG;
    $filepath = $CFG->dirroot . LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL;

    if (!is_readable($filepath) || !is_writable($filepath)) {
        return false;
    }

    $content = file_get_contents($filepath);
    $ok      = true;

    if (strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER) === false) {
        $patched = str_replace(
            LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR,
            LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT,
            $content
        );
        $ok = ($patched !== $content);
        if ($ok) {
            local_msgraph_api_mailer_write_phpmailer($filepath, $patched);
        }
    }

    return $ok;
}

/**
 * Remove the phpmailer_init hook from moodle_phpmailer::postSend(),
 * restoring the original Moodle file. Called on plugin uninstall.
 *
 * @return bool True on success or if patch was not present.
 */
function local_msgraph_api_mailer_remove_phpmailer_patch() {
    global $CFG;
    $filepath = $CFG->dirroot . LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL;

    if (!is_readable($filepath) || !is_writable($filepath)) {
        return false;
    }

    $content  = file_get_contents($filepath);
    $ok       = true;

    if (strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER) !== false) {
        $restored = str_replace(
            LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT,
            LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR,
            $content
        );
        $ok = ($restored !== $content);
        if ($ok) {
            local_msgraph_api_mailer_write_phpmailer($filepath, $restored);
        }
    }

    return $ok;
}

// The after_config logic is registered via db/hooks.php using the Moodle 5.x
// hook system. See classes/hook/after_config_callbacks.php.
