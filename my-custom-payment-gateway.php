<?php
/**
 * Plugin Name: My Custom Payment Gateway
 * Plugin URI: https://www.wpheka.com/
 * Description: Adds the Custom Payments gateway to your WooCommerce website.
 * Version: 1.0.0
 *
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com/
 *
 * Text Domain: my-custom-payment-gateway
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 6.6
 *
 * Copyright: © 2009-2024 Automattic.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * WC Custom Payment gateway plugin class.
 *
 * @class WC_Custom_Payments
 */
class WC_Custom_Payments
{

    /**
     * Plugin bootstrapping.
     */
    public static function init()
    {

        // Custom Payments gateway class.
        add_action('plugins_loaded', array( __CLASS__, 'includes' ), 0);

        // Make the Custom Payments gateway available to WC.
        add_filter('woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ));

        // Registers WooCommerce Blocks integration.
        add_action('woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_custom_woocommerce_block_support' ));
    }

    /**
     * Add the Custom Payment gateway to the list of available gateways.
     *
     * @param array
     */
    public static function add_gateway($gateways)
    {

        $options = get_option('woocommerce_my_custom_gateway_settings', array());

        if (isset($options['hide_for_non_admin_users'])) {
            $hide_for_non_admin_users = $options['hide_for_non_admin_users'];
        } else {
            $hide_for_non_admin_users = 'no';
        }

        if (( 'yes' === $hide_for_non_admin_users && current_user_can('manage_options') ) || 'no' === $hide_for_non_admin_users) {
            $gateways[] = 'My_Custom_WC_Gateway';
        }
        return $gateways;
    }

    /**
     * Plugin includes.
     */
    public static function includes()
    {

        // Make the My_Custom_WC_Gateway class available.
        if (class_exists('WC_Payment_Gateway')) {
            require_once 'includes/class-my-custom-wc-gateway.php';
        }
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_url()
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_abspath()
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * Registers WooCommerce Blocks integration.
     *
     */
    public static function woocommerce_gateway_custom_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'includes/blocks/class-wc-custom-payments-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_Gateway_Custom_Blocks_Support());
                }
            );
        }
    }
}

WC_Custom_Payments::init();
