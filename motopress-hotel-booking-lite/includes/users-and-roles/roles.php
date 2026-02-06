<?php

/**
 * @since 4.0.0
 */

namespace MPHB\UsersAndRoles;

class Roles {

	const VERSION = 3;

	const MANAGER  = 'mphb_manager';
	const WORKER   = 'mphb_worker';
	const CUSTOMER = 'mphb_customer';

	/**
	 * @var array
	 */
	public $roles = array();


	/**
	 * IMPORTANT: To avoid a PHP warning about too early string localization, use
	 * this method either after an init hook or after it.
	 * @return array [ string (role name) => \MPHB\UsersAndRoles\Role, ... ]
	 */
	public function getRoles() {

		if ( empty( $this->roles ) ) {

			$this->roles[ self::MANAGER ] = new Role(
				array(
					'name'        => self::MANAGER,
					'description' => __( 'Hotel Manager', 'motopress-hotel-booking' ),
				)
			);
	
			$this->roles[ self::WORKER ] = new Role(
				array(
					'name'        => self::WORKER,
					'description' => __( 'Hotel Worker', 'motopress-hotel-booking' ),
				)
			);
	
			$this->roles[ self::CUSTOMER ] = new Role(
				array(
					'name'        => self::CUSTOMER,
					'description' => __( 'Hotel Customer', 'motopress-hotel-booking' ),
				)
			);
		}

		return $this->roles;
	}

	/**
	 *
	 * @return int
	 */
	public static function getCurrentVersion() {
		return self::VERSION;
	}
}
