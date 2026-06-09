<?php

namespace MPHB\Admin\Fields;

class TotalPriceField extends NumberField {

	const TYPE = 'total-price';

	protected $step      = 0.01;
	protected $min       = 0;
	protected $inputType = 'number';

	public function renderInput() {

		// [MB-684] Prevent excess number of digits
		$this->value = round( $this->value, MPHB()->settings()->currency()->getPriceDecimalsCount() );

		$result      = sprintf( MPHB()->settings()->currency()->getPriceFormat(), parent::renderInput() );
		$result     .= ' <button type="button" id="mphb-recalculate-total-price" class="button button-secondary">' . __( 'Recalculate Total Price', 'motopress-hotel-booking' ) . '</button>';
		$result     .= '&nbsp;' . mphb_help_tip( __( 'Recalculate the total price and price breakdown using current rates, fees, taxes, and coupons. No changes are saved until the booking is updated.', 'motopress-hotel-booking' ), false, 'mphb-help-tip__size-medium' );
		$result     .= '<span class="mphb-preloader mphb-hide"></span>';
		$result     .= '<div class="mphb-errors-wrapper mphb-hide"></div>';
		return $result;
	}
}
