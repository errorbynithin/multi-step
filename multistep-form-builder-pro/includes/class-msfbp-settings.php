<?php
/**
 * Global settings for notifications.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Settings {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_msfbp_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Add settings page.
	 */
	public function register_menu() {
		add_submenu_page(
			'msfbp-forms',
			__( 'Form Settings', 'msfbp' ),
			__( 'Form Settings', 'msfbp' ),
			'manage_options',
			'msfbp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$settings = get_option( 'msfbp_settings', array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Form Settings', 'msfbp' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'msfbp_settings' ); ?>
				<input type="hidden" name="action" value="msfbp_save_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'User Email Notification', 'msfbp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_user_notification" value="1" <?php checked( msfbp_array_get( $settings, 'enable_user_notification', 0 ), 1 ); ?> />
								<?php esc_html_e( 'Send confirmation to submitter (uses their email field)', 'msfbp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Email Notification', 'msfbp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_admin_notification" value="1" <?php checked( msfbp_array_get( $settings, 'enable_admin_notification', 0 ), 1 ); ?> />
								<?php esc_html_e( 'Send notification to admin recipients', 'msfbp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From Name', 'msfbp' ); ?></th>
						<td><input type="text" class="regular-text" name="from_name" value="<?php echo esc_attr( msfbp_array_get( $settings, 'from_name', get_bloginfo( 'name' ) ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From Email', 'msfbp' ); ?></th>
						<td><input type="email" class="regular-text" name="from_email" value="<?php echo esc_attr( msfbp_array_get( $settings, 'from_email', get_option( 'admin_email' ) ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'User Email Subject', 'msfbp' ); ?></th>
						<td><input type="text" class="regular-text" name="user_subject" value="<?php echo esc_attr( msfbp_array_get( $settings, 'user_subject', __( 'Thank you for your submission', 'msfbp' ) ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Email Subject', 'msfbp' ); ?></th>
						<td><input type="text" class="regular-text" name="admin_subject" value="<?php echo esc_attr( msfbp_array_get( $settings, 'admin_subject', __( 'New form entry', 'msfbp' ) ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Recipients', 'msfbp' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="admin_recipients" value="<?php echo esc_attr( msfbp_array_get( $settings, 'admin_recipients', get_option( 'admin_email' ) ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated list of emails.', 'msfbp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'CC', 'msfbp' ); ?></th>
						<td><input type="text" class="regular-text" name="admin_cc" value="<?php echo esc_attr( msfbp_array_get( $settings, 'admin_cc', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'BCC', 'msfbp' ); ?></th>
						<td><input type="text" class="regular-text" name="admin_bcc" value="<?php echo esc_attr( msfbp_array_get( $settings, 'admin_bcc', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email CSS', 'msfbp' ); ?></th>
						<td>
							<textarea name="email_css" class="large-text code" rows="5"><?php echo esc_textarea( msfbp_array_get( $settings, 'email_css', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional inline CSS included at the top of email bodies.', 'msfbp' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'msfbp' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'msfbp' ) );
		}

		check_admin_referer( 'msfbp_settings' );

		$clean = array(
			'enable_user_notification'  => isset( $_POST['enable_user_notification'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'enable_admin_notification' => isset( $_POST['enable_admin_notification'] ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'from_name'                 => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'from_email'                => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'user_subject'              => isset( $_POST['user_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['user_subject'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'admin_subject'             => isset( $_POST['admin_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_subject'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'admin_recipients'          => isset( $_POST['admin_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_recipients'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'admin_cc'                  => isset( $_POST['admin_cc'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_cc'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'admin_bcc'                 => isset( $_POST['admin_bcc'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_bcc'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email_css'                 => isset( $_POST['email_css'] ) ? wp_kses_post( wp_unslash( $_POST['email_css'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		update_option( 'msfbp_settings', $clean );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'msfbp-settings',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
