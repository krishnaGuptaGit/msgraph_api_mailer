<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE;

// --- Filters ---
$filter_status  = optional_param('status',    -1, PARAM_INT);   // -1=all, 0=failed, 1=sent
$filter_to      = optional_param('recipient', '', PARAM_TEXT);
$filter_subject = optional_param('subject',   '', PARAM_TEXT);
$filter_from    = optional_param('datefrom',  '', PARAM_TEXT);  // YYYY-MM-DD
$filter_until   = optional_param('dateto',    '', PARAM_TEXT);  // YYYY-MM-DD
$page           = optional_param('page',       0, PARAM_INT);
$export         = optional_param('export',     0, PARAM_INT);
$perpage        = 25;

// --- Build SQL WHERE (avoid the reserved-word `to` column in SQL; filter it in PHP) ---
$where  = [];
$params = [];

if ($filter_status >= 0) {
    $where[]          = 'status = :status';
    $params['status'] = $filter_status;
}
if ($filter_subject !== '') {
    $where[]           = $DB->sql_like('subject', ':subject', false);
    $params['subject'] = '%' . $DB->sql_like_escape($filter_subject) . '%';
}
if ($filter_from !== '') {
    $ts = strtotime($filter_from);
    if ($ts !== false) {
        $where[]            = 'timecreated >= :datefrom';
        $params['datefrom'] = $ts;
    }
}
if ($filter_until !== '') {
    $ts = strtotime($filter_until);
    if ($ts !== false) {
        $where[]           = 'timecreated <= :dateto';
        $params['dateto']  = $ts + 86399; // end of that day
    }
}

$wheresql = $where ? implode(' AND ', $where) : '';

