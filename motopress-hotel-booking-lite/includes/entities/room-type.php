<?php

namespace MPHB\Entities;

use \MPHB\TaxesAndFees\TaxesAndFees;

class RoomType {

	/**
	 *
	 * @var int
	 */
	private $id;

	/**
	 *
	 *
	 * @var int
	 */
	private $originalId;

	/**
	 *
	 * @var string
	 */
	private $title;

	/**
	 *
	 * @var string
	 */
	private $description;

	/**
	 *
	 * @var string
	 */
	private $excerpt;

	/**
	 *
	 * @var int
	 */
	private $adults;

	/**
	 *
	 * @var int
	 */
	private $children;

	/**
	 * @var int|string
	 *
	 * @since 3.7.2
	 */
	private $totalCapacity;

	/**
	 * @var int Equal to $adults when not set (see RoomTypeRepository).
	 *
	 * @since 5.0.0
	 */
	private $baseAdults;

	/**
	 * @var int Equal to $children when not set (see RoomTypeRepository).
	 *
	 * @since 5.0.0
	 */
	private $baseChildren;

	/**
	 *
	 * @var string
	 */
	private $bedType;

	/**
	 *
	 * @var float
	 */
	private $size;

	/**
	 *
	 * @var string
	 */
	private $view;

	/**
	 *
	 * @var int[]
	 */
	private $servicesIds;

	/**
	 *
	 * @var \WP_Term[]
	 */
	private $categories;

	/**
	 *
	 * @var \WP_Term[]
	 */
	private $tags;

	/**
	 *
	 * @var \WP_Term[]
	 */
	private $facilities;

	/**
	 * @var array [%Attribute name% => [%Term ID% => %Term title%]]
	 *
	 * @see \MPHB\Repositories\RoomTypeRepository::mapPostToEntity()
	 */
	private $attributes;

	/**
	 *
	 * @var int
	 */
	private $imageId;

	/**
	 *
	 * @var int[]
	 */
	private $galleryIds;

	/**
	 *
	 * @var string
	 */
	private $status;

	// Google Hotels data
	private bool $isIncludeToGoogleHotels;
	private string $propertyId;
	private string $propertyTitle;
	private bool $isIndoorAccommodation;
	private bool $isOnsiteManaged;
	private bool $isAcceptingOvernightGuests;
	private bool $isAddressPubliclyListed;
	private string $propertyType; // 'hotel' | 'vacation_rental' | 'outdoor_lodging'
	private string $propertyCategory; // '' | HOTEL_CATEGORY_VALUE | VACATION_RENTAL_CATEGORY_VALUE
	private array $images;
	private array $propertyImages;
	private float $latitude;
	private float $longitude;
	private string $contactsMainPhone;
	private string $addressLine1;
	private string $addressLine2;
	private string $addressCity;
	private string $addressProvince;
	private string $addressPostalCode;
	private string $addressCountryCode;
	private string $ghTitle;
	private string $ghDescription;

