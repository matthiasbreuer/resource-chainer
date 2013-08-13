<?php
/**
 * Plugin Name: Resource Chainer
 * Plugin URI: https://github.com/matthiasbreuer/resource-chainer
 * Description: Concatenates JavaScript and CSS resources into less files for faster page loads and better caching.
 * Author: Matthias Breuer
 * Author URI: http://www.matthiasbreuer.com
 * Version: 1.0.2
 * Network: true
 * Text Domain: resource-chainer
 * Domain Path: /lang
 * License: The MIT License
 * License URI: http://opensource.org/licenses/MIT
 */


define( 'RC_PATH', plugin_dir_path( __FILE__ ) );
define( 'RC_URL', plugin_dir_url( __FILE__ ) );

if ( ! is_multisite() ) {
	define( 'RC_CACHE_PATH', RC_PATH . 'cache/' );
	define( 'RC_CACHE_URL', RC_URL . 'cache/' );
} else {
	define( 'RC_CACHE_PATH', RC_PATH . 'cache/' . get_current_blog_id() . '/' );
	define( 'RC_CACHE_URL', RC_URL . 'cache/' . get_current_blog_id() . '/' );
}

if ( ! is_admin() ) {
	if ( ! defined( 'WP_DEBUG' )
		|| ! WP_DEBUG
		|| ! in_array(
			$GLOBALS[ 'pagenow' ],
			array( 'wp-login.php', 'wp-register.php' )
		)
	) {
		require_once( RC_PATH . 'includes/class-wprc-resource-chainer.php' );
		new WPRC_Resource_Chainer();
	}
}

register_activation_hook( __FILE__, 'rc_activate' );
function rc_activate()
{
	wp_schedule_event( time(), 'weekly', 'rc_clear_cache' );
}

register_deactivation_hook( __FILE__, 'rc_deactivate' );
function rc_deactivate()
{
	wp_clear_scheduled_hook( 'rc_clear_cache' );
}

add_filter( 'cron_schedules', 'rc_cron_add_weekly' );
function rc_cron_add_weekly( $schedules )
{
	$schedules[ 'weekly' ] = array(
		'interval' => 604800,
		'display'  => __( 'Once Weekly' )
	);

	return $schedules;
}

add_action( 'rc_clear_cache', 'rc_clear_cache' );
function rc_clear_cache()
{
	$max_cached_files = apply_filters( 'rc_max_cached_files', 25 );
	if ( $handle = opendir( RC_CACHE_PATH ) ) {
		$files = array();
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( $file == "." || $file == ".." || $file == '.htaccess' ) {
				continue;
			}
			$files[ ] = $file;
		}
		closedir( $handle );

		if ( count( $files ) > $max_cached_files ) {
			usort( $files, 'rc_order_by_modified' );

			for ( $i = 0; $i < count( $files ) - $max_cached_files; $i ++ ) {
				unlink( RC_CACHE_PATH . $files[ $i ] );
			}
		}
	}
}

function rc_order_by_modified( $a, $b )
{
	$a_time = filemtime( RC_CACHE_PATH . $a );
	$b_time = filemtime( RC_CACHE_PATH . $b );

	if ( $a_time == $b_time ) {
		return 0;
	}

	return $a_time < $b_time ? - 1 : 1;
}