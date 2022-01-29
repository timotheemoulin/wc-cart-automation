<?php
/**
 * Plugin Name:     Woocommerce Cart Automation
 * Plugin URI:      https://github.com/timotheemoulin/wp-cart-automation
 * Description:     Enhance your sales with custom cart automation process linked to your .
 * Author:          Timothée Moulin
 * Author URI:      https://github.com/timotheemoulin
 * Text Domain:     wc-cart-automation
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Wc_Promo_Cart
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WCCA_PLUGIN_FILE' ) ) {
	define( 'WCCA_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WCCA_PLUGIN_NAME' ) ) {
	define( 'WCCA_PLUGIN_NAME', 'wc-cart-automation' );
}

// Include the autoloader.
include_once dirname( WCCA_PLUGIN_FILE ) . '/includes/autoloader.php';

function wcca(): WCCA\WCCA {
	return WCCA\WCCA::instance();
}

// Init everything
wcca();
