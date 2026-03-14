<?php
defined('MOODLE_INTERNAL') || die();

// Custom admin setting classes with placeholder support.
require_once(__DIR__ . '/classes/admin/configtext_placeholder.php');

use local_msgraph_api_mailer\admin\configtext_placeholder;
use local_msgraph_api_mailer\admin\configpassword_placeholder;
use local_msgraph_api_mailer\admin\configcheckbox_with_required;

if (!isset($hassiteconfig) || $hassiteconfig) {
    $settings = new admin_settingpage('local_msgraph_api_mailer', get_string('pluginname', 'local_msgraph_api_mailer'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new configcheckbox_with_required(
        'local_msgraph_api_mailer/enabled',
        get_string('enabled', 'local_msgraph_api_mailer'),
        get_string('enabled_desc', 'local_msgraph_api_mailer'),
        0,
        ['tenant_id', 'client_id', 'client_secret', 'sender_email'],
        get_string('enabled_requires_config', 'local_msgraph_api_mailer')
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/tenant_id',
        get_string('tenant_id', 'local_msgraph_api_mailer'),
        get_string('tenant_id_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/client_id',
        get_string('client_id', 'local_msgraph_api_mailer'),
        get_string('client_id_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
    ));

    $settings->add(new configpassword_placeholder(
        'local_msgraph_api_mailer/client_secret',
        get_string('client_secret', 'local_msgraph_api_mailer'),
        get_string('client_secret_desc', 'local_msgraph_api_mailer'),
        '',
        'Enter your Azure AD client secret value'
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/sender_email',
        get_string('sender_email', 'local_msgraph_api_mailer'),
        get_string('sender_email_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_EMAIL,
        null,
        'no-reply@yourdomain.com'
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/sender_display_name',
        get_string('sender_display_name', 'local_msgraph_api_mailer'),
        get_string('sender_display_name_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        'Moodle Notifications'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_msgraph_api_mailer/log_emails',
        get_string('log_emails', 'local_msgraph_api_mailer'),
        get_string('log_emails_desc', 'local_msgraph_api_mailer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_msgraph_api_mailer/fallback_smtp',
        get_string('fallback_smtp', 'local_msgraph_api_mailer'),
        get_string('fallback_smtp_desc', 'local_msgraph_api_mailer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_msgraph_api_mailer/read_receipt_enabled',
        get_string('read_receipt_enabled', 'local_msgraph_api_mailer'),
        get_string('read_receipt_enabled_desc', 'local_msgraph_api_mailer'),
        0
    ));

    // -- Test & Validate section
    $settings->add(new admin_setting_heading(
        'local_msgraph_api_mailer_test_heading',
        get_string('test_email', 'local_msgraph_api_mailer'),
        get_string('test_email_desc', 'local_msgraph_api_mailer')
    ));

    // Test email address (used by the inline AJAX buttons below)
    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/test_email_temp',
        get_string('test_email_address', 'local_msgraph_api_mailer'),
        get_string('test_email_address_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_EMAIL,
        null,
        'user@example.com'
    ));

    // Links: test page + email log page + changelog
    $testpageurl      = new moodle_url('/local/msgraph_api_mailer/index.php');
    $logpageurl       = new moodle_url('/local/msgraph_api_mailer/emaillog.php');
    $changelogpageurl = new moodle_url('/local/msgraph_api_mailer/changelog.php');
    $settings->add(new admin_setting_description(
        'local_msgraph_api_mailer_testpage_link',
        '',
        html_writer::tag('a',
            html_writer::tag('i', '', ['class' => 'fa fa-external-link']) . ' ' .
            get_string('open_test_page', 'local_msgraph_api_mailer'),
            ['href' => $testpageurl->out(false), 'class' => 'btn btn-info mr-2', 'target' => '_blank']
        ) .
        html_writer::tag('a',
            html_writer::tag('i', '', ['class' => 'fa fa-list']) . ' ' .
            get_string('view_email_logs', 'local_msgraph_api_mailer'),
            ['href' => $logpageurl->out(false), 'class' => 'btn btn-secondary mr-2', 'target' => '_blank']
        ) .
        html_writer::tag('a',
            html_writer::tag('i', '', ['class' => 'fa fa-history']) . ' ' .
            get_string('view_changelog', 'local_msgraph_api_mailer'),
            ['href' => $changelogpageurl->out(false), 'class' => 'btn btn-outline-secondary', 'target' => '_blank']
        ) .
        html_writer::tag('p', 'Quick test from this page:', ['class' => 'mt-3 mb-1 text-muted small'])
    ));

    // Inline AJAX buttons — only load JS when this exact settings page is rendered.
    // $ADMIN->fulltree is true on admin/index.php too, so we also check the section param.
    if ($ADMIN->fulltree) {
        $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
        if ($currentsection === 'local_msgraph_api_mailer') {
            global $PAGE;
            $PAGE->requires->js_call_amd('local_msgraph_api_mailer/tester', 'init');
        }
    }
}
