<?php

declare(strict_types=1);

namespace MPHB\Admin\Metaboxes;

use MPHB\Entities\Payment;
use MPHB\PostTypes\PaymentCPT\Statuses as PaymentStatuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaymentAuthedFundsMetabox extends AbstractFieldsMetabox {
	/**
	 * @access protected
	 */
	public function display(): void {
		parent::display();

		$payment = $this->getPayment();

		if ( $payment !== null && $payment->hasPendingAuthedFunds() ) {
			?>
			<p class="mphb-spaced-actions">
				<input class="button button-primary" name="mphb_capture_authed_amount" type="submit" value="<?php
					// translators: Button label. "Charge Now" means to charge (take) the authorized payment from the customer.
					esc_html_e( 'Charge Now', 'motopress-hotel-booking' ); ?>">

				<!--input class="button button-secondary" name="mphb_release_authed_amount" type="submit" value="<?php
					// translators: Button label. "Return Funds" means to cancel the authorization and return the held funds to the customer.
					echo esc_attr( __( 'Return Funds', 'motopress-hotel-booking' ) ); ?>"-->

				<button class="button button-secondary" id="mphb_maybe_release_authed_amount" type="button"><?php
					// translators: Button label. "Return Funds" means to cancel the authorization and return the held funds to the customer.
					esc_html_e( 'Return Funds', 'motopress-hotel-booking' ); ?></button>
			</p>

			<dialog class="dialog-confirm" closedby="any" id="mphb_release_funds_confirmation">
				<p><?php esc_html_e( 'Are you sure you want to remove the payment hold without charging the customer? The booking will not be canceled automatically and must be canceled manually if needed.', 'motopress-hotel-booking' ); ?></p>
				<div class="controls">
					<!--button class="button button-primary button-ok" type="button"><?php esc_html_e( 'Yes', 'motopress-hotel-booking' ); ?></button-->
					<input class="button button-primary button-ok" name="mphb_release_authed_amount" type="submit" value="<?php echo esc_attr( __( 'Yes', 'motopress-hotel-booking' ) ); ?>">
					<button class="button button-secondary button-cancel" type="button"><?php esc_html_e( 'Cancel', 'motopress-hotel-booking' ); ?></button>
				</div>
			</dialog>
			<?php
		}
	}

	protected function getRawFields(): array {
		$payment = $this->getPayment();
		$funds = ( $payment !== null ) ? $payment->getAuthedFunds() : null;

		if ( $funds === null ) {
			return array();
		}

		$fields = array();

		if ( $funds->isEditable() ) {
			$fields += array(
				'authed_funds_amount_capturable' => array(
					'default'     => round( $funds->getCapturableAmount(), 2 ),
					'description' => __( 'The amount reserved at checkout.', 'motopress-hotel-booking' ),
					'label'       => __( 'Reserved Amount', 'motopress-hotel-booking' ),
					'disabled'    => true,
					'size'        => 'price',
					'type'        => 'number',
				),
				'authed_funds_amount' => array(
					'default'     => round( $funds->getAmount(), 2 ),
					'description' => __( 'You can’t charge more than the reserved amount. To charge extra, create an additional payment.', 'motopress-hotel-booking' ),
					// translators: Field label. "Сharge" refers to the amount to charge (capture) from a previously authorized payment.
					'label'       => __( 'Сharge', 'motopress-hotel-booking' ),
					'max'         => $funds->getCapturableAmount(),
					'min'         => 0.0,
					'step'        => 0.01,
					'disabled'    => $payment->isFinished(),
					'size'        => 'price',
					'type'        => 'number',
				),
			);
		} else {
			$fields += array(
				'authed_funds_amount' => array(
					'default'  => round( $funds->getAmount(), 2 ),
					'label'    => __( 'Amount', 'motopress-hotel-booking' ),
					'disabled' => true,
					'size'     => 'price',
					'type'     => 'number',
				),
			);
		}

		$fields += array(
			'authed_funds_time' => array(
				'default' => $funds->formatAuthorizationTime( 'wp' ) ?: mphb_tmpl_placeholder(),
				// translators: Column or field label. Indicates the date and time when the payment was authorized (funds reserved but not yet captured).
				'label'   => __( 'Reserved On', 'motopress-hotel-booking' ),
				'type'    => 'placeholder',
			),
			'authed_funds_expiration_time' => array(
				'default'     => $funds->formatExpirationTime( 'wp' ) ?: mphb_tmpl_placeholder(),
				'description' => ( $funds->getExpirationAction() === 'release' )
					? __( 'If you don’t charge the payment, the funds will be released automatically after this time. Some payment gateways may release them earlier.', 'motopress-hotel-booking' )
					: __( 'After this time, the payment will be automatically charged. Some payment gateways may charge it earlier than the specified deadline.', 'motopress-hotel-booking' ),
				// translators: Column or field label. Indicates the date and time until which the payment authorization (reserved funds) is valid.
				'label'       => __( 'Reserved Till', 'motopress-hotel-booking' ),
				'type'        => 'placeholder',
			),
		);

		return $fields;
	}

	protected function getSlug(): string {
		return 'mphb_payment_authed_funds_metabox';
	}

	protected function getTitle(): string {
		return __( 'Reserved Funds', 'motopress-hotel-booking' );
	}

	protected function isEnabled(): bool {
		if ( $this->editPage->isCurrentAddNewPage() || ! parent::isEnabled() ) {
			return false;
		}

		$payment = $this->getPayment();

		return $payment !== null
			&& $payment->getStatus() !== PaymentStatuses::STATUS_PENDING
			&& $payment->hasAuthedFunds();
	}

	protected function saveFields( \WP_Post $post, array $values ): void {
		$payment = $this->getPayment();

		if ( $payment === null ) {
			return;
		}

		$capturingFunds = isset( $_POST['mphb_capture_authed_amount'] );
		$releasingFunds = isset( $_POST['mphb_release_authed_amount'] );

		// Update payment amounts
		if ( isset( $values['authed_funds_amount'] ) && $payment->hasPendingAuthedFunds() ) {
			$newAuthedAmount = (float) $values['authed_funds_amount'];

			// Don't update "fund_amount" when releasing funds
			if ( ! $releasingFunds ) {
				update_post_meta( $payment->getId(), '_mphb_authed_funds_amount', $newAuthedAmount );

				// Keep the payment object updated
				$payment->getAuthedFunds()->setAmount( $newAuthedAmount );
			}

			// Don't update payment amount when not capturing funds
			if ( $capturingFunds ) {
				$newAmount = $newAuthedAmount - $payment->getPaymentFee();

				update_post_meta( $payment->getId(), '_mphb_amount', $newAmount );

				// Keep the payment object updated
				$payment->setAmount( $newAmount );
			}
		}

		// Capture/release funds by changing the status of the payment
		if ( $capturingFunds || $releasingFunds ) {
			if ( $payment->isFinished() ) {
				// Failed requests? Send again.
				if ( $payment->isFailed() || $releasingFunds ) {
					MPHB()->paymentManager()->releaseAuthorizedFunds( $payment );
				} else {
					MPHB()->paymentManager()->captureAuthorizedFunds( $payment );
				}

			} elseif ( isset( $_POST['mphb_capture_authed_amount'] ) ) {
				// Change status of the submit metabox
				$_POST['mphb_post_status'] = PaymentStatuses::STATUS_COMPLETED;

			} elseif ( isset( $_POST['mphb_release_authed_amount'] ) ) {
				$_POST['mphb_post_status'] = PaymentStatuses::STATUS_CANCELLED;
			}
		}
	}

	private function getPayment(): ?Payment {
		$postId = $this->editPage->getPostId();

		if ( $postId !== 0 ) {
			return mphb_bookings_facade()->findPaymentById( $postId );
		} else {
			return null;
		}
	}
}
