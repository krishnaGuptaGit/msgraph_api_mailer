/**
 * MS Graph Mailer — Settings Page Test Functionality
 *
 * Adds to the settings page:
 *  - Connection status badge (auto-checked on load)
 *  - Check Permissions button
 *  - Send Test Email button
 *  - Send Test with Attachment button
 *
 * @module     local_msgraph_api_mailer/tester
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str'], function($, str) {
    'use strict';

    return {
        strings: {},

        init: function() {
            var self = this;

            str.get_strings([
                {key: 'check_permissions_btn',          component: 'local_msgraph_api_mailer'},
                {key: 'send_test_email_btn',            component: 'local_msgraph_api_mailer'},
                {key: 'test_email_address',             component: 'local_msgraph_api_mailer'},
                {key: 'test_result',                    component: 'local_msgraph_api_mailer'},
                {key: 'send_test_with_attachment_btn',  component: 'local_msgraph_api_mailer'},
                {key: 'status_badge_checking',          component: 'local_msgraph_api_mailer'},
                {key: 'connection_badge_connected',     component: 'local_msgraph_api_mailer'},
                {key: 'connection_badge_disconnected',  component: 'local_msgraph_api_mailer'}
            ]).done(function(s) {
                self.strings = {
                    checkPermissions:        s[0],
                    sendTestEmail:           s[1],
                    testEmailAddress:        s[2],
                    testResult:              s[3],
                    sendTestWithAttachment:  s[4],
                    statusChecking:          s[5],
                    connected:               s[6],
                    disconnected:            s[7]
                };
                self.addTestSection();
            }).fail(function() {
                self.strings = {
                    checkPermissions:       'Check Permissions',
                    sendTestEmail:          'Send Test Email',
                    testEmailAddress:       'Test Email Address',
                    testResult:             'Test Result',
                    sendTestWithAttachment: 'Send Test with Attachment',
                    statusChecking:         'Checking connection...',
                    connected:              'Connected',
                    disconnected:           'Disconnected'
                };
                self.addTestSection();
            });
        },

        addTestSection: function() {
            var self = this;

            if ($('#msgraph-test-buttons').length) {
                return;
            }

            var html =
                '<div id="msgraph-test-buttons" class="msgraph-test-section"' +
                ' style="margin-top:25px;padding:20px;border:2px solid #0078d4;border-radius:8px;background:#f8f9fa;">' +

                // ── Connection status badge ─────────────────────────────────
                '<div id="msgraph-status-row" style="margin-bottom:14px;display:flex;align-items:center;gap:10px;">' +
                '<strong style="font-size:.9rem;">Connection Status:</strong>' +
                '<span id="msgraph-status-badge"' +
                ' style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;background:#e9ecef;color:#495057;">' +
                '<span id="msgraph-status-dot" style="width:8px;height:8px;border-radius:50%;background:#adb5bd;display:inline-block;"></span>' +
                '<span id="msgraph-status-text">' + self.strings.statusChecking + '</span>' +
                '</span>' +
                '</div>' +

                // ── Action buttons ──────────────────────────────────────────
                '<div class="test-buttons" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px;">' +
                '<button type="button" id="check-permissions-btn" class="btn btn-outline-primary btn-sm">' +
                '<i class="fa fa-plug"></i> ' + self.strings.checkPermissions +
                '</button>' +
                '<button type="button" id="send-test-btn" class="btn btn-primary btn-sm">' +
                '<i class="fa fa-envelope"></i> ' + self.strings.sendTestEmail +
                '</button>' +
                '<button type="button" id="send-test-attachment-btn" class="btn btn-secondary btn-sm">' +
                '<i class="fa fa-paperclip"></i> ' + self.strings.sendTestWithAttachment +
                '</button>' +
                '</div>' +

                // ── Result area ─────────────────────────────────────────────
                '<div id="test-result" style="margin-top:12px;"></div>' +
                '</div>';

            // Insert after the test email input field
            var inserted = false;
            var $input = $('#id_s_local_msgraph_api_mailer_test_email_temp');
            if ($input.length) {
                $input.closest('.form-group, .fitem').after(html);
                inserted = true;
            }
            if (!inserted) {
                var $form = $('form.mform');
                if ($form.length) {
                    $form.append(html);
                    inserted = true;
                }
            }
            if (!inserted) {
                $('#region-main').append(html);
            }

            self.bindEvents();

            // Auto-check connection status on load
            self.refreshStatusBadge();
        },

        bindEvents: function() {
            var self = this;

            $('#check-permissions-btn').off('click').on('click', function() {
                self.checkPermissions();
            });

            $('#send-test-btn').off('click').on('click', function() {
                self.sendTestEmail(false);
            });

            $('#send-test-attachment-btn').off('click').on('click', function() {
                self.sendTestEmail(true);
            });
        },

        /**
         * Auto-refresh the connection status badge (called on page load).
         */
        refreshStatusBadge: function() {
            var self = this;
            self.setBadge('checking');

            $.ajax({
                url:      M.cfg.wwwroot + '/local/msgraph_api_mailer/ajax.php',
                type:     'POST',
                dataType: 'json',
                data:     {action: 'get_connection_status', sesskey: M.cfg.sesskey},
                success: function(r) {
                    self.setBadge(r.connected ? 'connected' : 'disconnected');
                },
                error: function() {
                    self.setBadge('disconnected');
                }
            });
        },

        /**
         * Update the connection badge appearance.
         * @param {string} state  'checking' | 'connected' | 'disconnected'
         */
        setBadge: function(state) {
            var self = this;
            var $badge = $('#msgraph-status-badge');
            var $dot   = $('#msgraph-status-dot');
            var $text  = $('#msgraph-status-text');

            if (state === 'connected') {
                $badge.css({background: '#d4edda', color: '#155724'});
                $dot.css('background', '#28a745');
                $text.text(self.strings.connected);
            } else if (state === 'disconnected') {
                $badge.css({background: '#f8d7da', color: '#721c24'});
                $dot.css('background', '#dc3545');
                $text.text(self.strings.disconnected);
            } else {
                $badge.css({background: '#e9ecef', color: '#495057'});
                $dot.css('background', '#adb5bd');
                $text.text(self.strings.statusChecking);
            }
        },

        /**
         * Check permissions and show detailed result.
         */
        checkPermissions: function() {
            var self = this;
            var $result = $('#test-result');
            $result.html('<div class="alert alert-info py-2">Checking permissions...</div>');

            $.ajax({
                url:      M.cfg.wwwroot + '/local/msgraph_api_mailer/ajax.php',
                type:     'POST',
                dataType: 'json',
                data:     {action: 'check_permissions', sesskey: M.cfg.sesskey},
                success: function(r) {
                    var msg = r.message || 'Unexpected response';
                    $result.html(r.success
                        ? '<div class="alert alert-success py-2">' + msg + '</div>'
                        : '<div class="alert alert-danger py-2">'  + msg + '</div>');
                    self.setBadge(r.success ? 'connected' : 'disconnected');
                },
                error: function(xhr) {
                    $result.html('<div class="alert alert-danger py-2">Server error (HTTP ' + xhr.status + ')</div>');
                    self.setBadge('disconnected');
                }
            });
        },

        /**
         * Send a test email, with or without attachment.
         * @param {boolean} withAttachment
         */
        sendTestEmail: function(withAttachment) {
            var $result = $('#test-result');
            var email   = $('#id_s_local_msgraph_api_mailer_test_email_temp').val();

            if (!email) {
                $result.html('<div class="alert alert-warning py-2">Please enter a test email address in the field above.</div>');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $result.html('<div class="alert alert-warning py-2">Please enter a valid email address.</div>');
                return;
            }

            var action  = withAttachment ? 'send_test_email_attachment' : 'send_test_email';
            var label   = withAttachment ? 'Sending test email with attachment...' : 'Sending test email...';
            $result.html('<div class="alert alert-info py-2">' + label + '</div>');

            $.ajax({
                url:      M.cfg.wwwroot + '/local/msgraph_api_mailer/ajax.php',
                type:     'POST',
                dataType: 'json',
                data:     {action: action, email: email, sesskey: M.cfg.sesskey},
                success: function(r) {
                    $result.html(r.success
                        ? '<div class="alert alert-success py-2">' + r.message + '</div>'
                        : '<div class="alert alert-danger py-2">'  + r.message + '</div>');
                },
                error: function(xhr) {
                    $result.html('<div class="alert alert-danger py-2">Server error (HTTP ' + xhr.status + ')</div>');
                }
            });
        }
    };
});
