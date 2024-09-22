<?php

namespace MPHB\Admin\MenuPages;

use MPHB\BookingsCalendar;

class CalendarMenuPage extends AbstractMenuPage {

	private $calendar;

	public function addActions() {
		parent::addActions();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminScripts' ), 15 );
	}

	public function setupCalendar() {
		$this->calendar = new BookingsCalendar();
	}

	public function enqueueAdminScripts() {
		if ( $this->isCurrentPage() ) {
			MPHB()->getAdminScriptManager()->enqueue();
		}
	}

	public function render() {


		$this->addTitleAction( __( 'New Booking', 'motopress-hotel-booking' ), '#', array( 'class' => 'button-disabled', 'after' => mphb_upgrade_to_premium_message() ) );

		$this->setupCalendar();
		?>
		<div class="wrap">
			<h1 class="mphb-booking-calendar-title wp-heading-inline"><?php esc_html_e( 'Booking Calendar', 'motopress-hotel-booking' ); ?></h1>
			<?php
			$this->calendar->render();
			?>
		</div>
		<?php
	}

	public function onLoad() {
		if ( ! BookingsCalendar::hasEnoughFilterData() ) {

			$redirectToCustomPeriod = add_query_arg(
				array(
					'page'   => $this->getName(),
					'period' => MPHB()->settings()->main()->getDefaultCalendarPeriod(),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirectToCustomPeriod );
		}
	}

	protected function getMenuTitle() {
		return __( 'Calendar', 'motopress-hotel-booking' );
	}

	protected function getPageTitle() {
		return __( 'Booking Calendar', 'motopress-hotel-booking' );
	}

}
