<?php

namespace MediaWiki\Extension\Trending;

use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\DBError;

class PageViewCounter {
	/**
	 * Returns the stored view count for a title, using the configured data source.
	 */
	public static function getPageViewCount( Title $title ): int {
		$dataSource = self::getDataSource();

		if ( $dataSource === 'Trending' ) {
			return self::getTrendingCount( $title );
		}

		if ( $dataSource === 'HitCounters' ) {
			if ( class_exists( \HitCounters\HitCounters::class ) ) {
				return (int)( \HitCounters\HitCounters::getCount( $title ) ?? 0 );
			}
			throw new LogicException( 'HitCounters data source selected but HitCounters is not installed.' );
		}

		throw new LogicException( 'Invalid page view count data source: ' . $dataSource );
	}

	/**
	 * Count to show readers. Includes the current page view when applicable,
	 * because the DB increment is deferred until after output.
	 */
	public static function getDisplayCount( Title $title, ?IContextSource $context = null ): int {
		$count = self::getPageViewCount( $title );

		if ( $context !== null && self::shouldCountCurrentView( $title, $context ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * Upsert a page view increment. Uses onTransactionCommitOrIdle for SQLite.
	 */
	public static function persistIncrement( int $page_id ): void {
		if ( $page_id <= 0 ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$dbw = $services->getConnectionProvider()->getPrimaryDatabase();
		$fname = __METHOD__;
		$now = $dbw->timestamp();

		$dbw->onTransactionCommitOrIdle(
			static function () use ( $dbw, $page_id, $now, $fname ) {
				try {
					$dbw->upsert(
						'trending_pageview',
						[
							'tp_page_id' => $page_id,
							'tp_count' => 1,
							'tp_updated' => $now,
						],
						[ [ 'tp_page_id' ] ],
						[
							'tp_count = tp_count + 1',
							'tp_updated' => $now,
						],
						$fname
					);
				} catch ( DBError $e ) {
					MWExceptionHandler::logException( $e );
				}
			},
			$fname
		);
	}

	public static function getDataSource(): string {
		$services = MediaWikiServices::getInstance();
		/** @var ExtensionConfig $config */
		$config = $services->getService( ExtensionConfig::SERVICE_NAME );
		return $config->getDataSource();
	}

	private static function shouldCountCurrentView( Title $title, IContextSource $context ): bool {
		$request = $context->getRequest();
		if ( $request->getVal( 'action', 'view' ) !== 'view' ) {
			return false;
		}

		$ctxTitle = $context->getTitle();
		if ( !$ctxTitle || !$ctxTitle->equals( $title ) ) {
			return false;
		}

		if ( !$title->exists() || !$title->isContentPage() ) {
			return false;
		}

		$user = $context->getUser();
		if ( $user->isRegistered() && $user->isBot() ) {
			return false;
		}

		return true;
	}

	private static function getTrendingCount( Title $title ): int {
		$pageId = (int)$title->getArticleID();
		if ( $pageId <= 0 ) {
			return 0;
		}

		$services = MediaWikiServices::getInstance();
		$db_provider = $services->getConnectionProvider();
		$dbr = $db_provider->getReplicaDatabase();

		$row = $dbr->newSelectQueryBuilder()
			->select( [ 'tp_count' ] )
			->from( 'trending_pageview' )
			->where( [ 'tp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchRow();

		return $row ? (int)$row->tp_count : 0;
	}
}
