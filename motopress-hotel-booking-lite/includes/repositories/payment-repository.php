<?php

namespace MPHB\Repositories;

use MPHB\Entities\{ AuthorizedFunds, Payment, WPPostData };

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaymentRepository extends AbstractPostRepository {
	protected $type = 'payment';

	/**
	 * @param \WP_Post|int $post
	 * @return Payment
	 */
	public function mapPostToEntity( $post ) {
		if ( is_a( $post, '\WP_Post' ) ) {
			$id = $post->ID;
		} else {
			$id   = absint( $post );
			$post = get_post( $id );
		}

		$atts = array(
			'id'            => $id,
			'status'        => $post->post_status,
			'date'          => new \DateTime( $post->post_date ),
			'modifiedDate'  => new \DateTime( $post->post_modified ),
			'gatewayId'     => get_post_meta( $id, '_mphb_gateway', true ),
			'gatewayMode'   => get_post_meta( $id, '_mphb_gateway_mode', true ),
			'amount'        => (float) get_post_meta( $id, '_mphb_amount', true ),
			'paymentFee'    => (float) get_post_meta( $id, '_mphb_payment_fee', true ),
			'currency'      => get_post_meta( $id, '_mphb_currency', true ),
			'transactionId' => get_post_meta( $id, '_mphb_transaction_id', true ),
			'bookingId'     => (int) get_post_meta( $id, '_mphb_booking_id', true ),
			'email'         => get_post_meta( $id, '_mphb_email', true ),
		);

		$authedFunds = array(
			'amount'            => get_post_meta( $id, '_mphb_authed_funds_amount', true ),
			'amount_capturable' => get_post_meta( $id, '_mphb_authed_funds_amount_capturable', true ),
			'expiration_action' => get_post_meta( $id, '_mphb_authed_funds_expiration_action', true ),
			'expiration_time'   => get_post_meta( $id, '_mphb_authed_funds_expiration_time', true ),
			'time'              => get_post_meta( $id, '_mphb_authed_funds_time', true ),
		);

		if ( ! empty( $authedFunds['amount'] ) || ! empty( $authedFunds['amount_capturable'] ) ) {
			$atts['authed_funds'] = AuthorizedFunds::createFromFields( $id, $authedFunds );
		}

		return new Payment( $atts );
	}

	/**
	 * @param Payment $entity
	 * @return WPPostData
	 */
	public function mapEntityToPostData( $entity ) {

		$postAtts = array(
			'ID'          => $entity->getId(),
			'post_metas'  => array(),
			'post_status' => $entity->getStatus(),
			'post_date'   => $entity->getDate()->format( 'Y-m-d H:i:s' ),
			'post_type'   => MPHB()->postTypes()->payment()->getPostType(),
		);

		$postAtts['post_metas'] = array(
			'_mphb_gateway'        => $entity->getGatewayId(),
			'_mphb_gateway_mode'   => $entity->getGatewayMode(),
			'_mphb_amount'         => $entity->getAmount(),
			'_mphb_payment_fee'    => $entity->getPaymentFee(),
			'_mphb_currency'       => $entity->getCurrency(),
			'_mphb_transaction_id' => $entity->getTransactionId(),
			'_mphb_booking_id'     => $entity->getBookingId(),
			'_mphb_email'          => $entity->getEmail(),
		);

		if ( $entity->hasAuthedFunds() ) {
			$authedFunds = $entity->getAuthedFunds()->toArray();

			$postAtts['post_metas'] += array(
				'_mphb_authed_funds_amount'            => $authedFunds['amount'],
				'_mphb_authed_funds_amount_capturable' => $authedFunds['amount_capturable'],
				'_mphb_authed_funds_expiration_action' => $authedFunds['expiration_action'],
				'_mphb_authed_funds_expiration_time'   => $authedFunds['expiration_time'],
				'_mphb_authed_funds_time'              => $authedFunds['time'],
			);
		}

		return new WPPostData( $postAtts );
	}

	/**
	 * @param int $id
	 * @return Payment
	 */
	public function findById( $id, $force = false ) {
		return parent::findById( $id, $force );
	}

	/**
	 * @param array $atts
	 * @return Payment[]
	 */
	public function findAll( $atts = array() ) {
		return parent::findAll( $atts );
	}

	/**
	 * @since 5.0.0
	 *
	 * @param string $transactionId
	 * @return Payment|null
	 */
	public function findByTransactionId( $transactionId ) {
		return $this->findByMeta( '_mphb_transaction_id', $transactionId );
	}
}
