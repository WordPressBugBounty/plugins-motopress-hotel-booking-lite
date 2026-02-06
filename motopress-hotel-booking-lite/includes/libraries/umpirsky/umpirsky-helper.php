<?php

declare(strict_types=1);

namespace MPHB\Libraries\Umpirsky;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 5.2.4
 */
class Umpirsky_Helper {
	/**
	 * @return array <code>[ Country code => Country name ]</code>
	 */
	public static function getCountryList( ?string $locale = null ): array {

		if ( empty( $locale ) ) {
			$locale = determine_locale();
		}

		$locale_replacements = apply_filters(
			'mphb_wp2umpirsky_country_locale_replacements',
			array(
				'bel'    => 'be',
				'rup'    => 'ro',
				'rup_MK' => 'ro',
				'szl'    => 'pl',
				'szl_PL' => 'pl',
				'fur'    => 'it',
				'pt_BR'  => 'pt',
				'kir'    => 'ky',
			)
		);

		if ( array_key_exists( $locale, $locale_replacements ) ) {
			$locale = $locale_replacements[ $locale ];
		}

		$locale_parts        = preg_split( '/[_\-]/', $locale ); // e.g. "zh_Hant_TW" -> ["zh", "Hant", "TW"]
		$locale_primary_lang = $locale_parts[0]; // "uk_UA" -> "uk"

		$dir = MPHB()->getPluginPath( 'vendors/country-list/data/' );

		$path_to_list      = $dir . $locale . '.php';
		$path_to_base_list = $dir . $locale_primary_lang . '.php';

		if ( file_exists( $path_to_list ) ) {
			return require $path_to_list;
		} elseif ( file_exists( $path_to_base_list ) ) {
			return require $path_to_base_list;
		} else {
			// setting English by default if nothing found
			return require $dir . 'en.php';
		}
	}

	/**
	 * @return array <code>[ Currency code => Currency name ]</code>
	 */
	public static function getCurrencyList( ?string $locale = null ): array {

		if ( empty( $locale ) ) {
			$locale = determine_locale();
		}

		$locale_replacements = apply_filters(
			'mphb_wp2umpirsky_currency_locale_replacements',
			array(
				'bel'        => 'be',
				'szl'        => 'pl',
				'szl_PL'     => 'pl',
				'rup'        => 'ro',
				'rup_MK'     => 'ro',
				'fur'        => 'it',
				'pt_BR'      => 'pt',
				'kir'        => 'ky',
				'zh_TW'      => 'zh_Hant_TW',
				'zh_MO'      => 'zh_Hant_MO',
			)
		);

		if ( array_key_exists( $locale, $locale_replacements ) ) {
			$locale = $locale_replacements[ $locale ];
		}

		$locale_parts        = preg_split( '/[_\-]/', $locale ); // e.g. "zh_Hant_TW" -> ["zh", "Hant", "TW"]
		$locale_primary_lang = $locale_parts[0]; // "uk_UA" -> "uk"

		$dir = MPHB()->getPluginPath( 'vendors/currency-list/data/' );

		$path_to_list      = $dir . $locale . '.php';
		$path_to_base_list = $dir . $locale_primary_lang . '.php';

		if ( file_exists( $path_to_list ) ) {
			return require $path_to_list;
		} elseif ( file_exists( $path_to_base_list ) ) {
			return require $path_to_base_list;
		} else {
			// setting English by default if nothing found
			return require $dir . 'en.php';
		}
	}
}
