<?php
/**
 * Seed sample form.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Seeder {

	/**
	 * Seed if no forms exist.
	 */
	public static function maybe_seed() {
		global $wpdb;
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . msfbp_table( 'forms' ) );
		if ( $count > 0 ) {
			return;
		}

		$sample = file_get_contents( MSFBP_PLUGIN_DIR . 'assets/sample-form.json' );
		$data   = json_decode( $sample, true );

		$db = new MSFBP_DB();
		$db->save_form( $data );
	}
}
