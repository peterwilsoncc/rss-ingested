<?php
/**
 * RSS Ingested
 *
 * @package           RssIngested
 * @author            Peter Wilson
 * @copyright         2025 Peter Wilson
 * @license           MIT
 */

namespace PWCC\RssIngested;

const PLUGIN_VERSION = '1.0.0';

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	RedirectSingle\bootstrap();
	Settings\bootstrap();
	Syndicate\bootstrap();
	Widget\bootstrap();

	add_action( 'pre_get_posts', __NAMESPACE__ . '\\remove_hidden_sites_from_post_query' );
	add_filter( 'post_link', __NAMESPACE__ . '\\syndicated_post_permalink', 10, 2 );
	add_filter( 'term_link', __NAMESPACE__ . '\\syndicated_site_term_link', 10, 3 );
	add_filter( 'the_title_rss', __NAMESPACE__ . '\\syndicated_post_title_rss', 10 );
	add_action( 'init', __NAMESPACE__ . '\\register_expired_post_status' );
}

/**
 * Register the expired post status.
 *
 * An internal status used for posts that have expired from the feed.
 */
function register_expired_post_status() {
	register_post_status(
		'pwp_expired',
		array(
			'label'                     => _x( 'Expired from feed', 'post', 'rss-ingested' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			// translators: %s: number of posts.
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'rss-ingested' ),
		)
	);
}


/**
 * Remove sites that are no longer being displayed from the post feed.
 *
 * @param WP_Query $query The query object.
 */
function remove_hidden_sites_from_post_query( $query ) {
	if ( is_admin() ) {
		// Make no changes in the admin.
		return;
	}

	if (
		$query->get( 'post_type' ) !== 'post'
		&& $query->get( 'post_type' ) !== array( 'post' )
		&& $query->get( 'post_type' ) !== ''
	) {
		// Only make changes to queries for posts.
		return;
	}

	$hidden_site_ids = Settings\get_term_ids_for_undisplayed_sites();
	if ( empty( $hidden_site_ids ) ) {
		// No sites are hidden.
		return;
	}

	$already_hidden = $query->get( 'category__not_in' );
	if ( ! is_array( $already_hidden ) ) {
		$already_hidden = array();
	}

	$query->set( 'category__not_in', array_merge( $already_hidden, $hidden_site_ids ) );
}

/**
 * Filter the permalink for syndicated posts.
 *
 * @param string  $permalink The post permalink.
 * @param WP_Post $post The post object.
 * @return string The permalink.
 */
function syndicated_post_permalink( $permalink, $post ) {
	if ( is_admin() ) {
		// Do nothing in the admin.
		return $permalink;
	}

	if ( 'post' === $post->post_type && get_post_meta( $post->ID, 'permalink', true ) ) {
		$permalink = get_post_meta( $post->ID, 'permalink', true );
	}
	return $permalink;
}

/**
 * Filter the permalink for syndicated sites.
 *
 * @param string  $term_link The term link.
 * @param WP_Term $term The term object.
 * @param string  $taxonomy The taxonomy.
 * @return string The term link.
 */
function syndicated_site_term_link( $term_link, $term, $taxonomy ) {
	if ( is_admin() ) {
		// Do nothing in the admin.
		return $term_link;
	}

	if ( 'category' === $taxonomy && get_term_meta( $term->term_id, 'syndication_link', true ) ) {
		$term_link = get_term_meta( $term->term_id, 'syndication_link', true );
	}
	return $term_link;
}

/**
 * Filter the title for syndicated posts in the RSS feed.
 *
 * @param string $title The post title.
 * @return string The post title prefixed with the feed title.
 */
function syndicated_post_title_rss( $title ) {
	$source_permalink = get_post_meta( get_the_ID(), 'permalink', true );
	$source_feed_url  = get_post_meta( get_the_ID(), 'syndicated_feed_url', true );

	if ( ! $source_permalink || ! $source_feed_url ) {
		return $title;
	}

	$feeds = Settings\get_syndicated_feeds();

	foreach ( $feeds as $feed ) {
		if ( $feed['site_link'] === $source_feed_url ) {
			$title = "{$feed['title']}: {$title}";
			break;
		}
	}

	return $title;
}
