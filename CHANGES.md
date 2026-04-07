# Changelog — MS Graph API Mailer

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/).

---

## [1.1.8] — 2026-04-08 (version 2026040800)

### Fixed
- **Mustache docblock format** — merged the two separate `{{! }}` comment blocks (license + `@template`) into one in all three templates (`changelog.mustache`, `email_log.mustache`, `test_validate.mustache`) to resolve prechecker "Example context JSON is unparsable" errors
- **AMD build files rebuilt** — `amd/build/index.min.js` and `amd/build/tester.min.js` (+ source maps) regenerated from source to clear "uncommitted change detected" prechecker errors

---

## [1.1.7] — 2026-04-07 (version 2026040700)

### Fixed
- **Array spacing in settings.php** — corrected missing/extra spaces around `=>` and after commas in the `$statusmap` array (lines 53–57) to comply with Moodle coding standard (phpcs)

---

## [1.1.0] — 2026-03-14 (version 2026031400)

### Added
- **Send Test with Attachment** button on settings page — sends a real `.xlsx` file to verify the full attachment pipeline end-to-end
- **Connection status badge** — live green/red indicator on the settings page, auto-checked on page load without requiring a button click
- **Large attachment support (up to 150 MB)** — attachments ≥ 3 MB are now uploaded via the Microsoft Graph upload session API (chunked PUT requests) instead of inline base64, bypassing the `sendMail` 3 MB payload limit
- **Attachment column in Email Log** — table and CSV export now show a paperclip indicator / "Yes"/"No" for emails that had attachments
- **Changelog page** (`/local/msgraph_api_mailer/changelog.php`) — version history accessible from the settings page
- `CHANGES.md` — this file

### Fixed
- **Attachment filename was temp name** (`tempup_XYZ`) — lib.php was using `$attach[1]` (PHPMailer's `basename($path)`) instead of `$attach[2]` (the display name passed to `addAttachment`)
- **Attachment had no extension** — MIME type detection now reads magic bytes from the already-loaded in-memory content instead of reopening the temp file (which Moodle may delete between reads). Correct extension is appended automatically for xlsx, docx, pptx, xls, pdf, and other formats
- **Uninstall error** (`Call to undefined function local_msgraph_api_mailer_remove_phpmailer_patch`) — `db/uninstall.php` now `require_once`s `lib.php` before calling patch-removal functions; Moodle does not auto-include `lib.php` before uninstall hooks

### Changed
- `graph_client::format_attachments()` renamed internally to `split_attachments()` returning `[$small, $large]` to support the two-path send flow
- Settings page link bar now includes Changelog button alongside Test Page and Email Log buttons

---

## [1.0.0] — 2026-03-13 (version 2026031302)

### Added
- Initial stable release
- Route all Moodle outgoing emails through Microsoft Graph API (replaces SMTP)
- OAuth2 client credentials flow with token caching (5-minute expiry buffer)
- Intercepts emails via `phpmailer_init` hook in `lib.php`; patches `moodle_phpmailer.php` on install to restore the hook removed in Moodle 5.x
- Patch auto-verified and re-applied via `core\hook\after_config` callback after Moodle upgrades
- Respects Moodle's `$CFG->divertallemailsto` email diverting setting
- SMTP fallback — optional automatic fallback to PHPMailer/SMTP on Graph API failure
- Email log viewer (`emaillog.php`) — searchable by recipient, subject, status, date range; CSV export; pagination
- Test & Validate page (`index.php`) — configuration status badges, Check Permissions, Send Test Email
- Inline AJAX test buttons on the settings page
- Mandatory field validation — cannot enable plugin without all Azure credentials filled in
- `db/install.xml` — creates `local_msgraph_api_mailer_log` table
- `db/install.php` / `db/uninstall.php` — apply/remove the phpmailer patch
- CLI test tool (`cli/test_send.php`) with `--status`, `--test`, `--to`, `--subject`, `--body` options
