<?php
/**
 * @var array{
 *     start_date: string ('' by default, format: YYYY-MM-DD),
 *     accommodation_type_ids: string (comma-separated list of room type ids, '' by default),
 *     className: string,
 * } $attributes Block attributes
 * @var string $content The default block content.
 * @var WP_Block $block The block instance.
 */

use MPHB\Shortcodes\GroupAvailabilityCalendarShortcode;

// phpcs:disable
echo '<div ' . get_block_wrapper_attributes() . '>';

$shortcode = MPHB()->getShortcodes()->getGroupAvailabilityCalendar();
echo $shortcode->render(
	array(
		GroupAvailabilityCalendarShortcode::SHORTCODE_ATTRIBUTE_START_DATE => $attributes['start_date'] ?? '',
		GroupAvailabilityCalendarShortcode::SHORTCODE_ATTRIBUTE_ACCOMMODATION_TYPES => $attributes['accommodation_type_ids'] ?? array(),
	),
	'',
	$shortcode->getName()
);

echo '</div>';
// phpcs:enable
