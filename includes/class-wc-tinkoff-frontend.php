<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Tinkoff_Frontend {
	public static $endpoint = 'tinkoff-settings';
	public static $title = 'Tinkoff settings';

	public function __construct() {
		$this->add_endpoint();
	}

	private function add_endpoint() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();
		$has_business_role = $this->user_bisuness_role_status( $user_id );

		// if user role qual business
		if ( $has_business_role ) {
			add_action( 'init', [ $this, 'tinkoff_setting_add_endpoint' ] );
			add_filter( 'woocommerce_account_menu_items', [ $this, 'tinkoff_settings_add_menu_link' ] );
			add_filter( 'woocommerce_get_query_vars', [ $this, 'get_query_vars' ], 0 );
			add_filter( 'the_title', [ $this, 'tinkoff_settings_endpoint_title' ] );
			add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', [
				$this,
				'tinkoff_settings_my_account_endpoint_content'
			] );
		}
	}

	public function get_query_vars( $vars ) {
		$vars[ self::$endpoint ] = self::$endpoint;

		return $vars;
	}

	public function tinkoff_settings_add_menu_link( $items ) {
		$custom_items = [
			self::$endpoint => self::$title,
		];

		$custom_items = array_slice( $items, 0, 1, true ) + $custom_items + array_slice( $items, 1, count( $items ), true );

		return $custom_items;
	}

	public function tinkoff_setting_add_endpoint() {
		add_rewrite_endpoint( self::$endpoint, EP_PAGES );
		flush_rewrite_rules();
	}

	/*
	 * if user business role return true else false
	 *
	 * @user_id
	 * @return bool
	*/
	public function user_bisuness_role_status( $user_id ) {
		// $user_meta = get_userdata($user_id);
		// $user_roles = $user_meta->roles;

		// Get custom users role
		$user_roles = get_user_meta( $user_id, 'rz_role' );

		return in_array( 'business', $user_roles );
	}

	public function tinkoff_settings_endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = self::$title;

			remove_filter( 'the_title', [ $this, 'tinkoff_settings_endpoint_title' ] );
		}

		return $title;
	}

	public function tinkoff_settings_my_account_endpoint_content() {
		$userCards = new WC_Tinkoff_Gateway();
		$userCards->display_card_content();
	}

}

new WC_Tinkoff_Frontend;
