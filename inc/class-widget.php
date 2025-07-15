<?php
/**
 * RSS Ingested
 *
 * @package           RSSIngested
 * @author            Peter Wilson
 * @copyright         2025 Peter Wilson
 * @license           MIT
 */

namespace PWCC\RssIngested\Widget;

use PWCC\RssIngested\Settings;

/**
 * The widget class to display links to each of the displayed
 * syndicated feeds.
 */
class Widget extends \WP_Widget {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'rss_ingested_widget',
			__( 'RSS Ingested', 'rss-ingested' ),
			array(
				'description' => __( 'Display the syndicated feeds.', 'rss-ingested' ),
			)
		);
	}

	/**
	 * Output the widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$feeds = Settings\get_displayed_feeds();
		$feeds = wp_list_sort( $feeds, 'title' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Developer data.
		echo $args['before_widget'];

		foreach ( $feeds as $feed ) {
			if ( ! $feed['display'] ) {
				continue;
			}

			echo '<ul>';
			echo '<li><a href="' . esc_url( $feed['site_link'] ) . '">' . esc_html( $feed['title'] ) . '</a> <a href="' . esc_url( $feed['feed_url'] ) . '">(feed)</a></li>';

			echo '</ul>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Developer data.
		echo $args['after_widget'];
	}

	/**
	 * Output the widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		// No form fields.
	}

	/**
	 * Update the widget instance.
	 *
	 * @param array $new_instance New widget instance.
	 * @param array $old_instance Old widget instance.
	 * @return array Updated widget instance.
	 */
	public function update( $new_instance, $old_instance ) {
		return $old_instance;
	}
}
