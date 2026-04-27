<?php

namespace MPHB\Repositories;

use \MPHB\Entities,
	MPHB\Utils\ValidateUtils;

class RoomTypeRepository extends AbstractPostRepository {

	protected $type = 'room_type';

	/**
	 * @param Entities\RoomType $entity
	 * @return \MPHB\Entities\WPPostData
	 */
	public function mapEntityToPostData( $entity ) {

		$postAtts = array(
			'ID'             => $entity->getId(),
			'post_status'    => $entity->getStatus(),
			'post_title'     => $entity->getTitle(),
			'post_content'   => $entity->getDescription(),
			'post_excerpt'   => $entity->getExcerpt(),
			'post_type'      => MPHB()->postTypes()->roomType()->getPostType(),
			'featured_image' => $entity->getFeaturedImageId(),
		);

		$postAtts['post_metas'] = array(
			'mphb_adults_capacity'        => $entity->getAdultsCapacity(),
			'mphb_children_capacity'      => $entity->getChildrenCapacity(),
			'mphb_total_capacity'         => $entity->getTotalCapacity(),
			'mphb_base_adults_capacity'   => $entity->getBaseAdultsCapacity(),
			'mphb_base_children_capacity' => $entity->getBaseChildrenCapacity(),
			'mphb_bed'                    => $entity->getBedType(),
			'mphb_size'                   => $entity->getSize(),
			'mphb_view'                   => $entity->getView(),
			'mphb_services'               => $entity->getServices(),

			'mphb_gh_is_include_to_google_hotels'     => $entity->isIncludeToGoogleHotels(),
			'mphb_gh_property_id'                     => $entity->getPropertyId(),
			'mphb_gh_property_title'                  => $entity->getPropertyTitle(),
			'mphb_gh_is_indoor_accommodation'         => $entity->isIndoorAccommodation(),
			'mphb_gh_is_onsite_managed'               => $entity->isOnsiteManaged(),
			'mphb_gh_is_accepting_overnight_guests'   => $entity->isAcceptingOvernightGuests(),
			'mphb_gh_is_address_publicly_listed'      => $entity->isAddressPubliclyListed(),
			'mphb_gh_property_type'                   => $entity->getPropertyType(),
			'mphb_gh_property_category'               => $entity->getPropertyCategory(),
			'mphb_gh_latitude'                        => $entity->getLatitude(),
			'mphb_gh_longitude'                       => $entity->getLongitude(),
			'mphb_gh_contacts_main_phone'             => $entity->getContactsMainPhone(),
			'mphb_gh_address_line1'                   => $entity->getAddressLine1(),
			'mphb_gh_address_line2'                   => $entity->getAddressLine2(),
			'mphb_gh_address_city'                    => $entity->getAddressCity(),
			'mphb_gh_address_province'                => $entity->getAddressProvince(),
			'mphb_gh_address_postal_code'             => $entity->getAddressPostalCode(),
			'mphb_gh_address_country_code'            => $entity->getAddressCountryCode(),
		);

		$postAtts['taxonomies'] = array(
			MPHB()->postTypes()->roomType()->getTagTaxName()      => wp_list_pluck( $entity->getTags(), 'term_id' ),
			MPHB()->postTypes()->roomType()->getCategoryTaxName() => wp_list_pluck( $entity->getCategories(), 'term_id' ),
			MPHB()->postTypes()->roomType()->getFacilityTaxName() => wp_list_pluck( $entity->getFacilities(), 'term_id' ),
		);

		foreach ( $entity->getAttributes() as $attributeName => $attributeTermIds ) {
			$attributeTaxonomy                            = mphb_attribute_taxonomy_name( $attributeName );
			$postAtts['taxonomies'][ $attributeTaxonomy ] = $attributeTermIds;
		}

		return new Entities\WPPostData( $postAtts );
	}

