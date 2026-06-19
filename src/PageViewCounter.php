<?php

namespace MediaWiki\Extension\Trending;

use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\DBError;

class PageViewCounter {
	public const DAILY_RETENTION_DAYS = 7;

	/**
	 * returns the stored view count for a title using the configured data source
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
	 * count to show readers (includes the current page view when applicable, because the db increment is deferred until after output)
	 */
	public static function getDisplayCount( Title $title, ?IContextSource $context = null ): int {
		$count = self::getPageViewCount( $title );

		if ( $context !== null && self::shouldCountCurrentView( $title, $context ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * upsert a page view increment (uses onTransactionCommitOrIdle for SQLite)
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

					$date = substr( $now, 0, 8 );
					$dbw->upsert(
						'trending_pageview_daily',
						[
							'tpd_page_id' => $page_id,
							'tpd_date' => $date,
							'tpd_count' => 1,
						],
						[ [ 'tpd_page_id', 'tpd_date' ] ],
						[
							'tpd_count = tpd_count + 1',
						],
						$fname
					);

					self::maybePruneDailyCounts();
				} catch ( DBError $e ) {
					MWExceptionHandler::logException( $e );
				}
			},
			$fname
		);
	}

	/**
	 * remove all stored counts for a deleted page
	 */
	public static function deletePageCounts( int $page_id ): void {
		if ( $page_id <= 0 ) {
			return;
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$fname = __METHOD__;

		try {
			$dbw->delete( 'trending_pageview', [ 'tp_page_id' => $page_id ], $fname );
			$dbw->delete( 'trending_pageview_daily', [ 'tpd_page_id' => $page_id ], $fname );
		} catch ( DBError $e ) {
			MWExceptionHandler::logException( $e );
		}
	}

	/**
	 * drop daily rows older than the retention window used for weekly trending
	 */
	public static function pruneDailyCounts(): void {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$cutoff_date = substr(
			wfTimestamp( TS_MW, time() - self::DAILY_RETENTION_DAYS * 86400 ),
			0,
			8
		);

		try {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'trending_pageview_daily' )
				->where( $dbw->expr( 'tpd_date', '<', $cutoff_date ) )
				->caller( __METHOD__ )
				->execute();
		} catch ( DBError $e ) {
			MWExceptionHandler::logException( $e );
		}
	}

	private static function maybePruneDailyCounts(): void {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->getWithSetCallback(
			$cache->makeKey( 'trending', 'prune-daily' ),
			3600,
			static function () {
				self::pruneDailyCounts();
				return 1;
			}
		);
	}

	public static function getDataSource(): string {
		$services = MediaWikiServices::getInstance();

		/** @var ExtensionConfig $config */
		$config = $services->getService( ExtensionConfig::SERVICE_NAME );

		return $config->getDataSource();
	}

	/**
	 * determines whether or not to count a page view
	 */
	public static function shouldCountPageView( WikiPage $wikiPage, User $user ): bool {
		if ( !$wikiPage->exists() ) {
			return false;
		}

		$title = $wikiPage->getTitle();
		if ( !$title->isContentPage() ) {
			return false;
		}

		if ( $user->isAllowed( 'bot' ) || $user->isAllowed( 'hitcounter-exempt' ) ) {
			return false;
		}

		return true;
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

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

		return self::shouldCountPageView( $wikiPage, $context->getUser() );
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
