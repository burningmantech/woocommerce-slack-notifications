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
		/*
		if ( ! wp_next_scheduled( 'clickandpledge_testmode_check' ) ) {
			wp_schedule_event( time(), 'hourly', array( self::instance(), 'clickandpledge_testmode_check') );
		}
		*/
		$enabled = get_option( 'wc_settings_tab_slack_woocommerce_enable_notifications' );
		if( $enabled == 'yes' ){	
			add_filter( 'woocommerce_payment_complete_order_status', 		array( self::instance(), 'rfvc_update_order_status'), 10, 2 );
			add_action( 'woocommerce_order_status_completed', 			array( self::instance(), 'mysite_woocommerce_order_status_completed'));
			add_action( 'woocommerce_order_status_cancelled', 			array( self::instance(), 'mysite_woocommerce_order_status_cancelled'));
			add_action( 'transition_post_status',  						array( self::instance(), 'post_status_change'), 10, 3 ); 
			add_action( 'woocommerce_update_options', 					array( self::instance(), 'payeezy_testmode_check'), 10, 1 ); 
		}
		if( is_admin()) {
			// Only register the AJAX for admins
			add_action( 'wp_ajax_test_slack', 								array( self::instance(), 'test_slack' ) ); 
			add_filter( 'woocommerce_settings_tabs_array', 					array( self::instance(), 'woo_new_section_tabs'), 50);
			add_action( 'woocommerce_settings_tabs_settings_slack_woocommerce', 	array( self::instance(), 'settings_tab') );
			add_action( 'woocommerce_update_options_settings_slack_woocommerce', 	array( self::instance(), 'update_settings') );
			
			
			add_action( 'in_admin_footer', 								array( self::instance(), 'enqueue_scripts'));
		}
		add_action( 'payeezy_testmode_check', 								array( self::instance(), 'payeezy_testmode_check') );
		add_action( 'wp_enqueue_scripts', 									array( self::instance(), 'dequeue_enqueue'), 70 );
		add_action( 'wp_ajax_record_woocommerce_errors', 						array( $this, 'ajax_record_woocommerce_errors') );
	}
	public function send_bulk_ga_data($data){
		$gap_options 	= get_option('gap_options');
		$site_url 	= parse_url(get_site_url());
		$i = 0;
		foreach ( $data as $index=>$message ) {
			// First build the current property
			$ga_data[$i]['v']	= 1;
			$ga_data[$i]['cid'] = 555;
			$ga_data[$i]['tid']	= $gap_options['gap_id'];
			$ga_data[$i]['t'] 	= 'event'; 
			$ga_data[$i]['ec']	= "WooCommerce";
			$ga_data[$i]['ea']	= "WooCommerce - Cart - Error";
			$ga_data[$i]['el']	= $message['el'];
			$ga_data[$i]['dh'] 	= $site_url['host'];
			$i++;
			// Then build the global property
			$ga_data[$i]['v']	= 1;
			$ga_data[$i]['cid'] = 555;
			$ga_data[$i]['tid']	= "UA-10418553-59";
			$ga_data[$i]['t'] 	= 'event'; 
			$ga_data[$i]['ec']	= "WooCommerce";
			$ga_data[$i]['ea']	= "WooCommerce - Cart - Error";
			$ga_data[$i]['el']	= $message['el'];
			$ga_data[$i]['dh'] 	= $site_url['host'];
			$i++;
		}
		$postfields = '';
		foreach($ga_data as $index=>$val){
			$postfields .= http_build_query($val)."\r\n";
		}
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://www.google-analytics.com/batch",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => $postfields,
		  CURLOPT_HTTPHEADER => array(
		    "cache-control: no-cache",
		    "content-type: text/html"
		  ),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
	
	}
	public function send_ga_data($data) {
		
		$data['v'] 	= 1;
		$data['cid'] 	= 555;
		$site_url = parse_url(get_site_url());
		$data['dr'] 	= $site_url['host'];

		$content = http_build_query($data);
		$content = utf8_encode($content);
		$url = "https://www.google-analytics.com/collect?" . $content;
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
		$r = curl_exec($ch);
	
		$error = curl_error($ch);
		curl_close($ch);

		if($data['tid']!='UA-10418553-59'){
			if($data['t']!='event'){
				$data['tid'] 	= 'UA-10418553-59';
				$this->form_load_GA_trigger($data);
			}
		}
		
	}
	public function ajax_record_woocommerce_errors(){
		global $current_user;
		
		$error_array = json_decode(file_get_contents('php://input'));
		foreach($error_array as $index=>$error){
			// Build the errors
			$data[]['el']	= strip_tags(wp_kses_post( $error ));
		}
		// Send to the bulk data transmitter
		$this->send_bulk_ga_data($data);
		exit;
		die();
	}
	public function dequeue_enqueue() {
		if(!is_plugin_active('woocommerce-gateway-firstdata/woocommerce-gateway-first-data.php'))  {
			wp_enqueue_script( 	'woocommerce-slack', 		'/wp-content/plugins/woocommerce-slack/assets/js/payeezy-google-notifications.js', 		array( 'jquery' ), time(), true );
		}
	}
	public function payeezy_testmode_check() {
		// Don't check for WPEngine yet, need to test first
		if( is_wpe()) {
			
	
		
			$settings = get_option('woocommerce_first_data_payeezy_gateway_credit_card_settings');
			if(!is_plugin_active('woocommerce-gateway-firstdata/woocommerce-gateway-first-data.php'))  {
				update_option( 'woocommerce_pe_active', 'false');
				$message 	= "WARNING: Production website's Payeezy Gateway is in not active!";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Payeezy Gateway no Active", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_pe_active')=='false'){
					$message 	= " Production website's Payeezy Gateway is active!";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message,  "Payeezy Gateway Active", $channel, $icon);
				}
				update_option( 'woocommerce_pe_active', 'true');
			}	
			
			if($settings['environment']=='demo'){
				update_option( 'woocommerce_pe_testmode', 'true');
				$message 	= "WARNING: Production website's Payeezy Gateway Environment is set to Demo";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message, "Payeezy Gateway Environment set to Demo", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_pe_testmode')=='true'){
					$message 	= "Production website's Payeezy Gateway Environment is set to Production";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Payeezy Environment set to Production", $channel, $icon);
				}
				update_option( 'woocommerce_pe_testmode', 'false');
			}
			
			if($settings['enabled']=='no'){
				update_option( 'woocommerce_pe_enabled', 'false');
				$message 	= "WARNING: Production website's Payeezy Gateway is not enabled";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Payeezy disabled", $channel, $icon);
			}
			else if($settings['enabled']=='yes'){
				if(get_option('woocommerce_pe_enabled')=='false'){
					$message 	= "Production website's Payeezy Gateway is enabled";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Payeezy Gateway enabled", $channel, $icon);
				}
				update_option( 'woocommerce_pe_enabled', 'true');
			}
		}
		
	}
	public function clickandpledge_testmode_check() {
		if( is_wpe()) {
			$settings = get_option('woocommerce_clickandpledge_settings');
			if(!is_plugin_active('woocommerce-click-pledge-gateway/gateway-clickandpledge.php'))  {
				update_option( 'woocommerce_pe_active', 'false');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is in not active!";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Click and Pledge no Active", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_pe_active')=='false'){
					$message 	= " Production website's Click and Pledge Gateway is active!";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message,  "Click and Pledge Active", $channel, $icon);
				}
				update_option( 'woocommerce_pe_active', 'true');
			}	
			
			if($settings['testmode']=='yes'){
				update_option( 'woocommerce_pe_testmode', 'true');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is in Testmode";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message, "Click and Pledge in Testmode", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_pe_testmode')=='true'){
					$message 	= "Production website's Click and Pledge Gateway is in Production";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Click and Pledge in Production", $channel, $icon);
				}
				update_option( 'woocommerce_pe_testmode', 'false');
			}
			
			if($settings['enabled']=='no'){
				update_option( 'woocommerce_pe_enabled', 'false');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is not enabled";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				self::slack_message($message,  "Click and Pledge disabled", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_pe_enabled')=='false'){
					$message 	= "Production website's Click and Pledge Gateway is enabled";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					self::slack_message($message, "Click and Pledge enabled", $channel, $icon);
				}
				update_option( 'woocommerce_pe_enabled', 'true');
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
				console.log(response);
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
		if($order->status=='processing' || $order->status=='completed'){
			$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_confirmed' );
			$status 	= "completed";
		}
		else {
			$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_cancelled' );
			$status 	= "cancelled";
		}
		
		$order_items = $order->get_items();
		if( count($order_items)==1){
			foreach ( $order_items  as $item_id => $item ) {
				 $product = $item->get_data(); 
			} 
		}
		
		$message = $product['name']." 
Order *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been ".$status;
		
		$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );

	
		self::slack_message($message, "Marketplace Purchase", $channel, $icon);
		
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
		$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		if ( 'shop_order' === $post->post_type) {
			
			if($new_status == "wc-cancelled")  {
			
			}
			else if($new_status == 'wc-processing'){
				
			}
			else if($new_status == 'wc-pending' && $old_status =='new') {
				$this->slack_message($message, "Marketplace Purchase", $channel);
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
Order *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been completed".json_encode($order);
	$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
	$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_confirmed' );
		self::slack_message($message, "Order Completed", $channel, $icon);
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
Order  *#".$order->ID."* by *".$order->data['billing']['first_name']." ".$order->data['billing']['last_name']."* for *$".number_format($order->data['total'],2)."* has been cancelled".json_encode($order);
		$channel 	= "#".get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$icon	= get_option( 'wc_settings_tab_slack_woocommerce_slack_icon_cancelled' );
		self::slack_message($message, "Order Cancelled", $channel, $icon);
	}
}
$wp_slack_woocommerce = new wp_slack_woocommerce;
$wp_slack_woocommerce->init();