	function mapPostToEntity( $post ) {

		$post       = ( is_a( $post, '\WP_Post' ) ) ? $post : get_post( $post );
		$id         = $post->ID;
		$originalId = MPHB()->translation()->getOriginalId( $id, MPHB()->postTypes()->roomType()->getPostType() );

		$adults = get_post_meta( $id, 'mphb_adults_capacity', true );
		$adults = (int) ( ! empty( $adults ) ? $adults : MPHB()->settings()->main()->getMinAdults() );

		$children = get_post_meta( $id, 'mphb_children_capacity', true );
		$children = (int) ( false !== $children ? $children : MPHB()->settings()->main()->getMinChildren() );

		$total = get_post_meta( $id, 'mphb_total_capacity', true );

		if ( $total !== '' ) {
			$total = intval( $total );
		}

		$baseAdults = get_post_meta( $id, 'mphb_base_adults_capacity', true );
		$baseAdults = (int) ( ! empty( $baseAdults ) ? $baseAdults : $adults );

		$baseChildren = get_post_meta( $id, 'mphb_base_children_capacity', true );
		$baseChildren = (int) ( ! empty( $baseChildren ) ? $baseChildren : $children );

		$size = get_post_meta( $id, 'mphb_size', true );
		$size = ! empty( $size ) ? (float) $size : 0.0;

		$services = get_post_meta( $id, 'mphb_services', true );
		$services = ! empty( $services ) ? $services : array();

		$gallery = get_post_meta( $id, 'mphb_gallery', true );
		$gallery = ! empty( $gallery ) ? explode( ',', $gallery ) : array();

		$atts = array(
			'id'             => $id,
			'original_id'    => $originalId,
			'title'          => $post->post_title,
			'description'    => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'adults'         => $adults,
			'children'       => $children,
			'total_capacity' => $total,
			'base_adults'    => $baseAdults,
			'base_children'  => $baseChildren,
			'bed_type'       => get_post_meta( $id, 'mphb_bed', true ),
			'size'           => $size,
			'view'           => get_post_meta( $id, 'mphb_view', true ),
			'services_ids'   => $services,
			'image_id'       => get_post_thumbnail_id( $id ),
			'gallery_ids'    => $gallery,
			'categories'     => wp_get_post_terms( $id, MPHB()->postTypes()->roomType()->getCategoryTaxName() ),
			'tags'           => wp_get_post_terms( $id, MPHB()->postTypes()->roomType()->getTagTaxName() ),
			'facilities'     => wp_get_post_terms( $id, MPHB()->postTypes()->roomType()->getFacilityTaxName() ),
			'attributes'     => $this->getAttributes( $id ),
			'status'         => get_post_status( $originalId ),
		);

		$allPostMeta = get_post_meta( $id );

		$atts['is_include_to_google_hotels'] = ! empty( $allPostMeta['mphb_gh_is_include_to_google_hotels'] ) ?
			ValidateUtils::validateBool( $allPostMeta['mphb_gh_is_include_to_google_hotels'][0] ) :
			false;

		$atts['property_id'] = ! empty( $allPostMeta['mphb_gh_property_id'] ) ?
			$allPostMeta['mphb_gh_property_id'][0] :
			'';

		$atts['property_title'] = ! empty( $allPostMeta['mphb_gh_property_title'] ) ?
			$allPostMeta['mphb_gh_property_title'][0] :
			'';

		$atts['is_indoor_accommodation'] = ! empty( $allPostMeta['mphb_gh_is_indoor_accommodation'] ) ?
			ValidateUtils::validateBool( $allPostMeta['mphb_gh_is_indoor_accommodation'][0] ) :
			false;

		$atts['is_onsite_managed'] = ! empty( $allPostMeta['mphb_gh_is_onsite_managed'] ) ?
			ValidateUtils::validateBool( $allPostMeta['mphb_gh_is_onsite_managed'][0] ) :
			false;

		$atts['is_accepting_overnight_guests'] = ! empty( $allPostMeta['mphb_gh_is_accepting_overnight_guests'] ) ?
			ValidateUtils::validateBool( $allPostMeta['mphb_gh_is_accepting_overnight_guests'][0] ) :
			false;

		$atts['is_address_publicly_listed'] = ! empty( $allPostMeta['mphb_gh_is_address_publicly_listed'] ) ?
			ValidateUtils::validateBool( $allPostMeta['mphb_gh_is_address_publicly_listed'][0] ) :
			false;

		$atts['property_type'] = ! empty( $allPostMeta['mphb_gh_property_type'] ) ?
			$allPostMeta['mphb_gh_property_type'][0] :
			'';

		$atts['property_category'] = ! empty( $allPostMeta['mphb_gh_property_category'] ) ?
			$allPostMeta['mphb_gh_property_category'][0] :
			'';

		$atts['latitude'] = ! empty( $allPostMeta['mphb_gh_latitude'] ) ?
			(float) $allPostMeta['mphb_gh_latitude'][0] :
			0.0;

		$atts['longitude'] = ! empty( $allPostMeta['mphb_gh_longitude'] ) ?
			(float) $allPostMeta['mphb_gh_longitude'][0] :
			0.0;

		$atts['contacts_main_phone'] = ! empty( $allPostMeta['mphb_gh_contacts_main_phone'] ) ?
			$allPostMeta['mphb_gh_contacts_main_phone'][0] :
			'';

		$atts['address_line1'] = ! empty( $allPostMeta['mphb_gh_address_line1'] ) ?
			$allPostMeta['mphb_gh_address_line1'][0] :
			'';

		$atts['address_line2'] = ! empty( $allPostMeta['mphb_gh_address_line2'] ) ?
			$allPostMeta['mphb_gh_address_line2'][0] :
			'';

		$atts['address_city'] = ! empty( $allPostMeta['mphb_gh_address_city'] ) ?
			$allPostMeta['mphb_gh_address_city'][0] :
			'';

		$atts['address_province'] = ! empty( $allPostMeta['mphb_gh_address_province'] ) ?
			$allPostMeta['mphb_gh_address_province'][0] :
			'';

		$atts['address_postal_code'] = ! empty( $allPostMeta['mphb_gh_address_postal_code'] ) ?
			$allPostMeta['mphb_gh_address_postal_code'][0] :
			'';

		$atts['address_country_code'] = ! empty( $allPostMeta['mphb_gh_address_country_code'] ) ?
			$allPostMeta['mphb_gh_address_country_code'][0] :
			'';

		return new Entities\RoomType( $atts );
	}

