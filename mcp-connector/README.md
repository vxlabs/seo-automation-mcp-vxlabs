# MCP Connector for WordPress

Turns a WordPress site into a remote [MCP](https://modelcontextprotocol.io) (Model Context Protocol) server, so an MCP client like Claude can manage posts, pages, comments, and users through API-key-authenticated tool calls — no separate backend required.

## Requirements

- WordPress 6.5+
- PHP 7.4+
- `$wpdb`/mysqli-backed database (no `pdo_mysql` dependency)

## Installation

1. Copy the `mcp-connector` folder into `wp-content/plugins/`.
2. Activate it under `Plugins`.
3. Follow **Setup** below.

## Setup

1. **Create a dedicated low-privilege user** for the connector — `Users → Add New`, role **Editor** (or lower) recommended. Every action a key performs is authorized against this user's real WordPress capabilities, so avoid binding a key to an Administrator account: a leaked key would otherwise grant full site control instead of just content management.
2. **(Recommended) Switch permalinks** to a non-Plain structure — `Settings → Permalinks → Post name`. This enables the clean `/mcp?api-key=...` URL. The `?rest_route=/mcp-connector/v1/mcp&api-key=...` form works either way if you'd rather skip this.
3. **Use HTTPS.** The admin page will warn you if the site isn't served over TLS. API keys sent over plain HTTP are exposed to anyone who can observe the network path, and if using the query-param form, potentially logged by intermediate servers/proxies too. Don't run this in production without HTTPS.
4. **Generate a key** — `MCP Connector → API Keys`, pick the dedicated user, generate. The full key is shown exactly once; copy it and the ready-to-use connector URL immediately, since neither can be retrieved again afterward (only a salted hash is stored).

## Connecting a client

**Claude Code (local):**
```
claude mcp add --transport http my-site "http://your-site/mcp?api-key=<key>"
```

**claude.ai custom connector:** requires a publicly reachable HTTPS URL — claude.ai's cloud infrastructure cannot reach `localhost`/private IPs. For local testing, tunnel first (e.g. `ngrok http 443`) and use the tunnel's HTTPS URL.

## Authentication

Two equivalent ways to present the key — pick whichever your client supports:

- Query string: `?api-key=<key>` (only option some connector UIs expose a single URL field for)
- Header: `Authorization: Bearer <key>` (preferred where supported — doesn't end up in URL-based logging)

Keys are stored only as a SHA-256 hash; the plaintext is shown once at generation time and cannot be recovered afterward. Revoking a key takes effect immediately.

## Tools exposed

| Tool | Description | Required capability |
|---|---|---|
| `list_posts` / `get_post` | Read blog posts | `edit_posts` for non-public statuses |
| `create_post` / `update_post` | Write blog posts | `edit_posts` (+`publish_posts` to publish) |
| `list_pages` / `get_page` | Read pages | `edit_pages` for non-public statuses |
| `create_page` / `update_page` | Write pages | `edit_pages` (+`publish_pages` to publish) |
| `list_comments` / `get_comment` | Read comments | `moderate_comments` to see non-approved |
| `moderate_comment` | Approve/spam/trash/unapprove | `moderate_comments` |
| `list_users` / `get_user` | Read users (no email addresses returned) | `list_users` |

Every tool call is authorized against the real WordPress capabilities of the user the presented key is bound to — there is no separate/parallel permission system to misconfigure.

## Extensibility

- `mcp_connector_client_ip` (filter) — resolve the real client IP yourself if the site sits behind a reverse proxy/CDN that doesn't populate `REMOTE_ADDR` with the true client address. Without this, rate limiting and audit logs key off whatever `REMOTE_ADDR` the web server sees, which may be a shared proxy IP for all traffic.
- `mcp_connector_rate_limit_max_attempts` / `mcp_connector_rate_limit_window_seconds` (filters) — tune the failed-auth-attempt budget (default: 20 attempts / 5 minutes per IP before a `429` with `Retry-After`).

## Security notes

- This plugin makes no outbound network calls of its own and does not phone home — it only exposes a local endpoint that you choose to share with an MCP client.
- Prefer the `Authorization: Bearer` header over the query-param form for any deployment where you control the client, to avoid the key appearing in server/proxy access logs.
- Rate limiting is per-IP via WordPress transients (works out of the box on any host; no external cache required, but also not shared across a multi-server/load-balanced deployment unless you configure a persistent object cache).

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
