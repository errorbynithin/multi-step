<?php
/**
 * Conditional logic engine.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Conditional {

	/**
	 * Evaluate conditional rule groups.
	 *
	 * @param array $conditions Rule definition with groups.
	 * @param array $values     Submitted values keyed by slug.
	 *
	 * @return bool
	 */
	public static function evaluate( $conditions, $values ) {
		if ( empty( $conditions['rules'] ) ) {
			return true;
		}

		$relation = isset( $conditions['relation'] ) && 'any' === $conditions['relation'] ? 'any' : 'all';

		$results = array();
		foreach ( $conditions['rules'] as $rule ) {
			$field_value = isset( $values[ $rule['field'] ] ) ? $values[ $rule['field'] ] : '';
			$results[]   = self::compare( $field_value, $rule['operator'], $rule['value'] );
		}

		return 'any' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * Compare a single rule.
	 */
	private static function compare( $field_value, $operator, $expected ) {
		switch ( $operator ) {
			case 'is':
				return (string) $field_value === (string) $expected;
			case 'is_not':
				return (string) $field_value !== (string) $expected;
			case 'contains':
				return is_array( $field_value ) ? in_array( $expected, $field_value, true ) : false !== strpos( (string) $field_value, (string) $expected );
			case 'greater':
				return floatval( $field_value ) > floatval( $expected );
			case 'less':
				return floatval( $field_value ) < floatval( $expected );
			case 'empty':
				return empty( $field_value );
			case 'not_empty':
				return ! empty( $field_value );
			default:
				return false;
		}
	}
}
