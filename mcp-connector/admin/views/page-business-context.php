<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status = isset( $_GET['mcp-context-status'] ) ? sanitize_key( wp_unslash( $_GET['mcp-context-status'] ) ) : '';
?>
<div class="wrap mcp-connector-wrap">
	<h1>Business Context</h1>
	<p>This is the source of truth Claude receives when researching, writing, and optimizing content. Include only information you are comfortable sending to connected AI clients.</p>

	<?php if ( 'connector-native' === $seo_provider ) : ?>
		<div class="notice notice-info inline"><p><strong>SEO output:</strong> MCP Connector is the active provider. Saved metadata and JSON-LD can be rendered on the public site.</p></div>
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

		<?php foreach ( $sections as $section_key => $section_label ) : ?>
			<div class="mcp-card">
				<h2><?php echo esc_html( $section_label ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $definitions as $key => $definition ) : ?>
						<?php if ( $section_key !== $definition['section'] ) { continue; } ?>
						<?php
						$value       = isset( $context[ $key ] ) ? $context[ $key ] : '';
						$is_lines    = in_array( $definition['type'], array( 'lines', 'url_lines' ), true );
						$input_value = $is_lines && is_array( $value ) ? implode( "\n", $value ) : $value;
						$placeholder = isset( $definition['placeholder'] ) ? $definition['placeholder'] : '';
						?>
						<tr>
							<th scope="row"><label for="mcp-context-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $definition['label'] ); ?></label></th>
							<td>
								<?php if ( in_array( $definition['type'], array( 'textarea', 'lines', 'url_lines' ), true ) ) : ?>
									<textarea id="mcp-context-<?php echo esc_attr( $key ); ?>" name="context[<?php echo esc_attr( $key ); ?>]" rows="5" class="large-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $input_value ); ?></textarea>
									<?php if ( $is_lines ) : ?><p class="description">Enter one item per line.</p><?php endif; ?>
								<?php else : ?>
									<input id="mcp-context-<?php echo esc_attr( $key ); ?>" name="context[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $definition['type'] ); ?>" value="<?php echo esc_attr( $input_value ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>

		<?php submit_button( 'Save Business Context' ); ?>
	</form>
</div>
