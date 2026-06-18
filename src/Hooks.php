<?php

namespace MediaWiki\Extension\Trending;

use MediaWiki\Context\IContextSource;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\WebRequest;
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

		if ( $title->inNamespace( NS_CATEGORY ) && !CategoryPopularBlock::wasInjected() ) {
			CategoryPopularBlock::inject( $title, $out );
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
