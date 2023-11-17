<?php
/**
 * Plugin Name: WooCommerce Slack Integration
 * Plugin URI: 
 * Description: This plugin allows you to send notifications to Slack channels whenever payment in WooCommerce is marked as complete.
 * Version: 2.0.0
 * Author: Trevor Bice
 * Text Domain: slack-woocommerce
 * Domain Path: /languages
 * License: GPL v2 or later
 */

define('WOOCOMMERCE_SLACK_ABS_PATH', ABSPATH . "wp-content/plugins/woocommerce-slack-notifications/");

include(WOOCOMMERCE_SLACK_ABS_PATH . 'class-testmode.php');
include(WOOCOMMERCE_SLACK_ABS_PATH . 'class-settings.php');

class wp_slack_woocommerce
{
	private static $instance = false;
	public static function instance()
	{
		if (!self::$instance) {
			self::$instance = new self;
			self::$instance->init();
		}
		return self::$instance;
	}
	public function init()
	{

		if (class_exists('wp_slack_woocommerce_testmode')) {
			$wp_slack_woocommerce_testmode = new wp_slack_woocommerce_testmode;
			$wp_slack_woocommerce_testmode->init();
		}

		if (class_exists('wp_slack_woocommerce_settings')) {
			$wp_slack_woocommerce_settings = new wp_slack_woocommerce_settings;
			$wp_slack_woocommerce_settings->init();
		}

		add_action('init', array($this, 'wp_init'), 10, 3);
		// add_action( 'admin_init', array( $this, 'wp_admin_init'), 10, 3 );
	}
	public function wp_init()
	{
		$enabled = get_option('wc_settings_tab_slack_woocommerce_enable_notifications');
		if ($enabled == 'yes') {
			add_filter('woocommerce_payment_complete_order_status', array($this, 'rfvc_update_order_status'), 10, 2);
			add_action('woocommerce_order_status_completed', array($this, 'mysite_woocommerce_order_status_completed'));
			add_action('woocommerce_order_status_cancelled', array($this, 'mysite_woocommerce_order_status_cancelled'));
			add_action('woocommerce_order_status_failed', array($this, 'mysite_woocommerce_order_status_failed'));
		}
		if (is_admin()) {
			add_action('wp_ajax_test_slack', array($this, 'test_slack'));
			add_action('in_admin_footer', array($this, 'enqueue_scripts'));
		}
	}

