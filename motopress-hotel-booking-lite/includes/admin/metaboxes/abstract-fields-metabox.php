<?php

declare(strict_types=1);

namespace MPHB\Admin\Metaboxes;

use MPHB\Admin\Fields\{ FieldFactory, InputField, TextField };
use MPHB\Core\StringEncryptHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractFieldsMetabox extends AbstractMetabox {
	/**
	 * @var InputField[]|null <code>[ Alias (unprefixed name) => InputField ]</code>
	 */
	private ?array $fields = null;

	private bool $isSaved = false; // To prevent recursion on
	                               // "save_post_{$postType}" action

	/**
	 * @access protected
	 */
	public function display(): void {
		$metaboxSlug = $this->getSlug();

		// Save nonce
		wp_nonce_field( "save_{$metaboxSlug}", "{$metaboxSlug}_nonce" );

		/**
		 * @param string $context
		 * @param string $priority
		 * @param AbstractFieldsMetabox $metabox
		 */
		do_action( "before_display_{$metaboxSlug}", $this->context, $this->priority, $this );

		switch ( $this->context ) {
			case static::CONTEXT_SIDE:
				$this->displaySide();
				break;

			default:
				$this->displayRegular();
				break;
		}

		/**
		 * @param string $context
		 * @param string $priority
		 * @param AbstractFieldsMetabox $metabox
		 */
		do_action( "after_display_{$metaboxSlug}", $this->context, $this->priority, $this );
	}

	/**
	 * @access protected
	 *
	 * @param int $postId
	 */
	public function save( $postId, \WP_Post $post ): void {
		if ( $this->isSaved || ! $this->canSave( $post ) ) {
			return;
		}

		// Update $isSaved before actually calling the method saveFields():
		// if it uses something like wp_update_post(), then the action
		// "save_post_{$post_type}" may trigger multiple times
		$this->isSaved = true;

		$this->saveFields( $post, $this->parseValues() );
	}

	protected function addActions(): void {
		parent::addActions();

		$postType = $this->editPage->getPostType();

		add_action( "save_post_{$postType}", array( $this, 'save' ), 5, 2 );
	}

	protected function canSave( \WP_Post $post ): bool {
		// Don't save anything for autosaves and revisions
		$isAutosave = defined( 'DOING_AUTOSAVE' ) || wp_is_post_autosave( $post ) !== false;
		$isRevision = wp_is_post_revision( $post ) !== false;

		if ( $isAutosave || $isRevision ) {
			return false;
		}

		// Don't trigger for other posts
		// phpcs:ignore WordPress.Security.NonceVerification -- before doing nonce verification
		if ( empty( $_POST['post_ID'] ) || intval( $_POST['post_ID'] ) !== $post->ID ) {
			return false;
		}

		// Is this a valid request?
		if ( ! $this->checkNonce() ) {
			return false;
		}

		return true;
	}

	protected function checkNonce(): bool {
		$metaboxSlug = $this->getSlug();

		if ( ! isset( $_REQUEST["{$metaboxSlug}_nonce"] ) ) {
			return false;
		}

		$saveNonce = sanitize_text_field( wp_unslash( $_REQUEST["{$metaboxSlug}_nonce"] ) );

		return (bool) wp_verify_nonce( $saveNonce, "save_{$metaboxSlug}" );
	}

	protected function createFields(): array {
		$metaboxSlug = $this->getSlug();

		/**
		 * @param array $fields <code>[ Prefixed/unprefixed name => Field args (array) ]</code>
		 */
		$rawFields = apply_filters( "{$metaboxSlug}_fields", $this->getRawFields() );

		$fields = array();

		foreach ( $rawFields as $alias => $args ) {
			$fields[ $alias ] = FieldFactory::create( mphb_prefix( $alias ), $args );
		}

		$postId = $this->editPage->getPostId();

		if ( $postId !== 0 ) {
			foreach ( $fields as $field ) {
				if ( $field->getType() === 'dynamic-select' ) {
					$field->updateDependency( get_post_meta( $postId, $field->getDependencyInput(), true ) );
				}

				if ( $field->getType() === 'post-id' ) {
					$field->setValue( $postId );
				} else {
					$field->setValue( get_post_meta( $postId, $field->getName(), $field->isUnique() ) );
				}
			}
		}

		return $fields;
	}

	// protected function displayFullwidth(): void -- see MetaBoxGroup::renderFullWideTableMetaBox()

	protected function displayRegular(): void {
		echo '<table class="form-table">';
			echo '<tbody>';
				$isTranslationPage = MPHB()->translation()->isTranslationPage();

				foreach ( $this->getFields() as $field ) {
					// Skip untranslatable fields on non-default languages
					if ( $isTranslationPage && ! $field->isTranslatable() ) {
						continue;
					}

					echo '<tr class="' . esc_attr( "mphb-{$field->getType()}-row" ) . '">';
						if ( $field->hasLabel() ) {
							echo '<th>', $field->getLabelTag(), '</th>'; // phpcs:ignore -- HTML content
						}

						echo '<td colspan="' . ( $field->hasLabel() ? 1 : 2 ) . '">';
							echo $field->render(); // phpcs:ignore -- HTML content
						echo '</td>';
					echo '</tr>';
				}
			echo '</tbody>';
		echo '</table>';
	}

	protected function displaySide(): void {
		echo '<div class="mphb-side-meta-box">';
			$isTranslationPage = MPHB()->translation()->isTranslationPage();

			foreach ( $this->getFields() as $field ) {
				// Skip untranslatable fields on non-default languages
				if ( $isTranslationPage && ! $field->isTranslatable() ) {
					continue;
				}

				echo '<div class="mphb-meta-field">';
					echo '<div>', $field->getLabelTag(), '</div>'; // phpcs:ignore -- HTML content
					echo $field->render(); // phpcs:ignore -- HMLT content
				echo '</div>';
			}
		echo '</div>';
	}

	/**
	 * @return InputField[]
	 */
	protected function getEditableFields(): array {
		return array_filter( $this->getFields(), fn( InputField $field ) => $field->isEditable() );
	}

	/**
	 * @return InputField[]
	 */
	protected function getFields(): array {
		if ( $this->fields === null ) {
			$this->fields = $this->createFields();
		}

		return $this->fields;
	}

	abstract protected function getRawFields(): array;

	protected function hasTranslatableFields(): bool {
		foreach ( $this->getFields() as $field ) {
			if ( $field->isTranslatable() ) {
				return true;
			}
		}

		return false;
	}

	protected function isEnabled(): bool {
		if ( ! MPHB()->translation()->isTranslationPage() ) {
			$isEnabled = true;
		} else {
			$isEnabled = MPHB()->translation()->isTranslatablePostType( $this->editPage->getPostType() )
				&& $this->hasTranslatableFields();
		}

		$metaboxSlug = $this->getSlug();

		return apply_filters( "{$metaboxSlug}_enabled", $isEnabled );
	}

	protected function parseValues( $input = null ): array {
		if ( $input === null ) {
			$input = $_POST;
		}

		$values = array();

		foreach ( $this->getEditableFields() as $alias => $field ) {
			$value = $input[ $field->getName() ] ?? null;

			if ( $value !== null ) {
				$value = $field->sanitize( $value );

				if ( $field instanceof TextField && $field->isEncoded() ) {
					$values[ "{$alias}__decoded" ] = $value;

					$value = StringEncryptHelper::encryptString( $value );
				}

				$values[ $alias ] = $value;
			}
		}

		return $values;
	}

	protected function saveFields( \WP_Post $post, array $values ): void {
		if ( empty( $values ) ) {
			return;
		}

		foreach ( $this->getEditableFields() as $alias => $field ) {
			$value = $values[ $alias ] ?? null;

			// Save or delete field
			$name = $field->getName();

			if ( $value === null ) {
				delete_post_meta( $post->ID, $name );

			} elseif ( $field->isUnique() || ! is_array( $value ) ) {
				update_post_meta( $post->ID, $name, $value );

			} else {
				delete_post_meta( $post->ID, $name );

				foreach ( $value as $item ) {
					add_post_meta( $post->ID, $name, $item );
				}
			}
		}
	}
}
