<?php
/**
 * RSS Ingested
 *
 * @package           RssIngested
 * @author            Peter Wilson
 * @copyright         2025 Peter Wilson
 * @license           MIT
 */

namespace PWCC\RssIngested\Syndicate;

use PWCC\RssIngested\Settings;
use WP_Query;

/**
 * Bootstrap the syndication code.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\register_cron_jobs' );
	add_action( 'pwp_syndicate_feed', __NAMESPACE__ . '\\syndicate_feed', 10, 1 );
	add_action( 'pwp_trash_expired_posts', __NAMESPACE__ . '\\trash_expired_posts' );
}

/**
 * Register a wp-cron job for each feed.
 */
function register_cron_jobs() {
	$feeds = Settings\get_syndicated_feeds();
	foreach ( $feeds as $feed ) {
		$timestamp = wp_next_scheduled( 'pwp_syndicate_feed', array( $feed['feed_url'] ) );
		if ( false === $timestamp ) {
			wp_schedule_event( time(), 'hourly', 'pwp_syndicate_feed', array( $feed['feed_url'] ) );
		}
	}

	// Trash expired posts after 30 days.
	$timestamp = wp_next_scheduled( 'pwp_trash_expired_posts' );
	if ( false === $timestamp ) {
		wp_schedule_event( time(), 'daily', 'pwp_trash_expired_posts' );
	}
}

/**
 * Trash expired posts.
 *
 * This function will trash posts that have been expired for more than 30 days.
 */
function trash_expired_posts() {
	$query = new WP_Query(
		array(
			'post_type'      => Settings\get_syndicated_feed_post_type(),
			'post_status'    => 'rss_post_expired',
			'posts_per_page' => -1,
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => '30 days ago',
				),
			),
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $query->posts ) ) {
		foreach ( $query->posts as $post_id ) {
			wp_trash_post( $post_id );
		}
	}
}

/**
 * Read a feed and syndicate the content.
 *
 * @param string $feed_url The URL of the feed to syndicate.
 */
function syndicate_feed( $feed_url ) {
	// First ensure that the feed is in the option.
	$feeds     = Settings\get_syndicated_feeds();
	$feed_urls = wp_list_pluck( $feeds, 'feed_url' );

	if ( ! in_array( $feed_url, $feed_urls, true ) ) {
		// Delete the cron job, the feed is no longer syndicated.
		wp_clear_scheduled_hook( 'pwp_syndicate_feed', array( $feed_url ) );
		return;
	}

	// Get the feed details from the option.
	$feed_data = wp_list_filter( $feeds, array( 'feed_url' => $feed_url ) );
	$feed_data = reset( $feed_data );

	// Fetch the feed.
	$response = fetch_feed( $feed_url );

	// If the feed could not be fetched, do not continue.
	if ( is_wp_error( $response ) ) {
		return 'is error';
	}

	$term = maybe_create_term( $feed_data );
	if ( is_wp_error( $term ) ) {
		// Something went wrong creating/getting the term.
		return;
	}

	// Unpublish items that are no longer in the feed.
	unpublished_expired_items( $response->get_items(), $feed_data, $term['term_id'] );

	// If the feed doesn't have any items, do not continue.
	if ( empty( $response->get_items() ) ) {
		return 'no items';
	}

	// Syndicate the feed items.
	foreach ( $response->get_items() as $item ) {
		syndicate_item( $item, $feed_data, $term['term_id'] );
	}
}

/**
 * Unpublish items that are no longer in the feed.
 *
 * As items are syndicated via RSS, there is no way of knowing
 * if a feed item has been unpublished or is no longer in the feed
 * due to a new item being added.
 *
 * This function will automatically unpublish items that are no longer
 * in the feed to prevent a stale post from being published.
 *
 * @param array $items     The feed items.
 * @param array $feed_data The feed data.
 * @param int   $term_id   The term ID for the feed.
 */
function unpublished_expired_items( $items, $feed_data, $term_id ) {
	$feed_slugs = array_map(
		function ( $item ) {
			return hash( 'sha256', $item->get_id() );
		},
		$items
	);

	$query = new WP_Query(
		array(
			'post_type'              => Settings\get_syndicated_feed_post_type(),
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- term in query is fine.
			'tax_query'              => array(
				array(
					'taxonomy' => Settings\get_syndicated_site_taxonomy(),
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		)
	);

	// Nothing to do if there are no posts.
	if ( ! $query->have_posts() ) {
		return;
	}

	$expired_posts = array();
	foreach ( $query->posts as $post ) {
		$post_guid = $post->post_name;
		if ( ! in_array( $post_guid, $feed_slugs, true ) ) {
			$expired_posts[] = $post->ID;
		}
	}

	// Unpublish the expired posts.
	foreach ( $expired_posts as $post_id ) {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'rss_post_expired',
			)
		);
	}
}


/**
 * Create or update the term for a feed.
 *
 * Creates a term for the feed based on the feed URL. If a term
 * exists, the name will be updated if it has changed.
 *
 * @param array $feed_data The feed data.
 * @return array|WP_Error The term data or a WP_Error object.
 */