	/**
	 *
	 * @param array $atts
	 */
	public function __construct( $atts ) {
		$this->id            = $atts['id'];
		$this->originalId    = $atts['original_id'];
		$this->title         = $atts['title'];
		$this->description   = $atts['description'];
		$this->excerpt       = $atts['excerpt'];
		$this->adults        = $atts['adults'];
		$this->children      = $atts['children'];
		$this->totalCapacity = $atts['total_capacity'];
		$this->baseAdults    = $atts['base_adults'];
		$this->baseChildren  = $atts['base_children'];
		$this->bedType       = $atts['bed_type'];
		$this->size          = $atts['size'];
		$this->view          = $atts['view'];
		$this->servicesIds   = $atts['services_ids'];
		$this->categories    = $atts['categories'];
		$this->tags          = $atts['tags'];
		$this->facilities    = $atts['facilities'];
		$this->attributes    = $atts['attributes'];
		$this->imageId       = $atts['image_id'];
		$this->galleryIds    = $atts['gallery_ids'];
		$this->status        = $atts['status'];

		// Google Hotels data
		$this->isIncludeToGoogleHotels = $atts['is_include_to_google_hotels'] ?? false;
		$this->propertyId = $atts['property_id'] ?? '';
		$this->propertyTitle = $atts['property_title'] ?? '';
		$this->isIndoorAccommodation = $atts['is_indoor_accommodation'] ?? false;
		$this->isOnsiteManaged = $atts['is_onsite_managed'] ?? false;
		$this->isAcceptingOvernightGuests = $atts['is_accepting_overnight_guests'] ?? false;
		$this->isAddressPubliclyListed = $atts['is_address_publicly_listed'] ?? false;
		$this->propertyType = $atts['property_type'] ?? 'outdoor_lodging';
		$this->propertyCategory = $atts['property_category'] ?? '';
		$this->latitude = $atts['latitude'] ?? 0;
		$this->longitude = $atts['longitude'] ?? 0;
		$this->contactsMainPhone = $atts['contacts_main_phone'] ?? '';
		$this->addressLine1 = $atts['address_line1'] ?? '';
		$this->addressLine2 = $atts['address_line2'] ?? '';
		$this->addressCity = $atts['address_city'] ?? '';
		$this->addressProvince = $atts['address_province'] ?? '';
		$this->addressPostalCode = $atts['address_postal_code'] ?? '';
		$this->addressCountryCode = $atts['address_country_code'] ?? '';
		$this->images = $atts['images'] ?? array();
		$this->propertyImages = $atts['property_images'] ?? array();
		$this->ghTitle = $atts['gh_title'] ?? '';
		$this->ghDescription = $atts['gh_description'] ?? '';
	}

	public function getPostData(): WPPostData {
		return MPHB()->getRoomTypeRepository()->mapEntityToPostData( $this );
	}

	/**
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getOriginalId() {
		return $this->originalId;
	}

	public function isOriginalRoomType(): bool {
		return $this->getId() === $this->getOriginalId();
	}

	public function getOriginalRoomType(): RoomType {
		return $this->isOriginalRoomType() ? $this : mphb_rooms_facade()->getRoomTypeById( $this->getOriginalId() );
	}

	/**
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 *
	 * @return string
	 */
	public function getExcerpt() {
		return $this->excerpt;
	}

	/**
	 * Check is room type has gallery
	 *
	 * @return bool
	 */
	public function hasGallery() {
		return ! empty( $this->galleryIds );
	}

	/**
	 * Retrieve ids of gallery's attachments
	 *
	 * @return array
	 */
	public function getGalleryIds() {
		return $this->galleryIds;
	}

	/**
	 * Check is room type has featured image
	 *
	 * @return bool
	 */
	public function hasFeaturedImage() {
		return (bool) $this->imageId;
	}

	/**
	 * Retrieve room type featured image id.
	 *
	 * @return string | int Room type featured image ID or empty string.
	 */
	public function getFeaturedImageId() {
		return $this->imageId;
	}

	/**
	 * Retrieve room type categories terms objects
	 *
	 * @return \WP_Term[]
	 */
	public function getCategories() {
		return $this->categories;
	}

	/**
	 * Retrieve room type tags terms objects
	 *
	 * @return \WP_Term[]
	 */
	public function getTags() {
		return $this->tags;
	}

	/**
	 *
	 * @return \WP_Term[]
	 */
	public function getFacilities() {
		return $this->facilities;
	}

	/**
	 *
	 * @return array [%Attribute name% => [%Term ID% => %Term title%]]
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 *
	 * @return string
	 */
	public function getView() {
		return $this->view;
	}

	/**
	 *
	 * @param bool $withUnits Optional. Whether to append units to size. Default FALSE.
	 * @return string
	 */
	public function getSize( $withUnits = false ) {
		return (string) ( $withUnits ? $this->size . MPHB()->settings()->units()->getSquareUnit() : $this->size );
	}

