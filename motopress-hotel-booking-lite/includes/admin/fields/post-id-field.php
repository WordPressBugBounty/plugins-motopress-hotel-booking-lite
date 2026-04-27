<?php

namespace MPHB\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostIdField extends TextField {
	const TYPE = 'post-id';

	protected function generateAttrs() {
		$attrs = InputField::generateAttrs();
		$attrs .= ' type="text"';

		return $attrs;
	}
}
