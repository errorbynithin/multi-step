<?php
/**
 * Validation engine.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Validator {

	/**
	 * Validate submission values against field definitions.
	 *
	 * @param array $fields Field definitions.
	 * @param array $values Submitted values.
	 *
	 * @return array Tuple of (errors, sanitized_values).
	 */
	public function validate( $fields, $values ) {
		$errors    = array();
		$sanitized = array();

		foreach ( $fields as $field ) {
			$slug    = $field['slug'];
			$type    = $field['type'];
			$raw     = isset( $values[ $slug ] ) ? $values[ $slug ] : '';
			$rules   = isset( $field['settings']['validation'] ) ? $field['settings']['validation'] : array();
			$required = ! empty( $field['required'] );

			if ( isset( $field['settings']['conditional'] ) && ! MSFBP_Conditional::evaluate( $field['settings']['conditional'], $values ) ) {
				continue;
			}

			if ( $required && ( '' === $raw || array() === $raw ) ) {
				$errors[ $slug ] = __( 'This field is required.', 'msfbp' );
				continue;
			}

			switch ( $type ) {
				case 'email':
					if ( $raw && ! is_email( $raw ) ) {
						$errors[ $slug ] = __( 'Please enter a valid email.', 'msfbp' );
					} else {
						$sanitized[ $slug ] = sanitize_email( $raw );
					}
					break;
				case 'number':
					if ( '' !== $raw && ! is_numeric( $raw ) ) {
						$errors[ $slug ] = __( 'Please enter a valid number.', 'msfbp' );
					} else {
						$sanitized[ $slug ] = floatval( $raw );
						if ( isset( $rules['min'] ) && $sanitized[ $slug ] < floatval( $rules['min'] ) ) {
							$errors[ $slug ] = sprintf( __( 'Minimum value is %s.', 'msfbp' ), $rules['min'] );
						}
						if ( isset( $rules['max'] ) && $sanitized[ $slug ] > floatval( $rules['max'] ) ) {
							$errors[ $slug ] = sprintf( __( 'Maximum value is %s.', 'msfbp' ), $rules['max'] );
						}
					}
					break;
				case 'file':
					if ( ! empty( $field['uploaded'] ) && is_array( $field['uploaded'] ) ) {
						$sanitized[ $slug ] = $field['uploaded'];
						break;
					}

					$sanitized[ $slug ] = $this->handle_file_upload( $slug, $field );
					if ( is_wp_error( $sanitized[ $slug ] ) ) {
						$errors[ $slug ] = $sanitized[ $slug ]->get_error_message();
					}
					break;
				case 'calculation':
					$formula    = isset( $field['settings']['formula'] ) ? $field['settings']['formula'] : '';
					$expression = preg_replace_callback(
						'/{field:([^}]+)}/',
						function( $matches ) use ( $values ) {
							return floatval( msfbp_array_get( $values, $matches[1], 0 ) );
						},
						$formula
					);
					$expression         = preg_replace( '/[^0-9\\.\\+\\-\\*\\/\\(\\) ]/', '', $expression );
					$sanitized[ $slug ] = $expression ? eval( 'return ' . $expression . ';' ) : 0; // phpcs:ignore Squiz.PHP.Eval.Discouraged
					break;
				default:
					$sanitized[ $slug ] = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : sanitize_text_field( $raw );
			}

			if ( isset( $rules['regex'] ) && ! empty( $sanitized[ $slug ] ) && ! preg_match( '#' . $rules['regex'] . '#', $sanitized[ $slug ] ) ) {
				$errors[ $slug ] = __( 'Value does not match the required format.', 'msfbp' );
			}
		}

		return array( $errors, $sanitized );
	}

	/**
	 * Handle uploads with allowlists.
	 */
	private function handle_file_upload( $slug, $field ) {
		if ( empty( $_FILES[ $slug ]['name'] ) ) {
			return array();
		}

		$allowed_mimes = isset( $field['settings']['allowed_mimes'] ) ? $field['settings']['allowed_mimes'] : array();
		$max_size      = isset( $field['settings']['max_size'] ) ? intval( $field['settings']['max_size'] ) : 5 * MB_IN_BYTES;

		$file = $_FILES[ $slug ];

		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_size', __( 'File is too large.', 'msfbp' ) );
		}

		$check_type = wp_check_filetype( $file['name'] );
		if ( $allowed_mimes && ! in_array( $check_type['type'], $allowed_mimes, true ) ) {
			return new WP_Error( 'file_type', __( 'File type not allowed.', 'msfbp' ) );
		}

		$upload_dir = msfbp_upload_dir();
		$filename   = wp_unique_filename( $upload_dir, $file['name'] );
		$target     = trailingslashit( $upload_dir ) . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return new WP_Error( 'file_move', __( 'Could not save the file.', 'msfbp' ) );
		}

		return array(
			'path' => $target,
			'url'  => trailingslashit( wp_upload_dir()['baseurl'] ) . 'msfbp/' . $filename,
			'name' => sanitize_file_name( $file['name'] ),
		);
	}
}