	/**
	 *
	 * @return string
	 */
	public function getBedType() {
		return $this->bedType;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int
	 */
	public function getBaseAdultsCapacity() {
		return $this->baseAdults;
	}

	/**
	 * @since 5.0.0
	 *
	 * @return int
	 */
	public function getBaseChildrenCapacity() {
		return $this->baseChildren;
	}

	/**
	 *
	 * @return int
	 */
	public function getAdultsCapacity() {
		return $this->adults;
	}

	/**
	 *
	 * @return int
	 */
	public function getChildrenCapacity() {
		return $this->children;
	}

	/**
	 * @return int|string
	 *
	 * @since 3.7.2
	 */
	public function getTotalCapacity() {
		return $this->totalCapacity;
	}

	/**
	 * @return bool
	 *
	 * @since 3.7.2
	 */
	public function hasLimitedTotalCapacity() {
		return ! empty( $this->totalCapacity );
	}

	/**
	 * @return int
	 *
	 * @since 3.7.2
	 */
	public function calcTotalCapacity() {
		if ( $this->hasLimitedTotalCapacity() ) {
			return $this->totalCapacity;
		} else {
			return $this->adults + $this->children;
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $childrenPreset Optional.
	 * @return int
	 */
	public function getMaxAdults( $childrenPreset = 0 ) {
		if ( ! $childrenPreset ) {
			return $this->getAdultsCapacity();
		} else {
			$minAdults      = mphb_get_min_adults();
			$adultsCapacity = $this->getAdultsCapacity();
			$totalCapacity  = $this->calcTotalCapacity();
			$placesLeft     = max( 0, $totalCapacity - $childrenPreset );

			return mphb_limit( $placesLeft, $minAdults, $adultsCapacity );
		}
	}

	/**
	 * @since 5.0.0
	 *
	 * @param int $adultsPreset Optional.
	 * @return int
	 */
	public function getMaxChildren( $adultsPreset = 0 ) {
		if ( ! $adultsPreset ) {
			return $this->getChildrenCapacity();
		} else {
			$minChildren      = mphb_get_min_children();
			$childrenCapacity = $this->getChildrenCapacity();
			$totalCapacity    = $this->calcTotalCapacity();
			$placesLeft       = max( 0, $totalCapacity - $adultsPreset );

			return mphb_limit( $placesLeft, $minChildren, $childrenCapacity );
		}
	}

	public function getLink() {
		return get_permalink( $this->id );
	}

	/**
	 *
	 * @return bool
	 */
	public function hasServices() {
		return ! empty( $this->servicesIds );
	}

	/**
	 * Retrieve services available for this room type
	 *
	 * @return int[]
	 */
	public function getServices() {
		return $this->servicesIds;
	}

	/**
	 *
	 * @return array
	 */
	public function getServicesPriceList() {
		$prices = array();
		foreach ( $this->servicesIds as $serviceId ) {
			$service = MPHB()->getServiceRepository()->findById( $serviceId );
			if ( $service ) {
				$prices[ $service->getId() ] = $service->getPrice();
			}
		}
		return $prices;
	}

	/**
	 * Retrieve minimal average price from today to +X (from settings) days.
	 *
	 * @return float
	 *
	 * @deprecated 3.8.3
	 * @see mphb_get_room_type_base_price()
	 */
	public function getDefaultPrice() {
		return mphb_get_room_type_base_price( $this );
	}

	/**
	 * Retrieve minimal price for dates
	 *
	 * @param \DateTime $checkInDate
	 * @param \DateTime $checkOutDate
	 * @return float
	 *
	 * @deprecated 3.8.3
	 * @see mphb_get_room_type_period_price()
	 */
	public function getDefaultPriceForDates( \DateTime $checkInDate, \DateTime $checkOutDate ) {
		return mphb_get_room_type_period_price( $checkInDate, $checkOutDate, $this );
	}

	/**
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 *
	 * @since 3.9.8
	 *
	 * @return \MPHB\TaxesAndFees\TaxesAndFees object.
	 */
	public function getTaxesAndFees() {
		$taxesAndFees = new TaxesAndFees();

		$roomType = MPHB()->getRoomTypeRepository()->findById( $this->originalId );
		$taxesAndFees->setRoomType( $roomType );

		return $taxesAndFees;
	}

	/**
	 *
	 * @since 3.9.8
	 *
	 * @return bool
	 */
	public function hasTaxesAndFees() {
		return $this->getTaxesAndFees()->hasTaxesAndFees();
	}

	public function isIncludeToGoogleHotels(): bool {
		return $this->isIncludeToGoogleHotels;
	}

	public function setIncludeToGoogleHotels( bool $isIncludeToGoogleHotels ): void {
		$this->isIncludeToGoogleHotels = $isIncludeToGoogleHotels;
	}

	public function getPropertyId(): string {
		return $this->propertyId;
	}

	public function setPropertyId( string $propertyId ): void {
		$this->propertyId = $propertyId;
	}

	public function getPropertyTitle(): string {
		return $this->propertyTitle;
	}

	public function setPropertyTitle( string $propertyTitle ): void {
		$this->propertyTitle = $propertyTitle;
	}

	public function isIndoorAccommodation(): bool {
		return $this->isIndoorAccommodation;
	}

	public function setIndoorAccommodation( bool $isIndoorAccommodation ): void {
		$this->isIndoorAccommodation = $isIndoorAccommodation;
	}

	public function isOnsiteManaged(): bool {
		return $this->isOnsiteManaged;
	}

	public function setOnsiteManaged( bool $isOnsiteManaged ): void {
		$this->isOnsiteManaged = $isOnsiteManaged;
	}

	public function isAcceptingOvernightGuests(): bool {
		return $this->isAcceptingOvernightGuests;
	}

	public function setAcceptingOvernightGuests( bool $isAcceptingOvernightGuests ): void {
		$this->isAcceptingOvernightGuests = $isAcceptingOvernightGuests;
	}

	public function isAddressPubliclyListed(): bool {
		return $this->isAddressPubliclyListed;
	}

	public function setAddressPubliclyListed( bool $isAddressPubliclyListed ): void {
		$this->isAddressPubliclyListed = $isAddressPubliclyListed;
	}

	public function getPropertyType(): string {
		return $this->propertyType;
	}

	public function setPropertyType( string $propertyType ): void {
		$this->propertyType = $propertyType;
	}

	public function getPropertyCategory(): string {
		return $this->propertyCategory;
	}

	public function setPropertyCategory( string $propertyCategory ): void {
		$this->propertyCategory = $propertyCategory;
	}

	public function getLatitude(): float {
		return $this->latitude;
	}

	public function setLatitude( float $latitude ): void {
		$this->latitude = $latitude;
	}

	public function getLongitude(): float {
		return $this->longitude;
	}

	public function setLongitude( float $longitude ): void {
		$this->longitude = $longitude;
	}

	public function getContactsMainPhone(): string {
		return $this->contactsMainPhone;
	}

	public function setContactsMainPhone( string $contactsMainPhone ): void {
		$this->contactsMainPhone = $contactsMainPhone;
	}

	public function getAddressLine1(): string {
		return $this->addressLine1;
	}

	public function setAddressLine1( string $addressLine1 ): void {
		$this->addressLine1 = $addressLine1;
	}

	public function getAddressLine2(): string {
		return $this->addressLine2;
	}

	public function setAddressLine2( string $addressLine2 ): void {
		$this->addressLine2 = $addressLine2;
	}

	public function getAddressCity(): string {
		return $this->addressCity;
	}

	public function setAddressCity( string $addressCity ): void {
		$this->addressCity = $addressCity;
	}

	public function getAddressProvince(): string {
		return $this->addressProvince;
	}

	public function setAddressProvince( string $addressProvince ): void {
		$this->addressProvince = $addressProvince;
	}

	public function getAddressPostalCode(): string {
		return $this->addressPostalCode;
	}

	public function setAddressPostalCode( string $addressPostalCode ): void {
		$this->addressPostalCode = $addressPostalCode;
	}

	public function getAddressCountryCode(): string {
		return $this->addressCountryCode;
	}

	public function setAddressCountryCode( string $addressCountryCode ): void {
		$this->addressCountryCode = $addressCountryCode;
	}

	public function getPropertyImages(): array {
		return $this->propertyImages;
	}

	public function setPropertyImages( array $images ): void {
		$this->propertyImages = $images;
	}

	public function getImages(): array {
		return $this->images;
	}

	public function setImages( array $images ): void {
		$this->images = $images;
	}

	public function getGHTitle() {
		return $this->ghTitle;
	}

	public function setGHTitle( string $title ): void {
		$this->ghTitle = $title;
	}

	public function getGHDescription() {
		return $this->ghDescription;
	}

	public function setGHDescription( string $description ): void {
		$this->ghDescription = $description;
	}
}
