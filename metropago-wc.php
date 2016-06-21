<?php
/**
 * Plugin Name: Metropago for WooCommerce
 * Description: Metropago payment gateway plugin for WooCommerce.
 * Version: 1.2
 * Author: Riverthia
 * Author URI: http://riverthia.com
 * Requires at least: 4.0
 * Tested up to: 4.4.2
 */

define('MWC_ROOT', dirname(__FILE__));
define('MWC_INDEX', plugin_dir_url(__FILE__));
define('MWC_TXTDOM', 'metropago-wc');

add_action('plugins_loaded', 'mwc_load_textdomain');
function mwc_load_textdomain(){
	load_plugin_textdomain(MWC_TXTDOM, false, plugin_basename(dirname(__FILE__)) . '/langs');
}

require MWC_ROOT . '/gateway.php';

