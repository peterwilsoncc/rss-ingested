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

use PWCC\RssIngested\Settings;

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
	add_filter( 'post_type_link', __NAMESPACE__ . '\\syndicated_post_permalink', 10, 2 );
	add_filter( 'term_link', __NAMESPACE__ . '\\syndicated_site_term_link', 10, 3 );
	add_filter( 'the_title_rss', __NAMESPACE__ . '\\syndicated_post_title_rss', 10 );
	add_action( 'init', __NAMESPACE__ . '\\register_cpt' );
	add_action( 'init', __NAMESPACE__ . '\\register_custom_taxonomy' );
	add_action( 'init', __NAMESPACE__ . '\\register_expired_post_status' );
	add_action( 'init', __NAMESPACE__ . '\\register_custom_block' );
}

/**
 * Register the custom RSS Ingested block.
 *
 * This is basically the latest posts block but with a custom query
 * to account for the custom post type and taxonomy.
 */
function register_custom_block() {
	// Only available in 6.8 and later.
	wp_register_block_types_from_metadata_collection( dirname( __DIR__ ) . '/build', dirname( __DIR__ ) . '/build/blocks-manifest.php' );

	$settings = array(
		'taxonomy' => Settings\get_syndicated_site_taxonomy(),
		'postType' => Settings\get_syndicated_feed_post_type(),
	);

	$script = '
	var PWCC = window.PWCC || {};
	PWCC.rssIngestedSettings = function() {
		return ' . wp_json_encode( $settings ) . '
	};
	';

	wp_add_inline_script(
		'pwcc-rss-ingested-editor-script',
		$script,
		'before'
	);
}

/**
 * Register the custom post type.
 */
function register_cpt() {
	if ( 'rss_syndicated_post' !== Settings\get_syndicated_feed_post_type() ) {
		// Don't register if the post type is not used.
		return;
	}

	register_post_type(
		'rss_syndicated_post',
		array(
			'label'               => __( 'Syndicated Posts', 'rss-ingested' ),
			'public'              => true,
			'show_in_rest'        => true,
			'rest_base'           => 'syndicated_posts',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
			'rewrite'             => array( 'slug' => 'syndicated-posts' ),
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-rss',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'hierarchical'        => false,
			'exclude_from_search' => false,
		)
	);
}

/**
 * Register the custom taxonomy for syndicated sites.
 */
function register_custom_taxonomy() {
	if ( 'rss_syndicated_site' !== Settings\get_syndicated_site_taxonomy() ) {
		// Don't register if the taxonomy is not used.
		return;
	}

	$labels = array(
		'name'          => __( 'Syndicated Sites', 'rss-ingested' ),
		'singular_name' => __( 'Syndicated Site', 'rss-ingested' ),
		'menu_name'     => __( 'Syndicated Sites', 'rss-ingested' ),
		'all_items'     => __( 'All Syndicated Sites', 'rss-ingested' ),
		'edit_item'     => __( 'Edit Syndicated Site', 'rss-ingested' ),
		'view_item'     => __( 'View Syndicated Site', 'rss-ingested' ),
		'update_item'   => __( 'Update Syndicated Site', 'rss-ingested' ),
		'add_new_item'  => __( 'Add New Syndicated Site', 'rss-ingested' ),
		'new_item_name' => __( 'New Syndicated Site Name', 'rss-ingested' ),
	);

	register_taxonomy(
		'rss_syndicated_site',
		'rss_syndicated_post',
		array(
			'label'             => __( 'Syndicated Sites', 'rss-ingested' ),
			'labels'            => $labels,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'syndicated_sites',
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'syndicated-site' ),
		)
	);
}

/**
 * Register the expired post status.
 *
 * An internal status used for posts that have expired from the feed.
 */
function register_expired_post_status() {
	register_post_status(
		'rss_post_expired',
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

	$ingested_post_type     = Settings\get_syndicated_feed_post_type();
	$ingested_post_taxonomy = Settings\get_syndicated_site_taxonomy();

	if (
		$query->get( 'post_type' ) !== $ingested_post_type
		&& $query->get( 'post_type' ) !== array( $ingested_post_type )
	) {
		// Only make changes to queries for posts.
		return;
	}

	if ( 'post' === $ingested_post_type && $query->get( 'post_type' ) === '' ) {
		// Account for default post type queries if using the default post type.
		return;
	}

	$hidden_site_ids = Settings\get_term_ids_for_undisplayed_sites();
	if ( empty( $hidden_site_ids ) ) {
		// No sites are hidden.
		return;
	}

	$hidden_tax_query = array(
		'taxonomy' => $ingested_post_taxonomy,
		'field'    => 'term_id',
		'terms'    => $hidden_site_ids,
		'operator' => 'NOT IN',
	);

	$tax_query     = $query->get( 'tax_query' );
	$new_tax_query = array();
	if ( ! is_array( $tax_query ) ) {
		$new_tax_query = array(
			array( $hidden_tax_query ),
		);
	} else {
		$new_tax_query = array(
			'relation' => 'AND',
			$hidden_tax_query,
			$tax_query,
		);
	}

	$query->set( 'tax_query', $new_tax_query );
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

	if ( Settings\get_syndicated_feed_post_type() === $post->post_type && get_post_meta( $post->ID, 'permalink', true ) ) {
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

	if ( Settings\get_syndicated_site_taxonomy() === $taxonomy && get_term_meta( $term->term_id, 'syndication_link', true ) ) {
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
