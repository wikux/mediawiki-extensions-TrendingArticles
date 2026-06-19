<?php

namespace MediaWiki\Extension\Trending;

use Skin;

class SkinHelper {
	public static function isCitizen( Skin $skin ): bool {
		return $skin->getSkinName() === 'citizen';
	}
}
