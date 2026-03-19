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
 * Email log viewer for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE;

// Filters.
$filterstatus  = optional_param('status', -1, PARAM_INT);    // Status: -1 = all, 0 = failed, 1 = sent.
$filterto      = optional_param('recipient', '', PARAM_TEXT);
$filtersubject = optional_param('subject', '', PARAM_TEXT);
$filterfrom    = optional_param('datefrom', '', PARAM_TEXT); // YYYY-MM-DD.
$filteruntil   = optional_param('dateto', '', PARAM_TEXT);   // YYYY-MM-DD.
$page          = optional_param('page', 0, PARAM_INT);
$export        = optional_param('export', 0, PARAM_INT);
$perpage       = 25;

// Build SQL WHERE clause (avoid the reserved-word 'to' column in SQL; filter it in PHP).
$where  = [];
$params = [];

if ($filterstatus >= 0) {
    $where[]          = 'status = :status';
    $params['status'] = $filterstatus;
}
if ($filtersubject !== '') {
    $where[]           = $DB->sql_like('subject', ':subject', false);
    $params['subject'] = '%' . $DB->sql_like_escape($filtersubject) . '%';
}
if ($filterfrom !== '') {
    $ts = strtotime($filterfrom);
    if ($ts !== false) {
        $where[]            = 'timecreated >= :datefrom';
        $params['datefrom'] = $ts;
    }
}
if ($filteruntil !== '') {
    $ts = strtotime($filteruntil);
    if ($ts !== false) {
        $where[]           = 'timecreated <= :dateto';
        $params['dateto']  = $ts + 86399; // End of that day.
    }
}

$wheresql = $where ? implode(' AND ', $where) : '';

