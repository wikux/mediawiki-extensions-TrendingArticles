<?php

namespace MediaWiki\Extension\Trending;

use CategoryPage;

class TrendingCategoryPage extends CategoryPage {
	public function closeShowCategory() {
		parent::closeShowCategory();
		CategoryPopularBlock::inject( $this->getTitle(), $this->getContext()->getOutput() );
	}
}
