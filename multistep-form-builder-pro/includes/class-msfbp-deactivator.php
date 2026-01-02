<?php
/**
 * Deactivation handler.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Deactivator {
	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
