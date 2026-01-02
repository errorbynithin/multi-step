<?php
/**
 * REST API routes.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_REST {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST endpoints.
	 */
	public function register_routes() {
		register_rest_route(
			'msfbp/v1',
			'/forms/(?P<id>\\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'get_form' ),
			)
		);

		register_rest_route(
			'msfbp/v1',
			'/forms',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'save_form' ),
			)
		);

		register_rest_route(
			'msfbp/v1',
			'/forms/(?P<id>\\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'submit_form' ),
			)
		);

		register_rest_route(
			'msfbp/v1',
			'/forms/(?P<id>\\d+)/partial',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'save_partial' ),
			)
		);

		register_rest_route(
			'msfbp/v1',
			'/resume/(?P<token>[a-zA-Z0-9\\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'get_partial' ),
			)
		);
	}

	/**
	 * Get form for admin editing.
	 */
	public function get_form( WP_REST_Request $request ) {
		$db   = new MSFBP_DB();
		$form = $db->get_form( $request['id'] );

		return rest_ensure_response( $form );
	}

	/**
	 * Save partial submission.
	 */
	public function save_partial( WP_REST_Request $request ) {
		$form_id = absint( $request['id'] );
		$values  = $request->get_param( 'values' );
		$token   = wp_generate_uuid4();

		set_transient(
			'msfbp_resume_' . $token,
			array(
				'form_id' => $form_id,
				'values'  => $values,
			),
			DAY_IN_SECONDS * 2
		);

		return rest_ensure_response(
			array(
				'token' => $token,
				'url'   => add_query_arg(
					array(
						'msfbp_resume' => $token,
					),
					home_url()
				),
			)
		);
	}

	/**
	 * Retrieve partial data by token.
	 */
	public function get_partial( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request['token'] );
		$data  = get_transient( 'msfbp_resume_' . $token );
		if ( ! $data ) {
			return new WP_Error( 'not_found', __( 'Token expired', 'msfbp' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Save form data.
	 */
	public function save_form( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$db     = new MSFBP_DB();
		$id     = $db->save_form( $params );

		return rest_ensure_response( array( 'id' => $id ) );
	}

	/**
	 * Handle final submission.
	 */
	public function submit_form( WP_REST_Request $request ) {
		$form_id = absint( $request['id'] );
		$values  = $request->get_param( 'values' );

		if ( ! $this->security_check( $request ) ) {
			return new WP_Error( 'spam', __( 'Spam detected', 'msfbp' ), array( 'status' => 429 ) );
		}

		$db   = new MSFBP_DB();
		$form = $db->get_form( $form_id );

		if ( ! $form ) {
			return new WP_Error( 'not_found', __( 'Form not found', 'msfbp' ), array( 'status' => 404 ) );
		}

		$validator = new MSFBP_Validator();
		list( $errors, $sanitized ) = $validator->validate( $form['fields'], $values );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_error', $errors, array( 'status' => 422 ) );
		}

		$entry_id = $db->insert_entry( $form_id, $form['fields'], $sanitized );

		$notifications = new MSFBP_Notifications();
		$notifications->maybe_send_notifications( $form, $sanitized, $entry_id );

		return rest_ensure_response(
			array(
				'entry_id' => $entry_id,
				'message'  => __( 'Form submitted successfully.', 'msfbp' ),
			)
		);
	}

	/**
	 * Basic spam checks.
	 */
	private function security_check( WP_REST_Request $request ) {
		if ( $request->get_param( 'msfbp_hp' ) ) {
			return false;
		}

		$key     = 'msfbp_rate_' . msfbp_ip_hash();
		$current = get_transient( $key );
		if ( $current && $current >= 5 ) {
			return false;
		}
		set_transient( $key, $current ? $current + 1 : 1, 60 );

		$secret = msfbp_get_option( 'recaptcha_secret', '' );
		if ( $secret ) {
			$token = $request->get_param( 'g-recaptcha-response' );
			if ( ! $token ) {
				return false;
			}

			$response = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'body' => array(
						'secret'   => $secret,
						'response' => sanitize_text_field( $token ),
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
