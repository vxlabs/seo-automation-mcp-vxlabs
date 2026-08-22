<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo = Mcp_Technical_Seo::get_all();

if ( isset( $_GET['settings-updated'] ) && ! get_settings_errors( Mcp_Technical_Seo::OPTION_GROUP ) ) {
	add_settings_error( Mcp_Technical_Seo::OPTION_GROUP, 'mcp_technical_seo_saved', 'Technical SEO settings saved.', 'success' );
}
?>
<p>Robots.txt, <code>llms.txt</code>, and a site-wide JSON-LD block. You can also have Claude read the business context and site content, then write and save these for you over MCP using the <code>get_technical_seo</code> / <code>update_technical_seo</code> tools.</p>

<?php if ( 'connector-native' !== $seo_provider ) : ?>
	<div class="notice notice-warning inline"><p><strong>SEO provider detected: <?php echo esc_html( $seo_provider ); ?>.</strong> Site-wide JSON-LD output is suppressed to prevent duplicate markup. robots.txt and llms.txt are unaffected — they're not something the detected plugin manages.</p></div>
<?php endif; ?>

<?php settings_errors( Mcp_Technical_Seo::OPTION_GROUP ); ?>

<?php foreach ( $seo['robots_txt_warnings'] as $warning ) : ?>
	<div class="notice notice-warning inline"><p><?php echo esc_html( $warning ); ?></p></div>
<?php endforeach; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php settings_fields( Mcp_Technical_Seo::OPTION_GROUP ); ?>

	<div class="postbox">
		<div class="inside">
			<h2>robots.txt</h2>
			<textarea id="mcp-robots-txt" name="<?php echo esc_attr( Mcp_Technical_Seo::OPT_ROBOTS ); ?>" rows="10" class="large-text code"><?php echo esc_textarea( $seo['robots_txt'] ); ?></textarea>
			<p class="description">Leave blank to use WordPress's default robots.txt.</p>
		</div>
	</div>

	<div class="postbox">
		<div class="inside">
			<h2>llms.txt</h2>
			<textarea id="mcp-llms-txt" name="<?php echo esc_attr( Mcp_Technical_Seo::OPT_LLMS ); ?>" rows="14" class="large-text code"><?php echo esc_textarea( $seo['llms_txt'] ); ?></textarea>
			<p class="description">Markdown. Served at <a href="<?php echo esc_url( $seo['llms_txt_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $seo['llms_txt_url'] ); ?></a> (and <code>/llm.txt</code> as an alias). Leave blank to serve neither.</p>
		</div>
	</div>

	<div class="postbox">
		<div class="inside">
			<h2>Site-wide JSON-LD</h2>
			<textarea id="mcp-jsonld" name="<?php echo esc_attr( Mcp_Technical_Seo::OPT_JSONLD ); ?>" rows="16" class="large-text code"><?php echo esc_textarea( $seo['jsonld'] ); ?></textarea>
			<p class="description">A Schema.org object (or an <code>@graph</code> array). Printed in <code>&lt;head&gt;</code> on every page, alongside any page-specific schema. Leave blank to print nothing.</p>
		</div>
	</div>

	<?php submit_button( 'Save Technical SEO' ); ?>
</form>
