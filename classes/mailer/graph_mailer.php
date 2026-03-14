<?php
namespace local_msgraph_api_mailer\mailer;

defined('MOODLE_INTERNAL') || die();

use local_msgraph_api_mailer\api\graph_client;

class graph_mailer {
    private $client;

    public function __construct() {
        $this->client = new graph_client();
    }

    public function send($emaildata) {
        try {
            // Extract email data
            $to = $this->extract_recipients($emaildata);
            $subject = $emaildata->get_subject();
            $body = $this->format_body($emaildata);
            $from = $emaildata->get_from();
            $attachments = $this->extract_attachments($emaildata);

            // Send via Graph API
            $result = $this->client->send_email($to, $subject, $body, $from, $attachments);

            // Log if enabled
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                $this->log_email($to, $subject, $result['success'], $result['response']);
            }

            return $result['success'];
        } catch (\Exception $e) {
            // Log error
            if (get_config('local_msgraph_api_mailer', 'log_emails')) {
                $this->log_email($to ?? [], $subject ?? 'Unknown', false, $e->getMessage());
            }

            // Fallback to SMTP if enabled
            if (get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
                return $this->fallback_to_smtp($emaildata);
            }

            throw $e;
        }
    }

    private function extract_recipients($emaildata) {
        $to = [];
        $to_addrs = $emaildata->get_to();

        if (is_array($to_addrs)) {
            foreach ($to_addrs as $addr) {
                if (method_exists($addr, 'get_address')) {
                    $to[] = $addr->get_address();
                } elseif (isset($addr->address)) {
                    $to[] = $addr->address;
                } elseif (is_string($addr)) {
                    $to[] = $addr;
                }
            }
        }

        return $to;
    }

    private function format_body($emaildata) {
        $body = $emaildata->get_body();

        if (empty($body)) {
            return '<p>No message body</p>';
        }

        // If body is plain text, convert to HTML
        if (strpos($body, '<') === false) {
            $body = nl2br(htmlspecialchars($body));
        }

        // Add basic styling
        $body = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">' . $body . '</div>';

        return $body;
    }

    private function extract_attachments($emaildata) {
        $attachments = [];

        try {
            if (method_exists($emaildata, 'get_attachments')) {
                $attachs = $emaildata->get_attachments();
                if (is_array($attachs)) {
                    foreach ($attachs as $attach) {
                        $attachments[] = [
                            'filename' => method_exists($attach, 'getFilename') ? $attach->getFilename() : (isset($attach->filename) ? $attach->filename : 'attachment'),
                            'filepath' => method_exists($attach, 'getFilePath') ? $attach->getFilePath() : (isset($attach->filepath) ? $attach->filepath : ''),
                            'mimetype' => method_exists($attach, 'getMimeType') ? $attach->getMimeType() : (isset($attach->mimetype) ? $attach->mimetype : 'application/octet-stream')
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Attachment extraction failed, continue without attachments
        }

        return $attachments;
    }

    private function log_email($to, $subject, $success, $response = '') {
        global $DB;

        try {
            $record = new \stdClass();
            $record->recipients = json_encode($to);
            $record->subject = $subject;
            $record->status = $success ? 1 : 0;
            $record->response = substr($response, 0, 2000);
            $record->timecreated = time();

            // Try to insert if table exists
            $DB->insert_record('local_msgraph_api_mailer_log', $record, false);
        } catch (\Exception $e) {
            // Table might not exist yet, ignore logging error
        }
    }

    private function fallback_to_smtp($emaildata) {
        // Use Moodle's default mailer via phpmailer
        try {
            // Create a new phpmailer instance
            $mail = new \phpmailer\phpmailer\phpmailer(true);

            // Configure from settings
            $mail->isSMTP();
            $mail->Host = get_config('local_msgraph_api_mailer', 'smtphost') ?? 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = get_config('local_msgraph_api_mailer', 'smtpuser') ?? '';
            $mail->Password = get_config('local_msgraph_api_mailer', 'smtppass') ?? '';
            $mail->SMTPSecure = get_config('local_msgraph_api_mailer', 'smtpsecure') ?? 'tls';
            $mail->Port = get_config('local_msgraph_api_mailer', 'smtpport') ?? 587;

            // Set email data
            $mail->setFrom($emaildata->get_from() ?? get_config('moodle', 'noreplyaddress'));
            foreach ($emaildata->get_to() as $addr) {
                $mail->addAddress($addr->address ?? $addr);
            }
            $mail->Subject = $emaildata->get_subject();
            $mail->Body = $emaildata->get_body();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\Exception $e) {
            return false;
        }
    }
}
