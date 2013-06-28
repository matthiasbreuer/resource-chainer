<?php
/**
 * Plugin Name: Resource Chainer
 * Plugin URI: https://github.com/matthiasbreuer/resource-chainer
 * Description: Combines your JavaScript and CSS resources into one file for faster page loads
 * Author: Matthias Breuer
 * Author URI: http://www.matthiasbreuer.com
 * Version: 1.0.0-rc4
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
		require_once( WPRC_PATH . 'includes/class-wprc-resource-chainer.php' );
		new WPRC_Resource_Chainer();
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

add_filter( 'cron_schedules', 'wprc_cron_add_weekly' );
function wprc_cron_add_weekly( $schedules )
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
	$max_cached_files = apply_filters( 'wprc_max_cached_files', 25 );
	if ( $handle = opendir( WPRC_CACHE_PATH ) ) {
		$files = array();
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( $file == "." || $file == ".." || $file == '.htaccess' ) {
				continue;
			}
			$files[ ] = $file;
		}
		closedir( $handle );

		if ( count( $files ) > $max_cached_files ) {
			usort( $files, 'wprc_order_by_modified' );

			for ( $i = 0; $i < count( $files ) - $max_cached_files; $i ++ ) {
				unlink( WPRC_CACHE_PATH . $files[ $i ] );
			}
		}
	}
}

function wprc_order_by_modified( $a, $b )
{
	$a_time = filemtime( WPRC_CACHE_PATH . $a );
	$b_time = filemtime( WPRC_CACHE_PATH . $b );

	if ( $a_time == $b_time ) {
		return 0;
	}

	return $a_time < $b_time ? - 1 : 1;
}