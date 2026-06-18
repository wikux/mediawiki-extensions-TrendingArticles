<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Trending\ExtensionConfig;

return [
	ExtensionConfig::SERVICE_NAME => static function ( MediaWiki\MediaWikiServices $services ): ExtensionConfig {
		return new ExtensionConfig(
			new ServiceOptions(
				ExtensionConfig::OPTIONS,
				$services->getMainConfig()
			)
		);
	},
];
