<?php

namespace MediaWiki\Extension\Trending;

use CategoryPage;

class TrendingCategoryPage extends CategoryPage {
	public function closeShowCategory() {
		$out = $this->getContext()->getOutput();
		$skin = $this->getContext()->getSkin();

		if ( SkinHelper::isCitizen( $skin ) ) {
			CategoryPopularBlock::inject( $this->getTitle(), $out, $skin );
		}

		parent::closeShowCategory();

		if ( !SkinHelper::isCitizen( $skin ) ) {
			CategoryPopularBlock::inject( $this->getTitle(), $out, $skin );
		}
	}
}
