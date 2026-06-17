<?php
/**
 * Plugin Name: WP Content Repurposer
 * Plugin URI:  https://bilalmahmood.dev
 * Description: Repurpose any WordPress post into a LinkedIn post, Twitter/X thread, and email newsletter intro — with one click using Claude AI.
 * Version:     1.4.0
 * Author:      Bilal Mahmood
 * Author URI:  https://bilalmahmood.dev
 * License:     GPL-2.0+
 * Text Domain: wp-content-repurposer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCR_VERSION', '1.4.0' );
define( 'WCR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCR_URL', plugin_dir_url( __FILE__ ) );

require_once WCR_PATH . 'includes/class-settings.php';
require_once WCR_PATH . 'includes/class-repurposer.php';
require_once WCR_PATH . 'includes/class-meta-box.php';

add_action( 'plugins_loaded', 'wcr_init' );

function wcr_init() {
    new WCR_Settings();
    new WCR_Meta_Box();
}
