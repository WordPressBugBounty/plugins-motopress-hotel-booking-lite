<?php

declare(strict_types=1);

namespace MPHB\Admin;

use MPHB\Utils\{ DateUtils, ParseUtils };

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * @see https://developer.wordpress.org/reference/classes/wp_list_table/
 * @see MenuPages\BookingRulesMenuPage
 */
class BlocksListTable extends \WP_List_Table {
	private const POSTS_PER_PAGE   = 20;
	private const SORTABLE_COLUMNS = array( 'block_id', 'date_from', 'date_to', 'ID' );
	private const SUPPORTS_AJAX    = true;

	private string $order = 'desc';

	private string $orderBy = 'date_from';

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'block',
				'plural'   => 'blocks',
				'ajax'     => self::SUPPORTS_AJAX,
				'screen'   => null, // Hook name used to determine the current
				                    // screen. Default null.
			)
		);

		if ( isset( $_REQUEST['order'] ) ) {
			$order = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) );

			if ( in_array( $order, array( 'asc', 'desc' ) ) ) {
				$this->order = $order;
			}
		}

		if ( isset( $_REQUEST['orderby'] ) ) {
			$orderby = sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) );

			if ( in_array( $orderby, self::SORTABLE_COLUMNS ) ) {
				$this->orderBy = $orderby;
			}
		}
	}

	/**
	 * Checks the current user's permissions
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_options' );
	}

	public function display_notices(): void {
		if ( isset( $_REQUEST['deleted'] ) ) {
			$deleted = ParseUtils::parseInt( $_REQUEST['deleted'], 0 );

			if ( $deleted > 0 ) {
				// phpcs:ignore -- HTML content
				echo mphb_tmpl_admin_notice(
					sprintf(
						_n( '%s block deleted.', '%s blocks deleted.', $deleted, 'motopress-hotel-booking' ),
						$deleted
					)
				);
			}
		}
	}

	/**
	 * Required to dictate the table's columns and titles.
	 *
	 * @return array An associative array <code>[ %slug% => %Title% ]</code>.
	 */
	public function get_columns() {
		$columns = array(
			// Note: WordPress will properly handle only "cb" checkboxes for
			// bulk actions
			'cb'                 => '<input type="checkbox">',
			'accommodation_type' => __( 'Accommodation Type', 'motopress-hotel-booking' ),
			'accommodation'      => __( 'Accommodation', 'motopress-hotel-booking' ),
			'date_from'          => __( 'From', 'motopress-hotel-booking' ),
			'date_to'            => __( 'Till', 'motopress-hotel-booking' ),
			'restrictions'       => __( 'Restriction', 'motopress-hotel-booking' )
				. ' '
				. mphb_help_tip(
					'<p>' . esc_html__( 'Not check-in rule marks the date as unavailable for check-in.', 'motopress-hotel-booking' ) . '</p>' .
						'<p>' . esc_html__( 'Not check-out rule marks the date as unavailable for check-out.', 'motopress-hotel-booking' ) . '</p>' .
						'<p>' . esc_html__( 'Not stay-in rule displays the date as blocked. This date is unavailable for check-in and check-out on the next date.', 'motopress-hotel-booking' ) . '</p>' .
						'<p>' . esc_html__( 'Not stay-in with Not check-out rules completely block the selected date, additionally displaying the previous date as unavailable for check-in.', 'motopress-hotel-booking' ) . '</p>',
					true
				),
			'comment'            => __( 'Comment', 'motopress-hotel-booking' ),
		);

		return $columns;
	}

	/**
	 * Just a getter. Not a method of WP_List_Table.
	 */
	public function get_plural() {
		return $this->_args['plural'];
	}

	/**
	 * This required method is where you prepare your data for display. This
	 * method will usually be used to query the database, sort and filter the
	 * data, and generally get it ready to be displayed. At a minimum, we should
	 * set $this->items and $this->set_pagination_args().
	 */
	public function prepare_items() {
		// The $_column_headers property takes an array to be used by class for
		// column headers
		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
//			'accommodation_type' // Primary column (string). First non-"cb"
			                     // column by default.
		);

		// Query items
		$blocks = MPHB()->getBlocksRepository()->queryItems(
			array(
				'orderby'  => array( $this->orderBy => $this->order ),
				'page'     => $this->get_pagenum(),
				'per_page' => self::POSTS_PER_PAGE,
			)
		);

		$this->items = array_values( $blocks );
		$itemsTotal  = MPHB()->getBlocksRepository()->getTotalCount();

		$this->set_pagination_args(
			array(
				'order'       => $this->order,
				'orderby'     => $this->orderBy,
				'per_page'    => self::POSTS_PER_PAGE,
				'total_items' => $itemsTotal,
				'total_pages' => ceil( $itemsTotal / self::POSTS_PER_PAGE ),
			)
		);
	}

	public function process_actions(): void {
		$this->process_action();
		$this->process_bulk_action();
	}

	public function _js_vars() {
		$data = array(
			'items'       => $this->items,
			'items_total' => $this->_pagination_args['total_items'],
			'nonce'       => array(
				'delete' => wp_create_nonce( 'delete' ),
			),
			'room_types'  => MPHB()->getRoomTypeRepository()->getIdTitleList(
				array(
					'order'   => 'ASC',
					'orderby' => 'ID',
				)
			),
			'rooms'       => array(), // [ Room type ID => [ Room ID => Room title ] ]
		);

		foreach ( $this->items as $item ) {
			$roomTypeId = $item['room_type_id'];

			if ( $roomTypeId !== 0 && ! array_key_exists( $roomTypeId, $data['rooms'] ) ) {
				$data['rooms'][ $roomTypeId ] = MPHB()->getRoomRepository()->getIdTitleListForRoomType( $roomTypeId );
			}
		}

		printf(
			'<script type="text/javascript">blocks_list_table_data = %s;</script>\n',
			wp_json_encode( $data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
		);

		parent::_js_vars(); // Print list_args variable
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_accommodation( $item ) {
		if ( $item['room_id'] === 0 ) {
			return __( 'All', 'motopress-hotel-booking' );
		} else {
			$room = mphb_rooms_facade()->getRoomById( $item['room_id'] );

			if ( $room !== null ) {
				return $room->getTitle();
			} else {
				return '#' . $item['room_id'];
			}
		}
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_accommodation_type( $item ) {
		$output = '<label>';

		if ( $item['room_type_id'] === 0 ) {
			$output .= __( 'All', 'motopress-hotel-booking' );
		} else {
			$roomType = mphb_rooms_facade()->getRoomTypeById( $item['room_type_id'] );

			if ( $roomType !== null ) {
				$output .= $roomType->getTitle();
			} else {
				$output .= '#' . $item['room_type_id'];
			}
		}

		$output .= '</label>';

		$rowActions = array(
			'editinline' => sprintf(
				'<button aria-expanded="false" aria-label="%s" class="button-link editinline" type="button">%s</button>',
				esc_attr(
					// Translators: %s: Block ID.
					sprintf( __( 'Quick edit block #%s inline', 'motopress-hotel-booking' ), $item['block_id'] )
				),
				esc_html__( 'Quick Edit', 'motopress-hotel-booking' )
			),
			'delete'     => sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					add_query_arg(
						array(
							// Not to be confused with bulk "action"
							'action1' => 'delete',
							'id'      => $item['block_id'],
						)
					),
					'delete'
				),
				esc_html__( 'Delete', 'motopress-hotel-booking' )
			),
		);

		return $output . ' ' . $this->row_actions( $rowActions );
	}

	/**
	 * Required if displaying checkboxes or using bulk actions. The "cb" column
	 * is given special treatment when columns are processed. It always needs to
	 * have it's own method.
	 *
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_cb( $item ) {
		return '<input name="ids[]" type="checkbox" value="' . esc_attr( $item['block_id'] ) . '">';
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_comment( $item ) {
		return $item['comment'] !== '' ? $item['comment'] : mphb_tmpl_placeholder();
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_date_from( $item ) {
		return DateUtils::convertDateFormat( $item['date_from'] );
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_date_to( $item ) {
		return DateUtils::convertDateFormat( $item['date_to'] );
	}

	/**
	 * This method is called when the parent class can't find a method
	 * specifically build for a given column.
	 *
	 * @param array $item A singular item (one full row's worth of data).
	 * @param string $column_name The name/slug of the column to be processed.
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_default( $item, $column_name ) {
		return '<span aria-hidden="true">' . mphb_tmpl_placeholder() . '</span>';
	}

	/**
	 * @param array $item A singular item (one full row's worth of data).
	 * @return string Text or HTML to be placed inside the column &lt;td&gt;.
	 */
	protected function column_restrictions( $item ) {
		$restrictions = array();

		if ( $item['not_check_in'] ) {
			$restrictions[] = __( 'Not check-in', 'motopress-hotel-booking' );
		}

		if ( $item['not_check_out'] ) {
			$restrictions[] = __( 'Not check-out', 'motopress-hotel-booking' );
		}

		if ( $item['not_stay_in'] ) {
			$restrictions[] = __( 'Not stay-in', 'motopress-hotel-booking' );
		}

		$count = count( $restrictions );

		if ( $count === 0 ) {
			return __( 'None', 'motopress-hotel-booking' );
		} elseif ( $count === 3 ) {
			return __( 'All', 'motopress-hotel-booking' );
		} else {
			return implode( ', ', $restrictions );
		}
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 *
	 * @param string $which "top"|"bottom". The location of the navigation.
	 */
	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions <?php echo esc_attr( $which ); ?>">
			<button class="button button-primary add-rule" type="button"><?php esc_html_e( 'Add rule', 'motopress-hotel-booking' ); ?></button>
		</div>
		<?php
	}

	/**
	 * @return array <code>[ Slug => Title ]</code>
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'motopress-hotel-booking' ),
		);

		return $actions;
	}

	/**
	 * @return array
	 */
	protected function get_hidden_columns() {
		// See WP_List_Table::get_column_info()
		return get_hidden_columns( $this->screen );
	}

	/**
	 * @see https://developer.wordpress.org/reference/classes/wp_list_table/get_sortable_columns/
	 *
	 * @return array <code>[
	 *     Column name => [
	 *         orderby => string,
	 *         order   => string ("asc"|"desc") or bool (is descending),
	 *         ...
	 *     ]
	 * ]</code>
	 */
	protected function get_sortable_columns() {
		$dateFromOrder = ( $this->orderBy === 'date_from' ) ? $this->order : false;
		$dateToOrder   = ( $this->orderBy === 'date_to' )   ? $this->order : false;

		$sortableColumns = array(
			// Also change the SORTABLE_COLUMNS constant
			'date_from' => array( 'date_from', $dateFromOrder ),
			'date_to'   => array( 'date_to', $dateToOrder ),
		);

		return $sortableColumns;
	}

	/**
	 * @return string[]
	 */
	protected function get_table_classes() {
		$classes = parent::get_table_classes();
		$classes[] = 'mphb-blocks-list-table';

		return $classes;
	}

	private function process_action(): void {
		if ( ! isset( $_GET['action1'] ) ) {
			return;
		}

		// Verify the nonce
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'motopress-hotel-booking' ) );
		}

		$nonce  = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		$action = sanitize_text_field( wp_unslash( $_GET['action1'] ) );

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'motopress-hotel-booking' ) );
		}

		$id = isset( $_GET['id'] ) ? ParseUtils::parseId( $_GET['id'] ) : false;

		/** @var int|false $deleted */
		$deleted = false;

		if ( $id !== 0 ) {
			switch( $action ) {
				case 'delete':
					$deleted = (int) MPHB()->getBlocksRepository()->deleteItem( $id );
					break;
			}
		}

		$redirectUrl = remove_query_arg( array( 'action1', 'id', '_wpnonce' ) );

		if ( $deleted !== false ) {
			$redirectUrl = add_query_arg( 'deleted', $deleted, $redirectUrl );
		}

		$isRedirecting = wp_redirect( $redirectUrl );

		if ( $isRedirecting ) {
			exit;
		}
	}

	private function process_bulk_action(): void {
		/** @var string|false $action */
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Verify the nonce
		check_admin_referer( 'bulk-' . $this->get_plural() );

		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
			? ParseUtils::parseIds( $_POST['ids'] )
			: array();

		/** @var int|false $deleted */
		$deleted = false;

		if ( ! empty( $ids ) ) {
			switch( $action ) {
				case 'delete':
					$isDeleted = MPHB()->getBlocksRepository()->deleteItems( $ids );
					$deleted   = ( $isDeleted ) ? count( $ids ) : 0;
					break;
			}
		}

		$redirectUrl = mphb_get_current_page_url();

		if ( $deleted !== false ) {
			$redirectUrl = add_query_arg( 'deleted', $deleted, $redirectUrl );
		}

		$isRedirecting = wp_redirect( $redirectUrl );

		if ( $isRedirecting ) {
			exit;
		}
	}
}
