<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'keys'      => 'API Keys',
	'context'   => 'Business Context',
	'technical' => 'Technical SEO',
	'seo'       => 'SEO Dashboard',
);

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'keys';
if ( ! isset( $tabs[ $active_tab ] ) ) {
	$active_tab = 'keys';
}
?>
<div class="wrap mcp-connector-wrap">
	<h1>Automator Agent</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcp-connector&tab=' . $tab_key ) ); ?>" class="nav-tab<?php echo $active_tab === $tab_key ? ' nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'keys' === $active_tab ) : ?>
		<?php require MCP_CONNECTOR_DIR . 'admin/views/tab-api-keys.php'; ?>
	<?php elseif ( 'context' === $active_tab ) : ?>
		<?php require MCP_CONNECTOR_DIR . 'admin/views/tab-business-context.php'; ?>
	<?php elseif ( 'technical' === $active_tab ) : ?>
		<?php require MCP_CONNECTOR_DIR . 'admin/views/tab-technical-seo.php'; ?>
	<?php elseif ( 'seo' === $active_tab ) : ?>
		<?php require MCP_CONNECTOR_DIR . 'admin/views/tab-seo-dashboard.php'; ?>
	<?php endif; ?>
</div>
