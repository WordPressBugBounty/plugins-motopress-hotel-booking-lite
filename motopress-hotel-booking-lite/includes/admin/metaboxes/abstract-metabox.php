<?php

declare(strict_types=1);

namespace MPHB\Admin\Metaboxes;

use MPHB\Admin\EditCPTPages\EditCPTPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractMetabox {
	public const CONTEXT_ADVANCED = 'advanced';
	public const CONTEXT_NORMAL   = 'normal';
	public const CONTEXT_SIDE     = 'side';

	public const PRIORITY_DEFAULT = 'default';
	public const PRIORITY_HIGH    = 'high';
	public const PRIORITY_LOW     = 'low';

	protected string $context;

	protected EditCPTPage $editPage;

	protected string $priority;

	/**
	 * @param string $context "normal"|"advanced"|"side"
	 * @param string $priority "high"|"default"|"low"
	 */
	public function __construct(
		EditCPTPage $editPage,
		string $context = self::CONTEXT_NORMAL,
		string $priority = self::PRIORITY_DEFAULT
	) {
		$this->editPage = $editPage;
		$this->context  = $context;
		$this->priority = $priority;

		$this->addActions();
	}

	/**
	 * @access protected
	 */
	abstract public function display(): void;

	public function getPostType(): string {
		return $this->editPage->getPostType();
	}

	protected function addActions(): void {
		$postType = $this->editPage->getPostType();

		add_action( "mphb_register_{$postType}_metaboxes", function () {
			if ( $this->editPage->isCurrentEditPage() && $this->isEnabled() ) {
				$this->register();
			}
		} );
	}

	abstract protected function getSlug(): string;

	abstract protected function getTitle(): string;

	protected function isEnabled(): bool {
		$metaboxSlug = $this->getSlug();

		return apply_filters( "{$metaboxSlug}_enabled", true );
	}

	protected function register(): void {
		add_meta_box(
			$this->getSlug(),
			$this->getTitle(),
			array( $this, 'display' ),
			$this->editPage->getPostType(),
			$this->context,
			$this->priority
		);
	}
}
