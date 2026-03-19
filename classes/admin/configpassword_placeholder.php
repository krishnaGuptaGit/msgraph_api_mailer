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
 * Admin password input with placeholder attribute support.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\admin;

/**
 * Admin password input with placeholder attribute support.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configpassword_placeholder extends \admin_setting_configpasswordunmask {
    /** @var string Placeholder text shown inside the input field. */
    protected $placeholder;

    /**
     * Constructor.
     *
     * @param string $name           Setting name.
     * @param string $visiblename    Visible name shown in admin UI.
     * @param string $description    Description text.
     * @param string $defaultsetting Default value.
     * @param string $placeholder    Placeholder text for the input.
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $placeholder = ''
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->placeholder = $placeholder;
    }

    /**
     * Output the HTML for this setting.
     *
     * @param mixed  $data  Current value.
     * @param string $query Search query string for highlighting.
     * @return string HTML output.
     */
    public function output_html($data, $query = '') {
        $html = parent::output_html($data, $query);
        if ($this->placeholder !== '') {
            $attr = 'placeholder="' . htmlspecialchars($this->placeholder, ENT_QUOTES) . '"';
            $html = str_replace('<input ', '<input ' . $attr . ' ', $html);
        }
        return $html;
    }
}
