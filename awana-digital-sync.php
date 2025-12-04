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
include_once 'includes/class-awana-rest-controller.php';

// Initialize the plugin
Awana_REST_Controller::init();

/**
 * Detect when Integrera updates pog_customer_number and sync to CRM
 * Integrera updates order meta directly, bypassing our API endpoint
 */
add_action( 'updated_postmeta', function( $meta_id, $object_id, $meta_key, $meta_value ) {
	// Only process pog_customer_number updates
	if ( $meta_key !== 'pog_customer_number' ) {
		return;
	}

	// Check if this is a WooCommerce order
	$order = wc_get_order( $object_id );
	if ( ! $order ) {
		return;
	}

	// Check if this order has the required CRM meta fields
	$invoice_id = $order->get_meta( 'crm_invoice_id', true );
	$member_id = $order->get_meta( 'crm_member_id', true );
	
	if ( empty( $invoice_id ) || empty( $member_id ) ) {
		return; // Not an Awana order, skip
	}

	// Check if we've already synced this POG customer number to CRM
	$last_synced_pog_number = $order->get_meta( '_pog_customer_synced_to_crm', true );
	
	// If this value was already synced, skip
	if ( $last_synced_pog_number === $meta_value ) {
		return;
	}

	// If there's no previous sync record, this is likely a new customer
	// (Integrera just created them in POG)
	$is_new_customer = empty( $last_synced_pog_number );

	if ( $is_new_customer && ! empty( $meta_value ) ) {
		Awana_Logger::info(
			'POG customer number updated by Integrera - syncing to CRM',
			array(
				'order_id'           => $object_id,
				'pog_customer_number' => $meta_value,
			)
		);

		// Use the existing webhook function
		Awana_CRM_Webhook::notify_pog_customer_number_to_crm( $order, $meta_value );
		
		// Mark as synced to prevent duplicate webhooks
		$order->update_meta_data( '_pog_customer_synced_to_crm', $meta_value );
		$order->save();
	}
}, 10, 4 );

/**
 * Detect pog_customer_number updates via order save hook (HPOS compatible backup)
 * This ensures compatibility with High-Performance Order Storage
 */
add_action( 'woocommerce_after_order_object_save', function( $order ) {
	// Only process if this is an Awana order
	$invoice_id = $order->get_meta( 'crm_invoice_id', true );
	$member_id = $order->get_meta( 'crm_member_id', true );
	
	if ( empty( $invoice_id ) || empty( $member_id ) ) {
		return; // Not an Awana order
	}

	$current_pog_number = $order->get_meta( 'pog_customer_number', true );
	$last_synced = $order->get_meta( '_pog_customer_synced_to_crm', true );
	
	// If we have a POG number that hasn't been synced yet
	if ( ! empty( $current_pog_number ) && $last_synced !== $current_pog_number ) {
		$is_new_customer = empty( $last_synced );
		
		if ( $is_new_customer ) {
			Awana_Logger::info(
				'New POG customer detected on order save - syncing to CRM',
				array(
					'order_id'           => $order->get_id(),
					'pog_customer_number' => $current_pog_number,
				)
			);

			Awana_CRM_Webhook::notify_pog_customer_number_to_crm( $order, $current_pog_number );
			
			// Mark as synced
			$order->update_meta_data( '_pog_customer_synced_to_crm', $current_pog_number );
			$order->save();
		}
	}
}, 10, 1 );

// Declare WooCommerce HPOS (High-Performance Order Storage) compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Copy pog_product_id from product meta to order line item meta
 * so Integrera gets it per purchased product.
 * This works for both checkout orders and API-created orders.
 */
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
	$product = $item->get_product();

	if ( ! $product ) {
		return;
	}

	// Read POG product id from product meta
	$pog_product_id = get_post_meta( $product->get_id(), 'pog_product_id', true );

	if ( ! empty( $pog_product_id ) ) {
		// Attach to line item meta with same key Integrera should read
		$item->add_meta_data( 'pog_product_id', $pog_product_id, true );
	}
}, 10, 4 );
