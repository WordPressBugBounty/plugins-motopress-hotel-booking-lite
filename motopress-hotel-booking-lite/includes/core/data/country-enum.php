<?php

namespace MPHB\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Contains country codes from Google Hotels documentation:
 * https://developers.google.com/hotels/hotel-prices/dev-guide/country-codes
 */
final class Country_Enum extends Abstract_Enum {


	/**
	 * @return array [ value (string) => db_id (string|int) ]
	 */
	protected static function get_value_to_db_id_map(): array {

		$country_code_to_label_map = self::get_all_labels();
		$country_codes             = array_keys( $country_code_to_label_map );

		return array_combine( $country_codes, $country_codes );
	}

	/**
	 * @return array [ value (string) => label (string) ]
	 */
	protected static function get_value_to_label_map( string $locale ): array {

		$path = MPHB()->getPluginPath(
			"vendors/country-list/data/{$locale}.php"
		);

		if ( ! file_exists( $path ) ) {

			$language = strtolower( strtok( $locale, '_' ) );

			$path = MPHB()->getPluginPath(
				"vendors/country-list/data/{$language}.php"
			);

			if ( ! file_exists( $path ) ) {

				$locale = 'en';
				$path = MPHB()->getPluginPath(
					"vendors/country-list/data/{$locale}.php"
				);
			}
		}

		$country_code_to_label_map = (array) require $path;

		return $country_code_to_label_map;
	}
}
