<?php

namespace MPHB;

class Translation {

	const WPML_STRING_DOMAIN = 'MotoPress Hotel Booking';

	/**
	 *
	 * @var string
	 */
	private $restoreAfterEmail = false;

	/**
	 *
	 * @var string
	 */
	private $restoreLanguage = null;

	/**
	 *
	 * @var string
	 */
	private $locale;

	public function __construct() {
		add_filter( 'mphb_translate_string', array( $this, 'translateString' ), 10, 4 );
		add_action( 'plugins_loaded', array( $this, 'improveWMPLCompability' ) );
		add_filter( 'plugin_locale', array( $this, 'setLocaleForEmails' ), 10, 2 );
	}

	public function improveWMPLCompability() {

		if ( ! $this->isActiveWPML() ) {
			return;
		}

		add_filter( '_mphb_translate_page_id', array( $this, 'wpmlTranslatePageId' ), 10, 2 );
		add_filter( '_mphb_translate_post_id', array( $this, 'wpmlTranslatePostId' ), 10, 2 );

		add_action( '_mphb_persistence_before_get_posts', array( $this, 'setWPMLDefaultLanguage' ) );
		add_action( '_mphb_persistence_after_get_posts', array( $this, 'resetWPMLStoredLanguage' ) );

		add_filter( '_mphb_translate_service', array( $this, 'translateService' ) );
		add_filter( '_mphb_translate_reserved_service', array( $this, 'translateReservedService' ) );
		add_filter( '_mphb_translate_room_type', array( $this, 'translateRoomType' ) );

		add_action( '_mphb_before_dropdown_pages', array( $this, 'setupDefaultLanguage' ) );
		add_action( '_mphb_after_dropdown_pages', array( $this, 'restoreLanguage' ) );

		add_action( '_mphb_translate_admin_email_before_send', array( $this, 'changeLanguageForAdminEmail' ) );
		add_action( '_mphb_translate_admin_email_after_send', array( $this, 'resetLanguageAfterEmail' ) );

		add_action( '_mphb_translate_customer_email_before_send', array( $this, 'changeLanguageForCustomerEmail' ) );
		add_action( '_mphb_translate_customer_email_after_send', array( $this, 'resetLanguageAfterEmail' ) );

		add_filter( 'wpml_copy_from_original_custom_fields', array( $this, 'wpmlCopyPostMeta' ) );
	}

	/**
	 *
	 * @param string $name
	 * @param string $value
	 * @param string $context
	 */
	public function registerWPMLString( $name, $value, $context = null ) {
		if ( is_null( $context ) ) {
			$context = self::WPML_STRING_DOMAIN;
		}
		do_action( 'wpml_register_single_string', $context, $name, $value );
	}

	/**
	 *
	 * @param string $value
	 * @param string $name
	 * @param string $context
	 * @param string $language
	 * @return string
	 */
	public function translateString( $value, $name, $context = null, $language = null ) {

		if ( ! $this->isActiveWPML() ) {
			return $value;
		}

		if ( is_null( $context ) ) {
			$context = self::WPML_STRING_DOMAIN;
		}

		if ( is_null( $language ) ) {
			$language = $this->getCurrentLanguage();
		}

		return apply_filters( 'wpml_translate_single_string', $value, $context, $name, $language );
	}

	/**
	 *
	 * @param Entities\Booking $booking
	 */
	public function changeLanguageForAdminEmail( $booking ) {

		$adminLanguage = $this->getDefaultLanguage();

		if ( $adminLanguage !== $this->getCurrentLanguage() ) {
			$this->restoreAfterEmail = true;
			$this->restoreLanguage   = $this->getRestoreLanguage();

			$this->switchLanguage( $adminLanguage );
		}

		$this->updateTextdomains();
	}

	/**
	 *
	 * @param Entities\Booking $booking
	 */
	public function changeLanguageForCustomerEmail( $booking ) {
		$language = $booking->getLanguage();

		if ( ! $this->isActiveLanguage( $language ) ) {
			$language = $this->getDefaultLanguage();
		}

		if ( $language !== $this->getCurrentLanguage() ) {
			$this->restoreAfterEmail = true;
			$this->restoreLanguage   = $this->getRestoreLanguage();

			$this->switchLanguage( $language );
		}

		$this->updateTextdomains();
	}

	public function updateTextdomains() {
		global $sitepress;
		$this->locale = $sitepress->get_locale( $this->getCurrentLanguage() );

		unload_textdomain( MPHB()->getTextDomain() );
		unload_textdomain( 'default' );

		MPHB()->loadTextDomain();
		load_default_textdomain( $this->locale );

		global $wp_locale;
		// Rebuilding $wp_locale is the point of this method: the textdomains above were just
		// reloaded for another locale, and core caches month and weekday names in this global.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_locale = new \WP_Locale();
	}

