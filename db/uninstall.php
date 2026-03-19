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
 * Plugin uninstall hook for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin uninstall hook — removes the phpmailer_init patch from
 * moodle_phpmailer.php, restoring the original Moodle file.
 *
 * @package local_msgraph_api_mailer
 * @return bool True on success.
 */
function xmldb_local_msgraph_api_mailer_uninstall() {
    require_once(__DIR__ . '/../lib.php');
    local_msgraph_api_mailer_remove_phpmailer_patch();
    return true;
}
