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
 * External function: get_connection_status.
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
 * Lightweight connection-status check used by the auto-loading badge on the settings page.
 * Returns a boolean connected flag and a short message instead of a full test result.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_connection_status extends external_api {
    /**
     * Describes input parameters (none required).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return connected/disconnected state for the settings-page badge.
     *
     * @return array {connected: bool, message: string}
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        self::validate_parameters(self::execute_parameters(), []);

        // Delegate to check_permissions and reformat for the badge.
        $result = check_permissions::execute();

        return [
            'connected' => $result['success'],
            'message'   => $result['message'],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'connected' => new external_value(PARAM_BOOL, 'True when the plugin can reach Microsoft Graph API'),
            'message'   => new external_value(PARAM_TEXT, 'Short human-readable status message'),
        ]);
    }
}