	protected function getAttributes( $roomTypeId ) {

		global $mphbAttributes;

		$attributes = array();

		foreach ( $mphbAttributes as $attribute ) {

			$attributeName = $attribute['attributeName'];
			$taxonomyName  = $attribute['taxonomyName'];

			$terms = wp_get_post_terms( $roomTypeId, $taxonomyName );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$terms                        = array_combine( wp_list_pluck( $terms, 'term_id' ), wp_list_pluck( $terms, 'name' ) );
				$attributes[ $attributeName ] = $terms;
			}
		}

		return $attributes;
	}

	public function getIdTitleList( $atts = array() ) {
		$defaults = array(
			'fields'      => 'all',
//			'orderby'     => 'ID',
//			'order'       => 'ASC',
			'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		);

		$atts = array_merge( $defaults, $atts );

		$posts = $this->persistence->getPosts( $atts );

		$list = array();
		foreach ( $posts as $post ) {
			$list[ $post->ID ] = $post->post_title;
		}
		return $list;
	}

	/**
	 * @param int  $id
	 * @param bool $force Optional.
	 * @return Entities\RoomType
	 */
	public function findById( $id, $force = false ) {
		return parent::findById( $id, $force );
	}

	/**
	 * @param int[]|string[] $accommodationTypeIdsOrSlugs
	 * @return Entities\RoomType[]
	 */
	public function findAllByIdsOrSlugs( array $accommodationTypeIdsOrSlugs ): array {

		if ( empty( $accommodationTypeIdsOrSlugs ) ) {
			return array();
		}

		$roomTypeIds   = array();
		$roomTypeSlugs = array();

		foreach ( $accommodationTypeIdsOrSlugs as $idOrSlug ) {

			if ( is_numeric( $idOrSlug ) ) {

				$roomTypeIds[] = absint( $idOrSlug );

			} else {

				$roomTypeSlugs[] = trim( '' . $idOrSlug );
			}
		}

		$roomTypeIds   = array_unique( $roomTypeIds );
		$roomTypeSlugs = array_unique( $roomTypeSlugs );

		$result = array();

		if ( ! empty( $roomTypeIds ) ) {

			$roomTypePosts = $this->persistence->getPosts(
				array(
					'fields'         => 'all',
					'post_status'    => array( 'publish' ),
					'post__in'       => $roomTypeIds,
					'posts_per_page' => -1,
					// get all posts on all languages
					'suppress_wpml_where_and_join_filter' => true,
				)
			);

			foreach ( $roomTypePosts as $roomTypePost ) {

				if ( empty( $result[ $roomTypePost->ID ] ) ) {

					$result[ $roomTypePost->ID ] = $this->mapPostToEntity( $roomTypePost );
				}
			}
		}

		if ( ! empty( $roomTypeSlugs ) ) {

			$roomTypePosts = $this->persistence->getPosts(
				array(
					'fields'         => 'all',
					'post_status'    => array( 'publish' ),
					'post_name__in'  => $roomTypeSlugs,
					'posts_per_page' => -1,
					// get all posts on all languages
					'suppress_wpml_where_and_join_filter' => true,
				)
			);

			foreach ( $roomTypePosts as $roomTypePost ) {

				if ( empty( $result[ $roomTypePost->ID ] ) ) {

					$result[ $roomTypePost->ID ] = $this->mapPostToEntity( $roomTypePost );
				}
			}
		}

		return $result;
	}
}
