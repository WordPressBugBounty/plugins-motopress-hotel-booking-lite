<?php

namespace MPHB\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PlaceholderField extends InputField {
	const TYPE = 'placeholder';

	public function isEditable(): bool {
		return false;
	}

	public function sanitize( $value ) {
		return $value;
	}

	protected function renderInput() {
		return '<label>' . $this->default . '</label>';
	}

	public static function renderValue( self $field ) {
		return '-';
	}
}
