=== SmartForms ===
Contributors: smartforms
Tags: forms, elementor, recaptcha, mcp, entries
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build WordPress forms, manage entries, embed forms in Elementor, and process submissions through MCP.

== Description ==

SmartForms provides a drag-and-drop form builder, entries, Elementor rendering, reCAPTCHA, AI-assisted spam review, and gated Email/SMTP/API delivery with durable retries.

== Installation ==

1. Upload and activate SmartForms.
2. Go to SmartForms > Forms.
3. Create and publish a form.
4. Embed it using its shortcode or the SmartForms Elementor widget.

== Changelog ==

= 0.3.0 =
* Added a Sync tab with Overview, Destinations, Spam Review, and Delivery Log screens.
* Added reusable WordPress-mail, custom SMTP, and authenticated API webhook destinations.
* Added durable delivery records, idempotency keys, HTTPS/SSRF protection, and retry scheduling.
* Added independent spam classification state and fail-closed delivery gating.
* Added MCP claim, spam-verdict, delivery-list, delivery-detail, and retry tools.
* Added encrypted destination secret storage.

= 0.2.0 =
* Added drag-and-drop field creation and field reordering in the form builder.
* New forms now start with an empty canvas instead of generated starter fields.

= 0.1.2 =
* Fixed access to the hidden form editor route after adding internal navigation tabs.

= 0.1.1 =
* Added persistent Dashboard, Forms, Entries, and Settings navigation inside SmartForms admin screens.

= 0.1.0 =
* Initial form builder and entry-management release.
* Added Google reCAPTCHA v2 checkbox, v2 invisible, and v3 verification.
* Added Elementor widget and shortcode renderer.
* Added capability-checked MCP form and entry tools through MCP Connector.
