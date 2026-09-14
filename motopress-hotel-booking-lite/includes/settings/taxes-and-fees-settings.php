<?php

namespace MPHB\Settings;

class TaxesAndFeesSettings {
	private ?array $fees = null;
	private ?array $accommodationTaxes = null;
	private ?array $serviceTaxes = null;
	private ?array $feeTaxes = null;

	public function getFees( $typeId = 0 ) {
		if ( is_null( $this->fees ) ) {
			$this->fees = get_option( 'mphb_fees', array() );
		}

		if ( $typeId != 0 ) {
			$taxes = $this->filterByTypeId( $this->fees, $typeId );
		} else {
			$taxes = $this->fees;
		}

		return $this->structurize( $taxes );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $typeId
	 * @return bool
	 */
	public function hasFees( $typeId = 0 ) {
		return ! empty( $this->getFees( $typeId ) );
	}

	/**
	 * @param int $typeId
	 *
	 * @return array
	 */
	public function getAccommodationTaxes( $typeId = 0 ) {
		if ( is_null( $this->accommodationTaxes ) ) {
			$this->accommodationTaxes = get_option( 'mphb_accommodation_taxes', array() );
		}

		if ( $typeId != 0 ) {
			$taxes = $this->filterByTypeId( $this->accommodationTaxes, $typeId );
		} else {
			$taxes = $this->accommodationTaxes;
		}

		return $this->structurize( $taxes );
	}

	/**
	 * Service taxes are not directly linked to accommodation type IDs ("rooms"
	 * value is "-").
	 */
	public function getServiceTaxes() {
		if ( is_null( $this->serviceTaxes ) ) {
			$this->serviceTaxes = get_option( 'mphb_service_taxes', array() );

			// Structurize immediately, as there will be no filters by room type
			// ID in the future
			if ( ! empty( $this->serviceTaxes ) ) {
				$this->serviceTaxes = $this->structurize( $this->serviceTaxes );
			}
		}

		return $this->serviceTaxes;
	}

	/**
	 * Fee taxes are based only on the fee list and are not filtered by
	 * accommodation type ID ("rooms" value is "-").
	 */
	public function getFeeTaxes() {
		if ( is_null( $this->feeTaxes ) ) {
			$this->feeTaxes = get_option( 'mphb_fee_taxes', array() );

			// Structurize immediately, as there will be no filters by room type
			// ID in the future
			if ( ! empty( $this->feeTaxes ) ) {
				$this->feeTaxes = $this->structurize( $this->feeTaxes );
			}
		}

		return $this->feeTaxes;
	}

	private function filterByTypeId( $rules, $filterId ) {
		$filteredRules = array();

		foreach ( $rules as $rule ) {
			// Filter by room type ID
			$roomTypes = $rule['rooms'];

			foreach ( $roomTypes as $roomType ) {
				$roomType = (int) $roomType;

				// 0 = "All types"
				if ( $roomType == 0 || $roomType == $filterId ) {
					$filteredRules[] = $rule;
					break;
				}
			} // foreach ( $roomTypes ... )
		} // foreach ( $rules ... )

		return $filteredRules;
	}

	/**
	 *
	 * @since 3.9.8 $rule['included']
	 */
	private function structurize( $rules ) {
		$convertedRules = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule['amount'] ) ) {
				$amount = (float) $rule['amount'];
			} else {
				$amount = array(
					'adults'   => (float) $rule['amount'][0],
					'children' => (float) $rule['amount'][1],
				);
			}

			// Compability with older versions
			$rule['included'] = isset( $rule['included'] ) ? (bool) $rule['included'] : 0;

			$convertedRules[] = array(
				'label'    => $rule['label'],
				'type'     => $rule['type'],
				'amount'   => $amount,
				'limit'    => (int) $rule['limit'],
				'included' => (bool) $rule['included'],
			);
		}

		return $convertedRules;
	}

}
