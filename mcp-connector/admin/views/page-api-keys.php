<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_table = new Mcp_Keys_List_Table();
$list_table->prepare_items();
?>
<div class="wrap mcp-connector-wrap">
	<h1>MCP Connector</h1>
	<p>Generate API keys to let Claude (or any MCP-compatible client) manage posts, pages, comments, and users on this site.</p>

	<?php if ( ! $site_is_https ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>This site is not being served over HTTPS.</strong> API keys sent to <code>/mcp</code> travel in the
				URL query string or an Authorization header — over plain HTTP these are visible to anyone able to observe
				the network traffic (and, if using the query-param form, may also be logged by servers/proxies along the
				way). Do not use this connector in production until the site has a valid TLS certificate.
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $permalinks_are_plain ) : ?>
		<div class="notice notice-warning">
			<p>
				Permalinks are currently set to "Plain." The connector still works via the URL below, but for the
				clean <code>/mcp?api-key=...</code> form, switch to a non-Plain structure under
				<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Settings &rarr; Permalinks</a>.
			</p>
		</div>
	<?php endif; ?>

	<div class="mcp-card">
		<h2>Generate New API Key</h2>
		<table class="form-table">
			<tr>
				<th><label for="mcp-key-label">Label</label></th>
				<td><input type="text" id="mcp-key-label" class="regular-text" placeholder="e.g. Claude Code – local" /></td>
			</tr>
			<tr>
				<th><label for="mcp-key-user">Acts as</label></th>
				<td>
					<?php
					wp_dropdown_users(
						array(
							'id'   => 'mcp-key-user',
							'name' => 'mcp-key-user',
						)
					);
					?>
					<p class="description">Every action this key performs is subject to this user's WordPress capabilities. Prefer a dedicated low-privilege user (e.g. an Editor account) over an administrator.</p>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="mcp-generate-key">Generate New Key</button>
		</p>
	</div>

	<h2>Existing Keys</h2>
	<?php $list_table->display(); ?>
</div>

<div id="mcp-reveal-modal" class="mcp-modal" style="display:none;">
	<div class="mcp-modal-content">
		<h2>Your new API key</h2>
		<p class="mcp-warning">This key will not be shown again. Copy it now and store it somewhere safe.</p>
		<label>API key</label>
		<input type="text" id="mcp-revealed-key" class="regular-text" readonly />
		<button type="button" class="button" id="mcp-copy-key">Copy</button>
		<label>Connector URL (paste this into Claude)</label>
		<input type="text" id="mcp-revealed-url" class="regular-text" readonly />
		<button type="button" class="button" id="mcp-copy-url">Copy</button>
		<p>
			<button type="button" class="button button-primary" id="mcp-close-modal">Done, I've saved it</button>
		</p>
	</div>
</div>
