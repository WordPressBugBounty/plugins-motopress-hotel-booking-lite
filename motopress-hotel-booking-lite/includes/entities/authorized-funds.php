<?php

declare(strict_types=1);

namespace MPHB\Entities;

use MPHB\Utils\DateUtils;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuthorizedFunds {
	private float $amount = 0.0;
	private float $capturableAmount = 0.0;

	private ?DateTime $authorizationTime = null;
	private ?DateTime $expirationTime = null;

	private string $expirationAction = 'release';

	private int $paymentId = 0;

	public function __construct( int $paymentId ) {
		$this->paymentId = $paymentId;
	}

	public function capture( ?float $capturedAmount = null ): void {
		if ( $capturedAmount !== null ) {
			$this->setAmount( $capturedAmount );
		}

		$this->setCapturableAmount( 0.0 );
		$this->setExpirationTime( new DateTime( 'now', DateUtils::getSiteTimeZone() ) );
	}

	/**
	 * @param string $format "mysql", "wp" or custom format.
	 * @return string Formatted date string or "" if authorization time is null.
	 */
	public function formatAuthorizationTime( string $format ): string {
		if ( $this->authorizationTime === null ) {
			return '';
		}

		switch ( $format ) {
			case 'mysql': return $this->authorizationTime->format( 'Y-m-d H:i:s' );
			case 'wp':    return $this->authorizationTime->format( MPHB()->settings()->dateTime()->getDateTimeFormatWP() );
			default:      return $this->authorizationTime->format( $format );
		}
	}

	/**
	 * @param string $format "mysql", "wp" or custom format.
	 * @return string Formatted date string or "" if expiration time is null.
	 */
	public function formatExpirationTime( string $format ): string {
		if ( $this->expirationTime === null ) {
			return '';
		}

		switch ( $format ) {
			case 'mysql': return $this->expirationTime->format( 'Y-m-d H:i:s' );
			case 'wp':    return $this->expirationTime->format( MPHB()->settings()->dateTime()->getDateTimeFormatWP() );
			default:      return $this->expirationTime->format( $format );
		}
	}

	public function getAmount(): float {
		return $this->amount;
	}

	public function getAuthorizationTime(): ?DateTime {
		return $this->authorizationTime;
	}

	public function getCapturableAmount(): float {
		return $this->capturableAmount;
	}

	public function getExpirationAction(): string {
		return $this->expirationAction;
	}

	public function getExpirationTime(): ?DateTime {
		return $this->expirationTime;
	}

	public function getPaymentId(): int {
		return $this->paymentId;
	}

	public function hasAuthorizationTime(): bool {
		return $this->authorizationTime !== null;
	}

	public function hasCapturableAmount(): bool {
		return $this->capturableAmount > 0;
	}

	public function hasExpirationTime(): bool {
		return $this->expirationTime !== null;
	}

	public function isEditable(): bool {
		return $this->isPending();
	}

	public function isPending(): bool {
		if ( ! $this->hasCapturableAmount() || $this->expirationTime === null ) {
			return false;
		} else {
			$now = new DateTime( 'now', DateUtils::getSiteTimeZone() );

			return $now <= $this->expirationTime;
		}
	}

	public function release(): void {
		$this->setCapturableAmount( 0.0 );
		$this->setExpirationTime( new DateTime( 'now', DateUtils::getSiteTimeZone() ) );
	}

	public function setAmount( float $amount ): void {
		$this->amount = $amount;
	}

	/**
	 * @param DateTime|string|null $authorizationTime
	 */
	public function setAuthorizationTime( $authorizationTime ): void {
		if ( $authorizationTime === '' ) {
			$authorizationTime = null;
		} elseif ( is_string( $authorizationTime ) ) {
			$authorizationTime = DateUtils::createDateTime( $authorizationTime, 'mysql' );
		}

		$this->authorizationTime = $authorizationTime;
	}

	public function setCapturableAmount( float $amount ): void {
		$this->capturableAmount = $amount;
	}

	public function setExpirationAction( string $action ): void {
		$this->expirationAction = $action;
	}

	/**
	 * @param DateTime|string|null $expirationTime
	 */
	public function setExpirationTime( $expirationTime ): void {
		if ( $expirationTime === '' ) {
			$expirationTime = null;
		} elseif ( is_string( $expirationTime ) ) {
			$expirationTime = DateUtils::createDateTime( $expirationTime, 'mysql' );
		}

		$this->expirationTime = $expirationTime;
	}

	public function setPaymentId( int $paymentId ): void {
		$this->paymentId = $paymentId;
	}

	/**
	 * @param string|false $formatDate "mysql", "wp" or custom date format.
	 */
	public function toArray( $formatDate = 'mysql' ): array {
		$fields = array(
			'amount'            => $this->getAmount(),
			'amount_capturable' => $this->getCapturableAmount(),
			'expiration_action' => $this->getExpirationAction(),
		);

		if ( $formatDate !== false ) {
			$fields += array(
				'expiration_time' => $this->formatExpirationTime( $formatDate ),
				'time'            => $this->formatAuthorizationTime( $formatDate ),
			);
		} else {
			$fields += array(
				'expiration_time' => $this->getExpirationTime(),
				'time'            => $this->getAuthorizationTime(),
			);
		}

		return $fields;
	}

	public static function createForAmount( int $paymentId, float $amount ): self {
		$self = new static( $paymentId );

		$self->setCapturableAmount( $amount );
		$self->setAmount( $amount );

		$self->setAuthorizationTime( current_time( 'mysql' ) );
		$self->setExpirationTime( new DateTime( '+7 days', DateUtils::getSiteTimeZone() ) );

		return $self;
	}

	public static function createFromFields( int $paymentId, array $fields ): self {
		$self = new static( $paymentId );

		if ( ! empty( $fields['amount_capturable'] ) ) {
			$self->setCapturableAmount( (float) $fields['amount_capturable'] );
		}

		if ( ! empty( $fields['amount'] ) ) {
			$self->setAmount( (float) $fields['amount'] );
		} else {
			$self->setAmount( $self->getCapturableAmount() );
		}

		$self->setExpirationAction( $fields['expiration_action'] ?? 'release' );
		$self->setExpirationTime( $fields['expiration_time'] ?? null );
		$self->setAuthorizationTime( $fields['time'] ?? null );

		return $self;
	}
}
