<?php
/**
 * Admin UI and builder.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Admin {

	/**
	 * Register admin hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_msfbp_save_form', array( $this, 'ajax_save_form' ) );
	}

	/**
	 * Register menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'MultiStep Form Builder Pro', 'msfbp' ),
			__( 'MultiStep Forms', 'msfbp' ),
			'manage_options',
			'msfbp-forms',
			array( $this, 'render_builder_page' ),
			'dashicons-feedback',
			26
		);
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue( $hook ) {
		if ( 'toplevel_page_msfbp-forms' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'msfbp-admin', MSFBP_PLUGIN_URL . 'assets/css/admin.css', array(), MSFBP_VERSION );
		wp_enqueue_script( 'msfbp-admin', MSFBP_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-element', 'wp-components', 'wp-api-fetch', 'jquery-ui-sortable' ), MSFBP_VERSION, true );

		wp_localize_script(
			'msfbp-admin',
			'MSFBP_Admin',
			array(
				'nonce'     => wp_create_nonce( 'msfbp_admin' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'restUrl'   => esc_url_raw( rest_url( 'msfbp/v1' ) ),
			)
		);
	}

	/**
	 * Render builder container.
	 */
	public function render_builder_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiStep Form Builder Pro', 'msfbp' ); ?></h1>
			<div id="msfbp-builder-root" data-form-template="<?php echo esc_attr( file_get_contents( MSFBP_PLUGIN_DIR . 'assets/sample-form.json' ) ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * AJAX save fallback (non-REST).
	 */
	public function ajax_save_form() {
		check_ajax_referer( 'msfbp_admin', 'nonce' );
		$data = isset( $_POST['form'] ) ? json_decode( wp_unslash( $_POST['form'] ), true ) : array();

		$db     = new MSFBP_DB();
		$form_id = $db->save_form( $data );

		wp_send_json_success(
			array(
				'id' => $form_id,
			)
		);
	}
}
