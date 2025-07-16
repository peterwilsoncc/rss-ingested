<?php
/**
 * Render the block content.
 *
 * @package           RssIngested
 * @author            Peter Wilson
 */

namespace PWCC\RssIngested\Render;

use PWCC\RssIngested\Settings;
use WP_Query;

$pwcc_rss_ingested_query_args = array(
	'post_type'           => Settings\get_syndicated_feed_post_type(),
	'posts_per_page'      => $attributes['postsToShow'],
	'post_status'         => 'publish',
	'order'               => $attributes['order'],
	'orderby'             => $attributes['orderBy'],
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
);

$pwcc_rss_ingested_block_core_latest_posts_excerpt_length = $attributes['excerptLength'];
add_filter( 'excerpt_length', 'block_core_latest_posts_get_excerpt_length', 20 );

if ( ! empty( $attributes['categories'] ) ) {
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- term__in query is fine.
	$pwcc_rss_ingested_query_args['tax_query'] = array(
		array(
			'taxonomy' => Settings\get_syndicated_site_taxonomy(),
			'field'    => 'id',
			'terms'    => array_column( $attributes['categories'], 'id' ),
		),
	);
}

$pwcc_rss_ingested_query        = new WP_Query();
$pwcc_rss_ingested_recent_posts = $pwcc_rss_ingested_query->query( $pwcc_rss_ingested_query_args );

$pwcc_rss_ingested_list_items_markup = '';

foreach ( $pwcc_rss_ingested_recent_posts as $pwcc_rss_ingested_post ) {
	$pwcc_rss_ingested_post_link = esc_url( get_permalink( $pwcc_rss_ingested_post ) );
	$pwcc_rss_ingested_title     = get_the_title( $pwcc_rss_ingested_post );

	if ( ! $pwcc_rss_ingested_title ) {
		$pwcc_rss_ingested_title = __( '(no title)', 'rss-ingested' );
	}

	$pwcc_rss_ingested_list_items_markup .= '<li>';

	$pwcc_rss_ingested_list_items_markup .= sprintf(
		'<a class="pwcc-rss-ingested-block-latest-posts__post-title" href="%1$s">%2$s</a>',
		esc_url( $pwcc_rss_ingested_post_link ),
		$pwcc_rss_ingested_title
	);

	if ( isset( $attributes['displayPostDate'] ) && $attributes['displayPostDate'] ) {
		$pwcc_rss_ingested_list_items_markup .= sprintf(
			'<time datetime="%1$s" class="pwcc-rss-ingested-block-latest-posts__post-date">%2$s</time>',
			esc_attr( get_the_date( 'c', $pwcc_rss_ingested_post ) ),
			get_the_date( '', $pwcc_rss_ingested_post )
		);
	}

	if ( isset( $attributes['displayPostContent'] ) && $attributes['displayPostContent']
		&& isset( $attributes['displayPostContentRadio'] ) && 'excerpt' === $attributes['displayPostContentRadio'] ) {
		$pwcc_rss_ingested_trimmed_excerpt = get_the_excerpt( $pwcc_rss_ingested_post );

		/*
			* Adds a "Read more" link with screen reader text.
			* [&hellip;] is the default excerpt ending from wp_trim_excerpt() in Core.
			*/
		if ( str_ends_with( $pwcc_rss_ingested_trimmed_excerpt, ' [&hellip;]' ) ) {
			/** This filter is documented in wp-includes/formatting.php */
			$pwcc_rss_ingested_excerpt_length = (int) apply_filters( 'excerpt_length', $pwcc_rss_ingested_block_core_latest_posts_excerpt_length ); //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			if ( $pwcc_rss_ingested_excerpt_length <= $pwcc_rss_ingested_block_core_latest_posts_excerpt_length ) {
				$pwcc_rss_ingested_trimmed_excerpt  = substr( $pwcc_rss_ingested_trimmed_excerpt, 0, -11 );
				$pwcc_rss_ingested_trimmed_excerpt .= sprintf(
					/* translators: 1: A URL to a post, 2: Hidden accessibility text: Post title */
					__( '… <a class="pwcc-rss-ingested-block-latest-posts__read-more" href="%1$s" rel="noopener noreferrer">Read more<span class="screen-reader-text">: %2$s</span></a>', 'rss-ingested' ),
					esc_url( $pwcc_rss_ingested_post_link ),
					esc_html( $pwcc_rss_ingested_title )
				);
			}
		}

		if ( post_password_required( $pwcc_rss_ingested_post ) ) {
			$pwcc_rss_ingested_trimmed_excerpt = __( 'This content is password protected.', 'rss-ingested' );
		}

		$pwcc_rss_ingested_list_items_markup .= sprintf(
			'<div class="pwcc-rss-ingested-block-latest-posts__post-excerpt">%1$s</div>',
			$pwcc_rss_ingested_trimmed_excerpt
		);
	}

	if ( isset( $attributes['displayPostContent'] ) && $attributes['displayPostContent']
		&& isset( $attributes['displayPostContentRadio'] ) && 'full_post' === $attributes['displayPostContentRadio'] ) {
		$pwcc_rss_ingested_post_content = html_entity_decode( $pwcc_rss_ingested_post->post_content, ENT_QUOTES, get_option( 'blog_charset' ) );

		if ( post_password_required( $pwcc_rss_ingested_post ) ) {
			$pwcc_rss_ingested_post_content = __( 'This content is password protected.', 'rss-ingested' );
		}

		$pwcc_rss_ingested_list_items_markup .= sprintf(
			'<div class="pwcc-rss-ingested-block-latest-posts__post-full-content">%1$s</div>',
			wp_kses_post( $pwcc_rss_ingested_post_content )
		);
	}

	$pwcc_rss_ingested_list_items_markup .= "</li>\n";
}

remove_filter( 'excerpt_length', 'block_core_latest_posts_get_excerpt_length', 20 );

$pwcc_rss_ingested_classes = array( 'pwcc-rss-ingested-block-latest-posts__list' );
if ( isset( $attributes['postLayout'] ) && 'grid' === $attributes['postLayout'] ) {
	$pwcc_rss_ingested_classes[] = 'is-grid';
}
if ( isset( $attributes['columns'] ) && 'grid' === $attributes['postLayout'] ) {
	$pwcc_rss_ingested_classes[] = 'columns-' . $attributes['columns'];
}
if ( isset( $attributes['displayPostDate'] ) && $attributes['displayPostDate'] ) {
	$pwcc_rss_ingested_classes[] = 'has-dates';
}
if ( isset( $attributes['displayAuthor'] ) && $attributes['displayAuthor'] ) {
	$pwcc_rss_ingested_classes[] = 'has-author';
}
if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
	$pwcc_rss_ingested_classes[] = 'has-link-color';
}

$pwcc_rss_ingested_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $pwcc_rss_ingested_classes ) ) );

printf(
	'<ul %1$s>%2$s</ul>',
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped on input.
	$pwcc_rss_ingested_wrapper_attributes,
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped on input.
	$pwcc_rss_ingested_list_items_markup
);
