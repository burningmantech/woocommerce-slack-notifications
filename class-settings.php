<?php
class wp_slack_woocommerce_settings extends wp_slack_woocommerce {
    public function init() {		
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'woo_new_section_tabs'), 50);
        add_action( 'woocommerce_settings_tabs_settings_slack_woocommerce', array( $this, 'settings_tab') );
        add_action( 'woocommerce_update_options_settings_slack_woocommerce', array( $this, 'update_settings') );
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
			  'id'   => 'wc_settings_tab_slack_woocommerce_slack_icon_completed'
		   ),
		   'icon-cancel' => array(
			  'name' => __( 'Cancelled Icon', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_slack_icon_cancelled'
		   ),
			'icon-fail' => array(
			  'name' => __( 'Failed Icon', 'wp-slack-woocommerce' ),
			  'type' => 'text',
			  'desc' => __( '', 'wp-slack-woocommerce' ),
			  'id'   => 'wc_settings_tab_slack_woocommerce_slack_icon_failed'
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
	public function woo_new_section_tabs( $sections ) {
		$sections['settings_slack_woocommerce'] = __( 'Slack Notifications', 'wp-slack-woocommerce' );
		return $sections;
	}
    public function update_settings() {
	    woocommerce_update_options(  $this->get_settings() );
	}
	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}
}