<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mcp_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
