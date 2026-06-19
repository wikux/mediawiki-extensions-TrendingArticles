<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Context\IContextSource;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\User\User;
use Skin;
use SkinTemplate;

class Hooks {
	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = __DIR__ . '/../sql';
		$updater->addExtensionTable( 'trending_pageview', "$dir/trending_pageview.sql" );
		$updater->addExtensionTable( 'trending_pageview_daily', "$dir/trending_pageview_daily.sql" );
		$updater->addExtensionUpdate( [ self::class . '::onSchemaUpdatePruneDailyCounts' ] );
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onSchemaUpdatePruneDailyCounts( DatabaseUpdater $updater ): void {
		PageViewCounter::pruneDailyCounts();
	}

	/**
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param RevisionRecord $deletedRev
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount
	 */
	public static function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	): void {
		PageViewCounter::deletePageCounts( $pageID );
	}

	/**
	 * @param Title $title
	 * @param mixed &$article
	 * @param IContextSource $context
	 */
	public static function onArticleFromTitle( Title $title, &$article, IContextSource $context ): bool {
		if ( $title->inNamespace( NS_CATEGORY ) ) {
			$article = new TrendingCategoryPage( $title );
		}
		return true;
	}

	/**
	 * count page views using the same hook HitCounters relies on (onPageViewUpdates)
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 */
	public static function onPageViewUpdates( WikiPage $wikiPage, User $user ): void {
		if ( !$wikiPage->exists() ) {
			return;
		}

		$title = $wikiPage->getTitle();
		if ( !$title->isContentPage() ) {
			return;
		}

		if ( $user->isRegistered() && $user->isBot() ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		/** @var ExtensionConfig $config */
		$config = $services->getService( ExtensionConfig::SERVICE_NAME );
		if ( $config->getDataSource() === 'HitCounters' ) {
			return;
		}

		$pageId = (int)$title->getArticleID();
		if ( $pageId <= 0 ) {
			return;
		}

		DeferredUpdates::addUpdate( new PageViewCountUpdate( $pageId ) );
	}

	/**
	 * fallback category block injection if ArticleFromTitle did not run (onBeforePageDisplay)
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$title = $out->getTitle();
		if ( !$title instanceof Title || !$title->exists() ) {
			return;
		}

		$request = $out->getRequest();
		if ( !$request instanceof WebRequest || $request->getVal( 'action', 'view' ) !== 'view' ) {
			return;
		}

		if ( $title->inNamespace( NS_CATEGORY ) ) {
			CategoryPopularBlock::registerStyles( $out, $skin );

			if ( !CategoryPopularBlock::wasInjected() ) {
				CategoryPopularBlock::inject( $title, $out, $skin );
			}
		}
	}

	/**
	 * page info footer (footer-info), same group as lastmod and copyright (onSkinAddFooterLinks)
	 *
	 * @param Skin $skin
	 * @param string $key
	 * @param array &$footerItems
	 */
	public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerItems ): void {
		PageViewFooterItem::addToFooterInfoLinks( $skin, $key, $footerItems );
	}

	/**
	 * page info footer item (footer-info portlet, MW 1.47+) (onSkinTemplateNavigation__Universal)
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigation__Universal( SkinTemplate $skinTemplate, array &$links ): void {
		PageViewFooterItem::addToFooterInfoPortlet( $skinTemplate, $links );
	}
}
