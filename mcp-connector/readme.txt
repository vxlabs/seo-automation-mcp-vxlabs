=== MCP Connector ===
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Exposes this WordPress site as a remote MCP (Model Context Protocol) server, so AI
clients such as Claude can manage posts, pages, comments, and users through
API-key-authenticated tool calls.

== Setup ==

1. Create a dedicated WordPress user for the connector (recommended role: Editor)
   under Users -> Add New. Avoid binding keys to an administrator account.
2. Activate this plugin.
3. Optionally switch Settings -> Permalinks to a non-Plain structure for the clean
   `/mcp?api-key=...` URL (the `?rest_route=` fallback works either way).
4. Go to MCP Connector -> API Keys, generate a key bound to the dedicated user,
   and copy the connector URL shown (only shown once).
5. Add it to Claude Code: `claude mcp add --transport http my-site "<connector url>"`
   or paste the URL into a claude.ai custom connector (requires a public HTTPS URL,
   e.g. via a tunnel, since claude.ai cannot reach localhost).

== Tools exposed ==

list_posts, get_post, create_post, update_post, list_pages, get_page, create_page,
update_page, list_comments, get_comment, moderate_comment, list_users, get_user.

Every tool call is authorized against the WordPress capabilities of the user the
presented API key is bound to.
