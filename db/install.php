<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin install hook — patches moodle_phpmailer.php to restore the
 * phpmailer_init callback that was present in Moodle < 5.x.
 */
function xmldb_local_msgraph_api_mailer_install() {
    local_msgraph_api_mailer_apply_phpmailer_patch();
    return true;
}
