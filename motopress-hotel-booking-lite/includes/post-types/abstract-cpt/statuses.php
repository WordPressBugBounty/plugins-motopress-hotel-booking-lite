<?php

namespace MPHB\PostTypes\AbstractCPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Statuses {
	protected $postType;
	protected $statuses = array();

	public function __construct( $postType ) {
		$this->postType = $postType;
		$this->initStatuses();
		add_action( 'init', array( $this, 'registerStatuses' ), 5 );
	}

	abstract protected function initStatuses();

	/**
	 * @return array
	 */
	public function getStatuses() {
		return $this->statuses;
	}

	abstract public function getStatusArgs( $statusName );

	public function hasStatus( string $status ): bool {
		return array_key_exists( $status, $this->statuses );
	}

	public function registerStatuses() {
		foreach ( $this->statuses as $statusName => $details ) {
			register_post_status( $statusName, $this->getStatusArgs( $statusName ) );
		}
	}

	public function getLabels() {
		$labels = array();

		foreach ( array_keys( $this->statuses ) as $statusName ) {
			$statusArgs            = $this->getStatusArgs( $statusName );
			$labels[ $statusName ] = $statusArgs['label'];
		}

		return $labels;
	}
}
