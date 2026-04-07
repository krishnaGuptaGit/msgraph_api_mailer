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
 * External function: send_test_email_attachment.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Sends a test email with a real .xlsx attachment (no file extension, like Moodle scheduled reports)
 * via Microsoft Graph API.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_test_email_attachment extends external_api {
    /**
     * Describes input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email' => new external_value(PARAM_EMAIL, 'Recipient email address for the test message'),
        ]);
    }

    /**
     * Build a .xlsx attachment, send it, and return a pass/fail result.
     *
     * @param  string $email Recipient email address.
     * @return array {success: bool, message: string}
     */
    public static function execute(string $email): array {
        global $CFG;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $params = self::validate_parameters(self::execute_parameters(), ['email' => $email]);
        $email  = $params['email'];

        require_once($CFG->dirroot . '/local/msgraph_api_mailer/lib.php');

        $senderemail = get_config('local_msgraph_api_mailer', 'sender_email');
        if (empty($senderemail)) {
            return [
                'success' => false,
                'message' => get_string('missing_config', 'local_msgraph_api_mailer'),
            ];
        }

        $tmpfile = self::build_test_xlsx();
        if (!$tmpfile) {
            return [
                'success' => false,
                'message' => 'Could not create test xlsx (ZipArchive extension required).',
            ];
        }

        $subject = 'Test Email with Attachment - MS Graph Mailer';
        $body    = '<h2>Attachment Test</h2>' .
                   '<p>This test email verifies that file attachments are delivered correctly.</p>' .
                   '<ul>' .
                   '<li><strong>Temp filename:</strong> ' .
                       htmlspecialchars(basename($tmpfile), ENT_QUOTES) . ' (no extension)</li>' .
                   '<li><strong>Expected attachment name:</strong> Test Report.xlsx</li>' .
                   '<li><strong>MIME detection:</strong> magic bytes (PK ZIP + xl/workbook marker)</li>' .
                   '</ul>' .
                   '<p>If you can open the attached <code>Test Report.xlsx</code>, ' .
                       'the attachment pipeline is working correctly.</p>' .
                   '<p><strong>Sent:</strong> ' . date('Y-m-d H:i:s') . '</p>';

        // Mimic exactly what Moodle scheduler produces:
        // Filepath is a temp path without extension, MIME is application/octet-stream.
        $attachments = [[
            'filepath' => $tmpfile,
            'filename' => 'Test Report',
            'mimetype' => 'application/octet-stream',
            'isstring' => false,
        ]];

        try {
            $client = new \local_msgraph_api_mailer\api\graph_client();
            $result = $client->send_email($email, $subject, $body, null, $attachments);

            @unlink($tmpfile);

            if ($result['success']) {
                local_msgraph_api_mailer_log_record([$email], $subject, 1, 'Test with attachment sent', 1);
                return [
                    'success' => true,
                    'message' => get_string('email_attachment_sent_success', 'local_msgraph_api_mailer'),
                ];
            }

            $errormsg = 'HTTP ' . $result['http_code'] . ' -- ' . substr($result['response'], 0, 500);
            local_msgraph_api_mailer_log_record([$email], $subject, 0, $errormsg, 1);
            return [
                'success' => false,
                'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') .
                             ' HTTP ' . $result['http_code'] .
                             (!empty($result['response'])
                                 ? ' - ' . substr(strip_tags($result['response']), 0, 200)
                                 : ''),
            ];
        } catch (\Exception $e) {
            @unlink($tmpfile);
            local_msgraph_api_mailer_log_record([$email], $subject, 0, $e->getMessage(), 1);
            return [
                'success' => false,
                'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') .
                             ' ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the test email was sent successfully'),
            'message' => new external_value(PARAM_TEXT, 'Human-readable result message'),
        ]);
    }

    /**
     * Build a minimal valid .xlsx file in a temp path WITHOUT a file extension.
     * Simulates the extensionless temp files produced by Moodle scheduled report delivery.
     *
     * @return string|false Absolute temp file path on success, false if ZipArchive is unavailable.
     */
    private static function build_test_xlsx() {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tempup_');
        unlink($tmp); // Remove placeholder so ZipArchive creates a clean file.

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE) !== true) {
            return false;
        }

        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels"' .
            ' ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml"' .
            ' ContentType="application/vnd.openxmlformats-officedocument' .
            '.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml"' .
            ' ContentType="application/vnd.openxmlformats-officedocument' .
            '.spreadsheetml.worksheet+xml"/>' .
            '</Types>'
        );

        $zip->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships' .
            ' xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1"' .
            ' Type="http://schemas.openxmlformats.org/officeDocument/2006' .
            '/relationships/officeDocument"' .
            ' Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook' .
            ' xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' .
            ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Test Report" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>'
        );

        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships' .
            ' xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1"' .
            ' Type="http://schemas.openxmlformats.org/officeDocument/2006' .
            '/relationships/worksheet"' .
            ' Target="worksheets/sheet1.xml"/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>' .
            '<row r="1">' .
            '<c r="A1" t="inlineStr"><is><t>Name</t></is></c>' .
            '<c r="B1" t="inlineStr"><is><t>Course</t></is></c>' .
            '<c r="C1" t="inlineStr"><is><t>Status</t></is></c>' .
            '</row>' .
            '<row r="2">' .
            '<c r="A2" t="inlineStr"><is><t>Test User</t></is></c>' .
            '<c r="B2" t="inlineStr"><is><t>Sample Course</t></is></c>' .
            '<c r="C2" t="inlineStr"><is><t>Completed</t></is></c>' .
            '</row>' .
            '</sheetData></worksheet>'
        );

        $zip->close();

        return file_exists($tmp) ? $tmp : false;
    }
}
