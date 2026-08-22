# SmartForms

SmartForms is a standalone WordPress form builder with entry management, Google reCAPTCHA, Elementor embedding, AI-assisted spam review, and gated Email/SMTP/API synchronization.

## Requirements

- WordPress 6.5+
- PHP 7.4+
- MCP Connector from this repository for MCP access (forms continue to work without it)
- Elementor is optional

## Installation

1. Upload the `smartforms` folder or its release ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate **SmartForms**.
3. Open **SmartForms → Forms** and create a form.
4. Publish it and use its `[smartforms id="123"]` shortcode, Gutenberg Shortcode block, or the SmartForms Elementor widget.

## Included field types

Text, Email, URL, Textarea, Multiple Choice, Checkbox, GDPR Agreement, Number, Phone Number, Dropdown, Address, Custom Button, Separator, Heading, Image, and Icon.

Payment fields are intentionally not part of this release. A secure payment implementation must use provider-hosted fields and verified webhooks; SmartForms must never accept raw card numbers.

## Admin screens

- **Dashboard:** form and entry totals plus recent submissions.
- **Forms:** create, publish, duplicate, embed, and trash forms.
- **Entries:** filter, view, export, add internal notes, and move entries through `new`, `read`, `in_progress`, `processed`, `spam`, and `trash`.
- **Sync:** configure reusable Email/SMTP and authenticated API destinations, review spam, and inspect or retry durable deliveries.
- **Settings:** validation messages, Google reCAPTCHA v2 checkbox/invisible or v3, entry retention, IP-hash logging, and uninstall behavior.

## Google reCAPTCHA

Set a version, site key, and secret under **SmartForms → Settings**, then enable reCAPTCHA on individual forms. Verification occurs server-side. Secret keys are never rendered on the frontend or returned through MCP.

## Elementor

When Elementor is active, SmartForms registers a **SmartForms** widget. Select a published form, choose whether to show its title, and apply basic button, label, and spacing styles. The widget uses the same server renderer as the shortcode.

## MCP tools

MCP Connector exposes these tools when both plugins are active:

- `smartforms_list_forms`
- `smartforms_get_form`
- `smartforms_create_form`
- `smartforms_update_form`
- `smartforms_duplicate_form`
- `smartforms_get_form_stats`
- `smartforms_list_entries`
- `smartforms_get_entry`
- `smartforms_update_entry_status`
- `smartforms_bulk_update_entries`
- `smartforms_add_entry_note`
- `smartforms_get_settings`
- `smartforms_claim_spam_reviews`
- `smartforms_get_spam_review`
- `smartforms_set_spam_verdict`
- `smartforms_list_deliveries`
- `smartforms_get_delivery`
- `smartforms_retry_delivery`

MCP settings never return the reCAPTCHA secret. Permanent deletion is intentionally absent from the first MCP tool set.

### Capabilities

- `smartforms_manage_forms`
- `smartforms_view_entries`
- `smartforms_manage_entries`
- `smartforms_manage_settings`
- `smartforms_delete_entries` (reserved for future explicit deletion UI/tools)
- `smartforms_manage_integrations`
- `smartforms_classify_entries`
- `smartforms_view_delivery_logs`
- `smartforms_retry_deliveries`

Administrators receive all capabilities. The included **SmartForms Manager** role can manage forms and entries, classify spam, view delivery logs, and retry deliveries, but cannot change destinations, global settings, or permanently delete entries. MCP tools inherit the capabilities of the WordPress user bound to the API key.

## Spam-gated synchronization

Stored submissions begin with `spam_status=pending`. Obvious spam can be blocked by local rules. An MCP client such as ChatGPT or Claude can claim pending reviews and submit `clean` or `spam` verdicts; administrators can make the same decision in WordPress. Only a clean verdict creates delivery jobs. If no MCP client is scheduled, entries remain pending and are not sent.

Destinations are attached per form. Email destinations use the site mailer or a custom SMTP connection. API destinations use HTTPS JSON webhooks with bearer, API-key, basic, or HMAC authentication. Secrets are encrypted at rest, never returned through MCP, and must be re-entered if WordPress authentication salts are rotated. Deliveries use an idempotency key and retry after 1 minute, 5 minutes, 30 minutes, 2 hours, and 12 hours before becoming dead.

## Data and privacy

Forms use a private `smartform` post type with revisions. Entries, activity, destinations, form assignments, and delivery attempts are stored in dedicated SmartForms tables. Each entry keeps a schema snapshot so historical answers remain understandable after a form changes.

Raw IP addresses are not stored. Optional IP logging stores only a site-salted hash. Retention defaults to `0` (keep until manually handled). Deactivation preserves all data. Uninstall also preserves data unless **Delete SmartForms data when uninstalled** was explicitly enabled beforehand.

## Extending MCP Connector

This repository adds an `mcp_connector_tools` filter to MCP Connector. Companion plugins can add definitions using the same `handler`, `description`, `inputSchema`, and `annotations` shape as built-in tools.

## Migration from SureForms

Run SmartForms alongside SureForms on a staging site first. Forms can be rebuilt now, but the automated SureForms JSON/CSV migration adapter needs real export fixtures from the target SureForms version so unsupported fields and integrations can be reported instead of silently discarded. Elementor SureForms widgets should remain in place until their corresponding SmartForms forms have been tested.
