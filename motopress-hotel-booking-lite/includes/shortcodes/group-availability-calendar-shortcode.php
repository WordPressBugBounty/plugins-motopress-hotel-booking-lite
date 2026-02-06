<?php

namespace MPHB\Shortcodes;

defined( 'ABSPATH' ) || exit;

class GroupAvailabilityCalendarShortcode extends AbstractShortcode {

	public const SHORTCODE_ATTRIBUTE_START_DATE          = 'start_date';
	public const SHORTCODE_ATTRIBUTE_ACCOMMODATION_TYPES = 'accommodation_types';
	public const SHORTCODE_ATTRIBUTE_CLASS_NAME          = 'class';

	protected $name = 'mphb_group_availability_calendar';

	/**
	 * @param array  $atts [
	 *                   'start_date' => string (Y-m-d)
	 *                   'accommodation_types' => string (coma separated list of accommodation type ids or slugs)
	 *                   'class' => string
	 *               ]
	 * @param null   $content
	 * @param string $name
	 *
	 * @return string
	 */
	public function render( $atts, $content, $shortcodeName ) {

		$atts = shortcode_atts(
			array(
				static::SHORTCODE_ATTRIBUTE_START_DATE          => '', // Y-m-d
				static::SHORTCODE_ATTRIBUTE_ACCOMMODATION_TYPES => '', // ids or slugs
				static::SHORTCODE_ATTRIBUTE_CLASS_NAME          => '',
			),
			$atts,
			$shortcodeName
		);

		$startDate                   = new \DateTime( 'now', wp_timezone() );
		$accommodationTypeIdsOrSlugs = array();
		$className                   = empty( $atts[ static::SHORTCODE_ATTRIBUTE_CLASS_NAME ] ) ?
			'' :
			trim( $atts[ static::SHORTCODE_ATTRIBUTE_CLASS_NAME ] );

		try {

			$startDateFromAtts = ( new \DateTime(
				$atts[ static::SHORTCODE_ATTRIBUTE_START_DATE ],
				wp_timezone()
			) )->
			setTime( 0, 0, 0 );

			if ( $startDateFromAtts > $startDate ) {
				$startDate = $startDateFromAtts;
			}

		} catch ( \Throwable $e ) {
			// ignore error
		}
		
		
		$accommodationTypeIdsOrSlugs = array_filter(
			explode( ',', $atts[ static::SHORTCODE_ATTRIBUTE_ACCOMMODATION_TYPES ] )
		);

		if ( empty( $accommodationTypeIdsOrSlugs ) ) {

			$accommodationTypes = mphb_rooms_facade()->getAllRoomTypes();

		} else {

			$accommodationTypes = mphb_rooms_facade()->findRoomTypesByIdsOrSlugs( $accommodationTypeIdsOrSlugs );
		}

		$javaScriptPropsJson = array(
			'startDate' => $atts[ static::SHORTCODE_ATTRIBUTE_START_DATE ] ? $startDate->format( 'Y-m-d' ) : '',
			'roomTypes' => array(),
		);

		ob_start();

		do_action( 'mphb_sc_before_group_availability_calendar' );

		$unique_id = uniqid( 'mphb-group-availability-calendar-' );
		?>

		<div <?php echo 'id="' . esc_attr( $unique_id ) . '"'; ?> class="mphb-group-availability-calendar <?php echo esc_attr( $className ); ?>">
			<div class="mphb-placeholder is-navigation" style="width: 200px;">&nbsp;</div>
			<div class="mphb-placeholder is-dates" style="width: 100%;">&nbsp;</div>
			<?php

			usort(
				$accommodationTypes,
				function( $a, $b ) {
					return $a->calcTotalCapacity() <=> $b->calcTotalCapacity();
				}
			);

			foreach ( $accommodationTypes as $accommodationType ) {

				$javaScriptPropsJson[ 'roomTypes' ][] = array(
					'id'      => $accommodationType->getId(),
					'title'   => $accommodationType->getTitle(),
					'pageUrl' => $accommodationType->getLink(),
				);

				echo '<div class="mphb-placeholder is-accommodation-title" style="width: 200px;">&nbsp;</div>';
				echo '<div class="mphb-placeholder is-accommodation-dates" style="width: 100%;">&nbsp;</div>';
			}
			?>
		</div>
		<script type="application/json" id="<?php echo esc_attr( $unique_id ); ?>_props">
			<?php echo wp_json_encode( $javaScriptPropsJson, JSON_UNESCAPED_UNICODE ); /** phpcs:ignore */ ?>
		</script>

		<?php

		do_action( 'mphb_sc_after_group_availability_calendar' );

		$content = ob_get_clean();

		return $content;
	}
}
