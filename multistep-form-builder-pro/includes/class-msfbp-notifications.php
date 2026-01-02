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
}
