<?php

namespace MPHB\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ValidateUtils {
	public static function convertCustomBookingRulesToBlocks( array $rules ): array {
		$blocks = array();

		foreach ( $rules as $rule ) {
			$block = array(
				'block_id'         => 0,
				'comment'          => $rule['comment'],
				'date_from'        => $rule['date_from'],
				'date_to'          => $rule['date_to'],
				'has_restrictions' => false, // Set later below
				'not_check_in'     => $rule['not_check_in'] ?? false,
				'not_check_out'    => $rule['not_check_out'] ?? false,
				'not_stay_in'      => $rule['not_stay_in'] ?? false,
				'room_id'          => absint( $rule['room_id'] ),
				'room_type_id'     => absint( $rule['room_type_id'] ),
			);

			if ( isset( $rule['restrictions'] ) ) {
				$block['not_check_in']  = in_array( 'check-in',  $rule['restrictions'] );
				$block['not_check_out'] = in_array( 'check-out', $rule['restrictions'] );
				$block['not_stay_in']   = in_array( 'stay-in',   $rule['restrictions'] );
			}

			$block['has_restrictions'] = $block['not_check_in']
				|| $block['not_check_out']
				|| $block['not_stay_in'];

			$blocks[] = $block;
		}

		return $blocks;
	}

	/**
	 * @since 5.0.0
	 *
	 * @param mixed $value
	 * @param float|null $min
	 * @param float|null $max
	 * @return float|false
	 */
	public static function validateFloat( $value, $min = null, $max = null ) {
		$options = array();

		if ( ! is_null( $min ) ) {
			$options['min_range'] = $min;
		}

		if ( ! is_null( $max ) ) {
			$options['max_range'] = $max;
		}

		if ( ! empty( $options ) ) {
			return filter_var( $value, FILTER_VALIDATE_FLOAT, array( 'options' => $options ) );
		} else {
			return filter_var( $value, FILTER_VALIDATE_FLOAT );
		}
	}

	/**
	 *
	 * @param mixed $value
	 * @param int   $min Optional.
	 * @param int   $max Optional.
	 * @return int|false Validated number or FALSE if the filter fails.
	 */
	public static function validateInt( $value, $min = null, $max = null ) {
		$options = array();

		if ( isset( $min ) ) {
			$options['min_range'] = $min;
		}

		if ( isset( $max ) ) {
			$options['max_range'] = $max;
		}

		if ( ! empty( $options ) ) {
			$options = array(
				'options' => $options,
			);
		}

		return ! empty( $options ) ? filter_var( $value, FILTER_VALIDATE_INT, $options ) : filter_var( $value, FILTER_VALIDATE_INT );
	}

	/**
	 * @deprecated 5.0.0
	 *
	 * @see ParseUtils::parseInt()
	 */
	public static function parseInt( $value, $min = null, $max = null ) {
		return ParseUtils::parseInt( $value, $min, $max );
	}

	/**
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function validateBool( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @param string $value
	 *
	 * @return int[]
	 */
	public static function validateCommaSeparatedIds( $value ) {
		$values = explode( ',', $value );
		return self::validateIds( $values );
	}

	/**
	 * @param mixed $value
	 * @return int|false
	 */
	public static function validateId( $value ) {
		return static::validateInt( $value, 0 );
	}

	/**
	 * @param mixed $values
	 * @return int[] Allows 0, unlike ParseUtils::parseIds().
	 */
	public static function validateIds( array $values ): array {
		$ids = array();

		foreach ( $values as $value ) {
			$id = static::validateInt( $value, 0 );

			if ( $id !== false ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	public static function validateRelation( $value ) {
		$value = strtoupper( $value );
		return ( $value == 'OR' || $value == 'AND' ? $value : 'OR' );
	}

	public static function validateOrder( $value ) {
		$value = strtoupper( $value );
		return ( $value == 'DESC' || $value == 'ASC' ? $value : 'DESC' );
	}

	/**
	 *
	 * @param bool $value
	 * @return bool
	 */
	public static function isNotEqualFalse( $value ) {
		return $value !== false;
	}

	/**
	 * @param mixed $value
	 * @return int|false
	 *
	 * @since 3.8.3
	 */
	public static function validateAdults( $value ) {
		$minAdults = MPHB()->settings()->main()->getMinAdults();
		$maxAdults = MPHB()->settings()->main()->getSearchMaxAdults();

		return self::validateInt( $value, $minAdults, $maxAdults );
	}

	/**
	 * @param mixed $value
	 * @return int|false
	 *
	 * @since 3.8.3
	 */
	public static function validateChildren( $value ) {
		$minChildren = MPHB()->settings()->main()->getMinChildren();
		$maxChildren = MPHB()->settings()->main()->getSearchMaxChildren();

		return self::validateInt( $value, $minChildren, $maxChildren );
	}
}
