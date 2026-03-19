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
 * Changelog page for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/changelog.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(
    get_string('pluginname', 'local_msgraph_api_mailer') . ' - ' .
    get_string('changelog_title', 'local_msgraph_api_mailer')
);
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$logurl      = new moodle_url('/local/msgraph_api_mailer/emaillog.php');

echo $OUTPUT->header();

$changelogtitle = get_string('changelog_title', 'local_msgraph_api_mailer');
$pluginname     = get_string('pluginname', 'local_msgraph_api_mailer');
$settingsurlout = $settingsurl->out(false);
$lgurlout       = $logurl->out(false);
$backtosettings = get_string('back_to_settings', 'local_msgraph_api_mailer');
$viewemaillogs  = get_string('view_email_logs', 'local_msgraph_api_mailer');

echo '<style>
.cl-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 28px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    max-width: 860px;
}
.cl-card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 14px 22px;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cl-card-header h4 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.cl-badge-version {
    background: #0078d4; color: #fff; padding: 2px 10px;
    border-radius: 12px; font-size: .78rem; font-weight: 600;
}
.cl-badge-stable {
    background: #28a745; color: #fff; padding: 2px 8px;
    border-radius: 12px; font-size: .72rem; font-weight: 600;
}
.cl-badge-latest {
    background: #fd7e14; color: #fff; padding: 2px 8px;
    border-radius: 12px; font-size: .72rem; font-weight: 600;
}
.cl-body { padding: 18px 22px; }
.cl-body ul { margin: 6px 0 0 0; padding-left: 20px; }
.cl-body ul li { margin-bottom: 5px; font-size: .9rem; line-height: 1.5; }
.cl-date { font-size: .8rem; color: #6c757d; margin-left: auto; }
.cl-type-new      { color: #0078d4; font-weight: 600; }
.cl-type-fix      { color: #28a745; font-weight: 600; }
.cl-type-improved { color: #6f42c1; font-weight: 600; }
</style>';

echo '<div style="max-width:860px; margin:0 auto;">';

// Header card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<i class="fa fa-history text-primary"></i>';
echo '<h4>' . $changelogtitle . '</h4>';
echo '<span class="cl-date">' . $pluginname . '</span>';
echo '</div>';
echo '</div>';

// V1.1.5 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.5</span>';
echo '<h4>Prechecker Compliance — Minor Fix</h4>';
echo '<span class="cl-badge-latest">Latest</span>';
echo '<span class="cl-date">2026-03-16</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Inline comment capitalisation</strong>' .
    ' &mdash; Six section comments in <code>changelog.php</code> used a lowercase' .
    ' <code>v</code> prefix (e.g. <code>// v1.1.4 card.</code>). phpcs requires inline' .
    ' comments to start with a capital letter, digit, or <code>...</code> sequence.' .
    ' Changed to <code>// V1.x.x card.</code> to satisfy the rule.</li>';
echo '</ul></div>';
echo '</div>';

// V1.1.4 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.4</span>';
echo '<h4>Prechecker Compliance Release</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-15</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Template-style PHP eliminated</strong>' .
    ' &mdash; Converted <code>changelog.php</code>, <code>emaillog.php</code>, and' .
    ' <code>index.php</code> from mixed HTML/PHP template style to pure PHP <code>echo</code>' .
    ' output. Each <code>&lt;?php ?&gt;</code> block reopened after a <code>?&gt;</code>' .
    ' was treated by phpcs as a new file section requiring a docblock, producing 220 errors.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Anonymous closures removed</strong>' .
    ' &mdash; Replaced <code>array_filter()</code> closures in <code>emaillog.php</code>' .
    ' with explicit <code>foreach</code> loops. phpcs required <code>@copyright</code> and' .
    ' <code>@license</code> tags on every closure, generating 120 repeated errors at a' .
    ' single line.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Decorator comment lines</strong>' .
    ' &mdash; Removed <code>// ---</code> separator lines in <code>lib.php</code> that did' .
    ' not end with a full stop, exclamation mark, or question mark.</li>';
echo '</ul></div>';
echo '</div>';

// V1.1.3 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.3</span>';
echo '<h4>Prechecker Compliance Release</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-15</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>AJAX bootstrap</strong> &mdash; Removed' .
    ' <code>ob_start()</code>/<code>ob_end_clean()</code> wrappers around <code>config.php</code>' .
    ' inclusion in <code>ajax.php</code>.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Unexpected MOODLE_INTERNAL guard</strong>' .
    ' &mdash; Removed redundant <code>defined(\'MOODLE_INTERNAL\') || die()</code> from' .
    ' <code>lib.php</code>.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Empty catch blocks</strong> &mdash; Added' .
    ' <code>unset($e)</code> to silent catch blocks in <code>lib.php</code>,' .
    ' <code>graph_mailer.php</code>, and <code>email_observer.php</code>.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Inline comment format</strong> &mdash;' .
    ' Capitalised and punctuated all inline comments across multiple files.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Multi-line define() call</strong>' .
    ' &mdash; Restructured <code>define(\'LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT\', ...)</code>' .
    ' so the opening parenthesis is the last character on its line.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Lang strings alphabetically sorted</strong>' .
    ' &mdash; Removed all section <code>//</code> comments and sorted every string key in' .
    ' <code>lang/en/local_msgraph_api_mailer.php</code> alphabetically.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>CRLF line endings</strong> &mdash; Converted' .
    ' Windows CRLF to Unix LF in <code>configcheckbox_with_required.php</code>' .
    ' and <code>configpassword_placeholder.php</code>.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Blank lines at end of control structures</strong>' .
    ' &mdash; Removed blank lines before closing braces in <code>lib.php</code>.</li>';
echo '</ul></div>';
echo '</div>';

// V1.1.2 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.2</span>';
echo '<h4>Bug Fix Release</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-15</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>PHP code visible on page</strong>' .
    ' &mdash; Removed redundant <code>global $OUTPUT, $PAGE</code> declaration from' .
    ' <code>changelog.php</code> that caused Moodle debug mode to display raw PHP source' .
    ' in the page body.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Em dash encoding</strong>' .
    ' &mdash; Replaced multi-byte em dash character in <code>$PAGE-&gt;set_title()</code>' .
    ' across all three page files with a plain hyphen to prevent encoding issues on some' .
    ' server configurations.</li>';
echo '</ul></div>';
echo '</div>';

// V1.1.1 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.1</span>';
echo '<h4>Prechecker Compliance</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-15</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Moodle prechecker compliance</strong>' .
    ' &mdash; All PHP and JavaScript files now fully pass the Moodle plugins directory' .
    ' prechecker: line length, inline comment punctuation, Bootstrap 5 classes, and' .
    ' coding standard checks.</li>';
echo '</ul></div>';
echo '</div>';

// V1.1.0 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.1.0</span>';
echo '<h4>Feature Release</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-14</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Send Test with Attachment</strong>' .
    ' &mdash; Settings page now has a dedicated button that sends a real <code>.xlsx</code>' .
    ' test file to verify the full attachment pipeline.</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Connection Status Badge</strong>' .
    ' &mdash; Settings page auto-checks Azure connectivity on load and shows a live green/red' .
    ' badge &mdash; no button click required.</li>';
echo '<li><span class="cl-type-new">NEW</span>' .
    ' <strong>Large Attachment Support (up to 150 MB)</strong> &mdash; Attachments &ge; 3 MB' .
    ' are now uploaded via the Microsoft Graph upload session API (chunked PUT) instead of' .
    ' inline base64, bypassing the <code>sendMail</code> payload limit.</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Attachment indicator in Email Log</strong>' .
    ' &mdash; Email log table and CSV export now show whether each email had an attachment' .
    ' (paperclip icon / "Yes"/"No" column).</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Changelog page</strong> &mdash; This page.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Attachment filename</strong>' .
    ' &mdash; Was using <code>$attach[1]</code> (temp basename like <code>tempup_XYZ</code>);' .
    ' now correctly uses <code>$attach[2]</code> (PHPMailer display name).</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Attachment extension missing</strong>' .
    ' &mdash; MIME type is now detected from in-memory file content (magic bytes + OOXML ZIP' .
    ' marker search) instead of reopening the temp file. Extension is automatically appended' .
    ' when missing.</li>';
echo '<li><span class="cl-type-fix">FIX</span> <strong>Uninstall error</strong>' .
    ' &mdash; <code>db/uninstall.php</code> now <code>require_once</code>s <code>lib.php</code>' .
    ' before calling patch-removal functions (Moodle does not auto-include <code>lib.php</code>' .
    ' before uninstall hooks).</li>';
echo '</ul></div>';
echo '</div>';

// V1.0.0 card.
echo '<div class="cl-card">';
echo '<div class="cl-card-header">';
echo '<span class="cl-badge-version">v1.0.0</span>';
echo '<h4>Initial Stable Release</h4>';
echo '<span class="cl-badge-stable">Stable</span>';
echo '<span class="cl-date">2026-03-13</span>';
echo '</div>';
echo '<div class="cl-body"><ul>';
echo '<li><span class="cl-type-new">NEW</span>' .
    ' Route all Moodle outgoing emails through <strong>Microsoft Graph API</strong>' .
    ' (replaces SMTP).</li>';
echo '<li><span class="cl-type-new">NEW</span>' .
    ' OAuth2 client credentials flow &mdash; token cached per request with 5-minute expiry' .
    ' buffer.</li>';
echo '<li><span class="cl-type-new">NEW</span>' .
    ' Respects Moodle\'s <strong>email diverting</strong> setting' .
    ' (<code>$CFG-&gt;divertallemailsto</code>).</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>SMTP fallback</strong>' .
    ' &mdash; optional automatic fallback to PHPMailer/SMTP on Graph API failure.</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Email log viewer</strong>' .
    ' &mdash; searchable, filterable table of all sent/failed emails with CSV export.</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Test &amp; Validate page</strong>' .
    ' &mdash; Check Permissions and Send Test Email from the admin UI.</li>';
echo '<li><span class="cl-type-new">NEW</span> <strong>Mandatory field validation</strong>' .
    ' &mdash; Cannot enable the plugin without all Azure credentials filled in.</li>';
echo '<li><span class="cl-type-new">NEW</span>' .
    ' Moodle 5.x compatibility &mdash; <code>phpmailer_init</code> hook restored via' .
    ' <code>moodle_phpmailer.php</code> patch (auto-applied on install, auto-verified via' .
    ' <code>after_config</code> hook).</li>';
echo '</ul></div>';
echo '</div>';

// Navigation.
echo '<div class="mb-4" style="display:flex; gap:8px;">';
echo '<a href="' . $settingsurlout . '" class="btn btn-secondary">';
echo '<i class="fa fa-arrow-left"></i> ' . $backtosettings;
echo '</a>';
echo '<a href="' . $lgurlout . '" class="btn btn-outline-secondary">';
echo '<i class="fa fa-list"></i> ' . $viewemaillogs;
echo '</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
