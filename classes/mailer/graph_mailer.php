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
 * Graph mailer wrapper class for sending emails via Microsoft Graph API.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\mailer;

use local_msgraph_api_mailer\api\graph_client;

/**
 * Graph mailer wrapper class for sending emails via Microsoft Graph API.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph_mailer {
    /** @var graph_client Graph API client instance. */
    private $client;

    /**
     * Constructor — instantiates the Graph API client.
     */
    public function __construct() {
        $this->client = new graph_client();
    }

    /**
     * Send an email using the Graph API client.
     *
     * @param object $emaildata Email data object with getter methods.
     * @return bool True on success, false on failure (or throws when fallback is disabled).
     */
    public function send($emaildata) {
        try {
            // Extract email data.
            $to          = $this->extract_recipients($emaildata);
            $subject     = $emaildata->get_subject();
            $body        = $this->format_body($emaildata);
            $from        = $emaildata->get_from();
            $attachments = $this->extract_attachments($emaildata);

            // Send via Graph API.
            $result = $this->client->send_email($to, $subject, $body, $from, $attachments);

            // Log if enabled.
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                $this->log_email($to, $subject, $result['success'], $result['response']);
            }

            return $result['success'];
        } catch (\Exception $e) {
            // Log error.
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                $this->log_email($to ?? [], $subject ?? 'Unknown', false, $e->getMessage());
            }

            // Fallback to SMTP if enabled.
            if (get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
                return $this->fallback_to_smtp($emaildata);
            }

            throw $e;
        }
    }

    /**
     * Extract recipient email addresses from the email data object.
     *
     * @param object $emaildata Email data object.
     * @return array Array of recipient email address strings.
     */
    private function extract_recipients($emaildata) {
        $to      = [];
        $toaddrs = $emaildata->get_to();

        if (is_array($toaddrs)) {
            foreach ($toaddrs as $addr) {
                if (method_exists($addr, 'get_address')) {
                    $to[] = $addr->get_address();
                } else if (isset($addr->address)) {
                    $to[] = $addr->address;
                } else if (is_string($addr)) {
                    $to[] = $addr;
                }
            }
        }

        return $to;
    }

    /**
     * Format the email body as HTML.
     *
     * @param object $emaildata Email data object.
     * @return string HTML body string.
     */
    private function format_body($emaildata) {
        $body = $emaildata->get_body();

        if (empty($body)) {
            return '<p>No message body</p>';
        }

        // If body is plain text, convert to HTML.
        if (strpos($body, '<') === false) {
            $body = nl2br(htmlspecialchars($body));
        }

        // Add basic styling.
        $body = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">' . $body . '</div>';

        return $body;
    }

    /**
     * Extract attachments from the email data object.
     *
     * @param object $emaildata Email data object.
     * @return array Array of attachment arrays with filepath, filename, mimetype keys.
     */
    private function extract_attachments($emaildata) {
        $attachments = [];

        try {
            if (method_exists($emaildata, 'get_attachments')) {
                $attachs = $emaildata->get_attachments();
                if (is_array($attachs)) {
                    foreach ($attachs as $attach) {
                        $attachments[] = [
                            'filename' => method_exists($attach, 'getFilename')
                                ? $attach->getFilename()
                                : (isset($attach->filename) ? $attach->filename : 'attachment'),
                            'filepath' => method_exists($attach, 'getFilePath')
                                ? $attach->getFilePath()
                                : (isset($attach->filepath) ? $attach->filepath : ''),
                            'mimetype' => method_exists($attach, 'getMimeType')
                                ? $attach->getMimeType()
                                : (isset($attach->mimetype) ? $attach->mimetype : 'application/octet-stream'),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently ignore attachment extraction errors and continue without attachments.
            unset($e);
        }

        return $attachments;
    }

    /**
     * Log an email send attempt to the database.
     *
     * @param array  $to       Array of recipient email addresses.
     * @param string $subject  Email subject.
     * @param bool   $success  True if email was sent successfully.
     * @param string $response API response or error message.
     */
    private function log_email($to, $subject, $success, $response = '') {
        global $DB;

        try {
            $record              = new \stdClass();
            $record->recipients  = json_encode($to);
            $record->subject     = $subject;
            $record->status      = $success ? 1 : 0;
            $record->response    = substr($response, 0, 2000);
            $record->timecreated = time();

            // Try to insert if table exists.
            $DB->insert_record('local_msgraph_api_mailer_log', $record, false);
        } catch (\Exception $e) {
            // Silently ignore logging errors.
            unset($e);
        }
    }

    /**
     * Attempt to send the email via PHPMailer/SMTP as a fallback.
     *
     * @param object $emaildata Email data object.
     * @return bool True on success, false on failure.
     */
    private function fallback_to_smtp($emaildata) {
        // Use Moodle's default mailer via phpmailer.
        try {
            // Create a new phpmailer instance.
            $mail = new \phpmailer\phpmailer\phpmailer(true);

            // Configure from settings.
            $mail->isSMTP();
            $mail->Host       = get_config('local_msgraph_api_mailer', 'smtphost') ?? 'localhost';
            $mail->SMTPAuth   = true;
            $mail->Username   = get_config('local_msgraph_api_mailer', 'smtpuser') ?? '';
            $mail->Password   = get_config('local_msgraph_api_mailer', 'smtppass') ?? '';
            $mail->SMTPSecure = get_config('local_msgraph_api_mailer', 'smtpsecure') ?? 'tls';
            $mail->Port       = get_config('local_msgraph_api_mailer', 'smtpport') ?? 587;

            // Set email data.
            $mail->setFrom($emaildata->get_from() ?? get_config('moodle', 'noreplyaddress'));
            foreach ($emaildata->get_to() as $addr) {
                $mail->addAddress($addr->address ?? $addr);
            }
            $mail->Subject = $emaildata->get_subject();
            $mail->Body    = $emaildata->get_body();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\Exception $e) {
            return false;
        }
    }
}
