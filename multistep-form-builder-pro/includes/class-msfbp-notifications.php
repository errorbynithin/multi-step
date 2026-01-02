<?php
/**
 * Notification engine.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Notifications {

	/**
	 * Send notifications when rules pass.
	 *
	 * @param array $form Form data.
	 * @param array $values Sanitized values.
	 * @param int   $entry_id Entry ID.
	 */
	public function maybe_send_notifications( $form, $values, $entry_id ) {
		$notifications = msfbp_array_get( $form['settings'], 'notifications', array() );

		foreach ( $notifications as $notification ) {
			if ( isset( $notification['conditional'] ) && ! MSFBP_Conditional::evaluate( $notification['conditional'], $values ) ) {
				continue;
			}

			$to      = $this->merge_tags( $notification['to'], $values, $entry_id );
			$subject = $this->merge_tags( $notification['subject'], $values, $entry_id );
			$body    = $this->merge_tags( $notification['message'], $values, $entry_id );

			wp_mail( $to, $subject, wp_kses_post( $body ) );
		}

		$this->send_global_notifications( $values, $entry_id );
	}

	/**
	 * Replace merge tags.
	 */
	private function merge_tags( $text, $values, $entry_id ) {
		$replacements = array(
			'{entry_id}'  => $entry_id,
			'{all_fields}' => wp_json_encode( $values ),
		);

		foreach ( $values as $slug => $value ) {
			$replacements[ '{field:' . $slug . '}' ] = is_array( $value ) ? implode( ',', $value ) : $value;
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}

	/**
	 * Send admin/user notifications based on global settings.
	 */
	private function send_global_notifications( $values, $entry_id ) {
		$settings = get_option( 'msfbp_settings', array() );
		$from     = $this->build_from_header( $settings );
		$headers  = array();
		if ( $from ) {
			$headers[] = $from;
		}
		if ( ! empty( $settings['admin_cc'] ) ) {
			$headers[] = 'Cc: ' . $settings['admin_cc'];
		}
		if ( ! empty( $settings['admin_bcc'] ) ) {
			$headers[] = 'Bcc: ' . $settings['admin_bcc'];
		}

		$body = $this->format_body( $values, $settings, $entry_id );

		// Admin notification.
		if ( ! empty( $settings['enable_admin_notification'] ) && ! empty( $settings['admin_recipients'] ) ) {
			$recipients = array_map( 'trim', explode( ',', $settings['admin_recipients'] ) );
			wp_mail(
				$recipients,
				isset( $settings['admin_subject'] ) ? $settings['admin_subject'] : __( 'New form entry', 'msfbp' ),
				$body,
				$headers
			);
		}

		// User notification.
		if ( ! empty( $settings['enable_user_notification'] ) ) {
			$user_email = isset( $values['email'] ) ? $values['email'] : '';
			if ( is_array( $user_email ) ) {
				$user_email = reset( $user_email );
			}
			if ( is_email( $user_email ) ) {
				wp_mail(
					$user_email,
					isset( $settings['user_subject'] ) ? $settings['user_subject'] : __( 'Thank you for your submission', 'msfbp' ),
					$body,
					$headers
				);
			}
		}
	}

	/**
	 * Build From header.
	 */
	private function build_from_header( $settings ) {
		$from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : '';
		$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : '';

		if ( $from_name && is_email( $from_email ) ) {
			return 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>';
		}

		return '';
	}

	/**
	 * Format body with CSS if provided.
	 */
	private function format_body( $values, $settings, $entry_id ) {
		$css  = isset( $settings['email_css'] ) ? $settings['email_css'] : '';
		$list = '';
		foreach ( $values as $key => $value ) {
			$list .= '<p><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ) . '</p>';
		}

		$body = '<div>';
		if ( $css ) {
			$body .= '<style>' . $css . '</style>';
		}
		$body .= '<h2>' . esc_html__( 'Form Submission', 'msfbp' ) . '</h2>';
		$body .= $list;
		$body .= '<p>' . sprintf( esc_html__( 'Entry ID: %s', 'msfbp' ), esc_html( $entry_id ) ) . '</p>';
		$body .= '</div>';

		return $body;
	}
}
