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
 * External services definition for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_msgraph_api_mailer_check_permissions' => [
        'classname'     => \local_msgraph_api_mailer\external\check_permissions::class,
        'description'   => 'Test OAuth2 token retrieval and verify Mail.Send permission in Azure AD.',
        'type'          => 'read',
        'capabilities'  => 'moodle/site:config',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_msgraph_api_mailer_get_connection_status' => [
        'classname'     => \local_msgraph_api_mailer\external\get_connection_status::class,
        'description'   => 'Return a lightweight connected/disconnected status for the settings badge.',
        'type'          => 'read',
        'capabilities'  => 'moodle/site:config',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_msgraph_api_mailer_send_test_email' => [
        'classname'     => \local_msgraph_api_mailer\external\send_test_email::class,
        'description'   => 'Send a test email (no attachment) via Microsoft Graph API.',
        'type'          => 'write',
        'capabilities'  => 'moodle/site:config',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'local_msgraph_api_mailer_send_test_email_attachment' => [
        'classname'     => \local_msgraph_api_mailer\external\send_test_email_attachment::class,
        'description'   => 'Send a test email with a real .xlsx attachment via Microsoft Graph API.',
        'type'          => 'write',
        'capabilities'  => 'moodle/site:config',
        'ajax'          => true,
        'loginrequired' => true,
    ],

];
