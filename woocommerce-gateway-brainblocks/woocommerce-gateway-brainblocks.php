<?php
/**
 * Plugin Name: WooCommerce Brainblocks Gateway
 * Plugin URI: https://brainblocks.io
 * Description: Accept payments with Nano using BrainBlocks
 * Author: BrainBlocks
 * Author URI: https://brainblocks.io
 * Version: 1.3
 * Text Domain: brainblocks-gateway
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2018 BrainBlocks
 *
 *
 * @package   Brainblocks-Gateway
 * @author    BrainBlocks
 * @category  Admin
 * @copyright Copyright: (c) 2018 BrainBlocks
 *
 * Accept payments with Nano using BrainBlocks 
 */
 
 
defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

function toCents($amount) {
    return (int)floor($amount * 100);
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_brainblocks_add_to_gateways( $gateways ) {
    $gateways[] = 'BrainBlocks_Gateway';
    return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'wc_brainblocks_add_to_gateways' );

function wc_brainblocks_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=brainblocks_gateway' ) . '">' . __( 'Configure', 'wc-gateway-brainblocks' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_brainblocks_gateway_plugin_links' );

add_action( 'plugins_loaded', 'wc_brainblocks_gateway_init', 11 );
function wc_brainblocks_gateway_init() {
    class BrainBlocks_Gateway extends WC_Payment_Gateway {
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
      
            $this->id                 = 'brainblocks_gateway';
            $this->icon               = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Brainblocks', 'wc-gateway-brainblocks' );
            $this->method_description = __( 'Allows Nano payments.', 'wc-gateway-brainblocks' );
            $this->order_button_text  = __( 'Continue to payment', 'wc-gateway-brainblocks' );
          
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'init', array( $this, 'maybe_return_from_brainblocks' ) );

            $this->maybe_return_from_brainblocks();
        }
    
        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
      
            $this->form_fields = apply_filters( 'wc_brainblocks_form_fields', array(
          
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-brainblocks' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Nano Payments via BrainBlocks', 'wc-gateway-brainblocks' ),
                    'default' => 'yes'
                ),
                
                'title' => array(
                    'title'       => __( 'Title', 'wc-gateway-brainblocks' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-brainblocks' ),
                    'default'     => __( 'Nano Payment', 'wc-gateway-brainblocks' ),
                    'desc_tip'    => true,
                ),
                
                'description' => array(
                    'title'       => __( 'Description', 'wc-gateway-brainblocks' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-brainblocks' ),
                    'default'     => __( 'Pay with Nano via BrainBlocks.', 'wc-gateway-brainblocks' ),
                    'desc_tip'    => true,
                ),
                
                'instructions' => array(
                    'title'       => __( 'Instructions', 'wc-gateway-brainblocks' ),
                    'type'        => 'textarea',
                    'description' => __( 'Pay with Nano via BrainBlocks.', 'wc-gateway-brainblocks' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                
                'destination' => array(
                    'title'       => __( 'Nano Address', 'wc-gateway-brainblocks' ),
                    'type'        => 'text',
                    'description' => __( 'Address to receive any sent Nano', 'wc-gateway-brainblocks' ),
                    'default'     => __( '', 'wc-gateway-brainblocks' ),
                    'desc_tip'    => true,
                ),

                'paypal_email' => array(
                    'title'       => __( 'PayPal Email Address', 'wc-gateway-brainblocks' ),
                    'type'        => 'text',
                    'description' => __( 'Address to receive PayPal Payments', 'wc-gateway-brainblocks' ),
                    'default'     => __( '', 'wc-gateway-brainblocks' ),
                    'desc_tip'    => true,
                ),
            ) );
        }
        
        public function maybe_return_from_brainblocks() {
          if(isset($_GET['token']) && isset($_GET['wc_order_id']))
          {
          	$token = $_GET['token'];
            $order_id = $_GET['wc_order_id'];
            if (!empty($token) && !empty($order_id)) {
                $this->process_payment($order_id);
            }
          } 
        }

        public function process_payment($order_id) {

            $order = wc_get_order( $order_id );

            $destination = $this->settings['destination'];
            $paypal = $this->settings['paypal_email'];
            $total = (string)round($order->get_total(), 2);
            $currency = strtolower(get_woocommerce_currency());
            $returnurl = $this->get_return_url( $order ) . '&wc_order_id=' . $order_id;

            $bbtoken = $_GET['token'];

            if (!$bbtoken) {

                $brainblocks_url = 'https://brainblocks.io/checkout?paypal-email=' . $paypal .'&payment.destination=' . $destination . '&payment.currency=' . $currency . '&payment.amount=' . $total . '&urls.return=' . urlencode($returnurl) . '&urls.cancel=' . urlencode($returnurl);
                return array(
                    'result'    => 'success',
                    'redirect'  => $brainblocks_url
                );
            }

            $request       = wp_remote_get( esc_url_raw ( 'https://brainblocks.io/api/session/' . $bbtoken . '/verify' ) );

            $response_code = wp_remote_retrieve_response_code( $request );

            $error = '';

            if ( 200 != $response_code ) {
                    $error = ('Incorrect response code from API: ' . esc_url_raw ( 'https://brainblocks.io/api/session/' . $bbtoken . '/verify' ) . ' (' . $response_code . ')');
            } else {
                $transaction = json_decode( wp_remote_retrieve_body( $request ) );

                if ($transaction->destination !== $destination) {
                    $error = ('Incorrect destination: ' . $transaction->destination . ' expected ' . $this->settings['destination']);
                } else if ($transaction->amount !== $total) {
                    $error = ('Incorrect amount: ' . var_export($transaction->amount, true) . ' expected ' . var_export($total, true));
                } else if ($transaction->currency !== $currency) {
                    $error = ('Incorrect currency: ' . $transaction->currency . ' expected ' . $currency);
                }
            }

            if ($error) {
                $order->update_status('failed', $error);

                wc_add_notice($error, 'error'); 

                return array(
                    'result' => 'failure'
                );
            }

            // Mark order status as processing and reduce stock
            $order->payment_complete();
    
            // Remove cart
            WC()->cart->empty_cart();
            
            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $returnurl
            );
        }
    
  } // end \BrainBlocks_Gateway class
}