// CSV export.
if ($export) {
    $all = $DB->get_records_select('local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC');

    // Apply recipient PHP-side filter.
    if ($filterto !== '') {
        $filtered = [];
        foreach ($all as $r) {
            if (stripos($r->recipients, $filterto) !== false) {
                $filtered[] = $r;
            }
        }
        $all = $filtered;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email_log_' . date('Ymd_His') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.
    fputcsv($fp, ['ID', 'Recipient(s)', 'Subject', 'Status', 'Attachment', 'Response', 'Date / Time']);
    foreach ($all as $r) {
        $recipients = json_decode($r->recipients, true);
        $recipients = is_array($recipients) ? implode(', ', $recipients) : $r->recipients;
        fputcsv($fp, [
            $r->id,
            $recipients,
            $r->subject,
            $r->status ? 'Sent' : 'Failed',
            !empty($r->has_attachment) ? 'Yes' : 'No',
            $r->response,
            date('Y-m-d H:i:s', $r->timecreated),
        ]);
    }
    fclose($fp);
    exit;
}

// Fetch records.
$allrecords = $DB->get_records_select('local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC');

// Apply recipient PHP-side filter (avoids SQL reserved-word issues with 'to' column).
if ($filterto !== '') {
    $filtered = [];
    foreach ($allrecords as $r) {
        if (stripos($r->recipients, $filterto) !== false) {
            $filtered[] = $r;
        }
    }
    $allrecords = $filtered;
}

$total   = count($allrecords);
$records = array_slice($allrecords, $page * $perpage, $perpage);

// Page setup.
$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/emaillog.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(
    get_string('pluginname', 'local_msgraph_api_mailer') . ' - ' .
    get_string('email_log_title', 'local_msgraph_api_mailer')
);
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$testurl     = new moodle_url('/local/msgraph_api_mailer/index.php');

// Build URL for paging bar (preserves current filters).
$pagebaseurl = new moodle_url('/local/msgraph_api_mailer/emaillog.php', array_filter([
    'status'    => $filterstatus >= 0 ? $filterstatus : null,
    'recipient' => $filterto !== '' ? $filterto : null,
    'subject'   => $filtersubject !== '' ? $filtersubject : null,
    'datefrom'  => $filterfrom !== '' ? $filterfrom : null,
    'dateto'    => $filteruntil !== '' ? $filteruntil : null,
], fn($v) => $v !== null));

// Build export URL.
$exportparams = array_filter([
    'status'    => $filterstatus >= 0 ? $filterstatus : null,
    'recipient' => $filterto !== '' ? $filterto : null,
    'subject'   => $filtersubject !== '' ? $filtersubject : null,
    'datefrom'  => $filterfrom !== '' ? $filterfrom : null,
    'dateto'    => $filteruntil !== '' ? $filteruntil : null,
    'export'    => 1,
], fn($v) => $v !== null);
$exporturl = new moodle_url('/local/msgraph_api_mailer/emaillog.php', $exportparams);

echo $OUTPUT->header();

// Pre-compute dynamic values for the filter form.
$settingsurlout  = $settingsurl->out(false);
$testurlout      = $testurl->out(false);
$exporturlout    = $exporturl->out(false);
$selall          = $filterstatus < 0 ? ' selected' : '';
$selsent         = $filterstatus === 1 ? ' selected' : '';
$selfailed       = $filterstatus === 0 ? ' selected' : '';
$valrecipient    = htmlspecialchars($filterto, ENT_QUOTES);
$valsubject      = htmlspecialchars($filtersubject, ENT_QUOTES);
$subjectph       = get_string('log_subject_placeholder', 'local_msgraph_api_mailer');
$valdatefrom     = htmlspecialchars($filterfrom, ENT_QUOTES);
$valdateto       = htmlspecialchars($filteruntil, ENT_QUOTES);
$logrecordscount = get_string('log_records_count', 'local_msgraph_api_mailer');
$exportbtnlabel  = get_string('log_btn_export', 'local_msgraph_api_mailer');

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
.log-badge-sent {
    background: #d4edda; color: #155724; padding: 2px 10px;
    border-radius: 12px; font-size: .78rem; font-weight: 600; white-space: nowrap;
}
.log-badge-failed {
    background: #f8d7da; color: #721c24; padding: 2px 10px;
    border-radius: 12px; font-size: .78rem; font-weight: 600; white-space: nowrap;
}
.log-response {
    max-width: 260px; overflow: hidden; text-overflow: ellipsis;
    white-space: nowrap; font-size: .8rem; color: #6c757d; cursor: help;
}
.filter-row       { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.filter-group     { display:flex; flex-direction:column; gap:3px; }
.filter-group label { font-size:.8rem; font-weight:600; margin:0; color:#495057; }
</style>';

echo '<div style="max-width:1140px; margin:0 auto;">';

// Filter card.
echo '<div class="msgraph-card">';
echo '<div class="msgraph-card-header">';
echo '<i class="fa fa-filter text-primary"></i>';
echo '<h4>' . get_string('log_filter_heading', 'local_msgraph_api_mailer') . '</h4>';
echo '</div>';
echo '<div class="msgraph-card-body">';
echo '<form method="get" action="">';
echo '<div class="filter-row">';

echo '<div class="filter-group">';
echo '<label for="fl-status">' . get_string('log_col_status', 'local_msgraph_api_mailer') . '</label>';
echo '<select id="fl-status" name="status" class="form-control form-control-sm" style="min-width:110px;">';
echo '<option value="-1"' . $selall . '>' . get_string('log_status_all', 'local_msgraph_api_mailer') . '</option>';
echo '<option value="1"' . $selsent . '>' . get_string('log_status_sent', 'local_msgraph_api_mailer') . '</option>';
echo '<option value="0"' . $selfailed . '>' . get_string('log_status_failed', 'local_msgraph_api_mailer') . '</option>';
echo '</select>';
echo '</div>';

echo '<div class="filter-group">';
echo '<label for="fl-recipient">' . get_string('log_col_recipient', 'local_msgraph_api_mailer') . '</label>';
echo '<input id="fl-recipient" type="text" name="recipient" class="form-control form-control-sm"';
echo ' placeholder="user@example.com" style="min-width:200px;" value="' . $valrecipient . '">';
echo '</div>';

echo '<div class="filter-group">';
echo '<label for="fl-subject">' . get_string('log_col_subject', 'local_msgraph_api_mailer') . '</label>';
echo '<input id="fl-subject" type="text" name="subject" class="form-control form-control-sm"';
echo ' placeholder="' . $subjectph . '" style="min-width:200px;" value="' . $valsubject . '">';
echo '</div>';

echo '<div class="filter-group">';
echo '<label for="fl-datefrom">' . get_string('log_filter_from', 'local_msgraph_api_mailer') . '</label>';
echo '<input id="fl-datefrom" type="date" name="datefrom" class="form-control form-control-sm"';
echo ' value="' . $valdatefrom . '">';
echo '</div>';

echo '<div class="filter-group">';
echo '<label for="fl-dateto">' . get_string('log_filter_to', 'local_msgraph_api_mailer') . '</label>';
echo '<input id="fl-dateto" type="date" name="dateto" class="form-control form-control-sm"';
echo ' value="' . $valdateto . '">';
echo '</div>';

echo '<div class="filter-group" style="flex-direction:row; gap:6px; padding-top:1px;">';
echo '<button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">';
echo '<i class="fa fa-search"></i> ' . get_string('log_btn_filter', 'local_msgraph_api_mailer');
echo '</button>';
echo '<a href="?" class="btn btn-secondary btn-sm" style="align-self:flex-end;">';
echo '<i class="fa fa-times"></i> ' . get_string('log_btn_clear', 'local_msgraph_api_mailer');
echo '</a>';
echo '</div>';

echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

// Log table card.
echo '<div class="msgraph-card">';
echo '<div class="msgraph-card-header" style="justify-content:space-between;">';
echo '<div style="display:flex; align-items:center; gap:8px;">';
echo '<i class="fa fa-list text-primary"></i>';
echo '<h4>' . get_string('email_log_title', 'local_msgraph_api_mailer') . '</h4>';
echo '</div>';
echo '<div style="display:flex; align-items:center; gap:10px;">';
echo '<span class="badge bg-primary text-white" style="font-size:.85rem; padding:5px 10px;">';
echo $total . ' ' . $logrecordscount;
echo '</span>';
echo '<a href="' . $exporturlout . '" class="btn btn-success btn-sm">';
echo '<i class="fa fa-download"></i> ' . $exportbtnlabel;
echo '</a>';
echo '</div>';
echo '</div>';

echo '<div style="padding:0;">';

if (empty($records)) {
    echo '<div class="p-4 text-center text-muted">';
    echo '<i class="fa fa-inbox fa-2x mb-2 d-block"></i>';
    echo get_string('log_no_records', 'local_msgraph_api_mailer');
    echo '</div>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-hover table-sm mb-0" style="font-size:.875rem;">';
    echo '<thead class="table-light">';
    echo '<tr>';
    echo '<th style="width:55px; padding-left:16px;">ID</th>';
    echo '<th>' . get_string('log_col_recipient', 'local_msgraph_api_mailer') . '</th>';
    echo '<th>' . get_string('log_col_subject', 'local_msgraph_api_mailer') . '</th>';
    echo '<th style="width:90px; text-align:center;">';
    echo get_string('log_col_status', 'local_msgraph_api_mailer');
    echo '</th>';
    echo '<th style="width:80px; text-align:center;">';
    echo get_string('log_col_attachment', 'local_msgraph_api_mailer');
    echo '</th>';
    echo '<th>' . get_string('log_col_response', 'local_msgraph_api_mailer') . '</th>';
    echo '<th style="width:155px;">' . get_string('log_col_datetime', 'local_msgraph_api_mailer') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($records as $r) {
        $recipients = json_decode($r->recipients, true);
        $recipients = is_array($recipients) ? implode(', ', $recipients) : $r->recipients;
        $badgeclass = $r->status ? 'log-badge-sent' : 'log-badge-failed';
        $statuslabel = $r->status
            ? get_string('log_status_sent', 'local_msgraph_api_mailer')
            : get_string('log_status_failed', 'local_msgraph_api_mailer');
        $responseesc = htmlspecialchars($r->response, ENT_QUOTES);

        echo '<tr>';
        echo '<td class="text-muted" style="padding-left:16px;">' . (int)$r->id . '</td>';
        echo '<td>' . htmlspecialchars($recipients, ENT_QUOTES) . '</td>';
        echo '<td>' . htmlspecialchars($r->subject, ENT_QUOTES) . '</td>';
        echo '<td style="text-align:center;">';
        echo '<span class="' . $badgeclass . '">' . $statuslabel . '</span>';
        echo '</td>';
        echo '<td style="text-align:center;">';
        if (!empty($r->has_attachment)) {
            echo '<span title="Has attachment" style="color:#0078d4;font-size:1rem;">';
            echo '<i class="fa fa-paperclip"></i>';
            echo '</span>';
        } else {
            echo '<span style="color:#adb5bd;">&mdash;</span>';
        }
        echo '</td>';
        echo '<td>';
        echo '<div class="log-response" title="' . $responseesc . '">' . $responseesc . '</div>';
        echo '</td>';
        echo '<td class="text-muted">' . date('Y-m-d H:i:s', $r->timecreated) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    echo '<div style="padding:12px 16px;">';
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pagebaseurl);
    echo '</div>';
}

echo '</div>';
echo '</div>';

// Navigation.
echo '<div class="mb-4" style="display:flex; gap:8px;">';
echo '<a href="' . $settingsurlout . '" class="btn btn-secondary">';
echo '<i class="fa fa-arrow-left"></i> ' . get_string('back_to_settings', 'local_msgraph_api_mailer');
echo '</a>';
echo '<a href="' . $testurlout . '" class="btn btn-outline-secondary">';
echo '<i class="fa fa-flask"></i> ' . get_string('test_validate', 'local_msgraph_api_mailer');
echo '</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
