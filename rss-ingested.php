<?php
/**
 * RSS Ingested
 *
 * @package           RssIngested
 * @author            Peter Wilson
 * @copyright         YYYY Peter Wilson
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name: RSS Ingested
 * Description: Ingest RSS Feeds for Syndication
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: Peter Wilson
 * Author URI: https://peterwilson.cc
 * License: MIT
 * Text Domain: rss-ingested
 */

namespace PWCC\RssIngested;

require_once __DIR__ . '/inc/class-widget.php';

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/redirect-single.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/syndicate.php';
require_once __DIR__ . '/inc/widget.php';

bootstrap();