function maybe_create_term( $feed_data ) {
	// Base the slug on the feed URL to allow for the site name to change.
	$term_slug  = hash( 'sha256', $feed_data['feed_url'] );
	$term_title = wp_strip_all_tags( $feed_data['title'] );

	$term = get_term_by( 'slug', $term_slug, Settings\get_syndicated_site_taxonomy() );

	if ( false === $term ) {
		$new_term = wp_insert_term(
			$term_title,
			Settings\get_syndicated_site_taxonomy(),
			array(
				'slug' => $term_slug,
			)
		);

		update_term_meta( $new_term['term_id'], 'syndication_link', wp_slash( sanitize_url( $feed_data['site_link'] ) ) );
		return $new_term;
	}

	// Update the term if the name or site link has changed.
	$term_syndication_link = get_term_meta( $term->term_id, 'syndication_link', true );
	if ( $term->name !== $term_title || $term_syndication_link !== $feed_data['site_link'] ) {
		$new_term = wp_update_term(
			$term->term_id,
			Settings\get_syndicated_site_taxonomy(),
			array(
				'name' => $term_title,
			)
		);

		update_term_meta( $new_term['term_id'], 'syndication_link', wp_slash( sanitize_url( $feed_data['site_link'] ) ) );
		return $new_term;
	}

	return array(
		'term_id'          => $term->term_id,
		'term_taxonomy_id' => $term->term_taxonomy_id,
	);
}

/**
 * Syndicate a feed item.
 *
 * Publishes the post as a syndicated post. If a post exists with the GUID
 * already, that post will be updated.
 *
 * @param object $item      The feed item to syndicate.
 * @param array  $feed_data The feed data.
 * @param int    $term_id   The term ID for the feed.
 */
function syndicate_item( $item, $feed_data, $term_id ) {
	$item_guid = $item->get_id();
	$ingesting = $feed_data['ingest'];

	/*
	 * Hash the GUID to create a unique post slug.
	 *
	 * This is a convenient way to ensure the post slug for each
	 * syndicated post is unique. It is not a security measure,
	 * therefore it is not necessary to use a salt.
	 */
	$post_slug = hash( 'sha256', $item_guid );

	$post_name_in = array(
		$post_slug,
		"{$post_slug}__trashed",
	);

	// Check if the item has already been syndicated.
	$query = new WP_Query(
		array(
			'post_type'              => Settings\get_syndicated_feed_post_type(),
			'post_status'            => 'all',
			'post_name__in'          => $post_name_in,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
		)
	);

	$updating = false;
	if ( $query->have_posts() ) {
		$updating = true;
		$post_id  = $query->posts[0]->ID;
		$old_post = get_post( $post_id );
	}

	// Ignore new posts if the feed is not set to ingest.
	if ( ! $ingesting && ! $updating ) {
		return;
	}

	$post_timestamp = $item->get_date( 'U' );
	$mysql_date_gmt = gmdate( 'Y-m-d H:i:s', $post_timestamp );

	$post_content = Settings\ingest_full_content()
		? $item->get_content()
		: $item->get_description();

	$post_data = array(
		'post_title'     => wp_strip_all_tags( $item->get_title() ),
		'post_content'   => wp_kses_post( $post_content ),
		'post_excerpt'   => wp_kses_post( $item->get_description() ),
		'post_date_gmt'  => $mysql_date_gmt,
		'post_status'    => 'publish',
		'post_type'      => Settings\get_syndicated_feed_post_type(),
		'post_name'      => $post_slug,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'to_ping'        => '',
		'meta_input'     => array(
			'syndicated_feed_guid' => $item_guid,
			'syndicated_feed_url'  => $feed_data['site_link'],
			'permalink'            => sanitize_url( $item->get_permalink() ),
		),
	);

	if ( $updating ) {
		$post_data['ID'] = $post_id;
		// Do not update the time.
		unset( $post_data['post_date_gmt'] );

		/*
		 * Only update the post status if the current status is rss_post_expired.
		 *
		 * Expired posts have been re-added to the feed and can be republished,
		 * other unpublished posts should remain unpublished as they were intentionally
		 * unpublished from the ingesting site.
		 */
		if ( 'rss_post_expired' !== $old_post->post_status ) {
			unset( $post_data['post_status'] );
		} elseif ( ! $ingesting ) {
			// Do not update expired posts if the feed is not set to ingest.
			return;
		}

		// Check if the post is unchanged.
		$old_source_permalink = get_post_meta( $post_id, 'permalink', true );
		if (
			! isset( $post_data['post_status'] )
			&& $old_post->post_title === $post_data['post_title']
			&& $old_post->post_content === $post_data['post_content']
			&& $old_post->post_excerpt === $post_data['post_excerpt']
			&& $old_source_permalink === $post_data['meta_input']['permalink']
		) {
			// Bypass the update, nothing has changed.
			return;
		}

		/*
		 * Expire the post if the feed is not set to ingest.
		 *
		 * The post has changed at the source so the post should be expired to prevent
		 * the ingesting site from showing out of date content. While this partially bypasses
		 * the ingestion setting, it is necessary as there may be legal reason the post
		 * has been removed, eg. a DMCA takedown.
		 */
		if ( ! $ingesting ) {
			$post_data                = array();
			$post_data['ID']          = $post_id;
			$post_data['post_status'] = 'rss_post_expired';
		}

		wp_update_post( $post_data );
		return;
	}

	$post_id = wp_insert_post( $post_data );
	wp_add_object_terms( $post_id, $term_id, Settings\get_syndicated_site_taxonomy() );
}
