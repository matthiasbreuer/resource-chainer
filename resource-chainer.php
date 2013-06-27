<?php
/**
 * Plugin Name: Resource Chainer
 * Plugin URI: https://github.com/matthiasbreuer/resource-chainer
 * Description: Combines your JavaScript and CSS resources into one file for faster page loads
 * Author: Matthias Breuer
 * Author URI: http://www.matthiasbreuer.com
 * Version: 1.0.0-rc3
 * Network: true
 * Text Domain: resource-chainer
 * Domain Path: /lang
 * License: The MIT License
 * License URI: http://opensource.org/licenses/MIT
 */


define( 'WPRC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPRC_URL', plugin_dir_url( __FILE__ ) );

if ( ! is_multisite() ) {
	define( 'WPRC_CACHE_PATH', WPRC_PATH . 'cache/' );
	define( 'WPRC_CACHE_URL', WPRC_URL . 'cache/' );
} else {
	define( 'WPRC_CACHE_PATH', WPRC_PATH . 'cache/' . get_current_blog_id() . '/' );
	define( 'WPRC_CACHE_URL', WPRC_URL . 'cache/' . get_current_blog_id() . '/' );
}

if ( ! is_admin() ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		require_once( WPRC_PATH . 'includes/frontend.php' );
	}
}

register_activation_hook( __FILE__, 'wprc_activate' );
function wprc_activate()
{
	wp_schedule_event( time(), 'weekly', 'wprc_clear_cache' );
}

register_deactivation_hook( __FILE__, 'wprc_deactivate' );
function wprc_deactivate()
{
	wp_clear_scheduled_hook( 'wprc_clear_cache' );
}

add_filter( 'cron_schedules', 'cron_add_weekly' );
function cron_add_weekly( $schedules )
{
	$schedules[ 'weekly' ] = array(
		'interval' => 604800,
		'display'  => __( 'Once Weekly' )
	);

	return $schedules;
}

add_action( 'wprc_clear_cache', 'wprc_clear_cache' );
function wprc_clear_cache()
{
	if ( $handle = WPRC_CACHE_PATH ) {
		$curr_time = time();
		$files     = array();
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( $file == "." || $file == ".." || $file == '.htaccess' ) {
				continue;
			}
			$files[ ] = $file;
		}
		closedir( $handle );

		if ( count( $files ) > 30 ) {
			usort( $files, 'wprc_order_by_date_modified' );

			for ( $i = 0; $i < count( $files ) - 30; $i ++ ) {
				unlink( WPRC_CACHE_PATH . $files[ $i ] );
			}
		}
	}
}

function wprc_order_by_date_modified( $a, $b )
{
	$a_time = filemtime( WPRC_CACHE_PATH . $a );
	$b_time = filemtime( WPRC_CACHE_PATH . $b );

	if ( $a_time == $b_time ) {
		return 0;
	}

	return $a_time < $b_time ? - 1 : 1;
}