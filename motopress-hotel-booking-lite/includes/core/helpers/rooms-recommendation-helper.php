<?php

namespace MPHB\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoomsRecommendationHelper {
	/**
	 * @param array $availableRooms [ room_type_original_id (int) => available_rooms_count (int) ]
	 * @return array [ room_type_original_id (int) => rooms_count (int), ... ]
	 */
	public static function getRecommendedRoomsCombination(
		array $availableRooms,
		int $requestedAdultsCount,
		int $requestedChildrenCount = 0
	): array {

		if ( empty( $availableRooms ) ||
			( $requestedAdultsCount <= 0 && $requestedChildrenCount <= 0 )
		) {
			return array();
		}

		$availableRoomsData = array();

		foreach ( $availableRooms as $roomTypeOriginalId => $availableRoomsCount ) {
			$roomType = mphb_rooms_facade()->getRoomTypeById( $roomTypeOriginalId );

			if ( ! $roomType ) {
				continue;
			}

			$roomTotalCapacity = $roomType->calcTotalCapacity();

			if ( 0 < $roomTotalCapacity &&
				( 0 < $roomType->getAdultsCapacity() || 0 < $roomType->getChildrenCapacity() )
			) {

				$roomMaxAdultsCount      = min( $roomType->getAdultsCapacity(), $roomTotalCapacity );
				$roomMaxChildrenCount    = min( $roomType->getChildrenCapacity(), $roomTotalCapacity );
				$roomMaxTotalGuestsCount = min( $roomTotalCapacity, $roomType->getAdultsCapacity() + $roomType->getChildrenCapacity() ); // Fix absurd big number for total capacity

				$roomMinAdultsCount = max(
					0,
					min(
						( $roomMaxTotalGuestsCount - $roomMaxChildrenCount ),
						$roomMaxAdultsCount
					)
				);
	
				$roomMinChildrenCount = max(
					0,
					min(
						( $roomMaxTotalGuestsCount - $roomMaxAdultsCount ),
						$roomMaxChildrenCount
					)
				);

				$minRoomsCountForAdults      = 0 < $roomMaxAdultsCount ? (int) ceil( $requestedAdultsCount / $roomMaxAdultsCount ) : 0;
				$minRoomsCountForChildren    = 0 < $roomMaxChildrenCount ? (int) ceil( $requestedChildrenCount / $roomMaxChildrenCount ) : 0;
				$minRoomsCountForTotalGuests = 0 < $roomMaxTotalGuestsCount ? (int) ceil( ( $requestedAdultsCount + $requestedChildrenCount ) / $roomMaxTotalGuestsCount ) : 0;
		
				// reduce available rooms count to reduce count of combinations
				$maxRequiredRoomsCount = min(
					$availableRoomsCount,
					max( $minRoomsCountForAdults, $minRoomsCountForChildren, $minRoomsCountForTotalGuests )
				);

				$availableRoomsData[ $roomTypeOriginalId ] = array(
					'min_adults_count'       => $roomMinAdultsCount,   // Equal to max_* couterparts, when
					'min_children_count'     => $roomMinChildrenCount, // total capacity = adults + children
					'max_adults_count'       => $roomMaxAdultsCount,
					'max_children_count'     => $roomMaxChildrenCount,
					'max_total_guests_count' => $roomMaxTotalGuestsCount,
					'available_rooms_count'  => $maxRequiredRoomsCount,
				);
			}
		}

		// Sort $availableRoomsData from biggest to lowest capacity (preserving indexes)
		uasort(
			$availableRoomsData,
			function ( $a, $b ) {

				// sort by adults count from biggest to lowest
				if ( $b['max_adults_count'] !== $a['max_adults_count'] ) {
					return $b['max_adults_count'] <=> $a['max_adults_count'];
				}

				// sort by children count from biggest to lowest
				if ( $b['max_children_count'] !== $a['max_children_count'] ) {
					return $b['max_children_count'] <=> $a['max_children_count'];
				}

				return 0;
			}
		);

		// we need to have empty combination to start add each new room type as separate combination
		$foundCombinations = array( 0 => array() );

		foreach ( $availableRoomsData as $roomTypeOriginalId => $roomsData ) {

			for ( $roomsCount = 1; $roomsCount <= $roomsData['available_rooms_count']; $roomsCount++ ) {

				$roomMinAdultsCount   = $roomsData['min_adults_count'];
				$roomMaxAdultsCount   = $roomsData['max_adults_count'];
				$roomMinChildrenCount = $roomsData['min_children_count'];
				$roomMaxChildrenCount = $roomsData['max_children_count'];

				for ( $roomAdultsCount = $roomMinAdultsCount; $roomAdultsCount <= $roomMaxAdultsCount; $roomAdultsCount++ ) {

					for ( $roomChildrenCount = $roomMinChildrenCount; $roomChildrenCount <= $roomMaxChildrenCount; $roomChildrenCount++ ) {

						if ( $roomsData['max_total_guests_count'] !== ( $roomAdultsCount + $roomChildrenCount ) ) {
							continue;
						}

						$combinationKeys = array_keys( $foundCombinations );

						foreach ( $combinationKeys as $combinationKey ) {

							$combinationAdultsCount          = 0;
							$combinationChildrenCount        = 0;
							$combinationMaxTotalGuestsCount  = 0;
							$combinationRoomsCountsByRoomTypeIds = array();

							if ( isset( $foundCombinations[ $combinationKey ]['adults_count'] ) ) {

								$foundCombination                    = $foundCombinations[ $combinationKey ];
								$combinationAdultsCount              = $foundCombination['adults_count'];
								$combinationChildrenCount            = $foundCombination['children_count'];
								$combinationMaxTotalGuestsCount      = $foundCombination['max_total_guests_count'];
								$combinationRoomsCountsByRoomTypeIds = $foundCombination['rooms_counts_by_room_type_ids'];
							}

							if ( $combinationAdultsCount < $requestedAdultsCount ||
								$combinationChildrenCount < $requestedChildrenCount
							) {

								$newCombinationRoomsCountsByRoomTypeIds = $combinationRoomsCountsByRoomTypeIds;

								if ( isset( $newCombinationRoomsCountsByRoomTypeIds[ $roomTypeOriginalId ] ) ) {
									$newCombinationRoomsCountsByRoomTypeIds[ $roomTypeOriginalId ]++;
								} else {
									$newCombinationRoomsCountsByRoomTypeIds[ $roomTypeOriginalId ] = 1;
								}

								// make sure combination does not have more rooms than available
								if ( $availableRoomsData[ $roomTypeOriginalId ]['available_rooms_count'] < $newCombinationRoomsCountsByRoomTypeIds[ $roomTypeOriginalId ] ) {
									continue;
								}

								$newCombinationAdultsCount = $combinationAdultsCount + $roomAdultsCount;
								$newCombinationChildrenCount = $combinationChildrenCount + $roomChildrenCount;
								$newCombinationMaxTotalGuestsCount = $combinationMaxTotalGuestsCount + $roomsData['max_total_guests_count'];
								$newCombinationNotFilledGuestsCount = max(
									0,
									$newCombinationMaxTotalGuestsCount
										- min( $newCombinationAdultsCount, $requestedAdultsCount )
										- min( $newCombinationChildrenCount, $requestedChildrenCount)
								);

								// generate combination key
								$newCombinationRoomsForKey = $newCombinationRoomsCountsByRoomTypeIds;
								ksort( $newCombinationRoomsForKey );

								$newCombinationKey = '';

								foreach ( $newCombinationRoomsForKey as $roomTypeId => $combinationRoomsCount ) {
									$newCombinationKey .= $roomTypeId . 'x' . $combinationRoomsCount . ',';
								}

								$newCombinationKey = rtrim( $newCombinationKey, ',' )
									. '-' . $newCombinationAdultsCount . '-' . $newCombinationChildrenCount;

								if ( ! isset( $foundCombinations[ $newCombinationKey ] ) ) {

									$foundCombinations[ $newCombinationKey ] = array(
										'adults_count'                  => $newCombinationAdultsCount,
										'children_count'                => $newCombinationChildrenCount,
										'max_total_guests_count'        => $newCombinationMaxTotalGuestsCount,
										'not_filled_guests_count'       => $newCombinationNotFilledGuestsCount,
										'rooms_counts_by_room_type_ids' => $newCombinationRoomsCountsByRoomTypeIds,
									);
								}
							}
						} // foreach combination
					} // for children
				} // for adults
			} // for rooms count
		} // for room type

		// remove first empty combination
		unset( $foundCombinations[ 0 ] );

		$recommendedCombination                     = array();
		$recommendedCombinationAdultsCount          = 0;
		$recommendedCombinationChildrenCount        = 0;
		$recommendedCombinationNotFilledGuestsCount = 0;
		$recommendedCombinationRoomsCount           = 0;

		foreach ( $foundCombinations as $combination ) {

			$combinationRoomsCount = array_sum( $combination['rooms_counts_by_room_type_ids'] );

			if (
				(
					1 === count( $combination['rooms_counts_by_room_type_ids'] )
					|| ! MPHB()->settings()->main()->isRecommendAndSearchSingleRoomTypeForRequestedGuestsCount()
				)
				&& (
					// get anything
					empty( $recommendedCombination )

					// get best combination
					|| (
						$combination['adults_count'] >= $requestedAdultsCount
						&& $combination['children_count'] >= $requestedChildrenCount
						&& (
							(
								// less not_filled_guests_count
								$combination['not_filled_guests_count'] < $recommendedCombinationNotFilledGuestsCount
							)
							||
							(
								// less rooms count with max 30% not filled guests count
								$combinationRoomsCount < $recommendedCombinationRoomsCount
								&& $combination['not_filled_guests_count'] <= 0.3 * $combination['max_total_guests_count']
							)
						)
					)

					// approximate result if we have no suited one
					|| (
						(
							$recommendedCombinationAdultsCount < $requestedAdultsCount
							|| $recommendedCombinationChildrenCount < $requestedChildrenCount
						)
						&& $combination['adults_count'] >= $recommendedCombinationAdultsCount
						&& $combination['children_count'] >= $recommendedCombinationChildrenCount
					)
				)
			) {
				$recommendedCombinationAdultsCount          = $combination['adults_count'];
				$recommendedCombinationChildrenCount        = $combination['children_count'];
				$recommendedCombination                     = $combination['rooms_counts_by_room_type_ids'];
				$recommendedCombinationNotFilledGuestsCount = $combination['not_filled_guests_count'];
				$recommendedCombinationRoomsCount           = $combinationRoomsCount;
			}
		}

		return $recommendedCombination;
	}
}
