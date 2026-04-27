<?php

namespace MPHB\Payments\Gateways;

use MPHB\Admin\Fields\FieldFactory;
use MPHB\Admin\Groups\SettingsGroup;
use MPHB\Admin\Tabs\SettingsSubTab;
use MPHB\Entities\{ Booking, Payment };
use MPHB\Payments\Gateways\GatewayManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Gateway implements GatewayInterface {
	const MODE_LIVE    = 'live';
	const MODE_SANDBOX = 'sandbox';

	/**
	 * @var string
	 */
	protected $id = '';

	/**
	 * @var string
	 */
	protected $adminTitle = '';

	/**
	 * @var string
	 */
	protected $title = '';

	/**
	 * @var array
	 */
	protected $paymentFieldsErrors = array();

	/**
	 * @var array
	 */
	protected $postedPaymentFields = array();

	/**
	 * @var array
	 */
	protected $paymentFields;

	/**
	 * @var type
	 */
	protected $showOptions = true;

	private $defaultOptions;

	/**
	 * @since 4.2.4
	 * @var bool
	 */
	protected $isSuspended = false;

	/**
	 * A list of fields for FieldFactory.
	 */
	private array $optionFields = array();

	public function __construct() {
		$this->id = $this->initId();
		$this->defaultOptions = $this->initDefaultOptions();

		$this->setupProperties();
		$this->setupPaymentFields();

		add_action( 'mphb_register_gateways', array( $this, 'preRegister' ) );
		add_action( 'mphb_init_gateways', array( $this, 'register' ) );
	}

	public function isShowOptions() {
		return $this->showOptions;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getAdminTitle() {
		return $this->adminTitle;
	}

	/**
	 * @return strings
	 */
	public function getAdminDescription() {
		return '';
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return string
	 * @since 3.6.1
	 */
	public function getInstructions() {
		return $this->getOption( 'instructions' );
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		return (bool) $this->getOption( 'enable' );
	}

	/**
	 * Whether is Gateway Eanbled and support current plugin settings (currency, etc.)
	 *
	 * @return boolean
	 */
	public function isActive() {
		return $this->isEnabled() && ! $this->isSuspended;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->getOption( 'description' );
	}

	/**
	 * @return array
	 */
	protected function initDefaultOptions() {
		return array(
			'title'                  => $this->id,
			'description'            => '',
			'instructions'           => '',
			'enabled'                => false,
			'is_sandbox'             => false,
			'payment_fee_fixed'      => 0.0,
			'payment_fee_percentage' => 0.0,
		);
	}

	protected function setupProperties() {
		$this->title = $this->getOption( 'title' );
	}

	/**
	 * @param string $optionName
	 * @return mixed
	 */
	protected function getOption( $optionName ) {
		$fullOptionName = "mphb_payment_gateway_{$this->id}_{$optionName}";
		$optionValue = get_option( $fullOptionName, $this->getDefaultOption( $optionName ) );

		$translatableOptions = array( 'title', 'description', 'instructions' );

		if ( in_array( $optionName, $translatableOptions ) ) {
			$optionValue = apply_filters( 'mphb_translate_string', $optionValue, $fullOptionName );
		}

		return $optionValue;
	}

	public function getOptionPaymentFeeFixed(): float {
		return (float) $this->getOption('payment_fee_fixed');
	}

	public function getOptionPaymentFeePercentage(): float {
		return (float) $this->getOption('payment_fee_percentage');
	}

	public function calculatePaymentFee( float $paymentTransactionAmount ): float {

		$paymentFeeFixed      = $this->getOptionPaymentFeeFixed();
		$paymentFeePercentage = $this->getOptionPaymentFeePercentage();

		$paymentFee = $paymentFeeFixed;

		if ( 0 < $paymentFeePercentage ) {
			$paymentFee = round( $paymentFeeFixed + ( $paymentFeePercentage / 100 ) * $paymentTransactionAmount, 2 );
		}

		return $paymentFee;
	}

	/**
	 * @param string $optionName
	 * @return mixed
	 */
	protected function getDefaultOption( $optionName ) {
		return isset( $this->defaultOptions[ $optionName ] ) ? $this->defaultOptions[ $optionName ] : '';
	}

	protected function initId() {
		return static::GATEWAY_ID;
	}

	public function setupPaymentFields() {
		$fields = $this->initPaymentFields();

		foreach ( $fields as $key => &$field ) {
			$field['type']     = isset( $field['type'] ) ? $field['type'] : 'text';
			$field['required'] = isset( $field['required'] ) ? $field['required'] : false;
			$field['meta_id']  = isset( $field['meta_id'] ) ? $field['meta_id'] : $key;
			$field['label']    = isset( $field['label'] ) ? $field['label'] : '';
		}

		$this->paymentFields = $fields;
	}

	/**
	 * @return array
	 */
	public function initPaymentFields() {
		return array();
	}

	/**
	 * @since 4.2.4
	 * @param string[] $suspendPayments
	 */
	public function preRegister( $suspendPayments ) {
		$this->isSuspended = in_array( $this->id, $suspendPayments );
	}

	/**
	 * @param GatewayManager $gatewayManager
	 */
	public function register( GatewayManager $gatewayManager ) {
		if ( ! $this->isSuspended ) {
			$gatewayManager->addGateway( $this );
		}
	}

	/**
	 * @return mixed|null
	 */
	abstract public function processPayment( Booking $booking, Payment $payment );

	public function captureAuthorizedFunds( Payment $payment ): bool {
		throw new \RuntimeException(
			esc_html__( 'This payment gateway does not support holding payments to be charged later.', 'motopress-hotel-booking' ) );
	}

	public function releaseAuthorizedFunds( Payment $payment ): bool {
		throw new \RuntimeException(
			esc_html__( 'This payment gateway does not support holding payments to be charged later.', 'motopress-hotel-booking' ) );
	}

	/**
	 * @return string
	 */
	public function getMode() {
		return $this->isSandbox() ? self::MODE_SANDBOX : self::MODE_LIVE;
	}

	/**
	 * @param array $input
	 * @param array $errors
	 * @return bool
	 */
	public function parsePaymentFields( $input, &$errors ) {
		foreach ( $this->paymentFields as $key => $field ) {
			if ( ! isset( $field['type'] ) ) {
				$field['type'] = 'text';
			}

			// Get Value
			switch ( $field['type'] ) {
				case 'checkbox':
					$this->postedPaymentFields[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
					break;
				case 'textarea':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$this->postedPaymentFields[ $key ] = isset( $input[ $key ] ) ? wp_strip_all_tags( wp_check_invalid_utf8( wp_unslash( $input[ $key ] ) ) ) : '';
					break;
				case 'email':
					$this->postedPaymentFields[ $key ] = isset( $input[ $key ] ) ? sanitize_email( wp_unslash( $input[ $key ] ) ) : '';
					break;
				case 'select':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$this->postedPaymentFields[ $key ] = isset( $input[ $key ] ) && array_key_exists( mphb_clean( wp_unslash( $input[ $key ] ) ), $field['list'] ) ? mphb_clean( $input[ $key ] ) : '';
					break;
				default:
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$this->postedPaymentFields[ $key ] = isset( $input[ $key ] ) ? mphb_clean( wp_unslash( $input[ $key ] ) ) : '';
					break;
			}

			// Validation: Required fields
			if ( $field['required'] && ( ! isset( $this->postedPaymentFields[ $key ] ) || '' === $this->postedPaymentFields[ $key ] ) ) {
				$fieldLabel = ( $field['label'] !== '' ) ? $field['label'] : $key;

				$this->paymentFieldsErrors[] = sprintf( __( '%s is a required field.', 'motopress-hotel-booking' ), $fieldLabel );
			}

			if ( ! empty( $this->postedPaymentFields[ $key ] ) ) {
				// Validation rules
				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {
					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'email':
								$this->postedPaymentFields[ $key ] = strtolower( $this->postedPaymentFields[ $key ] );

								if ( ! is_email( $this->postedPaymentFields[ $key ] ) ) {
									$this->paymentFieldsErrors[] = sprintf( __( '%s is not a valid email address.', 'motopress-hotel-booking' ), $field['label'] );
								}
						}
					}
				}
			}
		}

		$errors = array_merge( $errors, $this->paymentFieldsErrors );

		return empty( $this->paymentFieldsErrors );
	}

	/**
	 * @param Booking $booking
	 */
	public function renderPaymentFields( $booking ) {
		foreach ( $this->paymentFields as $key => $field ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo str_replace( '%field_placeholder%', $this->renderField( $key, $field ), $this->renderFieldWrapper( $key, $field ) );
		}
	}

	/**
	 * @param string $fieldName
	 * @param array  $fieldDetails
	 * @return string
	 */
	private function renderFieldWrapper( $fieldName, $fieldDetails ) {
		$fieldPlaceholder = '%field_placeholder%';
		ob_start();
		if ( $fieldDetails['type'] === 'hidden' ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $fieldPlaceholder;
		} else {
			$labelClass = 'mphb-billing-field-label';
			switch ( $fieldDetails['type'] ) {
				case 'checkbox':
					$labelClass .= ' mphb-checkbox-label';
					break;
				case 'radio':
					$labelClass .= ' mphb-radio-label';
					break;
			}
			?>
			<p class="<?php echo esc_attr( $fieldName ); ?>">
				<label for="<?php echo esc_attr( $fieldName ); ?>" class="<?php echo esc_attr( $labelClass ); ?>">
					<?php echo esc_html( $fieldDetails['label'] ); ?>
					<?php if ( $fieldDetails['required'] ) { ?>
						<abbr title="<?php esc_attr_e( 'Required', 'motopress-hotel-booking' ); ?>">*</abbr>
					<?php } ?>
				</label>
				<br />
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $fieldPlaceholder;
				?>
			</p>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * @param string $fieldName
	 * @param array  $fieldDetails
	 * @return string
	 */
	private function renderField( $fieldName, $fieldDetails ) {
		ob_start();
		switch ( $fieldDetails['type'] ) {
			case 'hidden':
				echo '<input type="hidden" id="' . esc_attr( $fieldName ) . '" name="' . esc_attr( $fieldName ) . '" />';
				break;
			case 'select':
				$list = ! empty( $fieldDetails['list'] ) ? $fieldDetails['list'] : array();
				echo '<select id="' . esc_attr( $fieldName ) . '" name="' . esc_attr( $fieldName ) . '" ' . ( $fieldDetails['required'] ? 'required="required"' : '' ) . '>';
				foreach ( $list as $id => $label ) {
					echo '<option value="' . esc_attr( $id ) . '"> ' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				break;
			case 'text':
			default:
				echo '<input type="text" id="' . esc_attr( $fieldName ) . '" name="' . esc_attr( $fieldName ) . '" ' . ( $fieldDetails['required'] ? 'required="required"' : '' ) . ' />';
				break;
		}
		return ob_get_clean();
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	public function storePaymentFields( $payment ) {
		$success = true;

		foreach ( $this->postedPaymentFields as $fieldName => $fieldValue ) {
			$updated = update_post_meta( $payment->getId(), $this->paymentFields[ $fieldName ]['meta_id'], $fieldValue );
			$success = $success && $updated;
		}

		return $success;
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
	protected function paymentCompleted( $payment ) {
		return MPHB()->paymentManager()->completePayment( $payment );
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
	protected function paymentFailed( $payment ) {
		return MPHB()->paymentManager()->failPayment( $payment );
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
	protected function paymentOnHold( $payment ) {
		return MPHB()->paymentManager()->holdPayment( $payment );
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
	protected function paymentRefunded( $payment ) {
		return MPHB()->paymentManager()->refundPayment( $payment );
	}

	public function getFields( bool $forceReload = false ): array {
		if ( empty( $this->optionFields ) || $forceReload ) {
			$this->optionFields = $this->initOptionFields();
		}

		return $this->optionFields;
	}

	protected function isPaymentFeeSupported(): bool {
		return false;
	}

	protected function initOptionFields(): array {
		$optionsField = array(
			'enable' => array(
				'type'        => 'checkbox',
				// translators: %s is the payment gateway title.
				'inner_label' => sprintf( __( 'Enable "%s"', 'motopress-hotel-booking' ), $this->title ),
				'default'     => $this->getDefaultOption( 'enable' ),
			),
			'is_sandbox' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Test Mode', 'motopress-hotel-booking' ),
				'inner_label' => __( 'Enable Sandbox Mode', 'motopress-hotel-booking' ),
				'description' => __( 'Sandbox can be used to test payments.', 'motopress-hotel-booking' ),
				'default'     => $this->getDefaultOption( 'is_sandbox' ),
			),
			'title' => array(
				'type'         => 'text',
				'label'        => __( 'Title', 'motopress-hotel-booking' ),
				'description'  => __( 'Payment method title that the customer will see on your website.', 'motopress-hotel-booking' ),
				'default'      => $this->getDefaultOption( 'title' ),
				'translatable' => true,
			),
			'description' => array(
				'type'         => 'textarea',
				'label'        => __( 'Description', 'motopress-hotel-booking' ),
				'description'  => __( 'Payment method description that the customer will see on your website.', 'motopress-hotel-booking' ),
				'default'      => $this->getDefaultOption( 'description' ),
				'translatable' => true,
			),
			'instructions' => array(
				'type'         => 'textarea',
				'label'        => __( 'Instructions', 'motopress-hotel-booking' ),
				'description'  => __( 'Instructions for a customer on how to complete the payment.', 'motopress-hotel-booking' ),
				'default'      => $this->getDefaultOption( 'instructions' ),
				'translatable' => true,
			),
		);

		if ( $this->isPaymentFeeSupported() ) {

			$optionsField['payment_fee_fixed'] = array(
				'type'         => 'amount',
				'label'        => __( 'Fixed Transaction Fee', 'motopress-hotel-booking' ),
				'description'  => __( 'A fixed fee added to the booking total to cover payment processor fees.', 'motopress-hotel-booking' ),
				'size'         => 'price',
				'min'          => 0,
				'default_render_type' => 'price',
				'default'      => 0,
			);

			$optionsField['payment_fee_percentage'] = array(
				'type'         => 'amount',
				'label'        => __( 'Percentage Transaction Fee', 'motopress-hotel-booking' ),
				'description'  => __( 'A percentage fee added to the booking total to cover payment processor fees.', 'motopress-hotel-booking' ),
				'size'         => 'price',
				'min'          => 0,
				'default_render_type' => 'percent',
				'default'      => 0,
			);
		}

		return $optionsField;
	}

	/**
	 * @param SettingsSubTab $subTab
	 * @since 3.6.1 added new filter - "mphb_gateway_has_instructions".
	 */
	public function registerOptionsFields( &$subTab ) {

		$fields = $this->getFields();

		$mainGroup = new SettingsGroup( "mphb_payments_{$this->id}_group", '', $subTab->getOptionGroupName() );

		$mainGroupFields = array();

		// Gateways will disable this field if something goes wrong
		$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_enable", $fields['enable'] );

		if ( apply_filters( 'mphb_gateway_has_sandbox', true, $this->getId() ) ) {
			$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_is_sandbox", $fields['is_sandbox'] );
		}

		$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_title", $fields['title'] );
		$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_description", $fields['description'] );

		if ( apply_filters( 'mphb_gateway_has_instructions', true, $this->getId() ) ) {
			$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_instructions", $fields['instructions'] );
		}

		if ( $this->isPaymentFeeSupported() ) {

			$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_payment_fee_fixed", $fields['payment_fee_fixed'] );
			$mainGroupFields[] = FieldFactory::create( "mphb_payment_gateway_{$this->id}_payment_fee_percentage", $fields['payment_fee_percentage'] );
		}

		$mainGroup->addFields( $mainGroupFields );
		$subTab->addGroup( $mainGroup );
	}

	/**
	 * @return bool
	 */
	public function isSandbox() {
		return (bool) $this->getOption( 'is_sandbox' );
	}

	/**
	 * @param Booking $booking
	 * @return string
	 */
	public function generateItemName( $booking ) {
		if ( $booking->getId() > 0 ) {
			return sprintf( __( 'Reservation #%d', 'motopress-hotel-booking' ), $booking->getId() );
		} else {
			return __( 'Accommodation(s) reservation', 'motopress-hotel-booking' );
		}
	}

	/**
	 * @param Booking $booking
	 * @return array
	 */
	public function getCheckoutData( $booking ) {
		$paymentTransactionTotal = $booking->calcDepositAmount();

		$checkoutData = array(
			'amount'             => $paymentTransactionTotal,
			'paymentDescription' => $this->generateItemName( $booking ),
		);

		if ( $this->isPaymentFeeSupported() ) {

			$paymentFee = $this->calculatePaymentFee( $paymentTransactionTotal );

			$checkoutData['paymentFee']     = $paymentFee;
			$checkoutData['paymentFeeHtml'] = mphb_format_price( $paymentFee );
		}

		return $checkoutData;
	}

	/**
	 * @return bool
	 */
	public function hasPaymentFields() {
		return empty( $this->paymentFields );
	}

	/**
	 * @return bool
	 */
	public function hasVisiblePaymentFields() {
		$visibleFields = array_filter(
			$this->paymentFields,
			function( $field ) {
				return $field['type'] !== 'hidden';
			}
		);

		return ! empty( $visibleFields );
	}
}