	public function enqueue_scripts()
	{
		?>
		<script>
			jQuery(window).ready(function () {
				jQuery("#test_slack").click(function () {
					jQuery.ajax({
						type: "post",
						url: 'admin-ajax.php',
						data: {
							action: "test_slack"
						},
						success: function (response) {
							console.log(response);
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Retrieves the ID of the most recent WooCommerce order.
	 *
	 * @return int|null The ID of the last order or null if no orders are found.
	 */
	public function get_last_order_id()
	{
		global $wpdb;

		// Get all WooCommerce order statuses
		$orderStatuses = array_keys(wc_get_order_statuses());
		$formattedStatuses = implode("','", $orderStatuses);

		// Construct the query to find the latest order ID
		$query = "
			SELECT MAX(ID) FROM {$wpdb->prefix}posts
			WHERE post_type = 'shop_order'
			AND post_status IN ('$formattedStatuses')
		";

		// Execute the query and get the result
		$result = $wpdb->get_col($query);

		// Return the first element in the result or null if empty
		return reset($result);
	}


	/**
	 * Sends a test message to Slack about the latest WooCommerce order.
	 *
	 * This function checks the status of the latest order and sends a test message to Slack
	 * indicating if the order is completed or cancelled.
	 */
	public function test_slack()
	{
		// Retrieve the ID of the latest order
		$latestOrderId = $this->get_last_order_id();
		$order = wc_get_order($latestOrderId);
		$orderStatus = $order->get_status();

		// Determine the icon and status for the Slack message
		if (in_array($orderStatus, ['processing', 'completed'])) {
			$icon = get_option('wc_settings_tab_slack_woocommerce_slack_icon_completed');
			$status = "completed";
		} else {
			$icon = get_option('wc_settings_tab_slack_woocommerce_slack_icon_cancelled');
			$status = "cancelled";
		}

		// Get product details from the order
		$productDetails = '';
		$orderItems = $order->get_items();
		if (count($orderItems) == 1) {
			foreach ($orderItems as $item) {
				$productDetails = $item->get_data();
			}
		}

		// Construct the Slack message
		$customerName = $order->data['billing']['first_name'] . " " . $order->data['billing']['last_name'];
		$formattedTotal = number_format($order->data['total'], 2);
		$message = "";
		if (isset($productDetails['name'])) {
			$message = "{$productDetails['name']}\n";
		}
		$message .= "Order *#{$order->ID}* by *{$customerName}* for *{$formattedTotal}* has been {$status}";

		// Retrieve Slack channel setting and send the message
		$channel = "#" . get_option('wc_settings_tab_slack_woocommerce_channel');
		$this->slack_message($message, "Order " . ucfirst($orderStatus) . " TEST", $channel, $icon);

		// Terminate execution
		wp_die();
	}

	/**
	 * Updates the order status to 'completed' if certain conditions are met.
	 *
	 * @param string $current_status The current status of the order.
	 * @param int $order_id The ID of the WooCommerce order.
	 * @return string The updated order status.
	 */
	public function rfvc_update_order_status($current_status, $order_id)
	{
		// Create an instance of the WooCommerce Order
		$order = new WC_Order($order_id);

		// Define the statuses that allow changing to 'completed'
		$allowed_statuses = ['on-hold', 'pending', 'failed'];

		// Check if the current status is 'processing' and the order's status is one of the allowed statuses
		if ($current_status === 'processing' && in_array($order->status, $allowed_statuses)) {
			// Update status to 'completed'
			return 'completed';
		}

		// Return the original status if conditions are not met
		return $current_status;
	}

	/**
	 * Sends a message to a specified Slack channel.
	 *
	 * @param string $message The message to be sent to Slack.
	 * @param string $username The username to be displayed in Slack.
	 * @param string $channel The channel where the message will be posted.
	 * @param string $icon Emoji icon to represent the user (default is robot face emoji).
	 * @return mixed The result of the Slack API call.
	 */
	public static function slack_message($message, $username, $channel, $icon = ":robot_face:")
	{
		$slackToken = get_option('wc_settings_tab_slack_token');
		$slackUrl = "https://slack.com/api/chat.postMessage";

		// Prepare the data for POST request
		$postData = http_build_query([
			"token" => $slackToken,
			"channel" => $channel,
			"text" => $message,
			"username" => $username,
			"icon_emoji" => $icon,
		]);

		// Initialize cURL session
		$curl = curl_init($slackUrl);

		// Set cURL options
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		// Execute cURL session and close
		$result = curl_exec($curl);
		if ($result === false) {
			$result = 'cURL Error: ' . curl_error($curl);
		}
		curl_close($curl);

		return $result;
	}

	public function mysite_woocommerce_order_status_completed($order_id)
	{
		$this->woocommerce_order_status_report($order_id, 'completed');
	}
	public function mysite_woocommerce_order_status_cancelled($order_id)
	{
		$this->woocommerce_order_status_report($order_id, 'cancelled');
	}
	public function mysite_woocommerce_order_status_failed($order_id)
	{
		$this->woocommerce_order_status_report($order_id, 'failed');
	}

	/**
	 * Sends a report to Slack about WooCommerce order status.
	 *
	 * @param int $order_id The ID of the WooCommerce order.
	 * @param string $status The new status of the order.
	 * @return void
	 */
	public function woocommerce_order_status_report($order_id, $status)
	{
		// Retrieve order and order items
		$order = wc_get_order($order_id);
		$order_items = $order->get_items();

		// Initialize product name
		$product_name = "";

		// Extract product name if only one item in order
		if (count($order_items) == 1) {
			foreach ($order_items as $item) {
				$product_data = $item->get_data();
				$product_name = preg_replace("# - Other#", "", $product_data['name']);
			}
		}

		// Edge case: if no product is found, return or handle accordingly
		if (empty($product_name)) {
			return; // or handle as needed
		}

		// Determine the status phrasing
		$hasbeen = $status == 'failed' ? 'has' : 'has been';

		// Format the order total
		$formatted_total = number_format($order->data['total'], 2);

		// Construct the message
		$customer_name = $order->data['billing']['first_name'] . " " . $order->data['billing']['last_name'];
		$message = "{$product_name}\nOrder *#{$order->ID}* by *{$customer_name}* for *${formatted_total}* {$hasbeen} {$status}";

		// Retrieve Slack channel and icon settings
		$channel = "#" . get_option('wc_settings_tab_slack_woocommerce_channel');
		$icon = get_option('wc_settings_tab_slack_woocommerce_slack_icon_' . $status);

		// Send the message to Slack
		$this->slack_message($message, "Order " . ucfirst($status), $channel, $icon);
	}

}
$wp_slack_woocommerce = new wp_slack_woocommerce;
$wp_slack_woocommerce->init();
