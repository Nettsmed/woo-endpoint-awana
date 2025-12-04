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
	 * Send webhook to CRM when Integrera updates POG customer number
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param mixed    $pog_customer_number POG customer number.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function notify_pog_customer_number_to_crm( $order, $pog_customer_number ) {
		$invoice_id = $order->get_meta( 'crm_invoice_id' );
		$member_id  = $order->get_meta( 'crm_member_id' );

		if ( empty( $invoice_id ) || empty( $member_id ) ) {
			Awana_Logger::warning(
				'Cannot send POG customer number webhook - missing invoice_id or member_id',
				array(
					'order_id' => $order->get_id(),
					'invoice_id' => $invoice_id,
					'member_id' => $member_id,
				)
			);
			return false;
		}

		// Get webhook URL from wp-config.php constant (required)
		if ( ! defined( 'AWANA_POG_CUSTOMER_WEBHOOK_URL' ) || empty( AWANA_POG_CUSTOMER_WEBHOOK_URL ) ) {
			Awana_Logger::error(
				'POG customer webhook URL not configured - AWANA_POG_CUSTOMER_WEBHOOK_URL must be defined in wp-config.php',
				array( 'order_id' => $order->get_id() )
			);
			return false;
		}
		$webhook_url = AWANA_POG_CUSTOMER_WEBHOOK_URL;

		// Get API key from wp-config.php constant (required)
		if ( ! defined( 'AWANA_POG_CUSTOMER_WEBHOOK_API_KEY' ) || empty( AWANA_POG_CUSTOMER_WEBHOOK_API_KEY ) ) {
			Awana_Logger::error(
				'POG customer webhook API key not configured - AWANA_POG_CUSTOMER_WEBHOOK_API_KEY must be defined in wp-config.php',
				array( 'order_id' => $order->get_id() )
			);
			return false;
		}
		$api_key = AWANA_POG_CUSTOMER_WEBHOOK_API_KEY;

		$payload = array(
			'invoiceId'         => $invoice_id,
			'memberId'          => $member_id,
			'pog_customer_number' => $pog_customer_number,
		);

		return self::send_pog_customer_webhook( $webhook_url, $payload, $api_key, 'POG customer number sync' );
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

	/**
	 * Send webhook to POG customer number webhook endpoint
	 * Uses x-api-key header format instead of Authorization Bearer
	 *
	 * @param string $url Webhook URL.
	 * @param array  $payload Payload data.
	 * @param string $api_key API key for authentication.
	 * @param string $event_name Event name for logging.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function send_pog_customer_webhook( $url, $payload, $api_key, $event_name ) {
		$args = array(
			'method'  => 'POST',
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-key'    => $api_key,
			),
			'body'    => wp_json_encode( $payload ),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Awana_Logger::error(
				sprintf( 'Failed to send %s webhook', $event_name ),
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
				sprintf( 'Successfully sent %s webhook', $event_name ),
				array(
					'url'         => $url,
					'status_code' => $status_code,
					'payload'     => $payload,
				)
			);
			return true;
		} else {
			Awana_Logger::warning(
				sprintf( 'Webhook returned non-2xx status for %s', $event_name ),
				array(
					'url'         => $url,
					'status_code' => $status_code,
					'response'    => wp_remote_retrieve_body( $response ),
					'payload'     => $payload,
				)
			);
			return new WP_Error( 'webhook_failed', 'Webhook returned error status', array( 'status' => $status_code ) );
		}
	}
}

