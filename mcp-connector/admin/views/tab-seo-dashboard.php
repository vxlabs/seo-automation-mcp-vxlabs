<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types       = Mcp_Seo_Dashboard::get_tabs();
$active_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
if ( ! isset( $post_types[ $active_post_type ] ) ) {
	reset( $post_types );
	$active_post_type = key( $post_types );
}

$list_table = new Mcp_Seo_Dashboard_List_Table( $active_post_type );
$list_table->prepare_items();
?>
<p class="description">
	Connector-managed SEO status for each content type. "Set"/"Missing" reflect the connector's own SEO fields
	(<code>_mcp_seo_*</code> postmeta) — not any third-party SEO plugin's data.
</p>

<?php if ( 'connector-native' !== $seo_provider ) : ?>
	<div class="notice notice-warning inline"><p><strong>SEO provider detected: <?php echo esc_html( $seo_provider ); ?>.</strong> Connector-native SEO editing is disabled here to prevent conflicting markup, so the "Edit SEO" action is hidden below.</p></div>
<?php endif; ?>

<nav class="nav-tab-wrapper mcp-seo-subtabs">
	<?php foreach ( $post_types as $slug => $label ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcp-connector&tab=seo&post_type=' . $slug ) ); ?>" class="nav-tab<?php echo $active_post_type === $slug ? ' nav-tab-active' : ''; ?>">
			<?php echo esc_html( $label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<form method="get">
	<input type="hidden" name="page" value="mcp-connector" />
	<input type="hidden" name="tab" value="seo" />
	<input type="hidden" name="post_type" value="<?php echo esc_attr( $active_post_type ); ?>" />
	<?php $list_table->search_box( 'Search', 'mcp-seo-search' ); ?>
	<?php $list_table->display(); ?>
</form>

<div id="mcp-seo-edit-modal" class="mcp-modal" style="display:none;">
	<div class="mcp-modal-content">
		<h2>Edit SEO</h2>
		<p class="mcp-seo-edit-error mcp-warning" style="display:none;"></p>

		<input type="hidden" id="mcp-seo-edit-post-id" />

		<label for="mcp-seo-edit-title">Meta Title</label>
		<input type="text" id="mcp-seo-edit-title" class="regular-text" maxlength="255" />
		<p class="description"><span id="mcp-seo-edit-title-count">0</span> / <?php echo (int) Mcp_Seo_Dashboard::TITLE_MAX_LEN; ?> characters</p>

		<label for="mcp-seo-edit-description">Meta Description</label>
		<textarea id="mcp-seo-edit-description" class="large-text" rows="3"></textarea>
		<p class="description"><span id="mcp-seo-edit-description-count">0</span> / <?php echo (int) Mcp_Seo_Dashboard::DESCRIPTION_MAX_LEN; ?> characters</p>

		<label for="mcp-seo-edit-canonical">Canonical URL</label>
		<input type="text" id="mcp-seo-edit-canonical" class="regular-text" />

		<label for="mcp-seo-edit-focus-topic">Focus Topic</label>
		<input type="text" id="mcp-seo-edit-focus-topic" class="regular-text" />

		<label for="mcp-seo-edit-keywords">Keywords</label>
		<input type="text" id="mcp-seo-edit-keywords" class="regular-text" />

		<p class="mcp-seo-edit-robots">
			<label><input type="checkbox" id="mcp-seo-edit-noindex" /> Noindex</label>
			<label><input type="checkbox" id="mcp-seo-edit-nofollow" /> Nofollow</label>
		</p>

		<p>
			<button type="button" class="button button-primary" id="mcp-seo-edit-save">Save</button>
			<button type="button" class="button" id="mcp-seo-edit-cancel">Cancel</button>
		</p>
	</div>
</div>
