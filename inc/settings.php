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
	$feeds = apply_filters( 'pwp_syndicated_feeds', $feeds );

	return $feeds;
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
		$term = get_term_by( 'slug', hash( 'sha256', $site['feed_url'] ), 'category' );

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
