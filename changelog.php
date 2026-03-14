<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/changelog.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_msgraph_api_mailer') . ' — ' . get_string('changelog_title', 'local_msgraph_api_mailer'));
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$logurl      = new moodle_url('/local/msgraph_api_mailer/emaillog.php');

echo $OUTPUT->header();
?>

<style>
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
    background: #0078d4;
    color: #fff;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: .78rem;
    font-weight: 600;
}
.cl-badge-stable  { background: #28a745; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: .72rem; font-weight: 600; }
.cl-badge-latest  { background: #fd7e14; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: .72rem; font-weight: 600; }
.cl-body { padding: 18px 22px; }
.cl-body ul { margin: 6px 0 0 0; padding-left: 20px; }
.cl-body ul li { margin-bottom: 5px; font-size: .9rem; line-height: 1.5; }
.cl-date { font-size: .8rem; color: #6c757d; margin-left: auto; }
.cl-type-new      { color: #0078d4; font-weight: 600; }
.cl-type-fix      { color: #28a745; font-weight: 600; }
.cl-type-improved { color: #6f42c1; font-weight: 600; }
</style>

<div style="max-width:860px; margin:0 auto;">

  <div class="cl-card">
    <div class="cl-card-header">
      <i class="fa fa-history text-primary"></i>
      <h4><?php echo get_string('changelog_title', 'local_msgraph_api_mailer'); ?></h4>
      <span class="cl-date"><?php echo get_string('pluginname', 'local_msgraph_api_mailer'); ?></span>
    </div>
  </div>

  <!-- v1.1.0 -->
  <div class="cl-card">
    <div class="cl-card-header">
      <span class="cl-badge-version">v1.1.0</span>
      <h4>Feature Release</h4>
      <span class="cl-badge-latest">Latest</span>
      <span class="cl-date">2026-03-14</span>
    </div>
    <div class="cl-body">
      <ul>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Send Test with Attachment</strong> — Settings page now has a dedicated button that sends a real
          <code>.xlsx</code> test file to verify the full attachment pipeline.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Connection Status Badge</strong> — Settings page auto-checks Azure connectivity on load and shows
          a live green/red badge — no button click required.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Large Attachment Support (up to 150 MB)</strong> — Attachments ≥ 3 MB are now uploaded via the
          Microsoft Graph upload session API (chunked PUT) instead of inline base64, bypassing the
          <code>sendMail</code> payload limit.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Attachment indicator in Email Log</strong> — Email log table and CSV export now show whether
          each email had an attachment (paperclip icon / "Yes"/"No" column).
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Changelog page</strong> — This page.
        </li>
        <li>
          <span class="cl-type-fix">FIX</span>
          <strong>Attachment filename</strong> — Was using <code>$attach[1]</code> (temp basename like
          <code>tempup_XYZ</code>); now correctly uses <code>$attach[2]</code> (PHPMailer display name).
        </li>
        <li>
          <span class="cl-type-fix">FIX</span>
          <strong>Attachment extension missing</strong> — MIME type is now detected from in-memory file content
          (magic bytes + OOXML ZIP marker search) instead of reopening the temp file, which Moodle may have
          already deleted. Extension is automatically appended when missing.
        </li>
        <li>
          <span class="cl-type-fix">FIX</span>
          <strong>Uninstall error</strong> — <code>db/uninstall.php</code> now <code>require_once</code>s
          <code>lib.php</code> before calling patch-removal functions (Moodle does not auto-include
          <code>lib.php</code> before uninstall hooks).
        </li>
      </ul>
    </div>
  </div>

  <!-- v1.0.0 -->
  <div class="cl-card">
    <div class="cl-card-header">
      <span class="cl-badge-version">v1.0.0</span>
      <h4>Initial Stable Release</h4>
      <span class="cl-badge-stable">Stable</span>
      <span class="cl-date">2026-03-13</span>
    </div>
    <div class="cl-body">
      <ul>
        <li>
          <span class="cl-type-new">NEW</span>
          Route all Moodle outgoing emails through <strong>Microsoft Graph API</strong> (replaces SMTP).
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          OAuth2 client credentials flow — token cached per request with 5-minute expiry buffer.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          Respects Moodle's <strong>email diverting</strong> setting (<code>$CFG->divertallemailsto</code>).
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>SMTP fallback</strong> — optional automatic fallback to PHPMailer/SMTP on Graph API failure.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Email log viewer</strong> — searchable, filterable table of all sent/failed emails with CSV export.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Test &amp; Validate page</strong> — Check Permissions and Send Test Email from the admin UI.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          <strong>Mandatory field validation</strong> — Cannot enable the plugin without all Azure credentials filled in.
        </li>
        <li>
          <span class="cl-type-new">NEW</span>
          Moodle 5.x compatibility — <code>phpmailer_init</code> hook restored via
          <code>moodle_phpmailer.php</code> patch (auto-applied on install, auto-verified via <code>after_config</code> hook).
        </li>
      </ul>
    </div>
  </div>

  <!-- Navigation -->
  <div class="mb-4" style="display:flex; gap:8px;">
    <a href="<?php echo $settingsurl->out(false); ?>" class="btn btn-secondary">
      <i class="fa fa-arrow-left"></i> <?php echo get_string('back_to_settings', 'local_msgraph_api_mailer'); ?>
    </a>
    <a href="<?php echo $logurl->out(false); ?>" class="btn btn-outline-secondary">
      <i class="fa fa-list"></i> <?php echo get_string('view_email_logs', 'local_msgraph_api_mailer'); ?>
    </a>
  </div>

</div>

<?php
echo $OUTPUT->footer();