	/**
	 * Set correct locale code for emails
	 *
	 * @param string $locale
	 * @param string $domain
	 * @return string
	 */
	public function setLocaleForEmails( $locale, $domain ) {

		if ( 'motopress-hotel-booking' === $domain && $this->locale ) {
			$locale = $this->locale;
		}

		return $locale;
	}

	/**
	 *
	 * @param Entities\Booking $booking
	 */
	public function resetLanguageAfterEmail( $booking ) {
		if ( $this->restoreAfterEmail ) {
			$this->switchLanguage( $this->restoreLanguage );
			$this->updateTextdomains();
			$this->restoreAfterEmail = false;
		}
	}

	/**
	 *
	 * @param string $language
	 * @return bool
	 */
	public function isActiveLanguage( $language ) {
		return apply_filters( 'wpml_language_is_active', null, $language );
	}

	public function setupDefaultLanguage() {
		$this->restoreLanguage = $this->getRestoreLanguage();
		$this->switchLanguage( $this->getDefaultLanguage() );
	}

	public function setupAllLanguages() {
		$this->restoreLanguage = $this->getRestoreLanguage();
		$this->switchLanguage( 'all' );
	}

	public function restoreLanguage() {
		$this->switchLanguage( $this->restoreLanguage );
	}

	/**
	 * Fill data for copy original content button
	 *
	 * Filters `wpml_copy_from_original_custom_fields`: the fields WPML's "Copy from
	 * original" button fills in, keyed by field name. A field is a descriptor
	 * `[ editor_name, editor_type, value ]`; WPML also puts a plain `post_type` string
	 * into the same array.
	 *
	 * @see \WPML_Post_Edit_Ajax::copy_from_original_custom_fields() for what arrives here.
	 *
	 * @param array<string, string|array<string, string>> $data
	 * @return array<string, string|array<string, string>>
	 */
	public function wpmlCopyPostMeta( $data ) {

		$trid = filter_input( INPUT_POST, 'trid' );

		if ( get_post_type( $trid ) === MPHB()->postTypes()->rate()->getPostType() ) {

			$rate = mphb_prices_facade()->getRateById( $trid );

			if ( $rate ) {
				$data['mphb_description'] = array(
					'editor_name' => 'mphb-mphb_description',
					'editor_type' => 'text',
					'value'       => $rate->getDescription(),
				);
			}
		}

		if ( get_post_type( $trid ) === MPHB()->postTypes()->roomType()->getPostType() ) {
			$roomType = MPHB()->getRoomTypeRepository()->findById( $trid );

			if ( $roomType ) {
				$data['mphb_view'] = array(
					'editor_name' => 'mphb-mphb_view',
					'editor_type' => 'text',
					'value'       => $roomType->getView(),
				);
			}
		}

		return $data;
	}

	/**
	 * Switch to the language a persistence query asks for, before it runs get_posts()
	 *
	 * Runs on `_mphb_persistence_before_get_posts`, which passes the WP_Query arguments.
	 * MPHB's own `mphb_language` key among them says which language to query in:
	 * `original` for the site's default language, or a WPML language code like `uk`.
	 * Without that key nothing is switched. `resetWPMLStoredLanguage()` switches back.
	 *
	 * @see \MPHB\Persistences\CPTPersistence::getPosts()
	 *
	 * @param array<string, mixed> $atts WP_Query arguments, optionally with `mphb_language`.
	 * @return void
	 */
	public function setWPMLDefaultLanguage( $atts ) {

		if ( ! isset( $atts['mphb_language'] ) ) {
			return;
		}

		$this->restoreLanguage = $this->getRestoreLanguage();

		switch ( $atts['mphb_language'] ) {
			case 'original':
				$toLanguage = $this->getDefaultLanguage();
				break;
			default:
				$toLanguage = $atts['mphb_language'];
				break;
		}

		$this->switchLanguage( $toLanguage );
	}

	/**
	 * Switch back to the language stored before the persistence query
	 *
	 * Runs on `_mphb_persistence_after_get_posts`, the counterpart of
	 * setWPMLDefaultLanguage(). The `mphb_language` check mirrors it: without that key
	 * nothing was switched, so there is nothing to restore. The action also passes the
	 * posts it found, but this callback is registered for one argument only.
	 *
	 * @see \MPHB\Persistences\CPTPersistence::getPosts()
	 *
	 * @param array<string, mixed> $atts WP_Query arguments, optionally with `mphb_language`.
	 * @return void
	 */
	public function resetWPMLStoredLanguage( $atts ) {

		if ( ! isset( $atts['mphb_language'] ) ) {
			return;
		}

		$this->switchLanguage( $this->restoreLanguage );
	}

