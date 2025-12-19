<?php
/**
 * Admin UI class for Awana Digital Sync
 *
 * @package Awana_Digital_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI class
 */
class Awana_Admin {

	/**
	 * Initialize the admin UI
	 */
	public static function init() {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_awana_manual_sync', array( $instance, 'handle_manual_sync_ajax' ) );
		add_action( 'wp_ajax_awana_retry_sync', array( $instance, 'handle_retry_sync_ajax' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Awana Sync', 'awana-digital-sync' ),
			__( 'Awana Sync', 'awana-digital-sync' ),
			'manage_woocommerce',
			'awana-sync',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_awana-sync' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		// Handle form submissions
		if ( isset( $_POST['awana_manual_sync'] ) && check_admin_referer( 'awana_manual_sync', 'awana_manual_sync_nonce' ) ) {
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( $order_id > 0 ) {
				$result = Awana_CRM_Webhook::sync_all_order_metadata_to_crm( $order_id );
				if ( $result['success'] ) {
					echo '<div class="notice notice-success"><p>' . esc_html( $result['message'] ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
				}
			}
		}

		if ( isset( $_POST['awana_retry_sync'] ) && check_admin_referer( 'awana_retry_sync', 'awana_retry_sync_nonce' ) ) {
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( $order_id > 0 ) {
				$result = Awana_CRM_Webhook::sync_all_order_metadata_to_crm( $order_id, true );
				if ( $result['success'] ) {
					echo '<div class="notice notice-success"><p>' . esc_html( $result['message'] ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html( $result['message'] ) . '</p></div>';
				}
			}
		}

		$failed_syncs = $this->get_failed_syncs();
		$stats = $this->get_sync_statistics();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Awana Sync Management', 'awana-digital-sync' ) ); ?></h1>

			<!-- Statistics -->
			<div class="awana-sync-stats" style="margin: 20px 0;">
				<h2><?php echo esc_html( __( 'Sync Statistics', 'awana-digital-sync' ) ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<td><strong><?php echo esc_html( __( 'Total Orders Synced', 'awana-digital-sync' ) ); ?>:</strong></td>
							<td><?php echo esc_html( $stats['total_synced'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php echo esc_html( __( 'Failed Syncs', 'awana-digital-sync' ) ); ?>:</strong></td>
							<td><?php echo esc_html( $stats['failed_count'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php echo esc_html( __( 'Success Rate', 'awana-digital-sync' ) ); ?>:</strong></td>
							<td><?php echo esc_html( $stats['success_rate'] ); ?>%</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Manual Sync -->
			<div class="awana-manual-sync" style="margin: 20px 0;">
				<h2><?php echo esc_html( __( 'Manual Sync', 'awana-digital-sync' ) ); ?></h2>
				<form method="post" action="">
					<?php wp_nonce_field( 'awana_manual_sync', 'awana_manual_sync_nonce' ); ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="order_id"><?php echo esc_html( __( 'Order ID', 'awana-digital-sync' ) ); ?></label>
								</th>
								<td>
									<input type="number" id="order_id" name="order_id" class="regular-text" min="1" required />
									<p class="description"><?php echo esc_html( __( 'Enter the WooCommerce order ID to sync.', 'awana-digital-sync' ) ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Sync Now', 'awana-digital-sync' ), 'primary', 'awana_manual_sync' ); ?>
				</form>
			</div>

			<!-- Failed Syncs -->
			<div class="awana-failed-syncs" style="margin: 20px 0;">
				<h2><?php echo esc_html( __( 'Failed Syncs', 'awana-digital-sync' ) ); ?></h2>
				<?php if ( empty( $failed_syncs ) ) : ?>
					<p><?php echo esc_html( __( 'No failed syncs found.', 'awana-digital-sync' ) ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html( __( 'Order ID', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Order Number', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Invoice ID', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Last Error', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Last Attempt', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Error Count', 'awana-digital-sync' ) ); ?></th>
								<th><?php echo esc_html( __( 'Actions', 'awana-digital-sync' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $failed_syncs as $order ) : ?>
								<tr>
									<td><?php echo esc_html( $order['order_id'] ); ?></td>
									<td><?php echo esc_html( $order['order_number'] ); ?></td>
									<td><?php echo esc_html( $order['invoice_id'] ); ?></td>
									<td>
										<span title="<?php echo esc_attr( $order['error'] ); ?>">
											<?php echo esc_html( $this->format_sync_error( $order['error'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $order['last_attempt'] ); ?></td>
									<td><?php echo esc_html( $order['error_count'] ); ?></td>
									<td>
										<form method="post" action="" style="display: inline;">
											<?php wp_nonce_field( 'awana_retry_sync', 'awana_retry_sync_nonce' ); ?>
											<input type="hidden" name="order_id" value="<?php echo esc_attr( $order['order_id'] ); ?>" />
											<?php submit_button( __( 'Retry', 'awana-digital-sync' ), 'small', 'awana_retry_sync', false ); ?>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle manual sync AJAX request
	 */
	public function handle_manual_sync_ajax() {
		check_ajax_referer( 'awana_manual_sync', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'awana-digital-sync' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( $order_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'awana-digital-sync' ) ) );
		}

		$result = Awana_CRM_Webhook::sync_all_order_metadata_to_crm( $order_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Handle retry sync AJAX request
	 */
	public function handle_retry_sync_ajax() {
		check_ajax_referer( 'awana_retry_sync', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'awana-digital-sync' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( $order_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'awana-digital-sync' ) ) );
		}

		$result = Awana_CRM_Webhook::sync_all_order_metadata_to_crm( $order_id, true );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Get failed syncs
	 *
	 * @return array Array of failed sync orders.
	 */
	private function get_failed_syncs() {
		$orders = wc_get_orders(
			array(
				'limit'      => 100,
				'meta_key'   => 'crm_sync_woo',
				'meta_value' => 'failed',
				'return'     => 'ids',
			)
		);

		$failed_syncs = array();

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$invoice_id = $order->get_meta( 'crm_invoice_id', true );
			$last_error = $order->get_meta( '_awana_sync_last_error', true );
			$last_attempt = $order->get_meta( '_awana_sync_last_attempt', true );
			$error_count = $order->get_meta( '_awana_sync_error_count', true );

			$failed_syncs[] = array(
				'order_id'     => $order_id,
				'order_number' => $order->get_order_number(),
				'invoice_id'   => $invoice_id ? $invoice_id : __( 'N/A', 'awana-digital-sync' ),
				'error'        => $last_error ? $last_error : __( 'Unknown error', 'awana-digital-sync' ),
				'last_attempt' => $last_attempt ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_attempt ) : __( 'Never', 'awana-digital-sync' ),
				'error_count'  => $error_count ? $error_count : 0,
			);
		}

		return $failed_syncs;
	}

	/**
	 * Get sync statistics
	 *
	 * @return array Statistics array.
	 */
	private function get_sync_statistics() {
		// Get all Awana orders
		$all_orders = wc_get_orders(
			array(
				'limit'      => -1,
				'meta_key'   => 'crm_invoice_id',
				'meta_compare' => 'EXISTS',
				'return'     => 'ids',
			)
		);

		$total_synced = count( $all_orders );
		$failed_count = 0;
		$success_count = 0;

		foreach ( $all_orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$sync_status = $order->get_meta( 'crm_sync_woo', true );
			if ( $sync_status === 'failed' ) {
				$failed_count++;
			} elseif ( $sync_status === 'success' ) {
				$success_count++;
			}
		}

		$success_rate = $total_synced > 0 ? round( ( $success_count / $total_synced ) * 100, 1 ) : 0;

		return array(
			'total_synced' => $total_synced,
			'failed_count' => $failed_count,
			'success_count' => $success_count,
			'success_rate' => $success_rate,
		);
	}

	/**
	 * Format sync error for display
	 *
	 * @param string $error Error message.
	 * @return string Formatted error message.
	 */
	private function format_sync_error( $error ) {
		if ( empty( $error ) ) {
			return __( 'No error message', 'awana-digital-sync' );
		}

		// Truncate long error messages
		if ( strlen( $error ) > 100 ) {
			return substr( $error, 0, 100 ) . '...';
		}

		return $error;
	}
}

