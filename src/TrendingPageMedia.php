<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use ExtensionRegistry;
use MediaWiki\Request\FauxRequest;

class TrendingPageMedia {
	/**
	 * @param list<array{title:\MediaWiki\Title\Title,count:int}> $pages
	 * @return array<int,array{thumbnail?:array{source:string,width:int,height:int},extract?:string}>
	 */
	public static function getForPages( array $pages, int $thumb_size, bool $include_extract ): array {
		$page_ids = [];
		foreach ( $pages as $entry ) {
			$page_id = $entry['title']->getArticleID();
			if ( $page_id > 0 ) {
				$page_ids[] = $page_id;
			}
		}

		if ( $page_ids === [] ) {
			return [];
		}

		$result = [];
		foreach ( $page_ids as $page_id ) {
			$result[$page_id] = [];
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$images = \PageImages\PageImages::getImages( $page_ids, $thumb_size );

			foreach ( $images as $page_id => $data ) {
				$page_id = (int)$page_id;

				if ( !isset( $data['thumbnail'] ) || !is_array( $data['thumbnail'] ) ) {
					continue;
				}

				$thumb = $data['thumbnail'];
				$source = self::normalizeApiText( $thumb['source'] ?? '' );

				if ( $source === '' ) {
					continue;
				}

				$result[$page_id]['thumbnail'] = [
					'source' => $source,
					'width' => (int)( $thumb['width'] ?? 0 ),
					'height' => (int)( $thumb['height'] ?? 0 ),
				];
			}
		}

		if ( $include_extract && ExtensionRegistry::getInstance()->isLoaded( 'TextExtracts' ) ) {
			$extracts = self::getExtracts( $page_ids );

			foreach ( $extracts as $page_id => $extract ) {
				$result[$page_id]['extract'] = $extract;
			}
		}

		return $result;
	}

	/**
	 * @param int[] $page_ids
	 * @return array<int,string>
	 */
	private static function getExtracts( array $page_ids ): array {
		$extracts = [];

		foreach ( array_chunk( $page_ids, ApiBase::LIMIT_SML1 ) as $chunk ) {
			$request = new FauxRequest( [
				'action' => 'query',
				'prop' => 'extracts',
				'pageids' => implode( '|', $chunk ),
				'exintro' => true,
				'explaintext' => true,
				'exchars' => 120,
				'exlimit' => 'max',
			] );

			$api = new ApiMain( $request );
			$api->execute();

			$pages = (array)$api->getResult()->getResultData(
				[ 'query', 'pages' ],
				[ 'Strip' => 'base' ]
			);

			foreach ( $pages as $page_id => $data ) {
				if ( !is_array( $data ) || !isset( $data['extract'] ) ) {
					continue;
				}

				$extract = self::normalizeApiText( $data['extract'] );

				if ( $extract !== '' ) {
					$extracts[(int)$page_id] = $extract;
				}
			}
		}

		return $extracts;
	}

	private static function normalizeApiText( mixed $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) && isset( $value['*'] ) && is_string( $value['*'] ) ) {
			return $value['*'];
		}
		
		return '';
	}
}
