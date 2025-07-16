<?php
/**
 * Test Syndication
 *
 * @package PWCC\RssIngested\Tests
 *
 * phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- term in query is fine.
 */

namespace PWCC\RssIngested\Tests;

use PWCC\RssIngested\Syndicate;
use PWCC\RssIngested\Settings;
use WP_UnitTestCase;

/**
 * Syndication Tests
 */
class Test_Syndication extends WP_UnitTestCase {

	/**
	 * Set up the initial posts for the syndication tests.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory object.
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		// Set up the test data via a request.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-latest.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Remove the filter so it doesn't get backed up in the default setup.
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test the feed term was created.
	 */
	public function test_feed_term_created() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		$this->assertInstanceof( 'WP_Term', $feed_term, 'The feed term should exist.' );
	}

	/**
	 * Test that the syndicated posts are created.
	 */
	public function test_syndicated_posts_created() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query posts for the feed.
		$query = new \WP_Query(
			array(
				'post_type'   => Settings\get_syndicated_feed_post_type(),
				'post_status' => 'all',
				'tax_query'   => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		$this->assertSame( 5, $query->found_posts, 'There should be 5 posts in the feed.' );

		// Expected post titles.
		$expected_titles = array(
			'Holiday Break',
			'State of the Word 2024: Legacy, Innovation, and Community',
			'Write Books With the Block Editor',
			'Openverse.org: A Sight for Sore Eyes',
			'WordPress 6.7.1 Maintenance Release',
		);

		$actual_titles = wp_list_pluck( $query->posts, 'post_title' );

		$this->assertSame( $expected_titles, $actual_titles, 'The post titles should match.' );
	}

	/**
	 * Ensure that posts unpublished on the ingesting site are not republished.
	 *
	 * This test is to ensure that posts that are unpublished on the ingesting site feed are not republished when the feed is fetched.
	 */
	public function test_posts_unpublished_not_republished_upon_feed_fetched() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the first published post.
		$query = new \WP_Query(
			array(
				'post_type'      => Settings\get_syndicated_feed_post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// The query should only return one post.
		$this->assertCount( 1, $query->posts, 'There should be 1 post in query.' );

		$post_id = $query->posts[0]->ID;
		$this->assertSame( 'publish', get_post_status( $post_id ), 'The post should be published initially.' );

		// Unpublish the post.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		// The post should now be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should be published initially.' );

		// Update the feed.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-latest.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// The post should still be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should still be a draft.' );
	}

	/**
	 * Ensure that posts unpublished on the ingesting site are not republished when the feed updates.
	 *
	 * This test is to ensure that posts that are unpublished on the ingesting site feed are not republished when the feed is updates.
	 */
	public function test_posts_unpublished_are_not_republished_upon_feed_update() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the first published post.
		$query = new \WP_Query(
			array(
				'post_type'      => Settings\get_syndicated_feed_post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// The query should only return one post.
		$this->assertCount( 1, $query->posts, 'There should be 1 post in query.' );

		$post_id = $query->posts[0]->ID;
		$this->assertSame( 'publish', get_post_status( $post_id ), 'The post should be published initially.' );

		// Unpublish the post.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		// The post should now be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should be published initially.' );

		// Update the feed.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-updated.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// The post should still be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should still be a draft.' );
	}

	/**
	 * Ensure that posts unpublished on the ingesting site are not republished when edited at source.
	 */
	public function test_posts_unpublished_are_not_republished_upon_post_edit() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the first published post.
		$query = new \WP_Query(
			array(
				'post_type'      => Settings\get_syndicated_feed_post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// The query should only return one post.
		$this->assertCount( 1, $query->posts, 'There should be 1 post in query.' );

		$post_id = $query->posts[0]->ID;
		$this->assertSame( 'publish', get_post_status( $post_id ), 'The post should be published initially.' );

		// Unpublish the post.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		// The post should now be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should be published initially.' );

		// Update the feed.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-edited.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// The post should still be a draft.
		$this->assertSame( 'draft', get_post_status( $post_id ), 'The post should still be a draft.' );
	}

	/**
	 * Ensure that posts that are no longer in the feed are set to expired.
	 */
	public function test_posts_no_longer_in_feed_are_set_to_expired() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the last published post.
		$query = new \WP_Query(
			array(
				'post_type'      => Settings\get_syndicated_feed_post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
				'order'          => 'DESC',
				'orderby'        => 'ID',
			)
		);

		// The query should only return one post.
		$this->assertCount( 1, $query->posts, 'There should be 1 post in query.' );

		$post_id = $query->posts[0]->ID;
		$this->assertSame( 'WordPress 6.7.1 Maintenance Release', get_the_title( $post_id ), 'The last post should be the original last post.' );
		$this->assertSame( 'publish', get_post_status( $post_id ), 'The post should be published initially.' );

		// Update the feed with a new feed that does not contain the post.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-updated.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// The post should now be expired.
		$this->assertSame( 'rss_post_expired', get_post_status( $post_id ), 'The post should be expired.' );
	}

	/**
	 * Ensure that expired posts are republished when they are in the feed again.
	 */
	public function test_expired_posts_are_republished_when_in_feed_again() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the posts in the term.
		$query = new \WP_Query(
			array(
				'post_type'   => Settings\get_syndicated_feed_post_type(),
				'post_status' => 'all',
				'tax_query'   => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// The query should return 5 posts.
		$this->assertCount( 5, $query->posts, 'There should be 5 posts in the feed.' );

		// Ensure all the posts are published.
		$post_statues = wp_list_pluck( $query->posts, 'post_status' );
		$this->assertSame( array( 'publish', 'publish', 'publish', 'publish', 'publish' ), $post_statues, 'All posts should be published.' );

		// Expire the first post.
		$first_post_id = $query->posts[0]->ID;
		wp_update_post(
			array(
				'ID'          => $first_post_id,
				'post_status' => 'rss_post_expired',
			)
		);

		// Ensure the post is expired.
		$this->assertSame( 'rss_post_expired', get_post_status( $first_post_id ), 'The first post should be expired.' );

		// Update the feed containing the expired post.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-latest.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// The post should now be published.
		$this->assertSame( 'publish', get_post_status( $first_post_id ), 'The first post should be republished.' );
	}

	/**
	 * Ensure edits to the source are reflected in the syndicated content.
	 *
	 * @dataProvider data_post_edits_at_source_are_reflected_in_syndicated_content
	 *
	 * @param int    $post_number    The post number in the feed containing an edit.
	 * @param string $edited_field   The field that was edited (prefix meta data with `meta--`).
	 * @param string $expected_value The expected value after the edit.
	 */
	public function test_post_edits_at_source_are_reflected_in_syndicated_content( $post_number, $edited_field, $expected_value ) {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Update the feed with edited content.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-edited.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Query the posts.
		$query = new \WP_Query(
			array(
				'post_type'   => Settings\get_syndicated_feed_post_type(),
				'post_status' => 'all',
				'tax_query'   => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		$post               = $query->posts[ $post_number ];
		$actual_field_value = $post->$edited_field;

		if ( str_starts_with( $edited_field, 'meta--' ) ) {
			$edited_field       = substr( $edited_field, 6 );
			$actual_field_value = get_post_meta( $post->ID, $edited_field, true );
		}

		// Ensure the edited field is updated.
		$this->assertSame( $expected_value, $actual_field_value, 'The edited field should be updated.' );
	}

	/**
	 * Data provider for the test_post_edits_at_source_are_reflected_in_syndicated_content test.
	 *
	 * @return array[] The data for the test.
	 */
	public function data_post_edits_at_source_are_reflected_in_syndicated_content() {
		return array(
			'post 1 title'            => array( 0, 'post_title', 'Post One Edited Title' ),
			'post 2 excerpt'          => array( 1, 'post_excerpt', 'Post two edited excerpt' ),
			'post 3 content'          => array( 2, 'post_content', '<p>Post three edited content</p>' ),
			'post 4 source permalink' => array( 3, 'meta--permalink', 'https://wordpress.org/news/2024/12/post-four-edited-permalink/' ),
		);
	}

	/**
	 * Test that new posts are ingested not ingested if the feed is not set to ingest.
	 */
	public function test_new_posts_are_not_ingested_for_uningested_feeds() {
		// Add the filter to prevent ingestion.
		add_filter(
			'pwcc_syndicated_feeds',
			function () {
				return array(
					array(
						'title'     => 'WordPress News',
						'feed_url'  => 'https://wordpress.org/news/feed/',
						'site_link' => 'https://wordpress.org/news/',
						'ingest'    => false,
						'display'   => true,
					),
				);
			}
		);

		// Update the feed with new content.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-updated.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Query all the posts.
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );
		$query     = new \WP_Query(
			array(
				'post_status'    => 'all',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// Get the post titles.
		$post_titles = wp_list_pluck( $query->posts, 'post_title' );

		// Ensure the new post was not ingested.
		$this->assertNotContains( 'WordPress Themes Need More Weird: A Call for Creative Digital Homes', $post_titles, 'The new post should not be ingested.' );
	}

	/**
	 * Test that updated posts for un-ingested feeds are expired.
	 */
	public function test_updated_posts_for_uningested_feeds_are_expired() {
		// Add the filter to prevent ingestion.
		add_filter(
			'pwcc_syndicated_feeds',
			function () {
				return array(
					array(
						'title'     => 'WordPress News',
						'feed_url'  => 'https://wordpress.org/news/feed/',
						'site_link' => 'https://wordpress.org/news/',
						'ingest'    => false,
						'display'   => true,
					),
				);
			}
		);

		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-edited.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Query the posts.
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );
		$query     = new \WP_Query(
			array(
				'post_status' => 'all',
				'tax_query'   => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// Get the post statuses.
		$post_statuses = wp_list_pluck( $query->posts, 'post_status' );
		$expected      = array( 'rss_post_expired', 'rss_post_expired', 'rss_post_expired', 'rss_post_expired', 'publish' );

		// Ensure the updated posts are expired.
		$this->assertSame( $expected, $post_statuses, 'The updated posts should be expired.' );
	}

	/**
	 * Test new posts are not created for uningested feeds.
	 */
	public function test_new_posts_not_created_for_uningested_feeds() {
		// Add the filter to prevent ingestion.
		add_filter(
			'pwcc_syndicated_feeds',
			function () {
				return array(
					array(
						'title'     => 'WordPress News',
						'feed_url'  => 'https://wordpress.org/news/feed/',
						'site_link' => 'https://wordpress.org/news/',
						'ingest'    => false,
						'display'   => true,
					),
				);
			}
		);

		// Update the feed with new content.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-updated.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Query the new post.
		$new_post = get_posts(
			array(
				'title'       => 'WordPress Themes Need More Weird: A Call for Creative Digital Homes',
				'post_status' => 'all',
			)
		);

		// Ensure the new post was not ingested.
		$this->assertEmpty( $new_post, 'The new post should not be ingested.' );
	}

	/**
	 * Test posts removed from the feed are expired for uningested feeds.
	 */
	public function test_posts_removed_from_feed_are_expired_for_uningested_feeds() {
		// Add the filter to prevent ingestion.
		add_filter(
			'pwcc_syndicated_feeds',
			function () {
				return array(
					array(
						'title'     => 'WordPress News',
						'feed_url'  => 'https://wordpress.org/news/feed/',
						'site_link' => 'https://wordpress.org/news/',
						'ingest'    => false,
						'display'   => true,
					),
				);
			}
		);

		// Update the feed with a new feed that does not contain the post.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-updated.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Query the removed post.
		$expired_posts = get_posts(
			array(
				'title'       => 'WordPress 6.7.1 Maintenance Release',
				'post_status' => 'all',
				'post_type'   => Settings\get_syndicated_feed_post_type(),
			)
		);
		$expired_post  = reset( $expired_posts );

		// Ensure the post is expired.
		$this->assertSame( 'rss_post_expired', get_post_status( $expired_post->ID ), 'The post should be expired.' );
	}

	/**
	 * Revisions should not be created for unchanged posts.
	 */
	public function test_unchanged_posts_at_source_do_not_create_revisions() {
		$feed_term = get_term_by( 'name', 'WordPress News', Settings\get_syndicated_site_taxonomy() );

		// Query the posts.
		$query = new \WP_Query(
			array(
				'post_status' => 'all',
				'tax_query'   => array(
					array(
						'taxonomy' => Settings\get_syndicated_site_taxonomy(),
						'field'    => 'slug',
						'terms'    => $feed_term->slug,
					),
				),
			)
		);

		// Get the first post.
		$posts = $query->posts;

		// Get the initial count of revisions for each post.
		$initial_revisions = array_map(
			function ( $post ) {
				return count( wp_get_post_revisions( $post->ID ) );
			},
			$posts
		);

		// Update the feed with the same content.
		self::filter_request( 'https://wordpress.org/news/feed/', 'wp-org-news-latest.rss' );
		Syndicate\syndicate_feed( 'https://wordpress.org/news/feed/' );

		// Get the updated count of revisions for each post.
		$updated_revisions = array_map(
			function ( $post ) {
				return count( wp_get_post_revisions( $post->ID ) );
			},
			$posts
		);

		// Ensure no revisions were created.
		$this->assertSame( $initial_revisions, $updated_revisions, 'No revisions should be created for unchanged posts.' );
	}

	/**
	 * Replace an external HTTP request with a local file.
	 *
	 * The file should be in the tests/data/requests directory.
	 *
	 * This adds a pre-flight filter to the HTTP request.
	 *
	 * @param mixed $url_to_replace   The URL of the request.
	 * @param mixed $replacement_file The file to replace the request with.
	 */
	public static function filter_request( $url_to_replace, $replacement_file ) {
		// Fail the test if the file does not exist.
		self::assertTrue( file_exists( __DIR__ . '/data/requests/' . $replacement_file ), 'The replacement file should exist.' );

		// Add the filter.
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( $url_to_replace, $replacement_file ) {
				// Normalize the URLs.
				$url            = untrailingslashit( set_url_scheme( $url ) );
				$url_to_replace = untrailingslashit( set_url_scheme( $url_to_replace ) );

				// Bail if the URLs do not match.
				if ( $url_to_replace !== $url ) {
					return $response;
				}

				// Replace the response with the file contents.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Needed for the tests.
				$response = file_get_contents( __DIR__ . '/data/requests/' . $replacement_file );
				return array(
					'body'     => $response,
					'headers'  => array(
						'content-type' => 'application/rss+xml; charset=UTF-8',
					),
					'response' => array(
						'code' => 200,
					),
				);
			},
			10,
			3
		);
	}
}
