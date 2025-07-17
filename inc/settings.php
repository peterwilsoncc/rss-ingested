<?php
/**
 * RSS Ingested
 *
 * @package           RssIngested
 * @author            Peter Wilson
 * @copyright         2025 Peter Wilson
 * @license           MIT
 */

namespace PWCC\RssIngested\Settings;

/**
 * Bootrap the settings.
 */
function bootstrap() {
}

/**
 * Array of syndicated feeds
 *
 * @todo Add all the feeds.
 *
 * @return array[] Array if syndicated feeds {
 *     Each feed should be in the following shape:
 *
 *     @type string $title     The title of the feed. Required.
 *     @type string $feed_url  The URL of the feed. Required.
 *     @type string $site_link The URL for linking to the site. Required.
 *     @type bool   $ingest    Whether to ingest the feed. Optional. Default true.
 *     @type bool   $display   Whether to display the feed. Optional. Default true.
 * }
 */
function get_syndicated_feeds() {
	$feeds = array(
		array(
			'title'     => 'bbPress',
			'feed_url'  => 'https://bbpress.org/blog/feed/',
			'site_link' => 'https://bbpress.org/',
		),
		array(
			'title'     => 'WordPress News',
			'feed_url'  => 'https://wordpress.org/news/feed/',
			'site_link' => 'https://wordpress.org/news/',
		),
		array(
			'title'     => 'WordPress Developer Blog',
			'feed_url'  => 'https://developer.wordpress.org/news/feed/',
			'site_link' => 'https://developer.wordpress.org/news/',
		),
		array(
			'title'     => 'Gutenberg Times',
			'feed_url'  => 'https://gutenbergtimes.com/feed/',
			'site_link' => 'https://gutenbergtimes.com/',
		),
		array(
			'title'     => 'WordCamp Central',
			'feed_url'  => 'https://central.wordcamp.org/feed/',
			'site_link' => 'https://central.wordcamp.org/',
		),
		array(
			'title'     => 'WordPress Tavern',
			'feed_url'  => 'https://wptavern.com/feed/',
			'site_link' => 'https://wptavern.com/',
		),
		array(
			'title'     => 'Matt',
			'feed_url'  => 'https://ma.tt/feed/?cat=-49',
			'site_link' => 'https://ma.tt/',
		),
	);

	foreach ( $feeds as $key => $feed ) {
		if ( ! isset( $feed['ingest'] ) ) {
			$feeds[ $key ]['ingest'] = true;
		}
		if ( ! isset( $feed['display'] ) ) {
			$feeds[ $key ]['display'] = true;
		}
	}

	/**
	 * Filters the syndicated feeds.
	 *
	 * @param array[] $feeds Array of syndicated feeds.
	 */
	$feeds = apply_filters( 'pwcc_syndicated_feeds', $feeds );

	return $feeds;
}

/**
 * Get the post type for syndicated feeds.
 *
 * @return string The post type for syndicated feeds.
 */
function get_syndicated_feed_post_type() {
	/**
	 * Filters the post type for syndicated feeds.
	 *
	 * The post type must be registered if modified from the default
	 *
	 * @param string $post_type The post type for syndicated feeds. Defaults to 'rss_syndicated_post'.
	 */
	return apply_filters( 'pwcc_rss_ingested_feed_post_type', 'rss_syndicated_post' );
}

/**
 * Get the taxonomy used for syndicated sites.
 *
 * @return string The taxonomy for syndicated sites.
 */
function get_syndicated_site_taxonomy() {
	/**
	 * Filters the taxonomy for syndicated sites.
	 *
	 * The taxonomy must be registered if modified from the default.
	 *
	 * @param string $taxonomy The taxonomy for syndicated sites. Defaults to 'rss_syndicated_site'.
	 */
	return apply_filters( 'pwcc_rss_ingested_site_taxonomy', 'rss_syndicated_site' );
}

/**
 * Whether to ingest the full content of syndicated posts.
 *
 * If true, the full content of the syndicated post will be included in the
 * post content. If false, the post content will only include the excerpt.
 *
 * @return bool Whether to ingest full content. Defaults to false.
 */
function ingest_full_content() {
	/**
	 * Filters whether to ingest full content for syndicated posts.
	 *
	 * @param bool $ingest Whether to ingest full content. Defaults to false.
	 */
	return apply_filters( 'pwcc_rss_ingested_ingest_full_content', false );
}

/**
 * Get the term IDs for any sites that are not being displayed.
 *
 * @return int[] Array of term IDs for sites that are not being displayed.
 */
function get_term_ids_for_undisplayed_sites() {
	$sites        = get_syndicated_feeds();
	$hidden_sites = array_filter(
		$sites,
		function ( $site ) {
			return ! $site['display'];
		}
	);

	if ( empty( $hidden_sites ) ) {
		return array();
	}

	// Get the term IDs for each of the hidden sites.
	$term_ids = array();

	foreach ( $hidden_sites as $site ) {
		$term = get_term_by( 'slug', hash( 'sha256', $site['feed_url'] ), get_syndicated_site_taxonomy() );

		if ( false !== $term ) {
			$term_ids[] = $term->term_id;
		}
	}

	return $term_ids;
}

/**
 * Get the feeds that are to be displayed.
 *
 * @return array[] Array of feeds to be displayed.
 */
function get_displayed_feeds() {
	$feeds = get_syndicated_feeds();
	$feeds = array_filter(
		$feeds,
		function ( $feed ) {
			return $feed['display'];
		}
	);

	return $feeds;
}
