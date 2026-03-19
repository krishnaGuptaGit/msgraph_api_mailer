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
 * CLI tool for testing MS Graph API Mailer connectivity and email delivery.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get command line options.
[$options, $unrecognized] = cli_get_params([
    'help'    => false,
    'to'      => null,
    'subject' => null,
    'body'    => null,
    'status'  => false,
    'test'    => false,
], ['h' => 'help']);

if ($options['help']) {
    cli_writeln("MS Graph Mailer Test Tool");
    cli_writeln("================================");
    cli_writeln("Usage: php test_send.php [options]");
    cli_writeln("");
    cli_writeln("Options:");
    cli_writeln("  --to EMAIL       Send test email to address.");
    cli_writeln("  --subject TEXT   Email subject (default: Test Email).");
    cli_writeln("  --body TEXT      Email body (HTML allowed).");
    cli_writeln("  --status         Check connection status.");
    cli_writeln("  --test           Run complete test.");
    cli_writeln("  --help           Show this help.");
    cli_writeln("");
    cli_writeln("Examples:");
    cli_writeln("  php test_send.php --status");
    cli_writeln("  php test_send.php --to user@example.com");
    cli_writeln("  php test_send.php --test");
    exit(0);
}

// Check connection status.
if ($options['status']) {
    cli_writeln("MS Graph Mailer - Connection Status");
    cli_writeln("====================================");

    $client = new \local_msgraph_api_mailer\api\graph_client();
    $result = $client->test_connection();

    if ($result['success']) {
        cli_writeln("Status: Connected");
        cli_writeln("Tenant ID: " . get_config('local_msgraph_api_mailer', 'tenant_id'));
        cli_writeln("Sender: " . get_config('local_msgraph_api_mailer', 'sender_email'));
    } else {
        cli_writeln("Status: Disconnected");
        cli_writeln("Error: " . $result['message']);
    }

    // Check if enabled.
    $enabled = get_config('local_msgraph_api_mailer', 'enabled');
    cli_writeln("");
    cli_writeln("Plugin Status: " . ($enabled ? "Enabled" : "Disabled"));
    exit(0);
}

// Run complete test.
if ($options['test']) {
    cli_writeln("MS Graph Mailer - Complete Test");
    cli_writeln("================================");

    // Check configuration.
    $tenantid    = get_config('local_msgraph_api_mailer', 'tenant_id');
    $clientid    = get_config('local_msgraph_api_mailer', 'client_id');
    $senderemail = get_config('local_msgraph_api_mailer', 'sender_email');

    if (empty($tenantid) || empty($clientid) || empty($senderemail)) {
        cli_writeln("Configuration incomplete.");
        exit(1);
    }

    cli_writeln("Configuration found.");
    cli_writeln("  - Tenant ID: " . substr($tenantid, 0, 8) . "...");
    cli_writeln("  - Sender: " . $senderemail);

    // Test connection.
    $client = new \local_msgraph_api_mailer\api\graph_client();
    $result = $client->test_connection();

    if (!$result['success']) {
        cli_writeln("Connection failed: " . $result['message']);
        exit(1);
    }

    cli_writeln("Connection successful.");
    exit(0);
}

// Send test email.
if ($options['to']) {
    cli_writeln("Sending test email...");
    cli_writeln("================================");

    $client  = new \local_msgraph_api_mailer\api\graph_client();
    $subject = $options['subject'] ?? 'Test Email from Moodle MS Graph Mailer';
    $body    = $options['body'] ?? '<h2>Test Email</h2><p>This is a test email sent via Microsoft Graph API from Moodle.</p>'
        . '<p>Plugin: MS Graph Mailer</p>';

    try {
        $result = $client->send_email($options['to'], $subject, $body, null, []);

        if ($result['success']) {
            cli_writeln("Email sent successfully.");
            cli_writeln("  - To: " . $options['to']);
            cli_writeln("  - Subject: " . $subject);
            cli_writeln("  - HTTP Code: " . $result['http_code']);
        } else {
            cli_writeln("Failed to send email.");
            cli_writeln("  - HTTP Code: " . $result['http_code']);
            cli_writeln("  - Response: " . substr($result['response'], 0, 200));
        }
    } catch (\Exception $e) {
        cli_writeln("Error: " . $e->getMessage());
        exit(1);
    }

    exit(0);
}

cli_writeln("No action specified. Use --help for options.");
