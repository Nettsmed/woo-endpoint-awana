<?php
/**
 * Order Handler class for Awana Digital Sync
 *
 * @package Awana_Digital_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Handler class
 */
class Awana_Order_Handler {

	/**
	 * Create or update order from invoice data
	 *
	 * @param array $data Invoice data from Digital system.
	 * @return WC_Order|WP_Error
	 */
	public static function create_or_update_order( $data ) {
		// Validate required fields
		$required = array( 'invoiceId', 'email', 'invoiceLines' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error(
					'bad_request',
					sprintf( 'Missing required field: %s', $field ),
					array( 'status' => 400 )
				);
			}
		}

		if ( ! is_array( $data['invoiceLines'] ) || count( $data['invoiceLines'] ) === 0 ) {
			return new WP_Error(
				'bad_request',
				'invoiceLines must be a non-empty array',
				array( 'status' => 400 )
			);
		}

		// Find or create order
		$order        = awana_find_order_by_invoice_id( $data['invoiceId'] );
		$is_new_order = false;

		if ( ! $order ) {
			// Create new order
			$order = wc_create_order();

			if ( is_wp_error( $order ) ) {
				Awana_Logger::error( 'Failed to create order', array( 'error' => $order->get_error_message() ) );
				return new WP_Error(
					'order_creation_failed',
					'Failed to create WooCommerce order',
					array( 'status' => 500 )
				);
			}

			$is_new_order = true;
			Awana_Logger::info( 'Order created', array( 'order_id' => $order->get_id(), 'invoice_id' => $data['invoiceId'] ) );
		} else {
			Awana_Logger::info( 'Order found, updating', array( 'order_id' => $order->get_id(), 'invoice_id' => $data['invoiceId'] ) );
		}

		// Set order data
		self::set_order_customer_data( $order, $data );
		$line_errors = self::set_order_line_items( $order, $data['invoiceLines'] );
		self::set_order_totals( $order, $data );
		self::set_order_status( $order, $data );
		self::set_order_meta( $order, $data );

		// Save order
		$order->save();

		// Store line errors and is_new_order flag for REST response
		$order->awana_line_errors = $line_errors;
		$order->awana_is_new_order = $is_new_order;

		// Trigger action hook
		do_action( 'awana_digital_invoice_created', $order, $data );

		return $order;
	}

	/**
	 * Set customer data on order
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data Invoice data.
	 */
	private static function set_order_customer_data( $order, $data ) {
		// Set guest customer (no customer_id)
		$order->set_customer_id( 0 );

		// Set billing information
		$billing_name = ! empty( $data['organizationName'] ) ? $data['organizationName'] : ( $data['memberName'] ?? '' );
		$name_parts   = awana_split_name( $billing_name );

		$order->set_billing_email( $data['email'] );
		$order->set_billing_first_name( $name_parts['first'] );
		$order->set_billing_last_name( $name_parts['last'] );
		$order->set_billing_company( ! empty( $data['organizationName'] ) ? $data['organizationName'] : '' );

		// Map country code (no -> NO)
		$country_code = ! empty( $data['countryId'] ) ? strtoupper( $data['countryId'] ) : 'NO';
		$order->set_billing_country( $country_code );
		$order->set_shipping_country( $country_code );

		// Copy billing to shipping (minimal, as this is an invoice)
		$order->set_shipping_first_name( $name_parts['first'] );
		$order->set_shipping_last_name( $name_parts['last'] );
		$order->set_shipping_company( ! empty( $data['organizationName'] ) ? $data['organizationName'] : '' );
	}

	/**
	 * Set line items on order
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $invoice_lines Array of invoice line items.
	 * @return array Array of errors encountered.
	 */
	private static function set_order_line_items( $order, $invoice_lines ) {
		// Remove existing line items if updating
		if ( $order->get_item_count() > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$order->remove_item( $item->get_id() );
			}
		}

		$line_errors = array();

		// Add invoice lines
		foreach ( $invoice_lines as $line ) {
			$result = awana_add_product_line_to_order( $order, $line );

			if ( ! $result['success'] ) {
				$line_errors[] = $result['error'];
				Awana_Logger::warning( 'Product not found', array( 'product_id' => $line['productId'] ?? 'N/A' ) );
			}
		}

		return $line_errors;
	}

	/**
	 * Set totals and payment method on order
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data Invoice data.
	 */
	private static function set_order_totals( $order, $data ) {
		// Set currency
		$order->set_currency( $data['currency'] ?? 'NOK' );

		// Set payment method
		$order->set_payment_method( 'invoice' );
		$order->set_payment_method_title( 'Invoice' );

		// Calculate totals
		$order->calculate_totals();

		// Compare with expected total and log if mismatch
		$expected_total = ! empty( $data['total'] ) ? floatval( $data['total'] ) : 0;
		$actual_total   = $order->get_total();
		if ( abs( $expected_total - $actual_total ) > 0.01 ) {
			Awana_Logger::warning(
				'Total mismatch',
				array(
					'expected' => $expected_total,
					'actual'   => $actual_total,
					'diff'     => $expected_total - $actual_total,
					'order_id' => $order->get_id(),
				)
			);
		}
	}

	/**
	 * Set order status
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data Invoice data.
	 */
	private static function set_order_status( $order, $data ) {
		$woo_status = awana_map_status_digital_to_woo( $data['status'] ?? 'pending' );
		$order->set_status( $woo_status );
	}

	/**
	 * Set order meta data
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data Invoice data.
	 */
	private static function set_order_meta( $order, $data ) {
		// Store both _digital_* and _crm_* for compatibility
		$order->update_meta_data( '_digital_invoice_id', $data['invoiceId'] );
		$order->update_meta_data( '_crm_invoice_id', $data['invoiceId'] ); // Alias for compatibility
		
		if ( ! empty( $data['invoiceNumber'] ) ) {
			$order->update_meta_data( '_digital_invoice_number', $data['invoiceNumber'] );
		}
		if ( ! empty( $data['memberId'] ) ) {
			$order->update_meta_data( '_digital_member_id', $data['memberId'] );
			$order->update_meta_data( '_crm_member_id', $data['memberId'] ); // Alias for compatibility
		}
		if ( ! empty( $data['memberName'] ) ) {
			$order->update_meta_data( '_digital_member_name', $data['memberName'] );
		}
		if ( ! empty( $data['organizationId'] ) ) {
			$order->update_meta_data( '_digital_organization_id', $data['organizationId'] );
			$order->update_meta_data( '_crm_organization_id', $data['organizationId'] ); // Alias for compatibility
		}
		if ( ! empty( $data['organizationName'] ) ) {
			$order->update_meta_data( '_digital_organization_name', $data['organizationName'] );
		}
		if ( ! empty( $data['type'] ) ) {
			$order->update_meta_data( '_digital_type', $data['type'] );
		}
		if ( ! empty( $data['source'] ) ) {
			$order->update_meta_data( '_digital_source', $data['source'] );
		}
		$order->update_meta_data( '_digital_sync_woo', 'synced' );

		if ( ! empty( $data['pogCustomerNumber'] ) ) {
			$order->update_meta_data( '_pog_customer_number', $data['pogCustomerNumber'] );
			$order->update_meta_data( '_pog_customer_id', $data['pogCustomerNumber'] ); // Alias for compatibility
		}

		// Store invoice dates
		if ( ! empty( $data['invoiceDate'] ) ) {
			$order->update_meta_data( '_digital_invoice_date', $data['invoiceDate'] );
		}
		if ( ! empty( $data['dueDate'] ) ) {
			$order->update_meta_data( '_digital_due_date', $data['dueDate'] );
		}
	}
}

