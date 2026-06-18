<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Config\ServiceOptions;

// this might not need to be an independent service in the future
class ExtensionConfig {
	public const SERVICE_NAME = 'TrendingConfig';

	public const DATA_SOURCE = 'PageViewCountDataSource';
	public const CATEGORY_LIMIT = 'TrendingCategoryLimit';
	public const SHOW_COUNTS = 'TrendingShowCounts';

	public const OPTIONS = [
		self::DATA_SOURCE,
		self::CATEGORY_LIMIT,
		self::SHOW_COUNTS,
	];

	public function __construct( private readonly ServiceOptions $options ) {
		$this->options->assertRequiredOptions( self::OPTIONS );
	}

	public function getDataSource(): string {
		return $this->options->get( self::DATA_SOURCE ) ?: 'Trending';
	}

	public function getCategoryLimit(): int {
		return max( 1, (int)$this->options->get( self::CATEGORY_LIMIT ) );
	}

	public function getShowCounts(): bool {
		return (bool)$this->options->get( self::SHOW_COUNTS );
	}
}
