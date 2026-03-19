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
 * Test and validate page for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(
    get_string('pluginname', 'local_msgraph_api_mailer') . ' - ' .
    get_string('test_validate', 'local_msgraph_api_mailer')
);
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->js_call_amd('local_msgraph_api_mailer/index', 'init');

// Read current configuration.
$enabled           = (bool) get_config('local_msgraph_api_mailer', 'enabled');
$tenantid          = get_config('local_msgraph_api_mailer', 'tenant_id');
$clientid          = get_config('local_msgraph_api_mailer', 'client_id');
$clientsecret      = get_config('local_msgraph_api_mailer', 'client_secret');
$senderemail       = get_config('local_msgraph_api_mailer', 'sender_email');
$senderdisplayname = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));
$isconfigured      = !empty($tenantid) && !empty($clientid) && !empty($clientsecret) && !empty($senderemail);

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);

echo $OUTPUT->header();

// Pre-compute dynamic values.
$settingsurlout      = $settingsurl->out(false);
$emaillogurl         = (new moodle_url('/local/msgraph_api_mailer/emaillog.php'))->out(false);
$disabledattr        = $isconfigured ? '' : ' disabled';
$enabledbadgeclass   = $enabled ? 'ok' : 'warn';
$enabledicon         = $enabled ? 'check' : 'pause';
$enabledtext         = $enabled
    ? get_string('plugin_enabled', 'local_msgraph_api_mailer')
    : get_string('plugin_disabled', 'local_msgraph_api_mailer');
$configbadgeclass    = $isconfigured ? 'ok' : 'err';
$configicon          = $isconfigured ? 'check' : 'times';
$configtext          = $isconfigured
    ? get_string('config_complete', 'local_msgraph_api_mailer')
    : get_string('config_incomplete', 'local_msgraph_api_mailer');
$senderdisplaynametext = get_string('sender_display_name_not_set', 'local_msgraph_api_mailer');

echo '<style>
.msgraph-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 24px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.msgraph-card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 14px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.msgraph-card-header h4 { margin: 0; font-size: 1.05rem; font-weight: 600; }
.msgraph-card-body { padding: 20px; }
.config-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .85rem;
    font-weight: 500;
}
.config-badge.ok   { background: #d4edda; color: #155724; }
.config-badge.err  { background: #f8d7da; color: #721c24; }
.config-badge.warn { background: #fff3cd; color: #856404; }
#permission-result, #email-result { margin-top: 14px; }
</style>';

echo '<div class="row justify-content-center" style="max-width:860px; margin: 0 auto;">';

// Configuration Status card.
echo '<div class="col-12">';
echo '<div class="msgraph-card">';
echo '<div class="msgraph-card-header">';
echo '<i class="fa fa-info-circle text-info"></i>';
echo '<h4>' . get_string('config_status', 'local_msgraph_api_mailer') . '</h4>';
echo '</div>';
echo '<div class="msgraph-card-body">';
echo '<div class="d-flex flex-wrap gap-2" style="gap:8px;">';

echo '<span class="config-badge ' . $enabledbadgeclass . '">';
echo '<i class="fa fa-' . $enabledicon . '-circle"></i>';
echo $enabledtext;
echo '</span>';

echo '<span class="config-badge ' . $configbadgeclass . '">';
echo '<i class="fa fa-' . $configicon . '-circle"></i>';
echo $configtext;
echo '</span>';

if (!empty($senderemail)) {
    echo '<span class="config-badge ok">';
    echo '<i class="fa fa-envelope"></i>';
    echo htmlspecialchars($senderemail, ENT_QUOTES);
    echo '</span>';
}

if (!empty($senderdisplayname)) {
    echo '<span class="config-badge ok">';
    echo '<i class="fa fa-user"></i>';
    echo htmlspecialchars($senderdisplayname, ENT_QUOTES);
    echo '</span>';
} else {
    echo '<span class="config-badge warn">';
    echo '<i class="fa fa-user-o"></i>';
    echo $senderdisplaynametext;
    echo '</span>';
}

echo '</div>';

if (!$isconfigured) {
    echo '<div class="alert alert-warning mt-3 mb-0">';
    echo get_string('missing_config', 'local_msgraph_api_mailer');
    echo ' <a href="' . $settingsurlout . '" class="alert-link">';
    echo get_string('go_to_settings', 'local_msgraph_api_mailer');
    echo '</a>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

// Check Permissions card.
echo '<div class="col-12">';
echo '<div class="msgraph-card">';
echo '<div class="msgraph-card-header">';
echo '<i class="fa fa-plug text-primary"></i>';
echo '<h4>' . get_string('connection_status', 'local_msgraph_api_mailer') . '</h4>';
echo '</div>';
echo '<div class="msgraph-card-body">';
echo '<p class="text-muted mb-3">' . get_string('connection_status_desc', 'local_msgraph_api_mailer') . '</p>';
echo '<button type="button" id="check-permissions-btn" class="btn btn-primary"' . $disabledattr . '>';
echo '<i class="fa fa-check-circle"></i> ';
echo get_string('check_permissions_btn', 'local_msgraph_api_mailer');
echo '</button>';
echo '<div id="permission-result"></div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Send Test Email card.
echo '<div class="col-12">';
echo '<div class="msgraph-card">';
echo '<div class="msgraph-card-header">';
echo '<i class="fa fa-envelope text-primary"></i>';
echo '<h4>' . get_string('send_test_email', 'local_msgraph_api_mailer') . '</h4>';
echo '</div>';
echo '<div class="msgraph-card-body">';
echo '<p class="text-muted mb-3">' . get_string('send_test_email_desc', 'local_msgraph_api_mailer') . '</p>';
echo '<div class="mb-3">';
echo '<label for="test-email-input" class="form-label">';
echo '<strong>' . get_string('recipient_email', 'local_msgraph_api_mailer') . '</strong>';
echo '</label>';
echo '<input type="email" id="test-email-input" class="form-control mb-2"';
echo ' placeholder="user@example.com" style="max-width:420px;"' . $disabledattr . '>';
echo '<div style="display:flex; gap:8px; flex-wrap:wrap;">';
echo '<button type="button" id="send-test-btn" class="btn btn-primary"' . $disabledattr . '>';
echo '<i class="fa fa-paper-plane"></i> ';
echo get_string('send_test_email_btn', 'local_msgraph_api_mailer');
echo '</button>';
echo '<button type="button" id="send-test-attachment-btn" class="btn btn-secondary"' . $disabledattr . '>';
echo '<i class="fa fa-paperclip"></i> ';
echo get_string('send_test_with_attachment_btn', 'local_msgraph_api_mailer');
echo '</button>';
echo '</div>';
echo '</div>';
echo '<div id="email-result"></div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Navigation.
echo '<div class="col-12 mb-4" style="display:flex; gap:8px; flex-wrap:wrap;">';
echo '<a href="' . $settingsurlout . '" class="btn btn-secondary">';
echo '<i class="fa fa-arrow-left"></i> ';
echo get_string('back_to_settings', 'local_msgraph_api_mailer');
echo '</a>';
echo '<a href="' . $emaillogurl . '" class="btn btn-outline-secondary">';
echo '<i class="fa fa-list"></i> ';
echo get_string('view_email_logs', 'local_msgraph_api_mailer');
echo '</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
