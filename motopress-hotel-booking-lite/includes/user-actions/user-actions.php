<?php

namespace MPHB\UserActions;

defined( 'ABSPATH' ) || exit;

class UserActions {

	private ActionLinkHelper $actionLinkHelper;
	private BookingCancellationAction $bookingCancellationAction;
	private BookingConfirmationAction $bookingConfirmationAction;
	private BookingViewAction $bookingViewAction;
	private CheckoutLinkAction $checkoutLinkAction;

	public function __construct() {

		$this->actionLinkHelper          = new ActionLinkHelper();
		$this->bookingCancellationAction = new BookingCancellationAction();
		$this->bookingConfirmationAction = new BookingConfirmationAction();
		$this->bookingViewAction         = new BookingViewAction();
		$this->checkoutLinkAction        = new CheckoutLinkAction();
	}

	public function getActionLinkHelper(): ActionLinkHelper {
		return $this->actionLinkHelper;
	}

	public function getBookingCancellationAction(): BookingCancellationAction {
		return $this->bookingCancellationAction;
	}

	public function getBookingConfirmationAction(): BookingConfirmationAction {
		return $this->bookingConfirmationAction;
	}

	/**
	 * @since 3.7.0
	 */
	public function getBookingViewAction(): BookingViewAction {
		return $this->bookingViewAction;
	}
}
