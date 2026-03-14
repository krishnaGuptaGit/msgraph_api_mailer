<?php
defined('MOODLE_INTERNAL') || die();

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