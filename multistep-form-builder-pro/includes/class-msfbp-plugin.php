<?php
/**
 * Main plugin orchestrator.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Plugin {

	/**
	 * Instances.
	 */
	private $admin;
	private $rest;
	private $render;
	private $entries;
	private $settings;
	private $rate_limit_window = 60;
	private $rate_limit_count  = 5;

	/**
	 * Boot plugin.
	 */
	public function __construct() {
		$this->admin   = new MSFBP_Admin();
		$this->settings = new MSFBP_Settings();
		$this->rest    = new MSFBP_REST();
		$this->render  = new MSFBP_Render();
		$this->entries = new MSFBP_Entries();
	}

	/**
	 * Run hooks.
	 */
	public function run() {
		$this->admin->hooks();
		$this->settings->hooks();
		$this->rest->hooks();
		$this->render->hooks();
		$this->entries->hooks();

		add_action( 'init', array( $this, 'register_post_hooks' ) );
	}

	/**
	 * Handle form submissions non-REST.
	 */
	public function register_post_hooks() {
		if ( isset( $_POST['_msfbp_nonce'], $_POST['msfbp_form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_post_submission();
		}
	}

	/**
	 * Validate and store submissions.
	 */
	private function handle_post_submission() {
		$form_id = absint( $_POST['msfbp_form_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_msfbp_nonce'] ) ), 'msfbp_submit_' . $form_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! $this->security_check() ) {
			return;
		}

		$db   = new MSFBP_DB();
		$form = $db->get_form( $form_id );
		if ( ! $form ) {
			return;
		}

		$values = array();
		foreach ( $form['fields'] as $field ) {
			$slug           = $field['slug'];
			$values[ $slug ] = isset( $_POST[ $slug ] ) ? wp_unslash( $_POST[ $slug ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$validator = new MSFBP_Validator();
		list( $errors, $sanitized ) = $validator->validate( $form['fields'], $values );
		if ( ! empty( $errors ) ) {
			return;
		}

		$entry_id = $db->insert_entry( $form_id, $form['fields'], $sanitized );

		$notifications = new MSFBP_Notifications();
		$notifications->maybe_send_notifications( $form, $sanitized, $entry_id );
	}

	/**
	 * Basic anti-spam: honeypot + rate limiting.
	 */
	private function security_check() {
		if ( ! empty( $_POST['msfbp_hp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		$key     = 'msfbp_rate_' . msfbp_ip_hash();
		$current = get_transient( $key );

		if ( $current && $current >= $this->rate_limit_count ) {
			return false;
		}

		set_transient( $key, $current ? $current + 1 : 1, $this->rate_limit_window );

		$secret = msfbp_get_option( 'recaptcha_secret', '' );
		if ( $secret ) {
			$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $token ) {
				return false;
			}

			$response = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'body' => array(
						'secret'   => $secret,
						'response' => $token,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['success'] ) ) {
				return false;
			}
		}

		return true;
	}
}
