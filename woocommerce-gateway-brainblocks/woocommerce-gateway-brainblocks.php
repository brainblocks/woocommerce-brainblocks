<?php
/**
 * Plugin Name: WooCommerce Brainblocks Gateway
 * Plugin URI: https://brainblocks.io
 * Description: Accept payments with RaiBlocks using brainblocks.io checkout
 * Author: Daniel Brain
 * Author URI: https://brainblocks.io
 * Version: 1.0.0
 * Text Domain: wc-gateway-brainblocks
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2018 Daniel Brain
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Brainblocks
 * @author    Daniel Brain
 * @category  Admin
 * @copyright Copyright: (c) 2018 Daniel Brain
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Accept payments with RaiBlocks using brainblocks.io checkout
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
	$gateways[] = 'WC_Gateway_Brainblocks';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_brainblocks_add_to_gateways' );
/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_brainblocks_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=brainblocks_gateway' ) . '">' . __( 'Configure', 'wc-gateway-brainblocks' ) . '</a>'
	);
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_brainblocks_gateway_plugin_links' );
/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Brainblocks
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_brainblocks_gateway_init', 11 );
function wc_brainblocks_gateway_init() {
	class WC_Gateway_Brainblocks extends WC_Payment_Gateway {
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'brainblocks_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Brainblocks', 'wc-gateway-brainblocks' );
            $this->method_description = __( 'Allows RaiBlocks payments.', 'wc-gateway-brainblocks' );
            $this->order_button_text  = __( 'brainblocks', 'wc-gateway-brainblocks' );
		  
			// Load the settings.
			$this->init_form_fields();
            $this->init_settings();
            
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            
            add_filter('woocommerce_order_button_html',array( $this, 'display_brainblocks_button_html' ),1);

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
        
        public function display_brainblocks_button_html($value) {

            $total = (string)round($this->get_order_total(), 2);
            $currency = strtolower(get_woocommerce_currency());

            ?>
                <style>
                    .brainblocks-standard-button, .brainblocks-raiblocks-button {
                        display: none;
                    }

                    #raiblocks-button {
                        text-align: center;
                    }

                    .woocommerce-checkout-review-order {
                        transition: height 0.5s ease-in-out;
                    }
                </style>

                <div class="brainblocks-standard-button">
                    <?= $value ?>
                </div>

                <div class="brainblocks-raiblocks-button">
                    <div id="raiblocks-button"></div>
                </div>


                <script>
                    (function() {
                        var $ = jQuery;

                        function loadBrainBlocksScript(callback) {
                            if (window.brainblocks) {
                                callback(window.brainblocks);
                            } 

                            if (window.brainblocksLoadCallbacks) {
                                return window.brainblocksLoadCallbacks.push(callback);
                            }

                            window.brainblocksLoadCallbacks = [ callback ];

                            var script = document.createElement('script');
                            script.src = 'https://brainblocks.io/brainblocks.min.js';
                            script.onload = function() {
                                window.brainblocksLoadCallbacks.forEach(function(cb) {
                                    cb(window.brainblocks);
                                });

                                delete window.brainblocksLoadCallbacks;
                            };

                            document.body.appendChild(script);
                        }

                        function isBrainBlocksButtonRendered() {
                            return Boolean(document.querySelector('#raiblocks-button').children.length);
                        }

                        function validateForm() {
                            var checkout_form = $( 'form.checkout' );

                            checkout_form.one('checkout_place_order', function(event) {
                                return false;
                            });

                            checkout_form.submit();

                            return new brainblocks.Promise(function(resolve) {
                                setTimeout(resolve, 400);
                            }).then(function() {

                                if (document.querySelector('.woocommerce-invalid')) {
                                    checkout_form.submit();
                                    return false;
                                }

                                return true;
                            });
                        }

                        function renderBrainBlocksButton() {

                            if (isBrainBlocksButtonRendered()) {
                                return;
                            }

                            brainblocks.Button.render({

                                style: {
                                    size: 'responsive'
                                },

                                payment: {
                                    destination: '<?= $this->settings['destination'] ?>',
                                    currency:    '<?= $currency ?>',
                                    amount:      '<?= $total ?>'
                                },

                                onClick: function() {
                                    return validateForm();
                                },

                                onPayment: function(data) {
                                    var checkout_form = $( 'form.checkout' );
                                    checkout_form.append('<input type="hidden" name="brainblocks_token" value="' + data.token + '">');
                                    checkout_form.submit();
                                }

                            }, '#raiblocks-button');
                        }

                        function toggleBrainBlocksButton() {

                            var isBrainBlocks = jQuery('.brainblocks-standard-button input').val() === 'brainblocks' || 
                                              jQuery('.brainblocks-standard-button button').html() === 'brainblocks' ||
                                              jQuery('.brainblocks-standard-button button').val() === 'brainblocks';

                            if (isBrainBlocks) {
                                if (!isBrainBlocksButtonRendered()) {
                                    loadBrainBlocksScript(function(brainblocks) {
                                        renderBrainBlocksButton();
                                    });
                                }

                                jQuery('.brainblocks-standard-button').hide();
                                jQuery('.brainblocks-raiblocks-button').show();
                            } else {
                                jQuery('.brainblocks-raiblocks-button').hide();
                                jQuery('.brainblocks-standard-button').show();
                            }
                        }

                        loadBrainBlocksScript(function() {
                            setTimeout(function() {
                                renderBrainBlocksButton();
                                toggleBrainBlocksButton();
                            }, 500);
                        });

                        jQuery('body').on('click', function() {
                            toggleBrainBlocksButton();
                        });

                        toggleBrainBlocksButton();
                    })();
                </script>
            <?php
        }
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_brainblocks_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-brainblocks' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable RaiBlocks Payments via brainblocks.io', 'wc-gateway-brainblocks' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-brainblocks' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-brainblocks' ),
					'default'     => __( 'RaiBlocks Payment', 'wc-gateway-brainblocks' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-brainblocks' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-brainblocks' ),
					'default'     => __( 'Pay with RaiBlocks via brainblocks.io.', 'wc-gateway-brainblocks' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-brainblocks' ),
					'type'        => 'textarea',
					'description' => __( 'Pay with RaiBlocks via brainblocks.io.', 'wc-gateway-brainblocks' ),
					'default'     => '',
					'desc_tip'    => true,
                ),
                
                'destination' => array(
					'title'       => __( 'RaiBlocks Address', 'wc-gateway-brainblocks' ),
					'type'        => 'text',
					'description' => __( 'Address to receive any sent RaiBlocks', 'wc-gateway-brainblocks' ),
					'default'     => __( '', 'wc-gateway-brainblocks' ),
					'desc_tip'    => true,
				),
			) );
        }
        
		public function process_payment($order_id) {

            $order = wc_get_order( $order_id );
            $transaction = json_decode(file_get_contents('https://brainblocks.io/api/session/' . $_POST['brainblocks_token'] . '/verify'));

            $total = (string)round($order->get_total(), 2);
            $currency = strtolower(get_woocommerce_currency());

            $error = '';

            if ($transaction->destination !== $this->settings['destination']) {
                $error = ('Incorrect destination: ' . $transaction->destination . ' expected ' . $this->settings['destination']);
            } else if ($transaction->amount !== $total) {
                $error = ('Incorrect amount: ' . var_export($transaction->amount, true) . ' expected ' . var_export($total, true));
            } else if ($transaction->currency !== $currency) {
                $error = ('Incorrect currency: ' . $transaction->currency . ' expected ' . $currency);
            }

            if ($error) {
                $order->update_status('failed', $error);

                wc_add_notice($error, 'error'); 

                return array(
                    'result' => 'failure'
                );
            }
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status('processing');
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_Gateway_Brainblocks class
}