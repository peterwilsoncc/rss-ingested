<?php
/**
 *  RSS Ingested
 *
 * @package           RssIngested
 * @author            Peter Wilson
 * @copyright         2025 Peter Wilson
 * @license           MIT
 */

namespace PWCC\RssIngested\RedirectSingle;

use PWCC\RssIngested\Settings;

/**
 * Bootrap the redirect single.
 */
function bootstrap() {
	add_filter( 'allowed_redirect_hosts', __NAMESPACE__ . '\\allowed_redirect_hosts', 10, 2 );
	add_action( 'send_headers', __NAMESPACE__ . '\\redirect_to_source' );
}

/**
 * Redirect requests to a single post to the source site.
 */
function redirect_to_source() {
	if ( ! is_singular( Settings\get_syndicated_feed_post_type() ) ) {
		return;
	}

	$source_site = get_post_meta( get_the_ID(), 'permalink', true );
	if ( ! $source_site ) {
		return;
	}

	$redirect_code = 302;
	if ( in_array( wp_get_environment_type(), array( 'production', 'staging' ), true ) ) {
		$redirect_code = 301;
	}

	wp_safe_redirect( $source_site, $redirect_code, 'RSS Ingested' );
	exit;
}

/**
 * Add the feeds to allowed redirect hosts.
 *
 * @param string[] $allowed_hosts Array of allowed hosts.
 * @param string   $destination_host The host of the destination URL for the current redirect.
 *
 * @return string[] Array of allowed hosts including sites above.
 */
function allowed_redirect_hosts( $allowed_hosts, $destination_host ) {
	if ( in_array( $destination_host, $allowed_hosts, true ) ) {
		// The host is already allowed, avoid the work.
		return $allowed_hosts;
	}

	$feeds = Settings\get_syndicated_feeds();

	foreach ( $feeds as $feed ) {
		$allowed_hosts[] = strtolower( wp_parse_url( $feed['feed_url'], PHP_URL_HOST ) );
		$allowed_hosts[] = strtolower( wp_parse_url( $feed['site_link'], PHP_URL_HOST ) );
	}

	return $allowed_hosts;
}