	public function switchLanguage( $language = null ) {
		if ( is_null( $language ) ) {
			$language = $this->getDefaultLanguage();
		}
		do_action( 'wpml_switch_language', $language );
	}

	/**
	 *
	 * @param int    $id
	 * @param string $language Optional. Current Language by default.
	 * @return int
	 */
	public function wpmlTranslatePageId( $id, $language = null ) {
		return $this->translateId( $id, 'page', $language );
	}

	/**
	 *
	 * @param int    $id
	 * @param string $language Optional. Current Language by default.
	 * @return int
	 */
	public function wpmlTranslatePostId( $id, $language = null ) {
		return $this->translateId( $id, null, $language );
	}

	/**
	 * Use the function before changing the site language. Otherwise
	 * getCurrentLanguage() will return wrong value.
	 *
	 * @return string Language code like "en", "uk", "ru" etc.
	 *
	 * @since 3.5.1
	 */
	protected function getRestoreLanguage() {
		if ( is_admin() && ! MPHB()->isAjax() ) {
			$locale = get_user_meta( get_current_user_id(), 'locale', true );

			if ( empty( $locale ) ) {
				return $this->getDefaultLanguage();
			} else {
				$language = explode( '_', $locale ); // phpcs:ignore parse locale: "en" => ["en"], "ru_RU" -> ["ru", "RU"]
				return $language[0];
			}
		} else {
			return $this->getCurrentLanguage();
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getCurrentLanguage() {
		return apply_filters( 'wpml_current_language', $this->getWPLanguage() );
	}

	/**
	 *
	 * @return string
	 */
	public function getWPLanguage() {
		$locale = get_locale();
		return substr( $locale, 0, 2 );
	}

	/**
	 *
	 * @return string
	 */
	public function getDefaultLanguage() {
		return apply_filters( 'wpml_default_language', $this->getWPLanguage() );
	}

	/**
	 * Locale of the default language, like "en_US".
	 *
	 * Not get_locale(): under WPML in the admin that returns the locale of the language
	 * taken from the auth cookie and caches it for the whole request, so switching the
	 * current language does not change it.
	 *
	 * @return string
	 */
	public function getDefaultLocale() {

		if ( ! $this->isActiveWPML() ) {
			return get_locale();
		}

		$languages = apply_filters( 'wpml_active_languages', null );

		return $languages[ $this->getDefaultLanguage() ]['default_locale'] ?? get_locale();
	}

	/**
	 * Home URL of the site in its default language, without a language prefix.
	 *
	 * Not home_url(): under WPML in the admin that returns the URL of the language taken
	 * from the auth cookie, and switching the current language does not change it - the
	 * same trap as get_locale(). Everything else home_url() applies (SSL, domain mapping)
	 * is kept, only the language is undone.
	 *
	 * @see https://wpml.org/wpml-hook/wpml_permalink/  Converts a URL to the given language.
	 *
	 * @return string
	 */
	public function getDefaultHomeUrl() {

		if ( ! $this->isActiveWPML() ) {
			return home_url();
		}

		return apply_filters( 'wpml_permalink', home_url(), $this->getDefaultLanguage() );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @param bool   $returnOriginal Optional. true by default.
	 *
	 * @return int|null
	 */
	public function getOriginalId( $id, $type, $returnOriginal = true ) {
		// phpcs:ignore old code: return apply_filters( 'wpml_master_post_from_duplicate', $id );
		return $this->translateId( $id, $type, $this->getDefaultLanguage(), $returnOriginal );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @param bool   $returnOriginal Optional. true by default.
	 *
	 * @return int|null
	 */
	public function getCurrentId( $id, $type, $returnOriginal = true ) {
		return $this->translateId( $id, $type, $this->getCurrentLanguage(), $returnOriginal );
	}

	/**
	 * <b>Note:</b> use methods <b>getPostTranslationIds()</b> or
	 * <b>getTaxTranslationIds()</b> to get IDs of the post or the taxonomy.
	 *
	 * Get translation ID of the objects on all languages.
	 *
	 * @param int    $id ID of the object on any language.
	 * @param string $type Object type (with prefix, like "post_", "tax_" etc.).
	 *
	 * @return array [%Language code% => %Translation ID%]. Example:
	 *               ["en" => 768, "uk" => 771]
	 *
	 * @global type $sitepress
	 */
	private function getAllTranslationIds( $id, $type ) {
		global $sitepress;

		if ( ! isset( $sitepress ) ) {
			$languageCode = substr( get_locale(), 0, 2 ); // phpcs:ignore get language code from locale "en", "uk" etc.
			return array( $languageCode => $id );
		}

		$trid         = $sitepress->get_element_trid( $id, $type );
		$translations = $sitepress->get_element_translations( $trid, $type );

		$ids = array_combine( wp_list_pluck( $translations, 'language_code' ), wp_list_pluck( $translations, 'element_id' ) );

		return $ids;
	}

	/**
	 * Get translation ID of the posts on all languages.
	 *
	 * @param int    $id ID of the post on any language.
	 * @param string $type Optional. Post type. "post" by default.
	 *
	 * @return array [%Language code% => %Translation ID%]. Example:
	 *               ["en" => 768, "uk" => 771]
	 */
	public function getPostTranslationIds( $id, $type = 'post' ) {
		return $this->getAllTranslationIds( $id, 'post_' . $type );
	}

	/**
	 * Get translation ID of the taxonomy terms on all languages.
	 *
	 * @param int    $id ID of the taxonomy term on any language.
	 * @param string $type Optional. Taxonomy name. "category" by default.
	 *
	 * @return array [%Language code% => %Translation ID%]. Example:
	 *               ["en" => 768, "uk" => 771]
	 */
	public function getTaxTranslationIds( $id, $type = 'category' ) {
		return $this->getAllTranslationIds( $id, 'tax_' . $type );
	}

	/**
	 * @param int    $id
	 * @param string $type Optional. Use post, page, {custom post type name}, nav_menu, nav_menu_item, category, tag, etc.
	 *                      You can also pass 'any', to let WPML guess the type, but this will only work for posts. 'any' by default.
	 * @param string $language Optional. Current language by default
	 * @param bool   $returnOriginal Optional. true by default.
	 *
	 * @return int|null
	 */
	public function translateId( $id, $type = null, $language = null, $returnOriginal = true ) {
		if ( ! $type ) {
			$type = 'any';
		}
		return apply_filters( 'wpml_object_id', $id, $type, $returnOriginal, $language );
	}

	/**
	 *
	 * @return bool
	 */
	public function isTranslationPage() {
		return $this->isActiveWPML() && $this->getCurrentLanguage() !== $this->getDefaultLanguage();
	}

	/**
	 *
	 * @param string $postType
	 * @return boolean
	 */
	public function isTranslatablePostType( $postType ) {
		return (bool) apply_filters( 'wpml_is_translated_post_type', null, $postType );
	}

	/**
	 *
	 * @return bool
	 */
	public function isActiveWPML() {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 *
	 * @return bool
	 */
	public function isOriginalId( $id, $type ) {
		$originalId = $this->getOriginalId( $id, $type, false );
		// Loose on purpose: callers pass the id straight from WordPress (e.g. $postarr['ID'],
		// a numeric string), while WPML returns an int. Strict comparison would never match.
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		return ( ! is_null( $originalId ) && $id == $originalId );
	}

	/**
	 * @param Entities\Service $service
	 * @param string           $language Optional. Current Language by default.
	 * @return Entities\Service
	 */
	public function translateService( $service, $language = null ) {

		$translatedId = $this->translateId( $service->getId(), MPHB()->postTypes()->service()->getPostType(), $language );

		$translatedService = MPHB()->getServiceRepository()->findById( $translatedId );

		return ! is_null( $translatedService ) ? $translatedService : $service;
	}

	/**
	 * @param Entities\ReservedService $reservedService
	 * @param string                   $language Optional. Current Language by default.
	 * @return Entities\ReservedService
	 */
	public function translateReservedService( $reservedService, $language = null ) {

		$translatedId = $this->translateId( $reservedService->getId(), MPHB()->postTypes()->service()->getPostType(), $language );

		$atts = array(
			'id'       => $translatedId,
			'adults'   => $reservedService->getAdults(),
			'quantity' => $reservedService->getQuantity(),
		);

		$translatedReservedService = Entities\ReservedService::create( $atts );

		return ! is_null( $translatedReservedService ) ? $translatedReservedService : $reservedService;
	}

	/**
	 *
	 * @param Entities\RoomType $roomType
	 * @param string            $language Optional. Current Language by default.
	 * @return Entities\RoomType
	 */
	public function translateRoomType( $roomType, $language = null ) {

		if ( is_null( $roomType ) ) {
			return $roomType;
		}

		$translatedId = $this->translateId( $roomType->getId(), MPHB()->postTypes()->roomType()->getPostType(), $language );

		$translatedRoomType = MPHB()->getRoomTypeRepository()->findById( $translatedId );

		return ! is_null( $translatedRoomType ) ? $translatedRoomType : $roomType;
	}

	/**
	 * @param array  $attributes [%Attribute name% => %Attribute ID%]
	 * @param string $language Optional. Current language by default.
	 *
	 * @return int[]
	 */
	public function translateAttributes( $attributes, $language = null ) {
		$translatedIds = array();

		foreach ( $attributes as $attributeName => $termId ) {
			$taxonomyName    = mphb_attribute_taxonomy_name( $attributeName );
			$translatedIds[] = $this->translateId( $termId, $taxonomyName, $language );
		}

		return $translatedIds;
	}
}
