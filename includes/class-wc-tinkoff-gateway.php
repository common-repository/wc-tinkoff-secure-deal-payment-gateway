<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Tinkoff_Gateway extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = WC_Tinkoff::$gateway_id;
		$this->icon               = WC_Tinkoff::$plugin_icon;
		$this->method_title       = esc_html__( 'Tinkoff Secure deal', 'wc-tinkoff-secure-deal-payment-gateway' );
		$this->method_description = esc_html__( 'Tinkoff Secure deal payment gateway', 'wc-tinkoff-secure-deal-payment-gateway' );
		$this->supports           = [ 'products', 'refunds' ];

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
		$this->language       = 'ru';
		$this->currency       = '643';
		$this->testmode       = 'yes' === $this->get_option( 'testmode' );
		$this->logging        = 'yes' === $this->get_option( 'logging' );
		$this->terminalKey    = $this->testmode ? $this->get_option( 'test_terminal_key' ) : $this->get_option( 'terminal_key' );
		$this->password       = $this->testmode ? $this->get_option( 'test_password' ) : $this->get_option( 'password' );
		$this->terminalOutKey = $this->testmode ? $this->get_option( 'test_terminal_out_key' ) : $this->get_option( 'terminal_out_key' );

		$this->api_url                = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/v2/Init' : 'https://securepay.tinkoff.ru/v2/Init';
		$this->api_confirm_url        = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/v2/Confirm' : 'https://securepay.tinkoff.ru/v2/Confirm';
		$this->api_get_state_url      = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/v2/GetState' : 'https://securepay.tinkoff.ru/v2/GetState';
		$this->api_e2c_url            = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/e2c/v2/Init' : 'https://securepay.tinkoff.ru/e2c/v2/Init';
		$this->api_e2c_payment_url    = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/e2c/v2/Payment' : 'https://securepay.tinkoff.ru/e2c/v2/Payment';
		$this->api_add_card_url       = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/e2c/v2/AddCard' : 'https://securepay.tinkoff.ru/e2c/v2/AddCard';
		$this->api_remove_card_url    = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/e2c/v2/RemoveCard' : 'https://securepay.tinkoff.ru/e2c/v2/RemoveCard';
		$this->api_get_cards_list_url = ( $this->testmode == 'yes' ) ? 'https://rest-api-test.tinkoff.ru/e2c/v2/GetCardList' : 'https://securepay.tinkoff.ru/e2c/v2/GetCardList';

		$this->return_url       = home_url( '/wc-api/tinkoff-return-url' ) . '?OrderId=${OrderId}';
		$this->fail_url         = home_url( '/wc-api/tinkoff-fail-url' ) . '?Success=${Success}&ErrorCode=${ErrorCode}&OrderId=${OrderId}&Message=${Message}&Details=${Details}';
		$this->notification_url = home_url( '/wc-api/tinkoff-notification-url' );

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_api_tinkoff-return-url', [ $this, 'order_confirm' ] );
		add_action( 'woocommerce_api_tinkoff-fail-url', [ $this, 'order_failed' ] );
		add_action( 'woocommerce_api_tinkoff-notification-url', [ $this, 'order_notification' ] );
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_update_order' ], 99, 4 );

		add_action( 'wp_ajax_addTinkoffCard', [ $this, 'tinkoff_add_card' ] );
		add_action( 'wp_ajax_nopriv_addTinkoffCard', [ $this, 'tinkoff_add_card' ] );

		add_action( 'wp_ajax_removeTinkoffCard', [ $this, 'tinkoff_remove_card' ] );
		add_action( 'wp_ajax_nopriv_removeTinkoffCard', [ $this, 'tinkoff_remove_card' ] );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled'               => [
				'title'       => __( 'Enabled/Disabled', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'label'       => __( 'Tinkoff Secure deal', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'                 => [
				'title'       => __( 'Title', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'default'     => 'carte CIB',
				'desc_tip'    => true,
			],
			'description'           => [
				'title'       => __( 'Describe', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'default'     => __( 'Pay with Tinkoff Secure deal', 'wc-tinkoff-secure-deal-payment-gateway' ),
			],
			'terminal_key'          => [
				'title' => __( 'Terminal key', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'text'
			],
			'password'              => [
				'title' => __( 'Password', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'password'
			],
			'terminal_out_key'      => [
				'title' => __( 'Terminal out key', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'text'
			],
			'logging'               => [
				'title'       => __( 'Enabled logging', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'label'       => __( 'Enabled/Disabled', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'testmode'              => [
				'title'       => __( 'Test mode', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'label'       => __( 'Enabled test mode', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Do you want to test with test API keys?', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_terminal_key'     => [
				'title' => __( 'Test terminal key', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'text'
			],
			'test_password'         => [
				'title' => __( 'Test password', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'password',
			],
			'test_terminal_out_key' => [
				'title' => __( 'Test terminal out key', 'wc-tinkoff-secure-deal-payment-gateway' ),
				'type'  => 'text'
			],
		];
	}

	public function order_confirm() {
		$order_id = intval( $_GET['OrderId'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log( __( 'Order not found (order_confirm)', 'wc-tinkoff-secure-deal-payment-gateway' ) );
		}
		$order->payment_complete( $order_id );
		WC()->cart->empty_cart();
		wp_redirect( $this->get_return_url( $order ) );
		die;
	}

	public function order_failed() {
		$order_id = intval( $_GET['OrderId'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log( __( 'Order not found (order_failed)', 'wc-tinkoff-secure-deal-payment-gateway' ) );
		}

		$error_code = 'Order failed: ' . sanitize_text_field( $_GET['ErrorCode'] );
		$message    = 'Order failed: ' . sanitize_text_field( $_GET['Message'] );
		$details    = 'Order failed: ' . sanitize_text_field( $_GET['Details'] );

		$this->log( 'Order failed: orderId - ' . $order_id . ', error_code - ' . $error_code . ', message - ' . $message . ', details - ' . $details );
		wc_add_notice( '<div class="woocommerce-error"><p>Ошибка оплаты. Пожалуйста попробуйте позже или обратитесь к администратору.</p></div>', 'error' );

		$order->update_status( 'failed' );

		wp_redirect( $order->get_cancel_order_url() );
		die;
	}

	public function order_notification() {
		$this->log( 'Success get notification' );

		// return code 200
		header( "HTTP/1.1 200 OK" );
	}

	public function process_payment( $order_id ) {
		// Get order data
		$order         = wc_get_order( $order_id );
		$order_data    = $order->get_data();
		$order_total   = intval( $order->get_total() ) * 100;
		$order_items   = $order->get_items();
		$order_comment = ! empty( $order->get_customer_note() ) ? $order->get_customer_note() : '';

		$request_products = [];

		foreach ( $order_items as $item ) {
			$product = $item->get_product();

			$request_products[] = [
				'Name'          => $item['name'],
				'Price'         => intval( $item['total'] ) * 100,
				'Quantity'      => $item['quantity'],
				'Amount'        => intval( $item['total'] ) * 100,
				'PaymentMethod' => 'full_prepayment',
				'PaymentObject' => 'service',
				'Tax'           => 'none'
			];
		}

		// Request data
		$request_data = [
			'TerminalKey'     => $this->terminalKey,
			'Amount'          => $order_total,
			'OrderId'         => strval( $order_id ),
			'Description'     => $order_comment,
			'DATA'            => [
				'Phone' => $order->get_billing_phone(),
				'Email' => $order->get_billing_email()
			],
			'Receipt'         => [
				'Email'    => $order->get_billing_email(),
				'Phone'    => $order->get_billing_phone(),
				'Taxation' => 'osn',
				'Items'    => $request_products
			],
			'SuccessURL'      => $this->return_url,
			'FailURL'         => $this->fail_url,
			'PayType'         => 'T',
			'NotificationURL' => $this->notification_url,
		];

		$request_data['Token'] = $this->getToken( [
			'Description' => $order_comment,
			'Amount'      => $order_total,
			'TerminalKey' => $this->terminalKey,
			'OrderId'     => strval( $order_id )
		] );

		$request_result = $this->sendRequest( $this->api_url, $request_data );

		// Save some order data
		$order->update_meta_data( '_' . $order_id . '_paymentId', $request_result['PaymentId'] );
		$order->save();

		if ( $request_result['Success'] === true && $request_result['ErrorCode'] === '0' ) {
			$result = [
				'result'   => 'success',
				'redirect' => $request_result['PaymentURL']
			];
		} else {
			$result = [
				'result'        => 'error',
				'error_message' => 'Error code: ' . $request_result['ErrorCode']
			];
		}

		return $result;
	}

	/*
	 * Make curl request
	 *
	 * @api_url, $data  string, array Get request url and array params
	 * @return json
	*/
	private function sendRequest( $api_url, $data ) {
		if ( is_array( $data ) ) {
			$data = json_encode( $data );
		}
		$this->log( 'request ' . $data );
		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body'    => $data,
		] );
		$body     = wp_remote_retrieve_body( $response );
		$this->log( 'body ' . $body );
		$json_out = json_decode( $body, true );
		if ( $json_out && isset( $json_out['ErrorCode'] ) && $json_out['ErrorCode'] !== "0" ) {
			$this->log( 'Error request. Error code - ' . $json_out['ErrorCode'] . ', error message - ' . $json_out['Message'] );
		}

		return $json_out;
	}

	/*
	 * On update order status to on-hold
	 * Call methods to pay out to salesman
	 *
	 * @order_id, $old_status, $new_status string
	*/
	public function on_update_order( $order_id, $old_status, $new_status, $order ) {
		$payment_method = $order->get_payment_method();

		if ( $new_status !== 'on-hold' || $payment_method !== 'tinkoff' ) {
			return false;
		}

		// @bool Return order confirmed status: true or false
		$orderConfirmed = $this->order_confirmed_status( $order_id );

		if ( $orderConfirmed !== true ) {
			$this->log( 'Order success confirm' );
			// Confirm Payment out
			$this->tinkoff_confirm( $order_id );
			// Init payment out
			$paymentId = $this->tinkoff_e2c_init( $order_id ); // Get PaymentId for payment out
			// Init pay to saller
			if ( ! empty( $paymentId ) ) {
				$this->tinkoff_e2c_payment( $order_id, $paymentId );
			} else {
				$this->log( 'Error payment payout. PaymentId is empty' );
			}
		}
	}

	/*
	 * Get confirmed order status
	 *
	 * @order_id
	 * @return bool
	*/
	public function order_confirmed_status( $order_id ) {
		$status = false;

		$orderGetState = $this->tinkoff_getState( $order_id );

		if ( $orderGetState['ErrorCode'] === '0' && $orderGetState['Status'] === 'CONFIRMED' ) {
			$status = true;
		}

		return $status;
	}

	/*
	 * Get order information
	 *
	 * @order_id
	 * @return json
	*/
	private function tinkoff_getState( $order_id ) {
		$paymentId = get_post_meta( $order_id, '_' . $order_id . '_paymentId', true );

		$request_data = [
			'TerminalKey' => $this->terminalKey,
			'PaymentId'   => $paymentId
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_get_state_url, $request_data );

		return $request_result;
	}

	/*
	* Confirm order
	*
	* @order_id
	* @return json
   */
	private function tinkoff_confirm( $order_id ) {
		$paymentId = get_post_meta( $order_id, '_' . $order_id . '_paymentId', true );

		$request_data = [
			'TerminalKey' => $this->terminalKey,
			'PaymentId'   => $paymentId,
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_confirm_url, $request_data );
	}

	/*
	 * Init payment out and return current paymentId
	 *
	 * @order_id
	 * @return number
	*/
	private function tinkoff_e2c_init( $order_id ) {
		$order         = wc_get_order( $order_id );
		$order_total   = intval( $order->get_total() ) * 100;
		$commission    = $order_total / 100 * 14; // 10% service comission + 3% tinkoff comission + 1% from payment amount
		$total_paymont = $order_total - $commission;
		// Get business client id by order
		$business_client_id = $this->get_business_client_id( $order );

		if ( empty( $business_client_id ) ) {
			$this->log( 'Error with init payout. Business client id is empty' );

			return false;
		}

		// Get card
		$cards          = $this->tinkoff_get_cards_list( $business_client_id ); // Get all cards
		$active_card_id = $this->get_active_card_id( $cards ); // True: return id of active card. False: return false
		$cardId         = $cards[ $active_card_id ]['CardId'];

		if ( empty( $cardId ) ) {
			$this->log( 'Error cardId. Please, check is seller has active card for payout.' );

			return false;
		}
		$request_data = [
			'TerminalKey' => $this->terminalOutKey,
			'OrderId'     => $order_id,
			'CardId'      => $cardId,
			'Amount'      => $total_paymont
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_e2c_url, $request_data );

		$paymentId = intval( $request_result['PaymentId'] );

		return $paymentId;
	}

	/*
	 * Payment out to saller and update order status
	 *
	 * @order_id string, $paymentId number
	*/
	private function tinkoff_e2c_payment( $order_id, $paymentId ) {
		$order = wc_get_order( $order_id );

		$request_data = [
			'TerminalKey' => $this->terminalOutKey,
			'PaymentId'   => $paymentId,
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_e2c_payment_url, $request_data );

		$order->update_status( 'processing' );
	}

	public function tinkoff_add_card() {
		$user_id = get_current_user_id();

		$request_data = [
			'TerminalKey' => $this->terminalOutKey,
			'CustomerKey' => $user_id,
			"IP"          => WC_Geolocation::get_ip_address(),
			"CheckType"   => "HOLD",
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_add_card_url, $request_data );

		if ( $request_result['PaymentURL'] ) {
			$result = [ 'url' => $request_result['PaymentURL'] ];
		} else {
			$result = [ 'error' => $request_result['ErrorCode'] ];
		}

		wp_send_json( result );
	}

	/*
	 * Get business client id by order
	 *
	 * @order
	 * @return number
	*/
	private function get_business_client_id( $order ) {
		$order_items = $order->get_items();

		foreach ( $order_items as $line_item ) {
			$listing_id = $line_item->get_meta( '_listing_id' );
		}

		$this->log( 'client id listing id ' . $listing_id );

		if ( empty( $listing_id ) ) {
			return false;
		}

		$listing_id = str_replace( [ '[', ']', '"' ], '', $listing_id );
		$seller_id  = get_post_field( 'post_author', $listing_id );

		return $seller_id;
	}

	/*
	 * Get cards list by user id
	 *
	 * @user_id number not request
	 * @return json
	*/
	public function tinkoff_get_cards_list( $user_id ) {
		$request_data = [
			'TerminalKey' => $this->terminalOutKey,
			'CustomerKey' => $user_id,
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_get_cards_list_url, $request_data );

		return $request_result;
	}

	public function get_current_user_card( $user_id ) {
		$user_id        = ! empty( $user_id ) ? $user_id : get_current_user_id();
		$user_card_data = get_user_meta( $user_id, 'tinkoff_gateway_user_card' );

		if ( empty( $user_card_data ) ) {
			$this->update_current_user_card( $user_id );
			$user_card_data = get_user_meta( $user_id, 'tinkoff_gateway_user_card' );
		}

		return $user_card_data;
	}

	public function update_current_user_card( $user_id ) {
		$user_id = ! empty( $user_id ) ? $user_id : get_current_user_id();

		$cards = $this->tinkoff_get_cards_list( $user_id );

		if ( empty( $cards ) ) {
			return false;
		}

		$active_card_id = $this->get_active_card_id( $cards ); // True: return id. False: return false

		if ( isset( $active_card_id ) && $active_card_id !== false ) {
			$card_data = [
				'pan'     => $cards[ $active_card_id ]['Pan'],
				'expdate' => $cards[ $active_card_id ]['ExpDate'],
			];

			update_user_meta( $user_id, 'tinkoff_gateway_user_card', $card_data );
		}
	}

	public function delete_current_user_card( $user_id ) {
		$user_id = ! empty( $user_id ) ? $user_id : get_current_user_id();

		delete_user_meta( $user_id, 'tinkoff_gateway_user_card', '' );
	}

	public function tinkoff_remove_card() {
		$user_id        = get_current_user_id();
		$cards          = $this->tinkoff_get_cards_list( $user_id );
		$active_card_id = $this->get_active_card_id( $cards ); // True: return id of active card. False: return false
		$CardId         = $cards[ $active_card_id ]['CardId'];

		$request_data = [
			'TerminalKey' => $this->terminalOutKey,
			'CardId'      => $CardId,
			'CustomerKey' => $user_id
		];

		$request_data['Token'] = $this->getToken( $request_data );

		$request_result = $this->sendRequest( $this->api_remove_card_url, $request_data );

		if ( $request_result['ErrorCode'] == "0" && $request_result['Status'] == 'D' ) {
			$result = [ 'url' => wc_get_account_endpoint_url( 'tinkoff-settings' ) ];
			$this->delete_current_user_card( $user_id );
		} else {
			$result = [ 'error' => $request_result['ErrorCode'] ];
		}

		wp_send_json( $result );
	}

	public function display_card_content() {
		$user_id = get_current_user_id();

		$current_card = $this->get_current_user_card( $user_id );

		if ( ! empty( $current_card ) ):
			echo '<h3>' . __( 'You have a linked card:', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</h3>';
			echo '<div style="display: flex;margin-bottom: 25px;">';
			echo '<div style="margin-right: 20px;">';
			echo '<p>' . __( 'Card number:', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</p>';
			echo '<span>' . esc_html( $current_card[0]['pan'] ) . '</span>';
			echo '</div>';
			echo '<div>';
			echo '<p>' . __( 'Expiry date:', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</p>';
			echo '<span>' . esc_html( $current_card[0]['expdate'] ) . '</span>';
			echo '</div>';
			echo '</div>';
			echo '<button class="woocommerce-Button button" id="removeTinkoffCard">' . __( 'Remove card', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</button>';
		else:
			echo '<p>' . __( 'For payouts you should link your card to the account', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</p>';
			echo '<button class="woocommerce-Button button" id="addTinkoffCard">' . __( 'Add card', 'wc-tinkoff-secure-deal-payment-gateway' ) . '</button>';
		endif;
	}

	/*
	 * True: Return id of row with active card
	 * False: Return false
	 *
	 * @card_array  array Get cards array
	 * @return number
	*/
	public function get_active_card_id( $card_array ) {

		$new_cards_list = array_column( $card_array, 'Status' );

		$card_id = array_search( "A", $new_cards_list ); // A - active card status

		return $card_id;
	}

	/*
	 * Create token from request params
	 *
	 * @args  array request params
	 * @return string
	*/
	private function getToken( $args ) {
		$token            = '';
		$args['Password'] = $this->password;
		ksort( $args );
		foreach ( $args as $arg ) {
			$token .= $arg;
		}
		$token = hash( 'sha256', $token );

		return $token;
	}

	public function log( $data, $prefix = '' ) {
		if ( $this->logging ) {
			wc_get_logger()->debug( "$prefix " . print_r( $data, 1 ), [ 'source' => $this->id ] );
		}
	}
}

new WC_Tinkoff_Gateway;
