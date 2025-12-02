<?php
/**
 * Sync Handler class for Awana Digital Sync
 *
 * @package Awana_Digital_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Handler class
 */
class Awana_Sync_Handler {

	/**
	 * Handle invoice sync from POG/Integrera
	 *
	 * @param array $data Sync data from POG/Integrera.
	 * @return array|WP_Error Array with success status and updated fields, or WP_Error on failure.
	 */
	public static function handle_sync( $data ) {
		// Validate required fields
		if ( empty( $data['invoiceId'] ) ) {
			return new WP_Error(
				'bad_request',
				'Missing required field: invoiceId',
				array( 'status' => 400 )
			);
		}

		// Find order
		$order = awana_find_order_by_invoice_id( $data['invoiceId'] );

		if ( ! $order ) {
			return new WP_Error(
				'order_not_found',
				sprintf( 'Order not found for invoiceId: %s', $data['invoiceId'] ),
				array( 'status' => 404 )
			);
		}

		$updated = array();

		// Update POG customer number
		if ( ! empty( $data['updatePogCustomerNumber'] ) && ! empty( $data['pogCustomerNumber'] ) ) {
			self::update_pog_customer_number( $order, $data['pogCustomerNumber'] );
			$updated['pogCustomerNumber'] = true;
		}

		// Update invoice status / payment
		if ( ! empty( $data['updateInvoiceStatus'] ) ) {
			if ( ! empty( $data['status'] ) ) {
				self::update_invoice_status( $order, $data );
				$updated['status'] = true;
			}
		}

		// Save order if any updates were made
		if ( ! empty( $updated ) ) {
			$order->save();
		}

		// Trigger action hook
		do_action( 'awana_digital_invoice_synced', $order, $data );

		return array(
			'success'    => true,
			'wooOrderId' => $order->get_id(),
			'updated'    => $updated,
		);
	}

	/**
	 * Update POG customer number on order
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param mixed    $pog_customer_number POG customer number.
	 */
	private static function update_pog_customer_number( $order, $pog_customer_number ) {
		$existing_pog_number = $order->get_meta( 'pog_customer_number' );
		$is_new_customer = empty( $existing_pog_number );

		$order->update_meta_data( 'pog_customer_number', $pog_customer_number );
		$order->update_meta_data( '_pog_last_sync_at', current_time( 'mysql' ) );
		
		Awana_Logger::info(
			'POG customer number updated',
			array(
				'order_id'           => $order->get_id(),
				'pog_customer_number' => $pog_customer_number,
				'is_new_customer'    => $is_new_customer,
			)
		);

		// Notify CRM if this is a new POG customer
		if ( $is_new_customer ) {
			Awana_CRM_Webhook::notify_pog_customer_created( $order, $pog_customer_number );
		}
	}

	/**
	 * Update invoice status and payment information
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data Sync data.
	 */
	private static function update_invoice_status( $order, $data ) {
		$woo_status = awana_map_status_digital_to_woo( $data['status'] );
		$order->set_status( $woo_status );

		if ( $data['status'] === 'paid' ) {
			$was_already_paid = $order->is_paid();

			// Mark as payment complete
			if ( ! $was_already_paid ) {
				$order->payment_complete();
			}

			// Store payment details
			if ( ! empty( $data['amountPaid'] ) ) {
				$order->update_meta_data( '_amount_paid', floatval( $data['amountPaid'] ) );
			}
			$order->update_meta_data( '_paid_via_pog', true );
			$order->update_meta_data( '_paid_at', current_time( 'mysql' ) );

			// Notify CRM if this is a new payment (wasn't already paid)
			if ( ! $was_already_paid ) {
				Awana_CRM_Webhook::notify_invoice_paid( $order, $data );
			}
		}

		Awana_Logger::info(
			'Invoice status updated',
			array(
				'order_id' => $order->get_id(),
				'status'   => $data['status'],
			)
		);
	}
}

