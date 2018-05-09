<?php
/**
 * Plugin Name: WooCommerce Slack Integration
 * Plugin URI: 
 * Description: This plugin allows you to send notifications to Slack channels whenever payment in WooCommerce is marked as complete.
 * Version: 1.0.2
 * Author: Trevor Bice
 * Text Domain: slack-woocommerce
 * Domain Path: /languages
 * License: GPL v2 or later
 */

class wp_slack_woocommerce {
	
	private static $instance = false;
	private $wpengine;
	
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
			self::$instance->init();
		}
		return self::$instance;
	}
 	public function init() {
		if ( ! wp_next_scheduled( 'clickandpledge_testmode_check' ) ) {
			wp_schedule_event( time(), 'hourly', array( self::instance(), 'clickandpledge_testmode_check') );
		}
		$enabled = get_option( 'wc_settings_tab_slack_woocommerce_enable_notifications' );
		if( $enabled == 'yes' ){	
			add_filter( 'woocommerce_payment_complete_order_status', 		array( self::instance(), 'rfvc_update_order_status'), 10, 2 );
			add_action( 'woocommerce_order_status_completed', 			array( self::instance(), 'mysite_woocommerce_order_status_completed'));
			add_action( 'woocommerce_order_status_cancelled', 			array( self::instance(), 'mysite_woocommerce_order_status_cancelled'));
			// add_action( 'transition_post_status',  						array( self::instance(), 'post_status_change'), 10, 3 ); 
			add_action( 'woocommerce_update_options', 					array( self::instance(), 'clickandpledge_testmode_check'), 10, 1 ); 
		}
		if( is_admin()) {
			// Only register the AJAX for admins
			add_action('wp_ajax_test_slack', 								array( self::instance(), 'test_slack' ) ); 
			add_filter( 'woocommerce_settings_tabs_array', 					array( self::instance(), 'woo_new_section_tabs'), 50);
			add_action( 'woocommerce_settings_tabs_settings_slack_woocommerce', 	array( self::instance(), 'settings_tab') );
			add_action( 'woocommerce_update_options_settings_slack_woocommerce', 	array( self::instance(), 'update_settings') );
			
			
			add_action( 'in_admin_footer', 								array( self::instance(), 'enqueue_scripts'));
		}
		add_action( 'clickandpledge_testmode_check', 						array( self::instance(), 'clickandpledge_testmode_check') );
	}
	
	public function clickandpledge_testmode_check() {
		if( is_wpe()) {
			$settings = get_option('woocommerce_clickandpledge_settings');
			if(!is_plugin_active('woocommerce-click-pledge-gateway/gateway-clickandpledge.php'))  {
				update_option( 'woocommerce_cp_active', 'false');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is in not active!";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Click and Pledge no Active", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_cp_active')=='false'){
					$message 	= " Production website's Click and Pledge Gateway is active!";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message,  "Click and Pledge Active", $channel, $icon);
				}
				update_option( 'woocommerce_cp_active', 'true');
			}	
			
			if($settings['testmode']=='yes'){
				update_option( 'woocommerce_cp_testmode', 'true');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is in Testmode";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message, "Click and Pledge in Testmode", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_cp_testmode')=='true'){
					$message 	= "Production website's Click and Pledge Gateway is in Production";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Click and Pledge in Production", $channel, $icon);
				}
				update_option( 'woocommerce_cp_testmode', 'false');
			}
			
			if($settings['enabled']=='no'){
				update_option( 'woocommerce_cp_enabled', 'false');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is not enabled";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Click and Pledge disabled", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_cp_enabled')=='false'){
					$message 	= "Production website's Click and Pledge Gateway is enabled";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Click and Pledge enabled", $channel, $icon);
				}
				update_option( 'woocommerce_cp_enabled', 'true');
			}
		}
		
	}
	public static function enqueue_scripts() {
?>
<script>
jQuery(window).ready(function(){
	jQuery("#test_slack").click(function() {
		jQuery.ajax({
				type : 		"post",
			url : 		'admin-ajax.php',
			data : {
				action: 		"test_slack"
			},
			success: function(response) {
				
			}
		}); 
	});
});
</script>
<?php
	}
	public function get_last_order_id(){
	    global $wpdb;
	    $statuses = array_keys(wc_get_order_statuses());
	    $statuses = implode( "','", $statuses );
	
	    // Getting last Order ID (max value)
	    $results = $wpdb->get_col( "
		   SELECT MAX(ID) FROM {$wpdb->prefix}posts
		   WHERE post_type LIKE 'shop_order'
		   AND post_status IN ('$statuses')
	    " );
	    return reset($results);
	}
	public static function test_slack() {
		$order = wc_get_order( self::get_last_order_id() );
		
		$order_items = $order->get_items();
		if( count($order_items)==1){
			foreach ( $order_items  as $item_id => $item ) {
				 $product = $item->get_data(); 
			} 
		}
		
		$message = $product['name']." 
Donation *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been completed";
		
		$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon' );
	
	
		self::slack_message($message, "Donation Website", $channel, $icon);
		
		wp_die();	
	}
	public static function update_settings() {
	    woocommerce_update_options(  self::get_settings() );
	}
	public static function settings_tab() {
		woocommerce_admin_fields( self::get_settings() );
	}
	public static function get_settings() {
	    $settings = array(
		   'section_title' => array(
			  'name'     => __( 'Slack Notifications', 'wp-slack-woocommerce' ),
			  'type'     => 'title',
			  'desc'     => '',
			  'id'       => 'wc_settings_tab_slack_woocommerce_title'
		   ),
		   'notifications_enable' => array(
			  'name' => __( 'Enable Notifications', 'wp-slack-woocommerce' ),
			  'type' => 'checkbox',
			  'desc' => __( 'Enable Slack Notifications using the settings below', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_enable_notifications'
		   ),
		   
		   'title' => array(
			  'name' => __( 'Slack Token', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_token'
		   ),
		   'channel' => array(
			  'name' => __( 'Slack Channel', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_channel'
		   ),
		   'icon-confirm' => array(
			  'name' => __( 'Confirmed Icon', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_slack_icon_confirmed'
		   ),
		   'icon-cancel' => array(
			  'name' => __( 'Cancelled Icon', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_slack_icon_cancelled'
		   ),
		   'test_slack' => array(
			  'name'     => __( '', 'wp-slack-woocommerce' ),
			  'type'     => 'title',
			  'desc'     => '<a href="#" id="test_slack">Test slack</a>',
			  'id'       => 'wc_settings_tab_slack_test_slack'
		   ),
		   'section_end' => array(
			   'type' => 'sectionend',
			   'id' => 'wc_settings_tab_slack_woocommerce_end'
		   )
	    );
	    return apply_filters( 'wc_settings_tab_slack_woocommerce', $settings );
	}
	public static function woo_new_section_tabs( $sections ) {
		$sections['settings_slack_woocommerce'] = __( 'Slack Notifications', 'wp-slack-woocommerce' );
		return $sections;
	}
	public function rfvc_update_order_status( $order_status, $order_id ) {
		$order = new WC_Order( $order_id );
		if ( 'processing' == $order_status && ( 'on-hold' == $order->status || 'pending' == $order->status || 'failed' == $order->status ) ) {
			return 'completed';
		}
		return $order_status;
	}
	public function post_status_change($new_status, $old_status, $post)  {
		if ( 'shop_order' === $post->post_type) {
			
			if($new_status == "wc-cancelled")  {
			
			}
			else if($new_status == 'wc-processing'){
				
			}
			else if($new_status == 'wc-pending' && $old_status =='new') {
				$this->slack_message($message, "Donation Website", "#monitor-donate");
			}	
		}
	}
	public function slack_message($message, $username, $channel, $icon = ":robot_face:")	{
		$token = get_option( 'wc_settings_tab_slack_token' );
		
		$ch = curl_init("https://slack.com/api/chat.postMessage");
		$data = http_build_query(array(
			"token" 		=> $token,
			"channel"		=> $channel, //"#mychannel",
			"text" 		=> $message, //"Hello, Foo-Bar channel message.",
			"username" 	=> $username,
			"icon_emoji"	=> $icon,
		));
		
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);
		curl_close($ch);
	
		// echo $result;
	
		return $result;
		
		
	}
	public function mysite_woocommerce_order_status_completed( $order_id ) {
		get_option( 'wc_settings_tab_slack_token' );
		$order = wc_get_order( $order_id );
		$order_items = $order->get_items();
		if( count($order_items)==1){
			foreach ( $order_items  as $item_id => $item ) {
				 $product = $item->get_data(); 
			} 
		}
		//$product_name = preg_replace("# - Other#", "",$product['name'] );
	$message = $product['name']." 
Donation *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been completed";
	$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
	$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_confirmed' );
		self::slack_message($message, "Donation Completed", $channel, $icon);
	}
	public function mysite_woocommerce_order_status_cancelled( $order_id ) {
		$order = wc_get_order( $order_id );
		$order_items = $order->get_items();
		if( count($order_items)==1){
			foreach ( $order_items  as $item_id => $item ) {
			
				 $product = $item->get_data();
				 
				 
			} 
		}
		//$product_name = preg_replace("# - Other#", "",$product['name'] );
		$message = $product_name."  
		Donation  *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been cancelled";
		$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_cancelled' );
		self::slack_message($message, "Donation Cancelled", $channel, $icon);
	}
}
$wp_slack_woocommerce = new wp_slack_woocommerce;
$wp_slack_woocommerce->init();
