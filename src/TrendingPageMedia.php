<?php

namespace MediaWiki\Extension\Trending;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;

class TrendingPageMedia {
	/**
	 * @param list<array{title:\MediaWiki\Title\Title,count:int}> $pages
	 * @return array<int,array{
	 *   thumbnail?:array{source:string,width:int,height:int},
	 *   display_title?:string,
	 *   shortdesc?:string
	 * }>
	 */
	public static function getForPages( array $pages, int $thumb_size ): array {
		$page_ids = [];
		$titles_by_id = [];
		foreach ( $pages as $entry ) {
			$page_id = $entry['title']->getArticleID();
			if ( $page_id > 0 ) {
				$page_ids[] = $page_id;
				$titles_by_id[$page_id] = $entry['title'];
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

		$page_props = MediaWikiServices::getInstance()->getPageProps()->getProperties(
			$titles_by_id,
			[ 'displaytitle', 'shortdesc' ]
		);

		foreach ( $page_props as $page_id => $props ) {
			$page_id = (int)$page_id;

			if ( !is_array( $props ) ) {
				continue;
			}

			$display_title = $props['displaytitle'] ?? '';
			if ( is_string( $display_title ) && $display_title !== '' ) {
				$result[$page_id]['display_title'] = $display_title;
			}

			$shortdesc = $props['shortdesc'] ?? '';
			if ( is_string( $shortdesc ) && $shortdesc !== '' ) {
				$result[$page_id]['shortdesc'] = $shortdesc;
			}
		}

		return $result;
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
