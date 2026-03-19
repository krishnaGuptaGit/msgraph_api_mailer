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
 * Hook callbacks for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\hook;

/**
 * Hook callbacks for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config_callbacks {
    /**
     * Re-applies the moodle_phpmailer.php patch automatically if a Moodle
     * upgrade has overwritten the file since the plugin was installed.
     *
     * @param \core\hook\after_config $hook The after_config hook instance.
     */
    public static function check_phpmailer_patch(\core\hook\after_config $hook): void {
        global $CFG;

        $filepath = $CFG->dirroot . '/lib/phpmailer/moodle_phpmailer.php';

        if (!is_readable($filepath)) {
            $newstatus = 'not_readable';
        } else {
            $content = file_get_contents($filepath);
            if (strpos($content, "get_plugins_with_function('phpmailer_init')") !== false) {
                $newstatus = 'ok';
            } else {
                // Patch is missing — attempt to re-apply (e.g. after a Moodle upgrade).
                require_once($CFG->dirroot . '/local/msgraph_api_mailer/lib.php');
                $result    = local_msgraph_api_mailer_apply_phpmailer_patch();
                $newstatus = match ($result) {
                    'ok', 'already_patched' => 'reapplied',
                    'not_writable'          => 'failed_readonly',
                    'anchor_not_found'      => 'failed_anchor',
                    default                 => 'failed_unknown',
                };
            }
        }

        // Only write to DB when the status changes to avoid unnecessary writes on every request.
        if ($newstatus !== get_config('local_msgraph_api_mailer', 'patch_status')) {
            set_config('patch_status', $newstatus, 'local_msgraph_api_mailer');
        }
    }
}
