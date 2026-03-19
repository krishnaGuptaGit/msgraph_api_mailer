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

// Filters.
$filterstatus  = optional_param('status', -1, PARAM_INT);   // -1 = all, 0 = failed, 1 = sent.
$filterto      = optional_param('recipient', '', PARAM_TEXT);
$filtersubject = optional_param('subject', '', PARAM_TEXT);
$filterfrom    = optional_param('datefrom', '', PARAM_TEXT); // YYYY-MM-DD.
$filteruntil   = optional_param('dateto', '', PARAM_TEXT);  // YYYY-MM-DD.
$page          = optional_param('page', 0, PARAM_INT);
$export        = optional_param('export', 0, PARAM_INT);
$perpage       = 25;

// Build SQL WHERE clause (recipients is filtered in PHP; avoids SQL reserved-word issues).
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

// CSV export — runs before any page output.
if ($export) {
    $all = $DB->get_records_select('local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC');

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

// Fetch records for the page view.
$allrecords = $DB->get_records_select(
    'local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC'
);

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
$pagepath = '/local/msgraph_api_mailer/emaillog.php';
$PAGE->set_url(new moodle_url($pagepath));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(
    get_string('pluginname', 'local_msgraph_api_mailer') . ' - ' .
    get_string('email_log_title', 'local_msgraph_api_mailer')
);
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$testurl     = new moodle_url('/local/msgraph_api_mailer/index.php');

// Build paging URL (preserves current filters).
$pagebaseurl = new moodle_url($pagepath, array_filter([
    'status'    => $filterstatus >= 0 ? $filterstatus : null,
    'recipient' => $filterto !== '' ? $filterto : null,
    'subject'   => $filtersubject !== '' ? $filtersubject : null,
    'datefrom'  => $filterfrom !== '' ? $filterfrom : null,
    'dateto'    => $filteruntil !== '' ? $filteruntil : null,
], fn($v) => $v !== null));

// Build export URL.
$exporturl = new moodle_url($pagepath, array_filter([
    'status'    => $filterstatus >= 0 ? $filterstatus : null,
    'recipient' => $filterto !== '' ? $filterto : null,
    'subject'   => $filtersubject !== '' ? $filtersubject : null,
    'datefrom'  => $filterfrom !== '' ? $filterfrom : null,
    'dateto'    => $filteruntil !== '' ? $filteruntil : null,
    'export'    => 1,
], fn($v) => $v !== null));

// Build records array for the template.
$recordrows = [];
foreach ($records as $r) {
    $recipients = json_decode($r->recipients, true);
    $recipients = is_array($recipients) ? implode(', ', $recipients) : $r->recipients;
    $recordrows[] = [
        'id'               => (int) $r->id,
        'recipients'       => $recipients,
        'subject'          => $r->subject,
        'status_label'     => $r->status
                              ? get_string('log_status_sent', 'local_msgraph_api_mailer')
                              : get_string('log_status_failed', 'local_msgraph_api_mailer'),
        'status_badge_class' => $r->status ? 'log-badge-sent' : 'log-badge-failed',
        'has_attachment'   => !empty($r->has_attachment),
        'response'         => $r->response,
        'timecreated'      => date('Y-m-d H:i:s', $r->timecreated),
    ];
}

// Build template data.
$templatedata = [
    'filter_status_all_selected'    => $filterstatus < 0,
    'filter_status_sent_selected'   => $filterstatus === 1,
    'filter_status_failed_selected' => $filterstatus === 0,
    'filter_recipient'              => $filterto,
    'filter_subject'                => $filtersubject,
    'subject_placeholder'           => get_string('log_subject_placeholder', 'local_msgraph_api_mailer'),
    'filter_datefrom'               => $filterfrom,
    'filter_dateto'                 => $filteruntil,
    'total'                         => $total,
    'export_url'                    => $exporturl->out(false),
    'has_records'                   => !empty($recordrows),
    'records'                       => $recordrows,
    'paging_bar'                    => $OUTPUT->paging_bar($total, $page, $perpage, $pagebaseurl),
    'settings_url'                  => $settingsurl->out(false),
    'test_url'                      => $testurl->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_msgraph_api_mailer/email_log', $templatedata);
echo $OUTPUT->footer();
