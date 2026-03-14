<?php
namespace local_msgraph_api_mailer\api;

defined('MOODLE_INTERNAL') || die();

class graph_client {

    /** Attachments >= this size (bytes) are uploaded via upload session instead of inline base64. */
    private const LARGE_ATTACHMENT_THRESHOLD = 3145728; // 3 MB

    /** Chunk size for upload session: must be a multiple of 320 KB. Using 4 × 320 KB = 1.25 MB. */
    private const UPLOAD_CHUNK_SIZE = 1310720; // 4 × 327680

    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $access_token;
    private $token_expiry;

    public function __construct() {
        $this->tenant_id     = trim((string) get_config('local_msgraph_api_mailer', 'tenant_id'));
        $this->client_id     = trim((string) get_config('local_msgraph_api_mailer', 'client_id'));
        $this->client_secret = trim((string) get_config('local_msgraph_api_mailer', 'client_secret'));
    }

    public function get_access_token() {
        if ($this->access_token && time() < $this->token_expiry) {
            return $this->access_token;
        }

        if (empty($this->tenant_id) || empty($this->client_id) || empty($this->client_secret)) {
            throw new \Exception('MS Graph Mailer: Missing configuration (Tenant ID, Client ID, or Client Secret)');
        }

        $url = 'https://login.microsoftonline.com/' . rawurlencode($this->tenant_id) . '/oauth2/v2.0/token';
        $postdata = 'grant_type=client_credentials'
            . '&client_id='     . rawurlencode($this->client_id)
            . '&client_secret=' . rawurlencode($this->client_secret)
            . '&scope='         . rawurlencode('https://graph.microsoft.com/.default');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => $postdata,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postdata),
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Failed to get access token: HTTP $httpCode - cURL error: $error - Response: $response");
        }

        $json = json_decode($response, true);
        if (!isset($json['access_token'])) {
            throw new \Exception('MS Graph Mailer: No access token in response: ' . $response);
        }

        $this->access_token = $json['access_token'];
        $this->token_expiry = time() + (($json['expires_in'] ?? 3600) - 300);

        return $this->access_token;
    }

    /**
     * Send an email via Microsoft Graph API.
     * Automatically routes large attachments (>= 3 MB) through an upload session
     * instead of inline base64 to stay within the sendMail payload limit.
     */
    public function send_email($to, $subject, $body, $from = null, $attachments = []) {
        $token               = $this->get_access_token();
        $sender_email        = trim((string) get_config('local_msgraph_api_mailer', 'sender_email'));
        $sender_display_name = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));
        $read_receipt        = (bool) get_config('local_msgraph_api_mailer', 'read_receipt_enabled');

        if (empty($sender_email)) {
            throw new \Exception('MS Graph Mailer: Sender email not configured');
        }

        $from_address = ['address' => $sender_email];
        if (!empty($sender_display_name)) {
            $from_address['name'] = $sender_display_name;
        }

        // Process all attachments: load content, detect MIME, fix filename extension.
        // Returns two lists: small (inline base64) and large (need upload session).
        [$small_attachments, $large_attachments] = $this->split_attachments($attachments);

        $message = [
            'subject'                => $subject,
            'body'                   => ['contentType' => 'HTML', 'content' => $body],
            'from'                   => ['emailAddress' => $from_address],
            'toRecipients'           => $this->format_recipients($to),
            'isReadReceiptRequested' => $read_receipt,
        ];

        if (!empty($small_attachments)) {
            $message['attachments'] = $small_attachments;
        }

        if (empty($large_attachments)) {
            // ── Fast path: single POST to /sendMail ──────────────────────────
            $postdata = json_encode(['message' => $message]);
            $url      = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender_email) . '/sendMail';
            $result   = $this->graph_request($url, $token, 'POST', $postdata);

            return [
                'success'   => ($result['http_code'] === 202),
                'http_code' => $result['http_code'],
                'response'  => $result['body'],
                'error'     => $result['error'],
            ];
        }

        // ── Slow path: draft → upload large attachments → send ───────────────
        return $this->send_via_draft($token, $sender_email, $message, $large_attachments);
    }

    public function test_connection() {
        try {
            $this->get_access_token();
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Process raw attachment list: load file content, detect MIME, fix filename.
     * Split into small (< 3 MB, inline base64) and large (>= 3 MB, upload session).
     *
     * @return array [small_attachments[], large_attachments[]]
     */
    private function split_attachments($attachments) {
        $small = [];
        $large = [];

        foreach ($attachments as $attachment) {
            if (!empty($attachment['isstring'])) {
                // addStringAttachment(): filepath holds raw string content, not a path.
                $content  = $attachment['filepath'];
                $mimetype = $attachment['mimetype'] ?? 'application/octet-stream';
            } elseif (file_exists($attachment['filepath'])) {
                $content  = file_get_contents($attachment['filepath']);
                $mimetype = $attachment['mimetype'] ?? 'application/octet-stream';
            } else {
                continue; // file missing — skip
            }

            if ($content === false || $content === '') {
                continue;
            }

            // Detect real MIME from in-memory content (avoids reopening temp files
            // that Moodle may delete before a second fopen call).
            if ($mimetype === 'application/octet-stream' || $mimetype === 'application/zip') {
                $detected = $this->detect_mime_from_content($content);
                if ($detected !== null) {
                    $mimetype = $detected;
                }
            }

            // Ensure filename has a proper extension.
            $name = $attachment['filename'];
            if (pathinfo($name, PATHINFO_EXTENSION) === '') {
                $ext = $this->mime_to_extension($mimetype);
                if ($ext !== '') {
                    $name .= '.' . $ext;
                }
            }

            $size = strlen($content);

            if ($size >= self::LARGE_ATTACHMENT_THRESHOLD) {
                // Large: will be uploaded via upload session after draft is created.
                $large[] = [
                    'name'     => $name,
                    'mimetype' => $mimetype,
                    'content'  => $content,
                    'size'     => $size,
                ];
            } else {
                // Small: include inline as base64 in the message body.
                $small[] = [
                    '@odata.type'  => '#microsoft.graph.fileAttachment',
                    'name'         => $name,
                    'contentType'  => $mimetype,
                    'contentBytes' => base64_encode($content),
                ];
            }
        }

        return [$small, $large];
    }

    /**
     * Send via draft → upload session flow (used when any attachment >= 3 MB).
     *
     * 1. POST /users/{sender}/messages          → create draft, get message ID
     * 2. For each large attachment:
     *    POST …/attachments/createUploadSession  → get upload URL
     *    PUT chunks to upload URL
     * 3. POST /users/{sender}/messages/{id}/send
     */
    private function send_via_draft($token, $sender_email, $message, $large_attachments) {
        $base = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender_email);

        // Step 1 — create draft message
        $result = $this->graph_request($base . '/messages', $token, 'POST', json_encode($message));

        if ($result['http_code'] !== 201) {
            return [
                'success'   => false,
                'http_code' => $result['http_code'],
                'response'  => $result['body'],
                'error'     => 'Failed to create draft: HTTP ' . $result['http_code'],
            ];
        }

        $draft      = json_decode($result['body'], true);
        $message_id = $draft['id'] ?? null;

        if (!$message_id) {
            return ['success' => false, 'http_code' => 0, 'response' => '', 'error' => 'No message ID in draft response'];
        }

        // Step 2 — upload each large attachment
        foreach ($large_attachments as $attachment) {
            $upload_result = $this->upload_large_attachment($token, $base, $message_id, $attachment);
            if (!$upload_result['success']) {
                // Clean up: delete the orphaned draft.
                $this->graph_request($base . '/messages/' . rawurlencode($message_id), $token, 'DELETE', '');
                return $upload_result;
            }
        }

        // Step 3 — send the draft
        $send_url = $base . '/messages/' . rawurlencode($message_id) . '/send';
        $result   = $this->graph_request($send_url, $token, 'POST', '');

        return [
            'success'   => ($result['http_code'] === 202),
            'http_code' => $result['http_code'],
            'response'  => $result['body'],
            'error'     => $result['error'],
        ];
    }

    /**
     * Upload a single large attachment to a draft message via Graph upload session.
     * Sends raw bytes in chunks (no base64).
     */
    private function upload_large_attachment($token, $base_url, $message_id, $attachment) {
        // Create upload session
        $session_url  = $base_url . '/messages/' . rawurlencode($message_id) . '/attachments/createUploadSession';
        $session_body = json_encode([
            'AttachmentItem' => [
                'attachmentType' => 'file',
                'name'           => $attachment['name'],
                'size'           => $attachment['size'],
                'contentType'    => $attachment['mimetype'],
            ],
        ]);

        $result = $this->graph_request($session_url, $token, 'POST', $session_body);

        if ($result['http_code'] !== 200) {
            return [
                'success'   => false,
                'http_code' => $result['http_code'],
                'response'  => $result['body'],
                'error'     => 'Failed to create upload session: HTTP ' . $result['http_code'],
            ];
        }

        $session    = json_decode($result['body'], true);
        $upload_url = $session['uploadUrl'] ?? null;

        if (!$upload_url) {
            return ['success' => false, 'http_code' => 0, 'response' => '', 'error' => 'No uploadUrl in session response'];
        }

        // Upload in chunks — no Authorization header needed (URL already contains credentials)
        $total  = $attachment['size'];
        $offset = 0;

        while ($offset < $total) {
            $chunk     = substr($attachment['content'], $offset, self::UPLOAD_CHUNK_SIZE);
            $chunk_len = strlen($chunk);
            $end       = $offset + $chunk_len - 1;

            $ch = curl_init($upload_url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => $chunk,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/octet-stream',
                    'Content-Length: ' . $chunk_len,
                    'Content-Range: bytes ' . $offset . '-' . $end . '/' . $total,
                ],
                CURLOPT_TIMEOUT        => 120,
            ]);

            $resp     = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlerr  = curl_error($ch);
            curl_close($ch);

            // 200 = chunk received (more expected), 201 = final chunk accepted
            if ($httpCode !== 200 && $httpCode !== 201) {
                return [
                    'success'   => false,
                    'http_code' => $httpCode,
                    'response'  => $resp,
                    'error'     => "Chunk upload failed at offset $offset: HTTP $httpCode" . ($curlerr ? " - $curlerr" : ''),
                ];
            }

            $offset += $chunk_len;
        }

        return ['success' => true, 'http_code' => 201, 'response' => '', 'error' => ''];
    }

    /**
     * Generic Graph API HTTP request helper.
     */
    private function graph_request($url, $token, $method, $body) {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        if ($body !== '') {
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body !== '' ? $body : null,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $resp     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        return ['body' => $resp, 'http_code' => $httpCode, 'error' => $curlerr];
    }

    private function format_recipients($recipients) {
        $formatted = [];
        if (is_array($recipients)) {
            foreach ($recipients as $email) {
                if (!empty($email)) {
                    $formatted[] = ['emailAddress' => ['address' => $email]];
                }
            }
        } elseif (!empty($recipients)) {
            $formatted[] = ['emailAddress' => ['address' => $recipients]];
        }
        return $formatted;
    }

    /**
     * Detect MIME type from file content already loaded in memory.
     * Reads magic bytes and, for ZIP files, searches for OOXML marker paths.
     * Does NOT reopen the file — temp files may already be deleted by Moodle.
     */
    private function detect_mime_from_content($content) {
        if (strlen($content) < 4) {
            return null;
        }
        $magic = substr($content, 0, 8);

        // PDF
        if (substr($magic, 0, 4) === '%PDF') {
            return 'application/pdf';
        }

        // OLE2 Compound Document — legacy .xls / .doc / .ppt
        if ($magic === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return 'application/vnd.ms-excel';
        }

        // ZIP-based (OOXML: xlsx, docx, pptx)
        if (substr($magic, 0, 4) === "PK\x03\x04") {
            // OOXML files contain their part paths in the ZIP central directory.
            if (strpos($content, 'xl/workbook') !== false || strpos($content, 'xl/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            if (strpos($content, 'word/document') !== false || strpos($content, 'word/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
            if (strpos($content, 'ppt/presentation') !== false || strpos($content, 'ppt/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            }
            return 'application/zip';
        }

        return null;
    }

    /**
     * Map a MIME type string to a file extension (without the dot).
     */
    private function mime_to_extension($mimetype) {
        $map = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel'                                                   => 'xls',
            'application/vnd.ms-word'                                                    => 'doc',
            'application/vnd.ms-powerpoint'                                              => 'ppt',
            'application/pdf'                                                            => 'pdf',
            'application/zip'                                                            => 'zip',
            'text/csv'                                                                   => 'csv',
            'text/plain'                                                                 => 'txt',
            'image/jpeg'                                                                 => 'jpg',
            'image/png'                                                                  => 'png',
            'image/gif'                                                                  => 'gif',
        ];
        return $map[$mimetype] ?? '';
    }
}
