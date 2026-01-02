<?php
/**
 * Entries management.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Entries {

	/**
	 * Register admin hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_entries_page' ) );
		add_action( 'admin_post_msfbp_export', array( $this, 'export' ) );
	}

	/**
	 * Add entries submenu.
	 */
	public function register_entries_page() {
		add_submenu_page(
			'msfbp-forms',
			__( 'Entries', 'msfbp' ),
			__( 'Entries', 'msfbp' ),
			'manage_options',
			'msfbp-entries',
			array( $this, 'render_entries_page' )
		);
	}

	/**
	 * Render entries list minimal.
	 */
	public function render_entries_page() {
		$db      = new MSFBP_DB();
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entries = $form_id ? $db->export_entries( $form_id ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Entries', 'msfbp' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="msfbp-entries" />
				<label for="form_id"><?php esc_html_e( 'Form ID', 'msfbp' ); ?></label>
				<input name="form_id" id="form_id" value="<?php echo esc_attr( $form_id ); ?>" />
				<button class="button button-primary"><?php esc_html_e( 'Filter', 'msfbp' ); ?></button>
				<?php if ( $form_id ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=msfbp_export&form_id=' . $form_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'msfbp' ); ?></a>
				<?php endif; ?>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Entry ID', 'msfbp' ); ?></th>
						<th><?php esc_html_e( 'Created', 'msfbp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'msfbp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['id'] ); ?></td>
							<td><?php echo esc_html( $entry['created_at'] ); ?></td>
							<td><?php echo esc_html( $entry['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Export CSV.
	 */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'msfbp' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $form_id ) {
			wp_die( esc_html__( 'Missing form', 'msfbp' ) );
		}

		$db      = new MSFBP_DB();
		$entries = $db->export_entries( $form_id );

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="msfbp-entries-' . $form_id . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'entry_id', 'field_id', 'value' ) );

		foreach ( $entries as $entry ) {
			foreach ( $entry['values'] as $value ) {
				$display = $value['value_long'] ? $value['value_long'] : ( $value['value_json'] ? $value['value_json'] : $value['value_num'] );
				fputcsv( $output, array( $entry['id'], $value['field_id'], $display ) );
			}
		}

		fclose( $output );
		exit;
	}
}
