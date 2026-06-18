<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Deferred\TransactionRoundAwareUpdate;

class PageViewCountUpdate implements DeferrableUpdate, TransactionRoundAwareUpdate {
	public function __construct(
		private readonly int $page_id
	) {
	}

	public function doUpdate(): void {
		PageViewCounter::persistIncrement( $this->page_id );
	}

	public function getTransactionRoundRequirement(): int {
		return TransactionRoundAwareUpdate::TRX_ROUND_ABSENT;
	}
}
