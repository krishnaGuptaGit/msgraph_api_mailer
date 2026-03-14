# MS Graph API Mailer — Installation Guide

## Plugin Details

| Property | Value |
|----------|-------|
| **Component name** | `local_msgraph_api_mailer` |
| **Folder name** | `msgraph_api_mailer` |
| **Moodle location** | `{moodle_root}/local/msgraph_api_mailer/` |
| **Requires** | Moodle 5.1+, PHP 8.3+ |

## File Structure

```
msgraph_api_mailer/
├── version.php                          Plugin metadata
├── settings.php                         Admin settings page
├── lib.php                              Core hook + log helper + phpmailer patch
├── ajax.php                             AJAX handler (test buttons)
├── index.php                            Test & Validate standalone page
├── emaillog.php                         Email log viewer
├── changelog.php                        Version history page
├── README.md                            Overview and documentation
├── INSTALL.md                           This file
├── CHANGES.md                           Version changelog
├── LICENSE                              GNU GPL v3
├── amd/
│   ├── src/tester.js                    Settings page AJAX (source)
│   ├── src/index.js                     Test page AJAX (source)
│   ├── build/tester.min.js              Compiled AMD module
│   └── build/index.min.js               Compiled AMD module
├── classes/
│   ├── admin/configtext_placeholder.php  Custom admin settings
│   ├── api/graph_client.php              Graph API client + attachment handling
│   ├── hook/after_config_callbacks.php   Auto-reapply phpmailer patch
│   ├── mailer/graph_mailer.php           Email orchestration
│   └── observer/email_observer.php       Event observer (legacy, unused)
├── cli/
│   └── test_send.php                    CLI test tool
├── db/
│   ├── install.xml                      Database schema
│   ├── install.php                      Install hook (applies phpmailer patch)
│   ├── uninstall.php                    Uninstall hook (removes phpmailer patch)
│   ├── upgrade.php                      DB upgrade steps
│   ├── hooks.php                        Moodle 5.x hook registration
│   └── events.php                       Event observers (empty)
├── lang/
│   └── en/local_msgraph_api_mailer.php  English language strings
└── styles/
    └── test_section.css                 CSS for test section
```

---

## Step 1 — Azure App Registration

1. Open [Azure Portal](https://portal.azure.com)
2. Go to **Microsoft Entra ID** → **App registrations** → **New registration**
3. Configure:
   - **Name**: `Moodle Email Sender` (or any descriptive name)
   - **Supported account types**: *Accounts in this organizational directory only (Single tenant)*
   - **Redirect URI**: leave blank
4. Click **Register**
5. **Note down** from the Overview page:
   - **Application (client) ID**
   - **Directory (tenant) ID**

6. **Add API permission:**
   - Go to **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions**
   - Search for and add: `Mail.Send`
   - Click **Grant admin consent for [your organisation]** ✅

7. **Create a client secret:**
   - Go to **Certificates & secrets** → **Client secrets** → **New client secret**
   - Enter a description and choose an expiry
   - Click **Add**
   - ⚠️ **Copy the secret value immediately** — it is hidden after you leave this page

---

## Step 2 — Install the Plugin in Moodle

1. Copy the `msgraph_api_mailer` folder to `{moodle_root}/local/`
   - The final path must be: `{moodle_root}/local/msgraph_api_mailer/`
2. Log in to Moodle as **site administrator**
3. Navigate to **Site Administration** → **Notifications**
4. Moodle detects the plugin automatically. Click **Upgrade Moodle database now**
5. The install hook patches `lib/phpmailer/moodle_phpmailer.php` to restore the `phpmailer_init` callback (required for Moodle 5.x)

---

## Step 3 — Configure the Plugin

1. Go to **Site Administration** → **Plugins** → **Local plugins** → **MS Graph API Mailer**
2. Enter your Azure credentials:

| Field | Value |
|-------|-------|
| Azure Tenant ID | Directory (tenant) ID from Step 1 |
| Client ID | Application (client) ID from Step 1 |
| Client Secret | Secret value from Step 1 |
| Sender Email Address | A licensed Microsoft 365 mailbox (e.g. `noreply@yourdomain.com`) |
| Sender Display Name | Friendly name shown in From field (optional) |
| Log sent emails | ✅ Recommended |
| Fallback to SMTP on failure | ✅ Recommended |

3. Tick **Enable MS Graph Mailer** (only available once all required fields are filled)
4. Click **Save changes**

---

## Step 4 — Verify

**From the settings page:**
- The **Connection Status** badge turns green automatically if credentials are valid
- Click **Check Permissions** for a detailed connection test
- Click **Send Test Email** to send a basic test
- Click **Send Test with Attachment** to verify the full attachment pipeline (sends a real `.xlsx` file)

**From the CLI** (run from your Moodle root directory):

```bash
# Check connection and configuration status
php local/msgraph_api_mailer/cli/test_send.php --status

# Send a test email
php local/msgraph_api_mailer/cli/test_send.php --to user@example.com

# Run the full test suite
php local/msgraph_api_mailer/cli/test_send.php --test
```

---

## Troubleshooting

### "Tenant does not exist"
The Tenant ID is wrong. Verify it in Azure Portal → Microsoft Entra ID → Overview.

### "Access denied" / 403 Forbidden
- Check that `Mail.Send` application permission has **admin consent granted** (green tick in Azure Portal)
- Verify the client secret hasn't expired

### "The specified object was not found in the store" / 404
The Sender Email does not exist as a licensed user or shared mailbox in your Microsoft 365 tenant.

### Plugin not appearing after installation
Purge all Moodle caches: **Site Administration → Development → Purge all caches**, then check that the folder is named exactly `msgraph_api_mailer` (not `msgraph_api_mailer-main` or similar).

### Moodle upgrade overwrites phpmailer patch
The plugin automatically re-applies the patch via a `core\hook\after_config` callback. If emails stop routing after a Moodle upgrade, visit the settings page once (the hook fires on every request) or purge caches.

---

## Uninstalling

1. Go to **Site Administration** → **Plugins** → **Plugin overview**
2. Find *MS Graph API Mailer* and click **Uninstall**
3. The uninstall hook removes the `moodle_phpmailer.php` patch and drops the log table
4. Confirm — Moodle reverts to standard SMTP delivery
