# Automator Agent for WordPress

**Let Claude run your WordPress site.**

Automator Agent turns any WordPress install into a remote [MCP](https://modelcontextprotocol.io) (Model Context Protocol) server, so an AI client like Claude can manage your posts, pages, comments, and users through API-key-authenticated tool calls — with no separate backend, no cloud dependency, and no data leaving your server unless you point a client at it.

- **Version:** 1.1.0
- **Requires:** WordPress 6.5+, PHP 7.4+
- **License:** GPL-2.0-or-later

---

## Table of contents

1. [Why this was created](#why-this-was-created)
2. [How it works](#how-it-works)
3. [What Claude can do](#what-claude-can-do)
4. [Requirements](#requirements)
5. [Installation](#installation)
6. [Setup — step by step](#setup--step-by-step)
7. [Connecting a client](#connecting-a-client)
8. [Authentication](#authentication)
9. [Managing keys](#managing-keys)
10. [Security](#security)
11. [FAQ](#faq)
12. [Troubleshooting](#troubleshooting)
13. [For developers](#for-developers)
14. [License](#license)

---

## Why this was created

AI assistants like Claude are good at writing and organizing content, but by default they can't *touch* your website — you end up copying drafts out of a chat window and pasting them into the WordPress editor by hand.

The **Model Context Protocol (MCP)** is an open standard that lets an AI client connect to external tools and act on your behalf. Automator Agent implements that standard *inside* WordPress. Once installed, you can say things like "draft a post about our product launch and set it to pending review," and Claude does it directly through your site — no copy-paste, no separate app to run.

Most ways of doing this involve standing up a separate service that talks to the WordPress REST API from the outside, which means another server to host, secure, and keep online. Automator Agent was built to avoid all of that:

- **It lives entirely inside WordPress.** No Node service, no external database, no extra host. It runs on WordPress's own database layer, so it works on ordinary shared hosting.
- **It never phones home.** The plugin makes no outbound network calls of its own. It simply exposes one endpoint that *you* choose to hand to a client.
- **It reuses WordPress's own permission system.** You don't configure a second set of permissions that can drift out of sync — every action is checked against a real WordPress user's capabilities.

The result: you stay in control, everything is scoped to permissions you already understand, and the whole thing installs like any other plugin.

---

## How it works

```
   Claude                /mcp endpoint              WordPress
 (MCP client)  ───────▶  JSON-RPC + API key  ───────▶  your posts,
              ◀───────         (this plugin)  ◀───────   pages, etc.
```

1. You generate an **API key** and bind it to a specific WordPress user.
2. You give the key (as part of a URL) to an MCP client such as Claude.
3. When Claude wants to do something, it calls a **tool** (e.g. `create_post`) over JSON-RPC at your site's `/mcp` endpoint.
4. The plugin authenticates the key, then authorizes the action against the **real WordPress capabilities** of the user the key is bound to — and only then carries it out.

Because authorization defers to WordPress itself, there is no parallel permission system to misconfigure. Bind a key to an Editor, and the client can do exactly what an Editor can do — nothing more.

---

## What Claude can do

Twenty-five tools across business context, technical SEO, content, media, comments, and users. Each is gated by a genuine WordPress capability:

| Tool | Kind | What it does | Requires |
|---|---|---|---|
| `list_posts` / `get_post` | Read | Read blog posts | `edit_posts` for non-public statuses |
| `create_post` / `update_post` | Write | Draft and edit posts | `edit_posts` (+ `publish_posts` to publish) |
| `list_pages` / `get_page` | Read | Read pages | `edit_pages` for non-public statuses |
| `create_page` / `update_page` | Write | Draft and edit pages | `edit_pages` (+ `publish_pages` to publish) |
| `list_comments` / `get_comment` | Read | Read comments | `moderate_comments` to see non-approved |
| `moderate_comment` | Write | Approve, spam, trash or unapprove | `moderate_comments` |
| `list_users` / `get_user` | Read | Read users (email addresses are never returned) | `list_users` |
| `get_business_context` | Read | Read the freeform business context (facts, audience, positioning, editorial guardrails) | Content editing capability |
| `update_business_context` | Write | Replace the business context with new freeform text | `manage_options` |
| `get_technical_seo` / `update_technical_seo` | Read/Write | Read or write robots.txt, llms.txt, and the site-wide JSON-LD block | `manage_options` |
| `get_seo_metadata` / `audit_content_seo` | Read | Inspect metadata, JSON-LD, and deterministic content checks | Read the content |
| `update_seo_metadata` | Write | Set title, description, canonical, robots, focus topic, and JSON-LD | Edit the content |
| `list_content_revisions` / `get_content_revision` | Read | Inspect WordPress revisions before or after an edit | Read the content |
| `list_media` / `get_media` | Read | Inspect media and image alt text | Read the attachment |
| `update_media_alt_text` | Write | Update image alt text | Edit the attachment |

If the bound user lacks the required capability, the call is refused — the same way WordPress would refuse that user in the admin dashboard.

---

## Requirements

- **WordPress** 6.5 or newer
- **PHP** 7.4 or newer
- A standard WordPress database (uses WordPress's built-in `$wpdb`/mysqli layer — no `pdo_mysql` extension required)
- **HTTPS strongly recommended** (required in practice for connecting cloud clients like claude.ai)

No external services, API accounts, or command-line access are needed to install the plugin itself.

---

## Installation

**Option A — Upload through the WordPress admin (easiest)**

1. Zip the `mcp-connector` folder if it isn't already a `.zip`.
2. In your dashboard, go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip, click **Install Now**, then **Activate**.

**Option B — Copy via FTP / file manager**

1. Copy the `mcp-connector` folder into `wp-content/plugins/`.
2. Go to **Plugins** in your dashboard and click **Activate** under *Automator Agent*.

Then follow [Setup](#setup--step-by-step) below.

---

## Setup — step by step

### 1. Create a dedicated low-privilege user

Go to **Users → Add New** and create an account just for the connector. Give it the **Editor** role (or lower) unless you have a specific reason to grant more.

> **Why this matters:** every action a key performs is authorized against this user's real capabilities. Bind a key to this Editor account and, even if the key leaks, it can only manage content — never install plugins, change settings, or take over your site. Avoid binding keys to an Administrator.

### 2. Fill in Business Context

Open **Automator Agent → Business Context** and write a freeform briefing: business facts, audience, positioning, brand voice, approved and restricted claims, priority topics, and internal URLs Claude should use. This information is private in WordPress but is returned to authorized MCP clients, so do not enter secrets.

The context is stored as a private WordPress record with revision history. Only an Administrator can change it through MCP; content editors can read it for writing work. You can also have Claude draft or refine it directly over MCP using the `get_business_context` / `update_business_context` tools.

### 2a. (Optional) Set up Technical SEO

Open **Automator Agent → Technical SEO** to set a custom robots.txt, an `llms.txt` file (served at `/llms.txt` and `/llm.txt`), and a site-wide JSON-LD block — each a plain textarea, no structured forms. All three can also be read and written by Claude over MCP with the `get_technical_seo` / `update_technical_seo` tools, using your Business Context and site content as source material.

### 3. Serve your site over HTTPS

API keys travel in the request URL or an `Authorization` header. Over plain HTTP those can be seen by anyone watching the network (and, for the query-string form, may be written into server/proxy logs). The connector's admin page will warn you if the site isn't on HTTPS. **Don't use this in production without a valid TLS certificate.**

### 4. (Recommended) Set clean permalinks

Go to **Settings → Permalinks** and choose any non-**Plain** structure (e.g. **Post name**). This enables the tidy `https://your-site/mcp` endpoint.

If you leave permalinks on **Plain**, everything still works — you'll use `?rest_route=/mcp-connector/v1/mcp` instead. The plugin generates the correct endpoint either way.

### 5. Generate an API key

1. Go to **Automator Agent → API Keys**.
2. Give the key a **Label** (e.g. *Claude Code – laptop*) so you can recognize it later.
3. Under **Acts as**, pick the dedicated user you created in step 1.
4. Click **Generate New Key**.

The full key and a legacy connector URL are shown **exactly once**. Copy the key immediately; only a hash is stored. Prefer sending the key using an `Authorization: Bearer` header. The query-string URL is retained for clients that cannot configure headers, but URLs may be recorded in server and proxy logs.

---

## Connecting a client

The base endpoint contains no credential:

```
https://your-site/mcp
```

### Claude Code (command line)

```bash
claude mcp add --transport http my-site "https://your-site/mcp" \
  --header "Authorization: Bearer <your-key>"
```

Then, inside Claude Code, ask it to use your site — e.g. *"list my latest 5 draft posts on my-site."*

### Claude Desktop

Add the same remote HTTP endpoint to Claude Desktop and configure `Authorization: Bearer <your-key>` as a request header. Restart Claude Desktop and the WordPress tools will appear.

### claude.ai (custom connector)

The current API-key release is not yet suitable for a production claude.ai custom connector because it does not implement the OAuth discovery and consent flow expected by hosted connectors. Use Claude Code or another client that can send a custom Authorization header. OAuth is the next transport milestone.

---

## Authentication

There are two equivalent ways to present the key — use whichever your client supports:

| Method | Form | When to use |
|---|---|---|
| Header | `Authorization: Bearer <key>` | **Preferred** — keeps the key out of URL-based logs |
| Query string | `?api-key=<key>` | Legacy compatibility only |

> Production Claude custom connectors should use an OAuth-based authorization flow. API keys are currently intended primarily for controlled, single-owner installations and development.

## SEO ownership and plugin compatibility

When no supported SEO plugin is detected, Automator Agent can output its own title, description, canonical, robots directives, per-page JSON-LD, and the site-wide JSON-LD block set under **Technical SEO**. It does not output a meta-keywords tag. The robots.txt and llms.txt overrides are connector-only and apply regardless of which SEO plugin is active.

When Yoast SEO, Rank Math, or AIOSEO is active, connector-native frontend output and SEO writes are disabled to prevent duplicate or contradictory markup. Reading and content-level audits still work. Provider-specific write adapters are planned for a later release.

Keys are stored only as a SHA-256 hash. The plaintext is shown once at generation time and can never be recovered afterward. **Revoking a key takes effect immediately.**

---

## Managing keys

Everything lives under **Automator Agent → API Keys**:

- **Multiple keys.** Generate a separate key per client or per person, each with its own label — so you can revoke one without disrupting the others.
- **Revoke instantly.** Delete a key from the list and it stops working on the next request.
- **Rotate.** To rotate, generate a new key, update your client, then revoke the old one.
- **Audit.** Every tool call is logged and keyed to the client's IP address, so you can see what a connected client did and when.

---

## Security

Automator Agent is designed to fail safe:

- **One source of truth for permissions.** Every call is authorized against the bound user's real WordPress capabilities. Bind to an Editor, not an Administrator.
- **Keys hashed, shown once.** Only a SHA-256 hash is stored; plaintext appears a single time at generation.
- **Per-IP rate limiting.** A failed-authentication budget (default **20 attempts / 5 minutes per IP**, then a `429` with `Retry-After`) slows brute-force attempts. It uses WordPress transients, so it works on any host with no external cache required.
- **No phone-home.** The plugin makes no outbound network calls of its own. It only exposes a local endpoint you explicitly choose to share.
- **Prefer the header.** Use `Authorization: Bearer` over the query-param form wherever you control the client, to keep keys out of access logs.

> **Note on load-balanced setups:** transient-based rate limiting isn't shared across multiple servers unless you've configured a persistent object cache.

---

## FAQ

**Do I need to run any command-line tools or a separate server?**
No. Installing the plugin is enough. The command-line examples are only for *connecting a client*, and even those are optional if you use claude.ai's connector UI.

**Will this give an AI full control of my site?**
Only as much as the user you bind the key to. Bind to an Editor and it can only manage content. It can never exceed that user's WordPress capabilities.

**Is my content sent to Anthropic or anyone else?**
The plugin itself never sends anything anywhere. Data only moves when *you* connect a client and that client makes a request. What your chosen AI client then does with the response is governed by that client's own privacy terms.

**Can I use a client other than Claude?**
Yes. Any MCP-compatible client that speaks HTTP JSON-RPC can connect.

**What happens if I lose a key?**
You can't recover it — only a hash is stored. Generate a new key and revoke the old one.

**Does it work on shared hosting?**
Yes. It uses WordPress's standard database layer and requires no special PHP extensions or external services.

---

## Troubleshooting

**The `/mcp` URL returns a 404.**
Permalinks are probably set to **Plain**, or rewrite rules need flushing. Either switch to a non-Plain permalink structure under **Settings → Permalinks** (visiting that page re-saves and flushes rules), or use the `?rest_route=/mcp-connector/v1/mcp` endpoint and send the key in the Authorization header.

**I get a 401 / authentication error.**
Double-check the key is correct and hasn't been revoked, and that you copied the whole thing. Remember the plaintext is only shown once — if unsure, generate a fresh key.

**I get a 429 (Too Many Requests).**
You've hit the failed-auth rate limit. Wait for the interval in the `Retry-After` header, and fix the credentials causing the failures.

**claude.ai says it can't reach my site.**
claude.ai needs a public HTTPS URL. A `localhost` or private-network address won't work — put the site online or use a tunnel like `ngrok`.

**A tool call is refused even though the key is valid.**
The bound user lacks the required WordPress capability for that action (see [What Claude can do](#what-claude-can-do)). Bind the key to a user with the appropriate role.

---

## For developers

Two filters let you adapt the connector to your environment:

- **`mcp_connector_client_ip`** — resolve the true client IP yourself when the site sits behind a reverse proxy or CDN that doesn't populate `REMOTE_ADDR` with the real client address. Without it, rate limiting and audit logs key off whatever `REMOTE_ADDR` the web server sees (which may be a shared proxy IP).
- **`mcp_connector_rate_limit_max_attempts`** / **`mcp_connector_rate_limit_window_seconds`** — tune the failed-auth budget (defaults: 20 attempts / 300 seconds per IP).

**Endpoint:** the REST route is `/mcp-connector/v1/mcp`, surfaced at the clean `/mcp` path via a rewrite rule when permalinks are non-Plain. It speaks JSON-RPC 2.0; `tools/list` enumerates the available tools and `tools/call` invokes one.

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
