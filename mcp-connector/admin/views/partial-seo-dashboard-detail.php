<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax-loaded detail panel for one post's SEO Dashboard row.
 * Expects $post_id, $seo (Mcp_Seo_Service::get_for_post()), and
 * $audit (Mcp_Seo_Service::audit_post()) in scope — set by
 * Mcp_Seo_Dashboard::render_detail_panel() before requiring this file.
 */

$title_len = function_exists( 'mb_strlen' ) ? mb_strlen( $seo['title'] ) : strlen( $seo['title'] );
$desc_len  = function_exists( 'mb_strlen' ) ? mb_strlen( $seo['description'] ) : strlen( $seo['description'] );

// Mirrors Mcp_Seo_Service::render_head_metadata()'s exact field derivation
// so this preview matches what actually prints in <head> on the front end.
$og_title  = $seo['title'] ? $seo['title'] : get_the_title( $post_id );
$share_url = $seo['canonical_url'] ? $seo['canonical_url'] : get_permalink( $post_id );
$og_type   = 'post' === get_post_type( $post_id ) ? 'article' : 'website';
?>
<div class="mcp-seo-detail-panel">

	<h4>Content Audit</h4>
	<ul class="mcp-seo-audit-stats">
		<li>Word count: <strong><?php echo esc_html( $audit['word_count'] ); ?></strong></li>
		<li>H1 count: <strong class="<?php echo $audit['content_h1_count'] > 1 ? 'mcp-seo-flag' : ''; ?>"><?php echo esc_html( $audit['content_h1_count'] ); ?></strong></li>
		<li>Links: <strong><?php echo esc_html( $audit['internal_and_external_links'] ); ?></strong></li>
		<li>Images: <strong><?php echo esc_html( $audit['image_count'] ); ?></strong></li>
		<li>Images missing alt text: <strong class="<?php echo $audit['images_missing_alt'] > 0 ? 'mcp-seo-flag' : ''; ?>"><?php echo esc_html( $audit['images_missing_alt'] ); ?></strong></li>
		<li>SEO title length: <strong><?php echo esc_html( $title_len ); ?></strong> chars</li>
		<li>Meta description length: <strong><?php echo esc_html( $desc_len ); ?></strong> chars</li>
		<li>Custom JSON-LD: <strong><?php echo $audit['has_custom_schema'] ? 'Yes' : 'No'; ?></strong></li>
	</ul>

	<?php if ( ! empty( $audit['issues'] ) ) : ?>
		<h4>Issues</h4>
		<ul class="mcp-seo-issue-list">
			<?php foreach ( $audit['issues'] as $issue ) : ?>
				<li class="mcp-seo-issue-<?php echo esc_attr( $issue['severity'] ); ?>"><?php echo esc_html( $issue['message'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h4>Open Graph / Twitter Preview</h4>
	<ul class="mcp-seo-og-preview">
		<li>og:type: <code><?php echo esc_html( $og_type ); ?></code></li>
		<li>og:title: <code><?php echo esc_html( $og_title ); ?></code></li>
		<li>og:url: <code><?php echo esc_html( $share_url ); ?></code></li>
		<?php if ( $seo['description'] ) : ?>
			<li>og:description: <code><?php echo esc_html( $seo['description'] ); ?></code></li>
		<?php endif; ?>
		<li>twitter:card: <code>summary</code></li>
	</ul>

	<h4>JSON-LD</h4>
	<?php if ( $seo['schema'] ) : ?>
		<pre class="mcp-seo-jsonld"><?php echo esc_html( wp_json_encode( $seo['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	<?php else : ?>
		<p><em>No per-post JSON-LD set.</em></p>
	<?php endif; ?>

	<?php if ( ! empty( $seo['warning'] ) ) : ?>
		<p class="mcp-warning"><?php echo esc_html( $seo['warning'] ); ?></p>
	<?php endif; ?>
</div>
