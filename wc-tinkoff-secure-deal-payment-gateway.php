<?php
/*
 * Plugin Name: Безопасные переводы Тинькофф для WooCommerce
 * Description: Автоматизируйте и защитите переводы ваших клиентов с помощью плагина для безопасных сделок от эквайрингового сервиса Тинькофф Кассы.
 * Author: OnePix
 * Author URI: https://onepix.net/
 * Version: 1.0.1
 * Text Domain: wc-tinkoff-secure-deal-payment-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Tinkoff
 */
class WC_Tinkoff
{
    private static $instance;
    public static $plugin_url;
    public static $gateway_id = 'tinkoff';
    public static $plugin_icon;
    public static $plugin_icon_error;
    public static $plugin_path;

    private function __construct()
    {
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$plugin_icon = self::$plugin_url . 'assets/images/logo.png';

        add_action('plugins_loaded', [$this, 'pluginsLoaded']);
        add_filter('woocommerce_payment_gateways', [$this, 'woocommercePaymentGateways']);
    }

    public function pluginsLoaded()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerceMissingWcNotice']);
            return;
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 10 );

        require_once 'includes/class-wc-tinkoff-gateway.php';
        require_once 'includes/class-wc-tinkoff-frontend.php';
    }

    public function enqueue_scripts(){
		wp_enqueue_script( 'tinkoff-ajax', self::$plugin_url . 'assets/js/scripts.js', array(), false );
        wp_localize_script('tinkoff-ajax', 'wp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function woocommerceMissingWcNotice()
    {
        echo '<div class="error"><p><strong>' . sprintf('Tinkoff payment gateway requires WooCommerce to be installed and active. You can download %s here.', '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
    }

    public function woocommercePaymentGateways($gateways)
    {
        $gateways[] = 'WC_Tinkoff_Gateway';
        return $gateways;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}

WC_Tinkoff::getInstance();
