<?php
/**
 * Product mapping functions for Awana Digital Sync
 *
 * @package Awana_Digital_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find WooCommerce product by product ID from Digital system
 *
 * @param mixed $product_id Product ID from Digital system (can be WooCommerce product ID or SKU).
 * @return WC_Product|false
 */
function awana_find_product( $product_id ) {
	if ( empty( $product_id ) ) {
		return false;
	}

	// Try to find product by ID first
	$product = wc_get_product( $product_id );

	// If not found, try SKU
	if ( ! $product ) {
		$product_id_by_sku = wc_get_product_id_by_sku( $product_id );
		if ( $product_id_by_sku ) {
			$product = wc_get_product( $product_id_by_sku );
		}
	}

	return $product;
}

/**
 * Add product line to order
 *
 * @param WC_Order $order WooCommerce order object.
 * @param array    $line_data Line item data from Digital system.
 * @return array Array with 'success' boolean and 'error' message if failed.
 */
function awana_add_product_line_to_order( $order, $line_data ) {
	$product = awana_find_product( $line_data['productId'] ?? null );

	if ( ! $product ) {
		return array(
			'success' => false,
			'error'   => sprintf( 'Product not found for productId: %s', $line_data['productId'] ?? 'N/A' ),
		);
	}

	$quantity = ! empty( $line_data['quantity'] ) ? absint( $line_data['quantity'] ) : 1;

	// Add product to order (WooCommerce will use product's price automatically)
	$item = $order->add_product( $product, $quantity );

	// Store VAT information in line item meta if provided
	if ( ! empty( $line_data['vatRate'] ) ) {
		wc_add_order_item_meta( $item->get_id(), '_vat_rate', $line_data['vatRate'] );
	}
	if ( ! empty( $line_data['vatCode'] ) ) {
		wc_add_order_item_meta( $item->get_id(), '_vat_code', $line_data['vatCode'] );
	}
	if ( ! empty( $line_data['vat'] ) ) {
		wc_add_order_item_meta( $item->get_id(), '_vat_amount', $line_data['vat'] );
	}
	if ( ! empty( $line_data['description'] ) ) {
		// Update line item name if custom description provided
		$item->set_name( $line_data['description'] );
	}

	return array(
		'success' => true,
		'item'    => $item,
	);
}