// --- CSV export ---
if ($export) {
    $all = $DB->get_records_select('local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC');

    // Apply recipient PHP-side filter
    if ($filter_to !== '') {
        $all = array_filter($all, function ($r) use ($filter_to) {
            return stripos($r->recipients, $filter_to) !== false;
        });
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email_log_' . date('Ymd_His') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
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

// --- Fetch records ---
$allrecords = $DB->get_records_select('local_msgraph_api_mailer_log', $wheresql, $params, 'timecreated DESC');

// Apply recipient PHP-side filter (avoids SQL reserved-word issues with `to` column)
if ($filter_to !== '') {
    $allrecords = array_filter($allrecords, function ($r) use ($filter_to) {
        return stripos($r->recipients, $filter_to) !== false;
    });
    $allrecords = array_values($allrecords);
}

$total   = count($allrecords);
$records = array_slice($allrecords, $page * $perpage, $perpage);

// --- Page setup ---
$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/emaillog.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_msgraph_api_mailer') . ' — ' . get_string('email_log_title', 'local_msgraph_api_mailer'));
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$testurl     = new moodle_url('/local/msgraph_api_mailer/index.php');

// Build URL for paging bar (preserves current filters)
$pagebaseurl = new moodle_url('/local/msgraph_api_mailer/emaillog.php', array_filter([
    'status'    => $filter_status >= 0 ? $filter_status : null,
    'recipient' => $filter_to    !== '' ? $filter_to    : null,
    'subject'   => $filter_subject !== '' ? $filter_subject : null,
    'datefrom'  => $filter_from  !== '' ? $filter_from  : null,
    'dateto'    => $filter_until !== '' ? $filter_until  : null,
], fn($v) => $v !== null));

// Build export URL
$exportparams = array_filter([
    'status'    => $filter_status >= 0 ? $filter_status : null,
    'recipient' => $filter_to    !== '' ? $filter_to    : null,
    'subject'   => $filter_subject !== '' ? $filter_subject : null,
    'datefrom'  => $filter_from  !== '' ? $filter_from  : null,
    'dateto'    => $filter_until !== '' ? $filter_until  : null,
    'export'    => 1,
], fn($v) => $v !== null);
$exporturl = new moodle_url('/local/msgraph_api_mailer/emaillog.php', $exportparams);

echo $OUTPUT->header();
?>

<style>
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
.log-badge-sent   { background:#d4edda; color:#155724; padding:2px 10px; border-radius:12px; font-size:.78rem; font-weight:600; white-space:nowrap; }
.log-badge-failed { background:#f8d7da; color:#721c24; padding:2px 10px; border-radius:12px; font-size:.78rem; font-weight:600; white-space:nowrap; }
.log-response     { max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.8rem; color:#6c757d; cursor:help; }
.filter-row       { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.filter-group     { display:flex; flex-direction:column; gap:3px; }
.filter-group label { font-size:.8rem; font-weight:600; margin:0; color:#495057; }
</style>

<div style="max-width:1140px; margin:0 auto;">

  <!-- Filter card -->
  <div class="msgraph-card">
    <div class="msgraph-card-header">
      <i class="fa fa-filter text-secondary"></i>
      <h4><?php echo get_string('log_filter_heading', 'local_msgraph_api_mailer'); ?></h4>
    </div>
    <div class="msgraph-card-body">
      <form method="get" action="">
        <div class="filter-row">

          <div class="filter-group">
            <label for="fl-status"><?php echo get_string('log_col_status', 'local_msgraph_api_mailer'); ?></label>
            <select id="fl-status" name="status" class="form-control form-control-sm" style="min-width:110px;">
              <option value="-1" <?php echo $filter_status <  0 ? 'selected' : ''; ?>><?php echo get_string('log_status_all', 'local_msgraph_api_mailer'); ?></option>
              <option value="1"  <?php echo $filter_status === 1 ? 'selected' : ''; ?>><?php echo get_string('log_status_sent', 'local_msgraph_api_mailer'); ?></option>
              <option value="0"  <?php echo $filter_status === 0 ? 'selected' : ''; ?>><?php echo get_string('log_status_failed', 'local_msgraph_api_mailer'); ?></option>
            </select>
          </div>

          <div class="filter-group">
            <label for="fl-recipient"><?php echo get_string('log_col_recipient', 'local_msgraph_api_mailer'); ?></label>
            <input id="fl-recipient" type="text" name="recipient" class="form-control form-control-sm"
                   placeholder="user@example.com" style="min-width:200px;"
                   value="<?php echo htmlspecialchars($filter_to, ENT_QUOTES); ?>">
          </div>

          <div class="filter-group">
            <label for="fl-subject"><?php echo get_string('log_col_subject', 'local_msgraph_api_mailer'); ?></label>
            <input id="fl-subject" type="text" name="subject" class="form-control form-control-sm"
                   placeholder="<?php echo get_string('log_subject_placeholder', 'local_msgraph_api_mailer'); ?>" style="min-width:200px;"
                   value="<?php echo htmlspecialchars($filter_subject, ENT_QUOTES); ?>">
          </div>

          <div class="filter-group">
            <label for="fl-datefrom"><?php echo get_string('log_filter_from', 'local_msgraph_api_mailer'); ?></label>
            <input id="fl-datefrom" type="date" name="datefrom" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars($filter_from, ENT_QUOTES); ?>">
          </div>

          <div class="filter-group">
            <label for="fl-dateto"><?php echo get_string('log_filter_to', 'local_msgraph_api_mailer'); ?></label>
            <input id="fl-dateto" type="date" name="dateto" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars($filter_until, ENT_QUOTES); ?>">
          </div>

          <div class="filter-group" style="flex-direction:row; gap:6px; padding-top:1px;">
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">
              <i class="fa fa-search"></i> <?php echo get_string('log_btn_filter', 'local_msgraph_api_mailer'); ?>
            </button>
            <a href="?" class="btn btn-secondary btn-sm" style="align-self:flex-end;">
              <i class="fa fa-times"></i> <?php echo get_string('log_btn_clear', 'local_msgraph_api_mailer'); ?>
            </a>
          </div>

        </div>
      </form>
    </div>
  </div>

  <!-- Log table card -->
  <div class="msgraph-card">
    <div class="msgraph-card-header" style="justify-content:space-between;">
      <div style="display:flex; align-items:center; gap:8px;">
        <i class="fa fa-list text-primary"></i>
        <h4><?php echo get_string('email_log_title', 'local_msgraph_api_mailer'); ?></h4>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <span class="badge badge-secondary" style="font-size:.85rem; padding:5px 10px;">
          <?php echo $total; ?> <?php echo get_string('log_records_count', 'local_msgraph_api_mailer'); ?>
        </span>
        <a href="<?php echo $exporturl->out(false); ?>" class="btn btn-success btn-sm">
          <i class="fa fa-download"></i> <?php echo get_string('log_btn_export', 'local_msgraph_api_mailer'); ?>
        </a>
      </div>
    </div>

    <div style="padding:0;">
      <?php if (empty($records)): ?>
        <div class="p-4 text-center text-muted">
          <i class="fa fa-inbox fa-2x mb-2 d-block"></i>
          <?php echo get_string('log_no_records', 'local_msgraph_api_mailer'); ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0" style="font-size:.875rem;">
            <thead class="thead-light">
              <tr>
                <th style="width:55px; padding-left:16px;">ID</th>
                <th><?php echo get_string('log_col_recipient', 'local_msgraph_api_mailer'); ?></th>
                <th><?php echo get_string('log_col_subject', 'local_msgraph_api_mailer'); ?></th>
                <th style="width:90px; text-align:center;"><?php echo get_string('log_col_status', 'local_msgraph_api_mailer'); ?></th>
                <th style="width:80px; text-align:center;"><?php echo get_string('log_col_attachment', 'local_msgraph_api_mailer'); ?></th>
                <th><?php echo get_string('log_col_response', 'local_msgraph_api_mailer'); ?></th>
                <th style="width:155px;"><?php echo get_string('log_col_datetime', 'local_msgraph_api_mailer'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r):
                  $recipients = json_decode($r->recipients, true);
                  $recipients = is_array($recipients) ? implode(', ', $recipients) : $r->recipients;
              ?>
              <tr>
                <td class="text-muted" style="padding-left:16px;"><?php echo (int)$r->id; ?></td>
                <td><?php echo htmlspecialchars($recipients, ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars($r->subject, ENT_QUOTES); ?></td>
                <td style="text-align:center;">
                  <span class="<?php echo $r->status ? 'log-badge-sent' : 'log-badge-failed'; ?>">
                    <?php echo $r->status
                        ? get_string('log_status_sent', 'local_msgraph_api_mailer')
                        : get_string('log_status_failed', 'local_msgraph_api_mailer'); ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <?php if (!empty($r->has_attachment)): ?>
                    <span title="Has attachment" style="color:#0078d4;"><i class="fa fa-paperclip"></i></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="log-response" title="<?php echo htmlspecialchars($r->response, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($r->response, ENT_QUOTES); ?>
                  </div>
                </td>
                <td class="text-muted"><?php echo date('Y-m-d H:i:s', $r->timecreated); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="padding:12px 16px;">
          <?php echo $OUTPUT->paging_bar($total, $page, $perpage, $pagebaseurl); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Navigation -->
  <div class="mb-4" style="display:flex; gap:8px;">
    <a href="<?php echo $settingsurl->out(false); ?>" class="btn btn-secondary">
      <i class="fa fa-arrow-left"></i> <?php echo get_string('back_to_settings', 'local_msgraph_api_mailer'); ?>
    </a>
    <a href="<?php echo $testurl->out(false); ?>" class="btn btn-outline-secondary">
      <i class="fa fa-flask"></i> <?php echo get_string('test_validate', 'local_msgraph_api_mailer'); ?>
    </a>
  </div>

</div>

<?php
echo $OUTPUT->footer();