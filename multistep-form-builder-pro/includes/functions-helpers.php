<?php
/**
 * Helper functions.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get plugin option with default.
 */
function msfbp_get_option( $key, $default = '' ) {
	$options = get_option( 'msfbp_settings', array() );
	return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Sanitize a field slug.
 */
function msfbp_sanitize_slug( $value ) {
	return sanitize_key( $value );
}

/**
 * Get upload dir for plugin files.
 */
function msfbp_upload_dir() {
	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'msfbp';
	wp_mkdir_p( $dir );
	return $dir;
}

/**
 * Return prepared table name.
 */
function msfbp_table( $suffix ) {
	global $wpdb;
	return $wpdb->prefix . 'msfbp_' . $suffix;
}

/**
 * Get IP hash for rate limiting and spam.
 */
function msfbp_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return wp_hash( $ip );
}

/**
 * Basic array getter with default.
 */
function msfbp_array_get( $array, $key, $default = null ) {
	return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}
