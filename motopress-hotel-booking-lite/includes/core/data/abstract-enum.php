<?php

namespace MPHB\Core;

defined( 'ABSPATH' ) || exit;


abstract class Abstract_Enum {

	/**
	 * @var array [ class_name (string) => [ value (string) => db_id (string|int) ] ]
	 */
	private static array $value_to_db_id_map = array();

	/**
	 * @var array [ class_name (string) => [ locale (string) => [ value (string) => label (string) ] ] ]
	 */
	private static array $value_to_label_map = array();


	private string $value;


	protected function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * @return static
	 * @throws \InvalidArgumentException if $enum_value is unknown
	 */
	public static function create_from_value( string $enum_value ) {

		if ( ! isset( static::get_all_db_ids()[ $enum_value ] ) ) {
			throw new \InvalidArgumentException( 'Unknown enum value: ' . esc_html( $enum_value ) );
		}

		return new static( $enum_value );
	}

	/**
	 * @param string|int $db_id
	 * @return static
	 * @throws \InvalidArgumentException if $db_id is unknown
	 */
	public static function create_from_db_id( $db_id ) {

		$db_id_to_value = array_flip( static::get_all_db_ids() );

		if ( ! isset( $db_id_to_value[ $db_id ] ) ) {
			throw new \InvalidArgumentException( 'Unknown enum db_id: ' . esc_html( $db_id ) );
		}

		return new static( $db_id_to_value[ $db_id ] );
	}

	/**
	 * @return array [ value (string) => db_id (string|int) ]
	 */
	abstract protected static function get_value_to_db_id_map(): array;

	/**
	 * @return array [ value (string) => label (string) ]
	 */
	abstract protected static function get_value_to_label_map( string $locale ): array;

	/**
	 * @return string[] all enum string values
	 */
	public static function get_all_values(): array {
		return array_keys( static::get_all_db_ids() );
	}

	/**
	 * @return array [ value (string) => db_id (string|int) ]
	 */
	public static function get_all_db_ids(): array {

		$class = static::class;

		if ( ! isset( self::$value_to_db_id_map[ $class ] ) ) {
			self::$value_to_db_id_map[ $class ] = static::get_value_to_db_id_map();
		}

		return self::$value_to_db_id_map[ $class ];
	}

	/**
	 * @return array [ value (string) => label (string), ... ]
	 */
	public static function get_all_labels( string $locale = '' ): array {

		if ( empty( $locale ) ) {
			$locale = get_locale() ? get_locale() : 'en';
		}

		$class = static::class;

		if ( ! isset( self::$value_to_label_map[ $class ][ $locale ] ) ) {
			self::$value_to_label_map[ $class ][ $locale ] = static::get_value_to_label_map( $locale );
		}

		return self::$value_to_label_map[ $class ][ $locale ];
	}

	public function get_value(): string {
		return $this->value;
	}

	/**
	 * @return string|int
	 */
	public function get_db_id() {
		return static::get_all_db_ids()[ $this->get_value() ];
	}

	public function get_label( string $locale = '' ): string {

		$labels = static::get_all_labels( $locale );

		return $labels[ $this->get_value() ] ?? $this->get_value();
	}

	public function is_equals( ?Abstract_Enum $enum_instance ): bool {
		return null !== $enum_instance &&
			$enum_instance instanceof static &&
			$this->get_value() === $enum_instance->get_value();
	}
}
