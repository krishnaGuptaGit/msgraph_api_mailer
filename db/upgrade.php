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
 * Plugin upgrade steps for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_msgraph_api_mailer plugin database schema.
 *
 * @package local_msgraph_api_mailer
 * @param int $oldversion Version number before the upgrade.
 * @return bool True on success.
 */
function xmldb_local_msgraph_api_mailer_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026031301) {
        // Rename column `to` (SQL reserved word) to `recipients`.
        $table = new xmldb_table('local_msgraph_api_mailer_log');
        $field = new xmldb_field('to', XMLDB_TYPE_TEXT, null, null, null, null, null, 'id');

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'recipients');
        }

        upgrade_plugin_savepoint(true, 2026031301, 'local', 'msgraph_api_mailer');
    }

    if ($oldversion < 2026031400) {
        // Add has_attachment column to track emails with attachments.
        $table = new xmldb_table('local_msgraph_api_mailer_log');
        $field = new xmldb_field('has_attachment', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026031400, 'local', 'msgraph_api_mailer');
    }

    return true;
}
