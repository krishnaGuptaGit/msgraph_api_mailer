<?php
namespace local_msgraph_api_mailer\observer;

defined('MOODLE_INTERNAL') || die();

use core\event\message_sent;
use local_msgraph_api_mailer\api\graph_client;

class email_observer {

    /**
     * Observer for message_sent event
     * @param message_sent $event
     */
    public static function handle_message_sent(message_sent $event) {
        if (!get_config('local_msgraph_api_mailer', 'enabled')) {
            return;
        }

        try {
            $client = new graph_client();

            // Get recipient email
            $userto = $event->get_user();
            $to_email = $userto->email;

            // Get sender
            $userfrom = $event->other['userfrom'] ?? null;
            $from_email = $userfrom->email ?? get_config('moodle', 'noreplyaddress');

            // Get message details
            $subject = $event->other['subject'] ?? '';
            $fullmessage = $event->other['fullmessage'] ?? '';
            $fullmessagehtml = $event->other['fullmessagehtml'] ?? '';

            // Use HTML message if available, otherwise use plain text
            $body = !empty($fullmessagehtml) ? $fullmessagehtml : nl2br(htmlspecialchars($fullmessage));

            // Send via Graph API
            $result = $client->send_email($to_email, $subject, $body);

            // Log if enabled
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                self::log_email($to_email, $subject, $result['success']);
            }
        } catch (\Exception $e) {
            // Silent fail - don't break Moodle functionality
            debugging('MS Graph Mailer error: ' . $e->getMessage());
        }
    }

    /**
     * Log email to database
     */
    private static function log_email($to, $subject, $success) {
        global $DB;

        $record = new \stdClass();
        $record->recipients = json_encode([$to]);
        $record->subject = $subject;
        $record->status = $success ? 1 : 0;
        $record->timecreated = time();

        try {
            $DB->insert_record('local_msgraph_api_mailer_log', $record);
        } catch (\Exception $e) {
            // Table might not exist, ignore
        }
    }
}
