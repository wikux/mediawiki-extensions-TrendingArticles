# TrendingArticles [BETA]

> [!CAUTION]  
> TrendingArticles is experimental. Use in production is discouraged.

TrendingArticles is a MediaWiki extension for MediaWiki 1.43+ that displays trending (or recently popular) articles on a per-category basis similar to Fandom. It can be configured to use an in-house data-source or HitCounters, but functionality is limited depending on the data source chosen. Continue reading for limitations and recommendations.

> [!NOTE]  
> TrendingArticles may be refered to as simply `Trending` with-in the source code

# Installation

## Requirements

* MediaWiki 1.43+

## Install via Git

You can use Git to install `TrendingArticles` in your MediaWiki extensions folder:

```
cd extensions/
git clone https://github.com/wikux/mediawiki-extensions-TrendingArticles.git
```

Depending on your infrastructure you may be interested in Git submodules. Alternatively, you can simply download the `TrendingArticles` folder and move it to the `extensions/` folder.

> [!IMPORTANT]  
> Running the update maintenance script is required when using the in-house data source (default/Trending), which is recommended, read below.

## Suggestions

The below extensions are also suggested to be installed (depending on your configurations):

* HitCounters (>=0.4.0)
* PageImages
* ShortDescription

# Configuration

There are two paths you can take with this extension. The first and the most modern path adds functionality like trending article blocks on each category page, but requires the [Citizen](https://www.mediawiki.org/wiki/Skin:Citizen) skin and the Trending (in-house) data source. The second path (the legacy path) simply adds a 'Most popular pages' header under each category, but works with the HitCounters data-source and any skin.

The modern path will require additional setup, such as updating the database schema via running the update.php maintenance script:

```
php maintenance/run.php update
```

This will create two new tables in your database: `trending_pageview`, `trending_pageview_daily`. You may want to verify these were successfuly created as this is an experimental extension.

# Experimental Disclaimer

View counting may be off. This extension is experimental and is designed for a specific stack. Please use Citizen as your wiki's skin and install PageImages and ShortDescription for the intended experience.