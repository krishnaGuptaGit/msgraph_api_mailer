# MS Graph API Mailer for Moodle

Route all Moodle outgoing emails through **Microsoft Graph API** instead of SMTP — bypassing common SMTP restrictions from hosting providers and enabling enterprise-grade email delivery via Microsoft 365.

---

## ⚠️ Important: This Plugin Modifies a Moodle Core File

**Read this section before installing.**

### What the plugin does to Moodle core

To intercept **all** outgoing Moodle emails — including direct `email_to_user()` calls from any Moodle feature or third-party plugin — this plugin injects a small hook block into:

```
lib/phpmailer/moodle_phpmailer.php
```

There is no fully supported Moodle extension point that intercepts email at this level. The `message/output/email` subsystem only covers the Moodle messaging framework; it does not intercept direct `email_to_user()` calls from forum digests, enrolment notifications, password resets, and many other core and plugin features. Patching `moodle_phpmailer.php` is the only way to reliably intercept all outgoing email.

### Lifecycle of the patch

| Event | What happens |
|---|---|
| Plugin **installed** | `db/install.php` applies the patch immediately |
| Plugin **uninstalled** | `db/uninstall.php` removes the patch and restores the original file |
| **Moodle upgraded** (file overwritten) | A `core\hook\after_config` callback detects the missing patch on the very first page load after upgrade and re-applies it automatically |
| Patch **fails** (read-only file) | The patch status is stored in plugin config and shown as a red warning on the plugin settings page |

### Implications for your deployment type

**Standard shared hosting / VM (writable Moodle root)**
No special action needed. The patch is applied on install and automatically restored after upgrades.

**Git-managed Moodle core**
`lib/phpmailer/moodle_phpmailer.php` will appear as a modified file in `git status`. If you reset or pull Moodle core files without purging Moodle caches first, the plugin's `after_config` hook will restore the patch on the next page load. However, if you run `git checkout lib/phpmailer/moodle_phpmailer.php` deliberately, emails will stop being intercepted until the next page load triggers the re-patch.

If you use `git stash`, automated deployments, or a policy that rejects core file modifications, you will need to explicitly allow this file to diverge, or pre-apply the patch as part of your deployment pipeline (see below).

**Immutable containers (read-only Docker, Kubernetes with read-only mounts)**
If `lib/phpmailer/moodle_phpmailer.php` is not writable at runtime, the patch cannot be applied or re-applied. The plugin will be installed but will not intercept any email. The settings page will show a red "Patch could not be applied: file is read-only" warning.

In this case you must pre-patch the file in your container image build:

```bash
# From your Moodle root, after copying the plugin:
php local/msgraph_api_mailer/cli/apply_patch.php
```

Or apply the patch as a step in your Dockerfile before setting the filesystem to read-only.

**Deployment automation (Ansible, Capistrano, rsync deployments)**
Ensure your deployment process does not overwrite `lib/phpmailer/moodle_phpmailer.php` from a clean Moodle source without also running the patch. The safest approach is to run `php admin/cli/upgrade.php` after each Moodle update; the `after_config` hook will restore the patch on the first web request after that.

### Verifying the patch is active

After install or upgrade, open the plugin settings page:

**Site Administration → Plugins → Local plugins → MS Graph API Mailer**

The **Core Patch Status** badge at the top of the page shows whether the patch is currently applied. A green badge means all outgoing email is being intercepted. A red badge means action is required — the specific reason is shown in the badge text.

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| Moodle | 5.1+ |
| PHP | 8.3+ |
| Microsoft 365 | Any plan with Exchange Online |
| Azure AD (Entra ID) | Admin access to register an app |

## Features

- **SMTP replacement** — intercepts all Moodle emails (notifications, assignments, forum posts, password resets, scheduled reports, etc.) and routes them via Graph API
- **Attachment support** — files up to 150 MB via chunked upload session; automatic MIME detection and extension correction for Moodle temp files
- **Read receipt option** — optionally request read receipts on all outgoing emails
- **Email log viewer** — searchable, filterable log of every sent/failed email with CSV export and attachment indicator
- **Connection status badge** — live green/red indicator on the settings page, auto-checked on load
- **Test tools** — Send Test Email and Send Test with Attachment buttons directly on the settings page
- **SMTP fallback** — optional automatic fallback to PHPMailer/SMTP if Graph API fails
- **Email diverting** — respects Moodle's `$CFG->divertallemailsto` setting
- **Mandatory field validation** — cannot enable the plugin without all Azure credentials filled in
- **Changelog page** — version history accessible from the settings page

