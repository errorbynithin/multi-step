<?php
/**
 * Activation handler.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Activator {

	/**
	 * Plugin activation hook.
	 */
	public static function activate() {
		self::create_tables();
		MSFBP_Seeder::maybe_seed();
	}

	/**
	 * Create custom database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$forms_table  = msfbp_table( 'forms' );
		$fields_table = msfbp_table( 'fields' );
		$entries      = msfbp_table( 'entries' );
		$values       = msfbp_table( 'entry_values' );

		$sql_forms = "CREATE TABLE {$forms_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'draft',
			settings LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$sql_fields = "CREATE TABLE {$fields_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			page_index INT NOT NULL DEFAULT 0,
			type VARCHAR(100) NOT NULL,
			label VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			required TINYINT(1) NOT NULL DEFAULT 0,
			settings LONGTEXT NULL,
			ordering INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY slug (slug)
		) {$charset_collate};";

		$sql_entries = "CREATE TABLE {$entries} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			ip_hash VARCHAR(255) NULL,
			user_agent VARCHAR(255) NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'submitted',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status)
		) {$charset_collate};";

		$sql_values = "CREATE TABLE {$values} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id BIGINT UNSIGNED NOT NULL,
			field_id BIGINT UNSIGNED NOT NULL,
			value_long LONGTEXT NULL,
			value_json LONGTEXT NULL,
			value_num DECIMAL(20,4) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY field_id (field_id)
		) {$charset_collate};";

		dbDelta( $sql_forms );
		dbDelta( $sql_fields );
		dbDelta( $sql_entries );
		dbDelta( $sql_values );

		add_option( 'msfbp_version', MSFBP_VERSION );
	}
}
