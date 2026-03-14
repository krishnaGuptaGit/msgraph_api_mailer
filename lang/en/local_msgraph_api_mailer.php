<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'MS Graph API Mailer';

// Settings
$string['enabled'] = 'Enable MS Graph Mailer';
$string['enabled_desc'] = 'Route all Moodle emails through Microsoft Graph API';
$string['enabled_requires_config'] = 'Please fill in Azure Tenant ID, Client ID, Client Secret, and Sender Email Address for enabling MS Graph API Mailer.';
$string['tenant_id'] = 'Azure Tenant ID';
$string['tenant_id_desc'] = 'Your Microsoft Entra (Azure AD) Tenant ID';
$string['client_id'] = 'Client ID';
$string['client_id_desc'] = 'Application (client) ID from Azure AD';
$string['client_secret'] = 'Client Secret';
$string['client_secret_desc'] = 'Client secret from Azure AD';
$string['sender_email'] = 'Sender Email Address';
$string['sender_email_desc'] = 'Email address from Microsoft 365 that will send emails';
$string['sender_display_name'] = 'Sender Display Name';
$string['sender_display_name_desc'] = 'Display name shown to recipients in the From field (e.g. "Moodle Notifications"). Note: Outlook users in the same Microsoft 365 organisation may see the Azure AD account name instead — test by sending to an external address (Gmail, etc.) to confirm it is working.';
$string['sender_display_name_not_set'] = 'No display name set';
$string['log_emails'] = 'Log sent emails';
$string['log_emails_desc'] = 'Store a record of every email sent (or failed) in the database for audit purposes';
$string['fallback_smtp'] = 'Fallback to SMTP on failure';
$string['fallback_smtp_desc'] = 'Use SMTP if Microsoft Graph API fails';

// Test section in settings
$string['test_email'] = 'Test Email';
$string['test_email_desc'] = 'Send a test email to verify configuration';
$string['test_email_address'] = 'Test Email Address';
$string['test_email_address_desc'] = 'Enter an email address to send a test email to';

// Buttons
$string['check_permissions_btn'] = 'Check Permissions';
$string['send_test_email_btn'] = 'Send Test Email';
$string['send_test_email'] = 'Send Test Email';
$string['send_test_with_attachment_btn'] = 'Send Test with Attachment';

// Standalone test page
$string['test_validate'] = 'Test & Validate';
$string['open_test_page'] = 'Open Test & Validate Page';
$string['config_status'] = 'Configuration Status';
$string['connection_status'] = 'Connection Status';
$string['connection_status_desc'] = 'Verify that your Azure credentials are correct and the Mail.Send permission is active.';
$string['send_test_email_desc'] = 'Send a test email to verify the complete email delivery pipeline.';
$string['recipient_email'] = 'Recipient Email Address';
$string['back_to_settings'] = 'Back to Settings';
$string['go_to_settings'] = 'Go to Settings';
$string['plugin_enabled'] = 'Plugin Enabled';
$string['plugin_disabled'] = 'Plugin Disabled';
$string['config_complete'] = 'All Azure settings configured';
$string['config_incomplete'] = 'Azure settings incomplete';

// Result messages
$string['permission_check_success'] = '&#10003; Connection successful! Mail.Send permission is enabled.';
$string['permission_check_failed'] = '&#10007; Connection failed. Please check your credentials and Azure AD permissions.';
$string['email_sent_success'] = '&#10003; Test email sent successfully!';
$string['email_sent_failed'] = '&#10007; Failed to send test email.';
$string['email_attachment_sent_success'] = '&#10003; Test email with attachment sent! Check your inbox for Test Report.xlsx.';
$string['missing_config'] = 'Please configure all Azure settings before testing.';

// Connection status badge
$string['status_badge_checking'] = 'Checking connection...';
$string['connection_badge_connected'] = 'Connected';
$string['connection_badge_disconnected'] = 'Disconnected';

// Misc
$string['settings_saved'] = 'Settings saved successfully';
$string['test_result'] = 'Test Result';
$string['status'] = 'Connection Status';
$string['connected'] = 'Connected to Microsoft Graph API';
$string['disconnected'] = 'Not connected';
$string['error'] = 'Error';
$string['email_logs'] = 'Email Logs';
$string['check_permissions'] = 'Check Permissions';
$string['check_permissions_desc'] = 'Test connection and verify Mail.Send permission is enabled';

// Read receipt
$string['read_receipt_enabled'] = 'Request read receipts';
$string['read_receipt_enabled_desc'] = 'Ask recipients to send a read receipt when they open emails. Note: recipients can decline and most email clients do not send receipts automatically.';

// Changelog
$string['view_changelog'] = 'Changelog';
$string['changelog_title'] = 'Changelog';
$string['changelog_desc'] = 'Version history for MS Graph API Mailer';

// Email log viewer
$string['view_email_logs'] = 'View Email Logs';
$string['log_col_attachment'] = 'Attachment';
$string['email_log_title'] = 'Email Log';
$string['log_filter_heading'] = 'Filters';
$string['log_col_status'] = 'Status';
$string['log_col_recipient'] = 'Recipient(s)';
$string['log_col_subject'] = 'Subject';
$string['log_col_response'] = 'Response';
$string['log_col_datetime'] = 'Date / Time';
$string['log_status_all'] = 'All';
$string['log_status_sent'] = 'Sent';
$string['log_status_failed'] = 'Failed';
$string['log_subject_placeholder'] = 'Search subject...';
$string['log_filter_from'] = 'From date';
$string['log_filter_to'] = 'To date';
$string['log_btn_filter'] = 'Filter';
$string['log_btn_clear'] = 'Clear';
$string['log_btn_export'] = 'Export CSV';
$string['log_records_count'] = 'records';
$string['log_no_records'] = 'No records found matching the current filters.';
