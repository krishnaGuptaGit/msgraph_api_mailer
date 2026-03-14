<?php
namespace local_msgraph_api_mailer\hook;

/**
 * Hook callbacks for local_msgraph_api_mailer.
 */
class after_config_callbacks {

    /**
     * Re-applies the moodle_phpmailer.php patch automatically if a Moodle
     * upgrade has overwritten the file since the plugin was installed.
     *
     * @param \core\hook\after_config $hook
     */
    public static function check_phpmailer_patch(\core\hook\after_config $hook): void {
        global $CFG;

        $filepath = $CFG->dirroot . '/lib/phpmailer/moodle_phpmailer.php';
        if (!is_readable($filepath)) {
            return;
        }

        $content = file_get_contents($filepath);
        if (strpos($content, "get_plugins_with_function('phpmailer_init')") === false) {
            require_once $CFG->dirroot . '/local/msgraph_api_mailer/lib.php';
            local_msgraph_api_mailer_apply_phpmailer_patch();
        }
    }
}
