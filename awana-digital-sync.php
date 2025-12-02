<?php
/**
 * Plugin Name: Awana Digital Sync
 * Plugin URI: https://awana.no
 * Description: Syncs invoices from Digital/CRM to WooCommerce as guest orders and handles POG/Integrera sync updates.
 * Version: 1.0.0
 * Author: Awana
 * Author URI: https://awana.no
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Text Domain: awana-digital-sync
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// Define plugin constants
define( 'AWANA_DIGITAL_SYNC_VERSION', '1.0.0' );
define( 'AWANA_DIGITAL_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'AWANA_DIGITAL_SYNC_URL', plugin_dir_url( __FILE__ ) );

// Include required files
include_once 'includes/class-awana-logger.php';
include_once 'includes/helpers.php';
include_once 'includes/product-mapping.php';
include_once 'includes/class-awana-crm-webhook.php';
include_once 'includes/class-awana-order-handler.php';
include_once 'includes/class-awana-sync-handler.php';
include_once 'includes/class-awana-rest-controller.php';

// Initialize the plugin
Awana_REST_Controller::init();
