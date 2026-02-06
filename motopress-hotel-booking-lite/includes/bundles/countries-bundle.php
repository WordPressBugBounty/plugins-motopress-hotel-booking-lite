<?php

namespace MPHB\Bundles;

use MPHB\Libraries\Umpirsky\Umpirsky_Helper;

/**
 * @since 5.2.4  added countries list
 */
class CountriesBundle {

	protected $countries = array();

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 4 );
	}

	public function init() {

		$countries = Umpirsky_Helper::getCountryList();
		$countries = apply_filters( 'mphb_countries', $countries );

		$this->countries = $countries;
	}

	/**
	 *
	 * @return array
	 */
	public function getCountriesList() {
		return $this->countries;
	}

	/**
	 *
	 * @param string $code
	 * @return string
	 */
	public function getCountryLabel( $code ) {
		return isset( $this->countries[ $code ] ) ? $this->countries[ $code ] : '';
	}

	/**
	 * @param string $label
	 * @return string|false
	 *
	 * @since 3.2.0
	 */
	public function getCountryCode( $label ) {
		return array_search( $label, $this->countries );
	}

}
