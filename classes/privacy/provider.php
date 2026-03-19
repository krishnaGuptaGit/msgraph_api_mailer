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
 * Privacy API implementation for MS Graph API Mailer.
 *
 * The plugin stores an outbound email log in local_msgraph_api_mailer_log.
 * Each row holds recipient email addresses and the email subject, which are
 * personal data. Rows are not linked to Moodle user IDs; user data requests
 * are matched by the user's primary email address against the recipients field.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for the MS Graph API Mailer plugin.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata describing what personal data this plugin stores.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_msgraph_api_mailer_log',
            [
                'recipients'     => 'privacy:metadata:local_msgraph_api_mailer_log:recipients',
                'subject'        => 'privacy:metadata:local_msgraph_api_mailer_log:subject',
                'status'         => 'privacy:metadata:local_msgraph_api_mailer_log:status',
                'has_attachment' => 'privacy:metadata:local_msgraph_api_mailer_log:has_attachment',
                'response'       => 'privacy:metadata:local_msgraph_api_mailer_log:response',
                'timecreated'    => 'privacy:metadata:local_msgraph_api_mailer_log:timecreated',
            ],
            'privacy:metadata:local_msgraph_api_mailer_log'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the given user.
     *
     * Log rows are matched by the user's primary email address appearing in
     * the JSON-encoded recipients field. The system context is returned when
     * at least one matching row is found.
     *
     * @param int $userid The Moodle user ID.
     * @return contextlist Contexts containing data for this user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $user = \core_user::get_user($userid, 'email');
        if (!$user || empty($user->email)) {
            return $contextlist;
        }

        $emailpattern = '%' . $DB->sql_like_escape($user->email) . '%';
        $sql = 'SELECT 1 FROM {local_msgraph_api_mailer_log} WHERE ' .
               $DB->sql_like('recipients', ':email', false);

        if ($DB->record_exists_sql($sql, ['email' => $emailpattern])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within the given context.
     *
     * Because log rows store recipients as a JSON string without Moodle user
     * IDs, a reverse lookup is not feasible. This method is intentionally
     * left as a no-op; deletion is handled per-user in delete_data_for_users().
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        // Reverse lookup from email address to user ID is not performed
        // because the recipients column stores JSON without Moodle user IDs.
    }

    /**
     * Export personal data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts for this user.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        if (empty($user->email)) {
            return;
        }

        $emailpattern = '%' . $DB->sql_like_escape($user->email) . '%';
        $sql = 'SELECT * FROM {local_msgraph_api_mailer_log}
                 WHERE ' . $DB->sql_like('recipients', ':email', false) . '
              ORDER BY timecreated ASC';

        $records = $DB->get_records_sql($sql, ['email' => $emailpattern]);
        if (empty($records)) {
            return;
        }

        $rows = [];
        foreach ($records as $record) {
            $rows[] = (object) [
                'recipients'     => $record->recipients,
                'subject'        => $record->subject,
                'status'         => $record->status
                                    ? get_string('log_status_sent', 'local_msgraph_api_mailer')
                                    : get_string('log_status_failed', 'local_msgraph_api_mailer'),
                'has_attachment' => transform::yesno((bool) $record->has_attachment),
                'response'       => $record->response,
                'timecreated'    => transform::datetime($record->timecreated),
            ];
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_msgraph_api_mailer'),
             get_string('email_logs', 'local_msgraph_api_mailer')],
            (object) ['records' => $rows]
        );
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * Only the system context is relevant; calling this purges the entire log.
     *
     * @param \context $context The context to delete data in.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_msgraph_api_mailer_log');
        }
    }

    /**
     * Delete personal data for the given user in the approved contexts.
     *
     * Removes log rows where the user's email address appears in the
     * JSON-encoded recipients field.
     *
     * @param approved_contextlist $contextlist Approved contexts for this user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        if (empty($user->email)) {
            return;
        }

        $emailpattern = '%' . $DB->sql_like_escape($user->email) . '%';
        $DB->delete_records_select(
            'local_msgraph_api_mailer_log',
            $DB->sql_like('recipients', ':email', false),
            ['email' => $emailpattern]
        );
    }

    /**
     * Delete personal data for a list of users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $user = \core_user::get_user($userid, 'email');
            if (!$user || empty($user->email)) {
                continue;
            }
            $emailpattern = '%' . $DB->sql_like_escape($user->email) . '%';
            $DB->delete_records_select(
                'local_msgraph_api_mailer_log',
                $DB->sql_like('recipients', ':email', false),
                ['email' => $emailpattern]
            );
        }
    }
}
