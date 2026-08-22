=== Automator Agent ===
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Exposes this WordPress site as a remote MCP (Model Context Protocol) server, so AI
clients such as Claude can manage content and SEO metadata through authenticated
tool calls informed by a site-owned business context.

== Setup ==

1. Create a dedicated WordPress user for the connector (recommended role: Editor)
   under Users -> Add New. Avoid binding keys to an administrator account.
2. Activate this plugin.
3. Open Automator Agent -> Business Context and enter the business facts, audience,
   brand voice, editorial rules, and SEO priorities Claude should follow.
4. Optionally switch Settings -> Permalinks to a non-Plain structure for the clean
   `/mcp` URL (the `?rest_route=` fallback works either way).
5. Go to Automator Agent -> API Keys, generate a key bound to the dedicated user,
   and copy the connector URL shown (only shown once).
6. Add it to an MCP client. Prefer sending the key in an `Authorization: Bearer`
   header; the generated query-string URL is retained only for legacy clients.

== Tools exposed ==

Business context, posts, pages, WordPress revisions, connector-native SEO metadata,
JSON-LD, deterministic SEO audits, media alt text, comments, and users are exposed.

When Yoast SEO, Rank Math, or AIOSEO is detected, connector-native frontend SEO
output and writes are disabled to avoid duplicate metadata. Provider-specific write
adapters are planned for a future release.

Every tool call is authorized against the WordPress capabilities of the user the
presented API key is bound to.

== Changelog ==

= 1.3.0 =
* Added an "Edit SEO" modal to the SEO Dashboard so meta title, description,
  canonical URL, focus topic, keywords, and robots directives can be edited
  in place, without leaving the dashboard for the post editor.
* Removed OG Image from the SEO Dashboard and from front-end output — it was
  derived from the featured image rather than a real SEO field.

= 1.1.0 =
* Added private, revision-enabled Business Context with a structured admin form.
* Added connector-native SEO title, description, canonical, robots, social metadata,
  per-content JSON-LD, and homepage Organization JSON-LD.
* Added SEO audit, revision inspection, media discovery, and image alt-text tools.
* Added structured MCP tool results, runtime input validation, protocol negotiation,
  Origin validation, and modification-token conflict protection.
* Hardened content, taxonomy, status-transition, and media capability checks.

= 1.0.0 =
* Initial MCP server, API-key management, and content/comment/user tools.
