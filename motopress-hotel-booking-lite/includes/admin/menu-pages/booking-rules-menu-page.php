<?php

namespace MPHB\Admin\MenuPages;

use MPHB\Admin\Fields\{ FieldFactory, InputField };
use MPHB\Admin\BlocksListTable;
use MPHB\Utils\DateUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingRulesMenuPage extends AbstractMenuPage {
	private const TAB_BLOCKS = 'blocks';
	private const TAB_RESERVATION_RULES = 'reservation';

	private const TABS = array(
		self::TAB_BLOCKS,
		self::TAB_RESERVATION_RULES,
	);

	private const NONCE_ACTION = 'mphb_save_booking_rules';
	private const NONCE_FIELD_NAME = 'mphb_booking_rules';

	private ?BlocksListTable $blocksList = null;

	/**
	 * @var array|null Fields for reservation rules tab
	 */
	private ?array $fields = null;

	/**
	 * @access private
	 */
	public function addActions() {
		parent::addActions();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_action( 'admin_notices', array( $this, 'showNotices' ) );
	}

	/**
	 * @access private
	 */
	public function enqueueScripts(): void {
		if ( ! $this->isCurrentPage() ) {
			return;
		}

		$currentTab = $this->getCurrentTab();

		if ( $currentTab === self::TAB_RESERVATION_RULES ) {
			MPHB()->getAdminScriptManager()->enqueue();

			wp_enqueue_script( 'mphb-jquery-serialize-json' );

		} elseif ( $currentTab === self::TAB_BLOCKS ) {
			MPHB()->getAdminScriptManager()->enqueue();
		}
	}

	/**
	 * @access protected
	 */
	public function onLoad() {
		if ( ! $this->isCurrentPage() ) {
			return;
		}

		$currentTab = $this->getCurrentTab();

		if ( $currentTab === self::TAB_RESERVATION_RULES ) {
			$canSave = false;

			if ( isset( $_POST['save'] ) && isset( $_POST[ self::NONCE_FIELD_NAME ] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD_NAME ] ) );

				$canSave = wp_verify_nonce( $nonce, self::NONCE_ACTION );
			}

			if ( $canSave ) {
				$this->saveReservationRules();
			}

		} elseif ( $currentTab === self::TAB_BLOCKS ) {
			$this->blocksList = new BlocksListTable();
			$this->blocksList->process_actions();
		}
	}

	/**
	 * @access protected
	 */
	public function render() {
		$tabs = array(
			self::TAB_RESERVATION_RULES => __( 'Booking Rules', 'motopress-hotel-booking' ),
			// translators: Section label. Refers to options for blocking an accommodation’s availability.
			self::TAB_BLOCKS            => __( 'Block Accommodations', 'motopress-hotel-booking' ),
		);

		$currentTab = $this->getCurrentTab();

		?>
		<div class="wrap">
			<h1 class="nav-tab-wrapper">
				<?php
				foreach ( $tabs as $tab => $title ) {
					if ( $tab === $currentTab ) {
						echo '<span class="nav-tab nav-tab-active">', esc_html( $title ), '</span>';
					} else {
						$urlArgs = ( $tab === self::TAB_RESERVATION_RULES ) ? array() : array( 'tab' => $tab );
						$tabUrl  = $this->getUrl( $urlArgs );

						echo '<a href="' . esc_url( $tabUrl ) . '" class="nav-tab">', esc_html( $title ), '</a>';
					}
				}
				?>
			</h1>

			<hr class="wp-header-end">

			<?php
			switch( $this->getCurrentTab() ) {
				case self::TAB_RESERVATION_RULES: $this->renderReservationRules(); break;
				case self::TAB_BLOCKS:            $this->renderBlocks();           break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * @access private
	 */
	public function showNotices(): void {
		if ( ! $this->isCurrentPage() ) {
			return;
		}

		$currentTab = $this->getCurrentTab();

		if ( $currentTab === self::TAB_RESERVATION_RULES ) {
			if ( isset( $_POST['save'] ) ) {
				// phpcs:ignore -- HTML content
				echo mphb_tmpl_admin_notice( __( 'Booking rules saved.', 'motopress-hotel-booking' ) );
			}

		} elseif ( $currentTab === self::TAB_BLOCKS ) {
			$this->blocksList->display_notices();
		}
	}

	protected function getMenuTitle() {
		return __( 'Booking Rules', 'motopress-hotel-booking' );
	}

	protected function getPageTitle() {
		return __( 'Booking Rules', 'motopress-hotel-booking' );
	}

	private function createFields(): void {
		// Load room types only on default language
		MPHB()->translation()->setupDefaultLanguage();

		$roomTypes = MPHB()->getRoomTypePersistence()->getIdTitleList(
			$atts = array(),
			$extend = array( 0 => __( 'All', 'motopress-hotel-booking' ) )
		);

		MPHB()->translation()->restoreLanguage();

		$seasons = MPHB()->getSeasonPersistence()->getIdTitleList(
			$atts = array(),
			$extend = array( 0 => __( 'All', 'motopress-hotel-booking' ) )
		);

		$daysOfWeek = DateUtils::getDaysList();

		// Consider first day settings: move first day to the top of the list
		$startDay = MPHB()->settings()->dateTime()->getFirstDay();

		if ( $startDay > 0 ) {
			$startPart  = array_slice( $daysOfWeek, $startDay, 7 - $startDay, true );
			$endPart    = array_slice( $daysOfWeek, 0, $startDay, true );
			$daysOfWeek = array_replace( $startPart, $endPart );
		}

		$this->fields = array();

		$this->fields['mphb_check_in_days'] = FieldFactory::create(
			'mphb_check_in_days',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Check-in days', 'motopress-hotel-booking' ),
				'empty_label' => __( 'Guests can check in any day.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'check_in_days',
						array(
							'type'    => 'multiple-checkbox',
							'label'   => __( 'Days', 'motopress-hotel-booking' ),
							'default' => range( 0, 6 ),
							'list'    => $daysOfWeek,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_check_in_days', array() )
		);

		$this->fields['mphb_check_out_days'] = FieldFactory::create(
			'mphb_check_out_days',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Check-out days', 'motopress-hotel-booking' ),
				'empty_label' => __( 'Guests can check out any day.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'check_out_days',
						array(
							'type'    => 'multiple-checkbox',
							'label'   => __( 'Days', 'motopress-hotel-booking' ),
							'default' => range( 0, 6 ),
							'list'    => $daysOfWeek,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_check_out_days', array() )
		);

		$this->fields['mphb_min_stay_length'] = FieldFactory::create(
			'mphb_min_stay_length',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Minimum stay', 'motopress-hotel-booking' ),
				'empty_label' => __( 'There are no minimum stay rules.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'min_stay_length',
						array(
							'type'        => 'number',
							'label'       => __( 'Minimum stay', 'motopress-hotel-booking' ),
							'inner_label' => __( 'nights', 'motopress-hotel-booking' ),
							'default'     => 1,
							'min'         => 1,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_min_stay_length', array() )
		);

		$this->fields['mphb_max_stay_length'] = FieldFactory::create(
			'mphb_max_stay_length',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Maximum stay', 'motopress-hotel-booking' ),
				'empty_label' => __( 'There are no maximum stay rules.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'max_stay_length',
						array(
							'type'        => 'number',
							'label'       => __( 'Maximum stay', 'motopress-hotel-booking' ),
							'inner_label' => __( 'nights', 'motopress-hotel-booking' ),
							'default'     => 15,
							'min'         => 1,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_max_stay_length', array() )
		);

		$this->fields['mphb_min_advance_reservation'] = FieldFactory::create(
			'mphb_min_advance_reservation',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Minimum advance reservation', 'motopress-hotel-booking' ),
				'empty_label' => __( 'There are no minimum advance reservation rules.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'min_advance_reservation',
						array(
							'type'        => 'number',
							'label'       => __( 'Minimum advance reservation', 'motopress-hotel-booking' ),
							'inner_label' => __( 'nights', 'motopress-hotel-booking' ),
							'default'     => 0,
							'min'         => 0,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_min_advance_reservation', array() )
		);

		$this->fields['mphb_max_advance_reservation'] = FieldFactory::create(
			'mphb_max_advance_reservation',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Maximum advance reservation', 'motopress-hotel-booking' ),
				'empty_label' => __( 'There are no maximum advance reservation rules.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'max_advance_reservation',
						array(
							'type'        => 'number',
							'label'       => __( 'Maximum advance reservation', 'motopress-hotel-booking' ),
							'inner_label' => __( 'nights', 'motopress-hotel-booking' ),
							'default'     => 0,
							'min'         => 0,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_max_advance_reservation', array() )
		);

		$this->fields['mphb_buffer_days'] = FieldFactory::create(
			'mphb_buffer_days',
			array(
				'type'        => 'rules-list',
				'label'       => __( 'Booking buffer', 'motopress-hotel-booking' ),
				'empty_label' => __( 'There are no booking buffer rules.', 'motopress-hotel-booking' ),
				'add_label'   => __( 'Add rule', 'motopress-hotel-booking' ),
				'add_anchor'  => true,
				'sortable'    => true,
				'default'     => array(),
				'fields'      => array(
					FieldFactory::create(
						'buffer_days',
						array(
							'type'        => 'number',
							'label'       => __( 'Booking buffer', 'motopress-hotel-booking' ),
							'inner_label' => __( 'nights', 'motopress-hotel-booking' ),
							'default'     => 0,
							'min'         => 0,
						)
					),
					FieldFactory::create(
						'room_type_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Accommodations', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $roomTypes,
						)
					),
					FieldFactory::create(
						'season_ids',
						array(
							'type'      => 'multiple-checkbox',
							'label'     => __( 'Seasons', 'motopress-hotel-booking' ),
							'all_value' => 0,
							'default'   => array(),
							'list'      => $seasons,
						)
					),
				),
			),
			get_option( 'mphb_buffer_days', array() )
		);
	}

	protected function getCurrentTab() {
		if ( ! isset( $_GET['tab'] ) ) {
			return self::TAB_RESERVATION_RULES;
		}

		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );

		if ( in_array( $tab, self::TABS ) ) {
			return $tab;
		} else {
			return self::TAB_RESERVATION_RULES;
		}
	}

	private function getField( string $name ): ?InputField {
		$fields = $this->getFields();

		return $fields[ $name ] ?? null;
	}

	/**
	 * @return InputField[]
	 */
	private function getFields(): array {
		if ( is_null( $this->fields ) ) {
			$this->createFields();
		}

		return $this->fields;
	}

	private function renderBlocks(): void {
		$this->blocksList->prepare_items();

		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		echo '<form action="" id="' . sanitize_key( $this->blocksList->get_plural() ), '-filter" method="POST">';
			// Make sure to return to our current page
			echo '<input name="page" type="hidden" value="' . esc_attr( $page ) . '">';

			$this->blocksList->display();
		echo '</form>';
	}

	private function renderReservationRules(): void {
		?>
		<form action="" autocomplete="off" method="POST">
			<?php
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD_NAME );

			$fields  = $this->getFields();
			$lastKey = array_key_last( $fields );

			foreach ( $fields as $name => $field ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $field->render();

				if ( $name !== $lastKey ) {
					echo '<br>';
					echo '<hr>';
				}
			}
			?>

			<p class="submit">
				<input class="button button-primary" id="publish" name="save" type="submit" value="<?php esc_attr_e( 'Save Changes', 'motopress-hotel-booking' ); ?>">
			</p>
		</form>
		<?php
	}

	private function sanitizeField( string $option, $value ) {
		$value = wp_unslash( $value );

		$field = $this->getField( $option );

		if ( $field !== null ) {
			$value = $field->sanitize( $value );
		}

		return $value;
	}

	private function saveField( string $option, $value ) {
		$field = $this->getField( $option );

		if ( $field !== null ) {
			$field->setValue( $value );
		}

		update_option( $option, $value, $autoload = false );
	}

	/**
	 * Build reservation rules and prepare season priorities.
	 */
	private function saveReservationRules(): void {
		$fields = array(
			'mphb_check_in_days',
			'mphb_check_out_days',
			'mphb_min_stay_length',
			'mphb_max_stay_length',
			'mphb_min_advance_reservation',
			'mphb_max_advance_reservation',
			'mphb_buffer_days',
		);

		foreach ( $fields as $fieldName ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$rules = ! empty( $_POST[ $fieldName ] ) ? $_POST[ $fieldName ] : array();

			// Use array_values() to remove custom indexes
			$rules = $this->sanitizeField( $fieldName, array_values( $rules ) );

			// All values are numbers, so convert all strings in the array into numbers
			array_walk_recursive( $rules, function ( &$value ) {
				$value = (int) $value;
			} );

			$this->saveField( $fieldName, $rules );
		}
	}
}
