<?php
/**
 * Plugin Name: WordPress Resource Chainer
 * Plugin URI: https://github.com/matthiasbreuer/wp-resource-chainer
 * Description: Combines your JavaScript and CSS resources into one file for faster page loads
 * Author: Matthias Breuer
 * Author URI: http://www.matthiasbreuer.com
 * Version: 1.0.0
 * Network: true
 * Text Domain: wp-resource-chainer
 * Domain Path: /lang
 * License: The MIT License
 * License URI: http://opensource.org/licenses/MIT
 */

define( 'WPRC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPRC_CACHE_PATH', WPRC_PATH . 'cache/' );
define( 'WPRC_URL', plugins_url() . '/wp-resource-chainer/' );
define( 'WPRC_CACHE_URL', WPRC_URL . 'cache/' );

if ( ! is_admin() ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		require_once ( WPRC_PATH . 'includes/frontend.php' );
	}
}