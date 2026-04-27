<?php

namespace MPHB\Admin\Groups;

use MPHB\Admin\Fields\TextField;
use MPHB\Core\StringEncryptHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaBoxGroup extends InputGroup {
	protected $postType;
	protected $context;
	protected $priority;
	protected $postId;
	private $atts;

	/**
	 * @since 3.9.1
	 *
	 * @param string $name
	 * @param string $label
	 * @param string $postType
	 * @param string $context Optional. The context within the screen where the boxes should display ('normal', 'side', and 'advanced'). Default: 'advanced'
	 * @param string $priority Optional. The priority within the context where the boxes should show ('high', 'default', 'low'). Default: 'default'
	 * @param array $atts Optional. Additional attributes for rendering.
	 */
	public function __construct( $name, $label, $postType, $context = 'advanced', $priority = 'default', $atts = array() ) {
		parent::__construct( $name, $label );
		$this->postType = $postType;
		$this->context  = $context;
		$this->priority = $priority;
		$this->atts     = $atts;
	}

	public function getPostType(): string {
		return $this->postType;
	}

	public function getPostId() {
		return $this->postId;
	}

	public function setPostId( $postId ) {
		$this->postId = $postId;
	}

	protected function load(): bool {
		$postId = get_the_ID(); // False before "wp" hook

		if ( empty( $this->fields ) || $postId === false ) {
			return false; // Not loaded
		}

		foreach ( $this->fields as $field ) {
			// Update dependency
			if ( $field->getType() === 'dynamic-select' ) {
				$field->updateDependency( get_post_meta( $postId, $field->getDependencyInput(), $single = true ) );
			}

			// Set value
			if ( $field->getType() === 'post-id' ) {
				$field->setValue( $postId );
			} else {
				// Always search with the parameter $single = false to
				// distinguish an empty field from a missing one
				$metaValues = get_post_meta( $postId, $field->getName(), $single = false );

				if ( $metaValues !== false && count( $metaValues ) >= 1 ) {
					$value = $field->isUnique() ? reset( $metaValues ) : $metaValues;

					$field->setValue( $value );
				}
			}
		}

		return true; // Loaded
	}

	public function render() {
		$this->load();

		wp_nonce_field( 'save_' . $this->getName(), '_nonce_' . $this->getName() );

		do_action( "mphb_render_meta_box_group_{$this->postType}_{$this->name}" );

		switch ( $this->context ) {
			case 'advanced':
				if ( isset( $this->atts['wide'] ) && $this->atts['wide'] ) {
					$this->renderFullWideTableMetaBox();
				} else {
					$this->renderRegularMetaBox();
				}
				break;
			case 'side':
				$this->renderSideMetaBox();
				break;
			default:
				$this->renderRegularMetaBox();
				break;
		}
	}

	private function renderRegularMetaBox() {
		$result = '<table class="form-table">';
		$result .= '<tbody>';

		foreach ( $this->fields as $field ) {

			// Prevent render untranslatable field on non-default languages
			if ( MPHB()->translation()->isTranslationPage()
				&& MPHB()->translation()->isTranslatablePostType( $this->getPostType() )
				&& ! $field->isTranslatable()
			) {
				continue;
			}

			$result .= '<tr class="' . esc_attr( 'mphb-' . $field->getType() . '-row' ) . '">';
				if ( $field->hasLabel() ) {
					$result .= '<th>';
					$result .= $field->getLabelTag();
					$result .= '</th>';
				}

				$result .= '<td colspan="' . ( $field->hasLabel() ? 1 : 2 ) . '">';
				$result .= $field->render();
				$result .= '</td>';
			$result .= '</tr>';
		}

		$result .= '</tbody>';
		$result .= '</table>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result;
	}

	/**
	 *
	 * @since 3.9.3
	 */
	private function renderFullWideTableMetaBox() {
		$result = '';
		foreach ( $this->fields as $field ) {
			// Prevent render untranslatable field on non-default languages
			if ( MPHB()->translation()->isTranslationPage() && MPHB()->translation()->isTranslatablePostType( $this->postType ) && ! $field->isTranslatable() ) {
				continue;
			}

			$result .= $field->render();
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result;
	}

	private function renderSideMetaBox() {
		$result = '<div class="mphb-side-meta-box">';
		foreach ( $this->fields as $field ) {
			$result .= '<div class="mphb-meta-field">';
			$result .= '<div>' . $field->getLabelTag() . '</div>';
			$result .= $field->render();
			$result .= '</div>';
		}
		$result .= '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result;
	}

	public function register() {
		// prevent showing empty groups on translation pages
		if ( MPHB()->translation()->isTranslationPage() && MPHB()->translation()->isTranslatablePostType( $this->postType ) && ! $this->hasTranslatableFields() ) {
			return;
		}
		add_meta_box( $this->name, $this->label, array( $this, 'render' ), $this->postType, $this->context, $this->priority );
	}

	/**
	 *
	 * @return bool
	 */
	public function isValidRequest() {
		return isset( $_REQUEST[ "_nonce_{$this->name}" ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ "_nonce_{$this->name}" ] ) ), "save_{$this->name}" );
	}

	/**
	 *
	 * @return bool
	 *
	 * @since 3.9.4 Deletes post meta if it has an empty array of fields.
	 */
	public function save() {

		if ( ! $this->isValidRequest() ) {
			return false;
		}

		$metaValues = $this->getAttsFromRequest( $_POST );

		if ( ! empty( $metaValues ) ) {
			foreach ( $this->getFields() as $field ) {
				$name = $field->getName();

				if ( ! isset( $metaValues[ $name ] ) ) {
					continue;
				}

				$value = $metaValues[ $name ];

				if ( $field instanceof TextField && $field->isEncoded() ) {
					$value = StringEncryptHelper::encryptString( $value );
				}

				if ( $field->isUnique() || ! is_array( $value ) ) {
					update_post_meta( $this->postId, $name, $value );

				} else {
					delete_post_meta( $this->postId, $name );

					foreach ( $value as $singleItem ) {
						add_post_meta( $this->postId, $name, $singleItem );
					}
				}
			}

		} else {
			foreach ( $this->getFields() as $field ) {
				if ( $field->isDisabled() || $field->isReadonly() ) {
					continue;
				}
				delete_post_meta( $this->postId, $field->getName() );
			}
		}
	}

	/**
	 *
	 * @return array
	 */
	public function getValues() {
		return $this->isValidRequest() ? $this->getAttsFromRequest( $_POST ) : array();
	}

	/**
	 *
	 * @return bool
	 */
	public function hasTranslatableFields() {

		$translatableFields = array_map(
			function( $field ) {
				return $field->isTranslatable();
			},
			$this->fields
		);

		$translatableFields = array_filter( $translatableFields );

		return ! empty( $translatableFields );
	}

}
