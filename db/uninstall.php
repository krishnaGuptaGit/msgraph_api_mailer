<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin uninstall hook — removes the phpmailer_init patch from
 * moodle_phpmailer.php, restoring the original Moodle file.
 */
function xmldb_local_msgraph_api_mailer_uninstall() {
    require_once(__DIR__ . '/../lib.php');
    local_msgraph_api_mailer_remove_phpmailer_patch();
    return true;
}
