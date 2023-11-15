<?php
class wp_slack_woocommerce_testmode extends wp_slack_woocommerce{
    public function init() {	
        $plugin_path = 'woocommerce-click-pledge-gateway/gateway-clickandpledge.php';
		if( (is_plugin_active($plugin_path) || is_plugin_inactive($plugin_path))
			&& file_exists(ABSPATH . '/wp-content/plugins/'.$plugin_path) ) {
			add_action( 'deactivate_'.$plugin_path, 	array( $this, 'clickandpledge_deactivate')); 
			add_action( 'activate_'.$plugin_path, 		array( $this, 'clickandpledge_activate')); 
		}

		$plugin_path = 'woocommerce-gateway-firstdata/woocommerce-gateway-first-data.php';
		if( (is_plugin_active($plugin_path) || is_plugin_inactive($plugin_path))
			&& file_exists(ABSPATH . '/wp-content/plugins/'.$plugin_path) ) {
			add_action( 'deactivate_'.$plugin_path, 	array( $this, 'payeezy_deactivate')); 
			add_action( 'activate_'.$plugin_path, 		array( $this, 'payeezy_activate')); 
		}

		add_action( 'woocommerce_update_options', array( $this, 'testmode_check'), 10, 1 );
        add_action( 'testmode_check', array( $this, 'testmode_check') );
	}

    /**
	 * Checks the test mode status of Payeezy and Click and Pledge gateways.
	 *
	 * This function checks if the specified WooCommerce gateways (Payeezy and Click and Pledge) 
	 * are active or inactive and whether their respective plugin files exist. If the conditions 
	 * are met, it calls respective functions to check the test mode status of these gateways.
	 */
	public function testmode_check() {
		$plugin_path = 'woocommerce-gateway-firstdata/woocommerce-gateway-first-data.php';
		if( (is_plugin_active($plugin_path) || is_plugin_inactive($plugin_path))
			&& file_exists(ABSPATH . '/wp-content/plugins/'.$plugin_path) ) {
			$this->payeezy_testmode_check();
		}

		$plugin_path = 'woocommerce-click-pledge-gateway/gateway-clickandpledge.php';
		if( (is_plugin_active($plugin_path) || is_plugin_inactive($plugin_path))
			&& file_exists(ABSPATH . '/wp-content/plugins/'.$plugin_path) ) {
			$this->clickandpledge_testmode_check();
		}
	}

    public function payeezy_activate(){
		$message 	= " Production website's Production website's Payeezy Gateway is active!";
		$icon 		= ":heavy_check_mark:";
		$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$this->slack_message($message, "Payeezy Gateway Plugin Active", $channel, $icon);
	}
	public function payeezy_deactivate(){
		$message 	= "WARNING: Production website's Production website's Payeezy Gateway is in not active!";
		$icon 		= ":warning:";
		$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$this->slack_message($message, "Payeezy Gateway Plugin Inactive", $channel, $icon);
	}
	public function payeezy_testmode_check() {
		// Don't check for WPEngine yet, need to test first
		$settings = get_option('woocommerce_first_data_payeezy_gateway_credit_card_settings');
		if($settings['environment']=='demo' && get_option('woocommerce_pe_testmode')!='true'){
			update_option( 'woocommerce_pe_testmode', 'true');
			$message 	= "WARNING: Production website's Payeezy Gateway Environment is set to Demo";
			$icon 	= ":warning:";
			$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
			$this->slack_message($message, "Payeezy Gateway Environment set to Demo", $channel, $icon);
		}
		else if($settings['environment']=='production') {
			if(get_option('woocommerce_pe_testmode')=='true'){
				$message 	= "Production website's Payeezy Gateway Environment is set to Production";
				$icon 	= ":heavy_check_mark:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				$this->slack_message($message, "Payeezy Environment set to Production", $channel, $icon);
			}
			update_option( 'woocommerce_pe_testmode', 'false');
		}

		if($settings['enabled']=='no' && get_option('woocommerce_pe_enabled')!='false'){
			update_option( 'woocommerce_pe_enabled', 'false');
			$message 	= "WARNING: Production website's Payeezy Gateway is not enabled";
			$icon 	= ":warning:";
			$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
			$this->slack_message($message,  "Payeezy disabled", $channel, $icon);
		}
		else if($settings['enabled']=='yes'){
			if(get_option('woocommerce_pe_enabled')=='false'){
				$message 	= "Production website's Payeezy Gateway is enabled";
				$icon 	= ":heavy_check_mark:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				$this->slack_message($message, "Payeezy Gateway enabled", $channel, $icon);
			}
			update_option( 'woocommerce_pe_enabled', 'true');
		}
	}
	public function clickandpledge_activate(){
		$message 	= " Production website's Click and Pledge Gateway is active!";
		$icon 		= ":heavy_check_mark:";
		$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$this->slack_message($message, "Click and Pledge Active", $channel, $icon);
	}
	public function clickandpledge_deactivate(){
		$message 	= "WARNING: Production website's Click and Pledge Gateway is in not active!";
		$icon 		= ":warning:";
		$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
		$this->slack_message($message, "Click and Pledge Inactive", $channel, $icon);
	}
	public function clickandpledge_testmode_check() {
		if( is_wpe()) {
			$settings 	= get_option('woocommerce_clickandpledge_settings');
			$p_settings = get_option('woocommerce_clickandpledge_paymentsettings');

			if($p_settings['testmode']=='yes'){
				update_option( 'woocommerce_cp_testmode', 'true');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is in Testmode";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				$this->slack_message($message, "Click and Pledge in Testmode", $channel, $icon);
			}
			else {
				if(get_option('woocommerce_cp_testmode')=='true'){
					$message 	= "Production website's Click and Pledge Gateway is in Production";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					$this->slack_message($message, "Click and Pledge in Production", $channel, $icon);
				}
				update_option( 'woocommerce_cp_testmode', 'false');
			}
			
			if($p_settings['enabled']=='yes'){
				if(get_option('woocommerce_cp_enabled')=='false'){
					$message 	= "Production website's Click and Pledge Gateway is enabled";
					$icon 	= ":heavy_check_mark:";
					$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
					$this->slack_message($message, "Click and Pledge enabled", $channel, $icon);
				}
				update_option( 'woocommerce_cp_enabled', 'true');
				
			}
			else{
				update_option( 'woocommerce_cp_enabled', 'false');
				$message 	= "WARNING: Production website's Click and Pledge Gateway is not enabled";
				$icon 	= ":warning:";
				$channel 	= get_option( 'wc_settings_tab_slack_woocommerce_channel' );
				$this->slack_message($message,  "Click and Pledge disabled", $channel, $icon);
			}
			
		}
		
	}
	
}