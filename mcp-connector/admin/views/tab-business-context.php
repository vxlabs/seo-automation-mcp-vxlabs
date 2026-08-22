<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status = isset( $_GET['mcp-context-status'] ) ? sanitize_key( wp_unslash( $_GET['mcp-context-status'] ) ) : '';
?>
<p>This is the source of truth Claude receives when researching, writing, and optimizing content. Include only information you are comfortable sending to connected AI clients. You can also have Claude draft or refine this for you over MCP using the <code>get_business_context</code> / <code>update_business_context</code> tools.</p>

<?php if ( 'connector-native' === $seo_provider ) : ?>
	<div class="notice notice-info inline"><p><strong>SEO output:</strong> Automator Agent is the active provider. Saved metadata and JSON-LD can be rendered on the public site.</p></div>
<?php else : ?>
	<div class="notice notice-warning inline"><p><strong>SEO provider detected: <?php echo esc_html( $seo_provider ); ?>.</strong> Connector-native metadata output and writes are disabled to prevent duplicate markup. Business context and content audits remain available.</p></div>
<?php endif; ?>

<?php if ( 'saved' === $status ) : ?>
	<div class="notice notice-success is-dismissible"><p>Business context saved. WordPress created a revision of the previous version.</p></div>
<?php elseif ( 'error' === $status ) : ?>
	<div class="notice notice-error"><p>Business context could not be saved. Check the server error log for details.</p></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="mcp_save_business_context" />
	<?php wp_nonce_field( 'mcp_save_business_context' ); ?>

	<div class="postbox">
		<div class="inside">
			<h2>Business context</h2>
			<textarea id="mcp-context-text" name="context_text" rows="24" class="large-text" placeholder="Business name, what we do, who we serve, brand voice, claims to avoid, priority topics…"><?php echo esc_textarea( $context_text ); ?></textarea>
			<p class="description">Freeform text. Write it like a briefing document for someone writing on the business's behalf.</p>
		</div>
	</div>

	<?php submit_button( 'Save Business Context' ); ?>
</form>
