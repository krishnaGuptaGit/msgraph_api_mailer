<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_msgraph_api_mailer') . ' — ' . get_string('test_validate', 'local_msgraph_api_mailer'));
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->js_call_amd('local_msgraph_api_mailer/index', 'init');

// Read current configuration
$enabled      = (bool) get_config('local_msgraph_api_mailer', 'enabled');
$tenant_id    = get_config('local_msgraph_api_mailer', 'tenant_id');
$client_id    = get_config('local_msgraph_api_mailer', 'client_id');
$client_secret = get_config('local_msgraph_api_mailer', 'client_secret');
$sender_email        = get_config('local_msgraph_api_mailer', 'sender_email');
$sender_display_name = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));
$is_configured = !empty($tenant_id) && !empty($client_id) && !empty($client_secret) && !empty($sender_email);

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);

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
.config-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .85rem;
    font-weight: 500;
}
.config-badge.ok  { background: #d4edda; color: #155724; }
.config-badge.err { background: #f8d7da; color: #721c24; }
.config-badge.warn { background: #fff3cd; color: #856404; }
#permission-result, #email-result { margin-top: 14px; }
</style>

<div class="row justify-content-center" style="max-width:860px; margin: 0 auto;">

  <!-- Configuration Status -->
  <div class="col-12">
    <div class="msgraph-card">
      <div class="msgraph-card-header">
        <i class="fa fa-info-circle text-info"></i>
        <h4><?php echo get_string('config_status', 'local_msgraph_api_mailer'); ?></h4>
      </div>
      <div class="msgraph-card-body">
        <div class="d-flex flex-wrap gap-2" style="gap:8px;">
          <span class="config-badge <?php echo $enabled ? 'ok' : 'warn'; ?>">
            <i class="fa fa-<?php echo $enabled ? 'check' : 'pause'; ?>-circle"></i>
            <?php echo $enabled
                ? get_string('plugin_enabled', 'local_msgraph_api_mailer')
                : get_string('plugin_disabled', 'local_msgraph_api_mailer'); ?>
          </span>
          <span class="config-badge <?php echo $is_configured ? 'ok' : 'err'; ?>">
            <i class="fa fa-<?php echo $is_configured ? 'check' : 'times'; ?>-circle"></i>
            <?php echo $is_configured
                ? get_string('config_complete', 'local_msgraph_api_mailer')
                : get_string('config_incomplete', 'local_msgraph_api_mailer'); ?>
          </span>
          <?php if (!empty($sender_email)): ?>
          <span class="config-badge ok">
            <i class="fa fa-envelope"></i>
            <?php echo htmlspecialchars($sender_email, ENT_QUOTES); ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($sender_display_name)): ?>
          <span class="config-badge ok">
            <i class="fa fa-user"></i>
            <?php echo htmlspecialchars($sender_display_name, ENT_QUOTES); ?>
          </span>
          <?php else: ?>
          <span class="config-badge warn">
            <i class="fa fa-user-o"></i>
            <?php echo get_string('sender_display_name_not_set', 'local_msgraph_api_mailer'); ?>
          </span>
          <?php endif; ?>
        </div>
        <?php if (!$is_configured): ?>
        <div class="alert alert-warning mt-3 mb-0">
          <?php echo get_string('missing_config', 'local_msgraph_api_mailer'); ?>
          <a href="<?php echo $settingsurl; ?>" class="alert-link">
            <?php echo get_string('go_to_settings', 'local_msgraph_api_mailer'); ?>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Check Permissions -->
  <div class="col-12">
    <div class="msgraph-card">
      <div class="msgraph-card-header">
        <i class="fa fa-plug text-primary"></i>
        <h4><?php echo get_string('connection_status', 'local_msgraph_api_mailer'); ?></h4>
      </div>
      <div class="msgraph-card-body">
        <p class="text-muted mb-3"><?php echo get_string('connection_status_desc', 'local_msgraph_api_mailer'); ?></p>
        <button type="button" id="check-permissions-btn" class="btn btn-primary" <?php echo $is_configured ? '' : 'disabled'; ?>>
          <i class="fa fa-check-circle"></i>
          <?php echo get_string('check_permissions_btn', 'local_msgraph_api_mailer'); ?>
        </button>
        <div id="permission-result"></div>
      </div>
    </div>
  </div>

  <!-- Send Test Email -->
  <div class="col-12">
    <div class="msgraph-card">
      <div class="msgraph-card-header">
        <i class="fa fa-envelope text-primary"></i>
        <h4><?php echo get_string('send_test_email', 'local_msgraph_api_mailer'); ?></h4>
      </div>
      <div class="msgraph-card-body">
        <p class="text-muted mb-3"><?php echo get_string('send_test_email_desc', 'local_msgraph_api_mailer'); ?></p>
        <div class="form-group">
          <label for="test-email-input"><strong><?php echo get_string('recipient_email', 'local_msgraph_api_mailer'); ?></strong></label>
          <div class="input-group" style="max-width:420px;">
            <input type="email" id="test-email-input" class="form-control"
                   placeholder="user@example.com"
                   <?php echo $is_configured ? '' : 'disabled'; ?>>
            <button type="button" id="send-test-btn" class="btn btn-primary" <?php echo $is_configured ? '' : 'disabled'; ?>>
              <i class="fa fa-paper-plane"></i>
              <?php echo get_string('send_test_email_btn', 'local_msgraph_api_mailer'); ?>
            </button>
          </div>
        </div>
        <div id="email-result"></div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="col-12 mb-4" style="display:flex; gap:8px; flex-wrap:wrap;">
    <a href="<?php echo $settingsurl; ?>" class="btn btn-secondary">
      <i class="fa fa-arrow-left"></i>
      <?php echo get_string('back_to_settings', 'local_msgraph_api_mailer'); ?>
    </a>
    <a href="<?php echo (new moodle_url('/local/msgraph_api_mailer/emaillog.php'))->out(false); ?>" class="btn btn-outline-secondary">
      <i class="fa fa-list"></i>
      <?php echo get_string('view_email_logs', 'local_msgraph_api_mailer'); ?>
    </a>
  </div>

</div>


<?php
echo $OUTPUT->footer();
