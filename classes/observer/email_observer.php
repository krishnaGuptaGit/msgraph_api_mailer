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
 * Event observer for email-related Moodle events.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\observer;

use core\event\message_sent;
use local_msgraph_api_mailer\api\graph_client;

/**
 * Event observer for email-related Moodle events.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_observer {
    /**
     * Observer for message_sent event.
     *
     * @param message_sent $event The message_sent event instance.
     */
    public static function handle_message_sent(message_sent $event) {
        if (!get_config('local_msgraph_api_mailer', 'enabled')) {
            return;
        }

        try {
            $client = new graph_client();

            // Get recipient email.
            $userto   = $event->get_user();
            $toemail  = $userto->email;

            // Get sender.
            $userfrom  = $event->other['userfrom'] ?? null;
            $fromemail = $userfrom->email ?? get_config('moodle', 'noreplyaddress');

            // Get message details.
            $subject         = $event->other['subject'] ?? '';
            $fullmessage     = $event->other['fullmessage'] ?? '';
            $fullmessagehtml = $event->other['fullmessagehtml'] ?? '';

            // Use HTML message if available, otherwise use plain text.
            $body = !empty($fullmessagehtml) ? $fullmessagehtml : nl2br(htmlspecialchars($fullmessage));

            // Send via Graph API.
            $result = $client->send_email($toemail, $subject, $body);

            // Log if enabled.
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                self::log_email($toemail, $subject, $result['success']);
            }
        } catch (\Exception $e) {
            // Silent fail — do not break Moodle functionality.
            debugging('MS Graph Mailer error: ' . $e->getMessage());
        }
    }

    /**
     * Log email to database.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param bool   $success True if email was sent successfully.
     */
    private static function log_email($to, $subject, $success) {
        global $DB;

        $record              = new \stdClass();
        $record->recipients  = json_encode([$to]);
        $record->subject     = $subject;
        $record->status      = $success ? 1 : 0;
        $record->timecreated = time();

        try {
            $DB->insert_record('local_msgraph_api_mailer_log', $record);
        } catch (\Exception $e) {
            // Silently ignore logging errors.
            unset($e);
        }
    }
}
