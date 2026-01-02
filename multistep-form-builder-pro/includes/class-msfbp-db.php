<?php
/**
 * DB layer.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_DB {

	/**
	 * Insert or update form.
	 */
	public function save_form( $data ) {
		global $wpdb;
		$table = msfbp_table( 'forms' );
		$now   = current_time( 'mysql' );

		$payload = array(
			'name'       => sanitize_text_field( $data['name'] ),
			'status'     => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft',
			'settings'   => wp_json_encode( msfbp_array_get( $data, 'settings', array() ) ),
			'updated_at' => $now,
		);

		if ( empty( $data['id'] ) ) {
			$payload['created_at'] = $now;
			$wpdb->insert(
				$table,
				$payload,
				array( '%s', '%s', '%s', '%s', '%s' )
			);
			$data['id'] = $wpdb->insert_id;
		} else {
			$wpdb->update(
				$table,
				$payload,
				array( 'id' => absint( $data['id'] ) ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		if ( isset( $data['fields'] ) ) {
			$this->save_fields( $data['id'], $data['fields'] );
		}

		return $data['id'];
	}

	/**
	 * Save fields for a form.
	 */
	public function save_fields( $form_id, $fields ) {
		global $wpdb;
		$table = msfbp_table( 'fields' );
		$wpdb->delete( $table, array( 'form_id' => absint( $form_id ) ), array( '%d' ) );

		$order = 0;
		foreach ( $fields as $field ) {
			$wpdb->insert(
				$table,
				array(
					'form_id'    => absint( $form_id ),
					'page_index' => isset( $field['page_index'] ) ? absint( $field['page_index'] ) : 0,
					'type'       => sanitize_text_field( $field['type'] ),
					'label'      => sanitize_text_field( $field['label'] ),
					'slug'       => msfbp_sanitize_slug( $field['slug'] ),
					'required'   => isset( $field['required'] ) ? absint( $field['required'] ) : 0,
					'settings'   => wp_json_encode( msfbp_array_get( $field, 'settings', array() ) ),
					'ordering'   => $order++,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
			);
		}
	}

	/**
	 * Get form and fields.
	 */
	public function get_form( $form_id ) {
		global $wpdb;

		$form = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . msfbp_table( 'forms' ) . ' WHERE id = %d', absint( $form_id ) ),
			ARRAY_A
		);

		if ( ! $form ) {
			return null;
		}

		$form['settings'] = json_decode( $form['settings'], true );
		$form['fields']   = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . msfbp_table( 'fields' ) . ' WHERE form_id = %d ORDER BY ordering ASC', absint( $form_id ) ),
			ARRAY_A
		);

		foreach ( $form['fields'] as &$field ) {
			$field['settings'] = json_decode( $field['settings'], true );
		}

		return $form;
	}

	/**
	 * List forms (for admin).
	 */
	public function list_forms() {
		global $wpdb;

		return $wpdb->get_results(
			'SELECT id, name, status, updated_at FROM ' . msfbp_table( 'forms' ) . ' ORDER BY updated_at DESC',
			ARRAY_A
		);
	}

	/**
	 * Store entry and values.
	 */
	public function insert_entry( $form_id, $fields, $values, $meta = array() ) {
		global $wpdb;
		$table_entries = msfbp_table( 'entries' );
		$table_values  = msfbp_table( 'entry_values' );
		$now           = current_time( 'mysql' );

		$wpdb->insert(
			$table_entries,
			array(
				'form_id'    => absint( $form_id ),
				'user_id'    => get_current_user_id(),
				'ip_hash'    => msfbp_ip_hash(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'status'     => isset( $meta['status'] ) ? sanitize_text_field( $meta['status'] ) : 'submitted',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$entry_id = $wpdb->insert_id;

		foreach ( $fields as $field ) {
			$field_id = absint( $field['id'] );
			$value    = isset( $values[ $field['slug'] ] ) ? $values[ $field['slug'] ] : '';

			$this->insert_entry_value( $table_values, $entry_id, $field_id, $value );
		}

		return $entry_id;
	}

	/**
	 * Insert a single value.
	 */
	private function insert_entry_value( $table, $entry_id, $field_id, $value ) {
		global $wpdb;
		$type = gettype( $value );
		$wpdb->insert(
			$table,
			array(
				'entry_id'   => $entry_id,
				'field_id'   => $field_id,
				'value_long' => is_string( $value ) ? wp_kses_post( $value ) : null,
				'value_json' => ( 'array' === $type || 'object' === $type ) ? wp_json_encode( $value ) : null,
				'value_num'  => is_numeric( $value ) ? $value : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s' )
		);
	}

	/**
	 * Export entries to array.
	 */
	public function export_entries( $form_id ) {
		global $wpdb;
		$entries_table = msfbp_table( 'entries' );
		$values_table  = msfbp_table( 'entry_values' );

		$entries = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$entries_table} WHERE form_id = %d ORDER BY created_at DESC", absint( $form_id ) ),
			ARRAY_A
		);

		foreach ( $entries as &$entry ) {
			$values = $wpdb->get_results(
				$wpdb->prepare( "SELECT field_id, value_long, value_json, value_num FROM {$values_table} WHERE entry_id = %d", $entry['id'] ),
				ARRAY_A
			);
			$entry['values'] = $values;
		}

		return $entries;
	}
}
