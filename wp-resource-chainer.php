<?php
/*
  Plugin Name: WordPress Resource Chainer
  Plugin URI: http://matthiasbreuer.com
  Description: Merges your JavaScript and CSS files into one file
  Version: 1.0.0
  Author: Matthias Breuer
  Author URI: http://matthiasbreuer.com
  License: All rights reserved
*/

define('WPRC_PATH', plugin_dir_path(__FILE__));
define('WPRC_CACHE_PATH', WPRC_PATH . 'cache/');
define('WPRC_URL', plugins_url() . '/wp-resource-chainer/');
define('WPRC_CACHE_URL', WPRC_URL . 'cache/');

if (!is_admin()) {
    require_once (WPRC_PATH . 'includes/frontend.php');
}