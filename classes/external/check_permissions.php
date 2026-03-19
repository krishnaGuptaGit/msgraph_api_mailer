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
 * External function: check_permissions.
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
 * Tests OAuth2 token retrieval and verifies the Mail.Send permission in Azure AD.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_permissions extends external_api {

    /**
     * Describes input parameters (none required).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Attempt an OAuth2 token fetch and return pass/fail with a message.
     *
     * @return array {success: bool, message: string}
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        self::validate_parameters(self::execute_parameters(), []);

        $tenantid     = get_config('local_msgraph_api_mailer', 'tenant_id');
        $clientid     = get_config('local_msgraph_api_mailer', 'client_id');
        $clientsecret = get_config('local_msgraph_api_mailer', 'client_secret');
        $senderemail  = get_config('local_msgraph_api_mailer', 'sender_email');

        if (empty($tenantid) || empty($clientid) || empty($clientsecret) || empty($senderemail)) {
            return [
                'success' => false,
                'message' => get_string('missing_config', 'local_msgraph_api_mailer'),
            ];
        }

        try {
            $client = new \local_msgraph_api_mailer\api\graph_client();
            $result = $client->test_connection();

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => get_string('permission_check_success', 'local_msgraph_api_mailer'),
                ];
            }

            return [
                'success' => false,
                'message' => get_string('permission_check_failed', 'local_msgraph_api_mailer') .
                             ' ' . $result['message'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('permission_check_failed', 'local_msgraph_api_mailer') .
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
            'success' => new external_value(PARAM_BOOL, 'Whether the permission check succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Human-readable result message'),
        ]);
    }
}
