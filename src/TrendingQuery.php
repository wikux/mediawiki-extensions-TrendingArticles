<?php

namespace MediaWiki\Extension\Trending;

use LogicException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\SelectQueryBuilder;

class TrendingQuery {
	/**
	 * @return list<array{title:Title,count:int}>
	 */
	public static function getTopPagesInCategory( Title $category, int $limit ): array {
		if ( !$category->inNamespace( NS_CATEGORY ) || $limit <= 0 ) {
			return [];
		}

		$dataSource = PageViewCounter::getDataSource();

		$services = MediaWikiServices::getInstance();
		$db_provider = $services->getConnectionProvider();
		$dbr = $db_provider->getReplicaDatabase();

		$contentNamespaces = $services->getNamespaceInfo()->getContentNamespaces();

		if ( $dataSource === 'HitCounters' ) {
			if ( !class_exists( \HitCounters\HitCounters::class ) ) {
				throw new LogicException( 'HitCounters data source selected but HitCounters is not installed.' );
			}
			
			$countColumn = 'page_counter';
			$countJoin = [ 'hit_counter', null, 'page_id = cl_from' ];
		} else {
			$countColumn = 'tp_count';
			$countJoin = [ 'trending_pageview', null, 'tp_page_id = page_id' ];
		}

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'page_id',
				'page_namespace',
				'page_title',
				'view_count' => $countColumn,
			] )
			->from( 'categorylinks' )
			->join( 'page', null, 'page_id = cl_from' )
			->join( $countJoin[0], $countJoin[1], $countJoin[2] )
			->where( [
				'cl_type' => 'page',
				'page_is_redirect' => 0,
			] )
			->andWhere( $dbr->expr( 'page_namespace', '=', $contentNamespaces ) )
			->orderBy( $countColumn, SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ );

		if ( $dbr->fieldExists( 'categorylinks', 'cl_target_id', __METHOD__ ) ) {
			$queryBuilder
				->join( 'linktarget', null, 'cl_target_id = lt_id' )
				->andWhere( [
					'lt_title' => $category->getDBkey(),
					'lt_namespace' => NS_CATEGORY,
				] );
		} else {
			$queryBuilder->andWhere( [ 'cl_to' => $category->getDBkey() ] );
		}

		$res = $queryBuilder->fetchResultSet();

		$pages = [];
		foreach ( $res as $row ) {
			$title = Title::makeTitleSafe( (int)$row->page_namespace, (string)$row->page_title );

			if ( !$title || !$title->isContentPage() ) {
				continue;
			}

			$pages[] = [
				'title' => $title,
				'count' => (int)$row->view_count,
			];
		}

		return $pages;
	}
}
