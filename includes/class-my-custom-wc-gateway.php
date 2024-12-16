<?php
/**
 * My_Custom_WC_Gateway class
 *
 * @author   WPHEKA
 * @package  WooCommerce Custom Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Custom Gateway.
 *
 * @class    My_Custom_WC_Gateway
 * @version  1.0.0
 */
class My_Custom_WC_Gateway extends WC_Payment_Gateway
{

    /**
     * Payment gateway instructions.
     * @var string
     *
     */
    protected $instructions;

    /**
     * Whether the gateway is visible for non-admin users.
     * @var boolean
     *
     */
    protected $hide_for_non_admin_users;

    /**
     * Unique id for the gateway.
     * @var string
     *
     */
    public $id = 'my_custom_gateway';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        
        $this->icon               = apply_filters('woocommerce_my_custom_gateway_gateway_icon', '');
        $this->has_fields         = false;
        $this->supports           = array(
            'pre-orders',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions'
        );

        $this->method_title       = _x('My Custom Payment Gateway', 'Custom payment method', 'my-custom-payment-gateway');
        $this->method_description = __('Description of my custom payment gateway.', 'my-custom-payment-gateway');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title                    = $this->get_option('title');
        $this->description              = $this->get_option('description');
        $this->instructions             = $this->get_option('instructions', $this->description);
        $this->hide_for_non_admin_users = $this->get_option('hide_for_non_admin_users');

        // Actions.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_scheduled_subscription_payment_custom', array( $this, 'process_subscription_payment' ), 10, 2);
        add_action('wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ), 10);
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'my-custom-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable My Custom Gateway', 'my-custom-payment-gateway'),
                'default' => 'yes',
            ),
            'hide_for_non_admin_users' => array(
                'type'    => 'checkbox',
                'label'   => __('Hide at checkout for non-admin users', 'my-custom-payment-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'my-custom-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'my-custom-payment-gateway'),
                'default'     => _x('Custom Payment', 'Custom payment method', 'my-custom-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'my-custom-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'my-custom-payment-gateway'),
                'default'     => __('Description of my custom payment gateway.', 'my-custom-payment-gateway'),
                'desc_tip'    => true,
            ),
            'result' => array(
                'title'    => __('Payment result', 'my-custom-payment-gateway'),
                'desc'     => __('Determine if order payments are successful when using this gateway.', 'my-custom-payment-gateway'),
                'id'       => 'woo_custom_payment_result',
                'type'     => 'select',
                'options'  => array(
                    'success'  => __('Success', 'my-custom-payment-gateway'),
                    'failure'  => __('Failure', 'my-custom-payment-gateway'),
                ),
                'default' => 'success',
                'desc_tip' => true,
            )
        );
    }

    /**
     * Process the payment and return the result.
     *
     * @param  int  $order_id
     * @return array
     */
    public function process_payment($order_id)
    {

        $payment_result = $this->get_option('result');
        $order = wc_get_order($order_id);

        if ('success' === $payment_result) {
            // Handle pre-orders charged upon release.
            if (class_exists('WC_Pre_Orders_Order')
                    && WC_Pre_Orders_Order::order_contains_pre_order($order)
                    && WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)
            ) {
                // Mark order as tokenized (no token is saved for the custom gateway).
                $order->update_meta_data('_wc_pre_orders_has_payment_token', '1');
                $order->save_meta_data();
                WC_Pre_Orders_Order::mark_order_as_pre_ordered($order);
            } else {
                $order->payment_complete();
            }

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url($order)
            );
        } else {
            $message = __('Order payment failed. Please review the Custom Payments gateway settings to ensure a successful transaction.', 'my-custom-payment-gateway');
            $order->update_status('failed', $message);
            throw new Exception($message);
        }
    }

    /**
     * Process subscription payment.
     *
     * @param  float     $amount
     * @param  WC_Order  $order
     * @return void
     */
    public function process_subscription_payment($amount, $order)
    {
        $payment_result = $this->get_option('result');

        if ('success' === $payment_result) {
            $order->payment_complete();
        } else {
            $order->update_status('failed', __('Subscription payment failed. Please review the Custom Payments gateway settings to ensure a successful transaction.', 'my-custom-payment-gateway'));
        }
    }

    /**
     * Process pre-order payment upon order release.
     *
     * Processes the payment for pre-orders charged upon release.
     *
     * @param WC_Order $order The order object.
     */
    public function process_pre_order_release_payment($order)
    {
        $payment_result = $this->get_option('result');

        if ('success' === $payment_result) {
            $order->payment_complete();
        } else {
            $message = __('Order payment failed. Please review the Custom Payments gateway settings to ensure a successful transaction.', 'my-custom-payment-gateway');
            $order->update_status('failed', $message);
        }
    }
}
