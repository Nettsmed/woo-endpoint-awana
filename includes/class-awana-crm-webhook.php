<?php
/**
 * CRM Webhook Handler class for Awana Digital Sync
 *
 * @package Awana_Digital_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRM Webhook Handler class
 */
class Awana_CRM_Webhook {

	/**
	 * Send webhook to CRM when POG customer is created/updated
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param mixed    $pog_customer_number POG customer number.
	 */
	public static function notify_pog_customer_created( $order, $pog_customer_number ) {
		$invoice_id = $order->get_meta( 'crm_invoice_id' );
		$member_id  = $order->get_meta( 'crm_member_id' );

		if ( empty( $invoice_id ) || empty( $member_id ) ) {
			Awana_Logger::warning(
				'Cannot send POG customer webhook - missing invoice_id or member_id',
				array(
					'order_id' => $order->get_id(),
					'invoice_id' => $invoice_id,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		$webhook_url = self::get_crm_webhook_url();
		if ( empty( $webhook_url ) ) {
			Awana_Logger::warning( 'CRM webhook URL not configured', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$payload = array(
			'invoiceId'         => $invoice_id,
			'memberId'          => $member_id,
			'pogCustomerNumber' => $pog_customer_number,
			'event'             => 'pog_customer_created',
			'timestamp'         => current_time( 'mysql' ),
		);

		return self::send_webhook( $webhook_url, $payload, 'POG customer created' );
	}

	/**
	 * Send webhook to CRM when invoice is paid
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $payment_data Payment data.
	 */
	public static function notify_invoice_paid( $order, $payment_data = array() ) {
		$invoice_id = $order->get_meta( 'crm_invoice_id' );
		$member_id  = $order->get_meta( 'crm_member_id' );

		if ( empty( $invoice_id ) || empty( $member_id ) ) {
			Awana_Logger::warning(
				'Cannot send payment webhook - missing invoice_id or member_id',
				array(
					'order_id' => $order->get_id(),
					'invoice_id' => $invoice_id,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		$webhook_url = self::get_crm_webhook_url();
		if ( empty( $webhook_url ) ) {
			Awana_Logger::warning( 'CRM webhook URL not configured', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$payload = array(
			'invoiceId'  => $invoice_id,
			'memberId'   => $member_id,
			'status'     => 'paid',
			'amountPaid' => ! empty( $payment_data['amountPaid'] ) ? $payment_data['amountPaid'] : $order->get_total(),
			'paidAt'     => $order->get_meta( '_paid_at' ) ?: current_time( 'mysql' ),
			'event'      => 'invoice_paid',
			'timestamp'  => current_time( 'mysql' ),
		);

		return self::send_webhook( $webhook_url, $payload, 'Invoice paid' );
	}

	/**
	 * Get CRM webhook URL from configuration
	 *
	 * @return string|false Webhook URL or false if not configured.
	 */
	private static function get_crm_webhook_url() {
		// Check if URL is defined in wp-config.php
		if ( defined( 'AWANA_CRM_WEBHOOK_URL' ) ) {
			return AWANA_CRM_WEBHOOK_URL;
		}

		// Check if URL is stored in options (for admin configuration)
		$url = get_option( 'awana_crm_webhook_url', '' );
		if ( ! empty( $url ) ) {
			return $url;
		}

		return false;
	}

	/**
	 * Send webhook to CRM
	 *
	 * @param string $url Webhook URL.
	 * @param array  $payload Payload data.
	 * @param string $event_name Event name for logging.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function send_webhook( $url, $payload, $event_name ) {
		$api_key = defined( 'AWANA_CRM_WEBHOOK_API_KEY' ) ? AWANA_CRM_WEBHOOK_API_KEY : '';

		$args = array(
			'method'  => 'POST',
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		);

		// Add API key to headers if configured
		if ( ! empty( $api_key ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Awana_Logger::error(
				sprintf( 'Failed to send %s webhook to CRM', $event_name ),
				array(
					'error'   => $response->get_error_message(),
					'url'     => $url,
					'payload' => $payload,
				)
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 200 && $status_code < 300 ) {
			Awana_Logger::info(
				sprintf( 'Successfully sent %s webhook to CRM', $event_name ),
				array(
					'url'         => $url,
					'status_code' => $status_code,
					'payload'     => $payload,
				)
			);
			return true;
		} else {
			Awana_Logger::warning(
				sprintf( 'CRM webhook returned non-2xx status for %s', $event_name ),
				array(
					'url'         => $url,
					'status_code' => $status_code,
					'response'    => wp_remote_retrieve_body( $response ),
					'payload'     => $payload,
				)
			);
			return new WP_Error( 'webhook_failed', 'CRM webhook returned error status', array( 'status' => $status_code ) );
		}
	}
}

