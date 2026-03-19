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
 * External function: send_test_email.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Sends a plain test email (no attachment) via Microsoft Graph API.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_test_email extends external_api {

    /**
     * Describes input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email' => new external_value(PARAM_EMAIL, 'Recipient email address for the test message'),
        ]);
    }

    /**
     * Send a test email and return a pass/fail result.
     *
     * @param  string $email Recipient email address.
     * @return array {success: bool, message: string}
     */
    public static function execute(string $email): array {
        global $CFG;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $params = self::validate_parameters(self::execute_parameters(), ['email' => $email]);
        $email  = $params['email'];

        require_once($CFG->dirroot . '/local/msgraph_api_mailer/lib.php');

        $senderemail    = get_config('local_msgraph_api_mailer', 'sender_email');
        $displaynamecfg = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));

        if (empty($senderemail)) {
            return [
                'success' => false,
                'message' => get_string('missing_config', 'local_msgraph_api_mailer'),
            ];
        }

        $subject = 'Test Email from MS Graph Mailer';
        $body    = '<h2>Test Email</h2>' .
                   '<p>This is a test email sent via Microsoft Graph API from Moodle.</p>' .
                   '<p><strong>Plugin:</strong> MS Graph Mailer</p>' .
                   '<p><strong>Sender Email:</strong> ' .
                       htmlspecialchars($senderemail, ENT_QUOTES) . '</p>' .
                   '<p><strong>Sender Display Name (configured):</strong> ' .
                       ($displaynamecfg !== ''
                           ? htmlspecialchars($displaynamecfg, ENT_QUOTES)
                           : '<em>Not configured</em>') . '</p>' .
                   '<p><strong>Moodle Version:</strong> ' . $CFG->release . '</p>' .
                   '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>' .
                   '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';

        try {
            $client = new \local_msgraph_api_mailer\api\graph_client();
            $result = $client->send_email($email, $subject, $body);

            if ($result['success']) {
                local_msgraph_api_mailer_log_record([$email], $subject, 1, 'Test email sent successfully');
                return [
                    'success' => true,
                    'message' => get_string('email_sent_success', 'local_msgraph_api_mailer'),
                ];
            }

            $errormsg = 'HTTP ' . $result['http_code'] . ' -- ' . substr($result['response'], 0, 500);
            local_msgraph_api_mailer_log_record([$email], $subject, 0, $errormsg);
            return [
                'success' => false,
                'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') .
                             ' HTTP ' . $result['http_code'] .
                             (!empty($result['response'])
                                 ? ' - ' . substr(strip_tags($result['response']), 0, 200)
                                 : ''),
            ];
        } catch (\Exception $e) {
            local_msgraph_api_mailer_log_record([$email], $subject, 0, $e->getMessage());
            return [
                'success' => false,
                'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') .
                             ' ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the test email was sent successfully'),
            'message' => new external_value(PARAM_TEXT, 'Human-readable result message'),
        ]);
    }
}