## Installation

### Step 1 — Azure App Registration

1. Open [Azure Portal](https://portal.azure.com) → **Microsoft Entra ID** → **App registrations** → **New registration**
2. Configure:
   - **Name**: `Moodle Email Sender` (or any name)
   - **Supported account types**: Accounts in this organizational directory only (Single tenant)
   - **Redirect URI**: leave blank
3. Click **Register**. Note down:
   - **Application (client) ID**
   - **Directory (tenant) ID**
4. Go to **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions**
   - Add: `Mail.Send`
   - Click **Grant admin consent for [your organisation]** ✅
5. Go to **Certificates & secrets** → **Client secrets** → **New client secret**
   - Set an expiry and click **Add**
   - **Copy the secret value immediately** — you cannot retrieve it later

### Step 2 — Install the Plugin

1. Download or clone the plugin
2. Copy the `msgraph_api_mailer` folder to `{moodle_root}/local/`
3. Log in to Moodle as administrator
4. Navigate to **Site Administration** → **Notifications** — Moodle will detect the new plugin and run the install routine automatically
5. Click **Upgrade Moodle database now**

### Step 3 — Configure the Plugin

1. Go to **Site Administration** → **Plugins** → **Local plugins** → **MS Graph API Mailer**
2. Fill in:

| Setting | Value |
|---------|-------|
| Enable MS Graph Mailer | ✅ (fill in all fields below first) |
| Azure Tenant ID | Directory (tenant) ID from Azure |
| Client ID | Application (client) ID from Azure |
| Client Secret | Secret value from Azure |
| Sender Email Address | A licensed Microsoft 365 mailbox (e.g. `noreply@yourdomain.com`) |
| Sender Display Name | e.g. `Moodle Notifications` (optional) |
| Log sent emails | ✅ recommended |
| Fallback to SMTP on failure | ✅ recommended |
| Request read receipts | Optional — recipients may decline |

3. Click **Save changes**

### Step 4 — Verify

Use the **Check Permissions** button on the settings page, or the CLI:

```bash
# From your Moodle root directory
php local/msgraph_api_mailer/cli/test_send.php --status
php local/msgraph_api_mailer/cli/test_send.php --to user@example.com
```

## Configuration Reference

| Setting | Default | Description |
|---------|---------|-------------|
| `enabled` | Off | Enable / disable email routing via Graph API |
| `tenant_id` | — | Microsoft Entra (Azure AD) Tenant ID |
| `client_id` | — | Application (client) ID |
| `client_secret` | — | Client secret value |
| `sender_email` | — | Microsoft 365 sender mailbox |
| `sender_display_name` | — | Display name in the From field |
| `log_emails` | On | Store each email result in the database |
| `fallback_smtp` | On | Fall back to SMTP if Graph API fails |
| `read_receipt_enabled` | Off | Request read receipts on all emails |

## Admin Pages

| Page | URL |
|------|-----|
| Settings | `Site Administration → Plugins → Local plugins → MS Graph API Mailer` |
| Test & Validate | `/local/msgraph_api_mailer/index.php` |
| Email Log | `/local/msgraph_api_mailer/emaillog.php` |
| Changelog | `/local/msgraph_api_mailer/changelog.php` |

## Attachment Handling

Moodle's scheduled reports and some notifications attach files as **temporary files with no extension** and a generic `application/octet-stream` MIME type. This plugin:

1. Reads the file content **into memory** before Moodle deletes the temp file
2. Detects the real MIME type from **magic bytes** (PDF `%PDF`, OLE2 legacy Office, OOXML ZIP + internal marker paths for xlsx/docx/pptx)
3. Appends the correct extension to the display filename automatically
4. For attachments **≥ 3 MB**: uses the [Graph API upload session](https://learn.microsoft.com/en-us/graph/outlook-large-attachments) (chunked PUT) — supports up to 150 MB
5. For attachments **< 3 MB**: included inline as base64 in the `sendMail` payload

## Troubleshooting

### "Tenant does not exist"
Verify the Tenant ID (Directory ID) from Azure Portal → Microsoft Entra ID → Overview.

### "Access denied" / "Forbidden" (403)
- Grant **admin consent** for the `Mail.Send` permission in Azure Portal
- Check the Client Secret hasn't expired (Azure Portal → App → Certificates & secrets)

### "Sender not in organisation" / 400 error
Verify the Sender Email exists as a licensed user or shared mailbox in your Microsoft 365 tenant.

### Emails arriving with wrong filename / no extension on attachment
Run **Send Test with Attachment** from the settings page to verify the pipeline end-to-end.

### Plugin not appearing in settings
Purge Moodle caches: **Site Administration → Development → Purge all caches**, then verify the plugin folder is named `msgraph_api_mailer`.

### CLI returns "config.php not found"
Run from your **Moodle root** directory, not from inside the plugin folder:
```bash
php local/msgraph_api_mailer/cli/test_send.php --status
```

## CLI Reference

```bash
# Check connection and config status
php local/msgraph_api_mailer/cli/test_send.php --status

# Run full test (connection + test email)
php local/msgraph_api_mailer/cli/test_send.php --test

# Send a test email to a specific address
php local/msgraph_api_mailer/cli/test_send.php --to user@example.com

# Custom subject and body
php local/msgraph_api_mailer/cli/test_send.php --to user@example.com --subject "Hello" --body "<p>Test</p>"
```

## Architecture

```
Moodle email_to_user()
    └── moodle_phpmailer::postSend()              ← lib/phpmailer/moodle_phpmailer.php
        │                                            PATCHED by this plugin on install
        └── get_plugins_with_function('phpmailer_init')   ← hook injected by patch
            └── local_msgraph_api_mailer_phpmailer_init($mail)    ← lib.php
                ├── Extract recipients, subject, body, attachments
                ├── graph_client::send_email()
                │   ├── Attachments < 3 MB  → sendMail (inline base64)
                │   └── Attachments ≥ 3 MB  → create draft → upload session → send
                └── Log result to local_msgraph_api_mailer_log table
```

The patch adds exactly one block to `postSend()` — a `get_plugins_with_function()` call that invokes any registered `phpmailer_init` callbacks before falling through to the standard SMTP send. This is the same mechanism that existed in Moodle 4.x and earlier. The plugin restores it because Moodle 5.x removed it.

**Key files:**

| File | Purpose |
|------|---------|
| `lib/phpmailer/moodle_phpmailer.php` | **Moodle core file — modified by this plugin** |
| `lib.php` | PHPMailer hook callback, log helper, patch apply/remove functions |
| `db/install.php` | Applies the patch on plugin install |
| `db/uninstall.php` | Removes the patch on plugin uninstall |
| `classes/api/graph_client.php` | OAuth2 token + Graph API calls + attachment splitting |
| `classes/hook/after_config_callbacks.php` | Detects and re-applies the patch after Moodle upgrades |

## Moodle 5.x Compatibility

Moodle 5.x removed the `phpmailer_init` plugin callback from `moodle_phpmailer.php`. This plugin patches that file on install to restore the callback. See the [⚠️ Important section](#️-important-this-plugin-modifies-a-moodle-core-file) at the top of this document for the full lifecycle and deployment implications.

## Building JavaScript

After editing `amd/src/*.js`, rebuild the minified files from the **Moodle root**:

```bash
grunt amd --root=local/msgraph_api_mailer
```

The built output goes to `amd/build/`. Include `amd/build/` files in your release zip.

## Changelog

See [CHANGES.md](CHANGES.md) for full version history.

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Follow [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle)
4. Rebuild AMD JS after any `amd/src/` changes (see above)
5. Submit a pull request

## License

This plugin is licensed under the [GNU General Public License v3.0](LICENSE).

Copyright (C) 2026. Released under GPL v3 or later.
