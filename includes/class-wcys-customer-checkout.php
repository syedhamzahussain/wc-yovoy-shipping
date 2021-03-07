<?php

/**
 * This class handles all the settings functionality in admin area
 *
 * @package Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCYS_Customer_Checkout' ) ) {

	/**
	 * Woo Bookings Settings Class
	 *
	 * @package Settings
	 */
	class WCYS_Customer_Checkout {

		/**
		 * Settings Tab Variable.
		 *
		 * @var static $settings_tab Settings Tab Variable.
		 */
		public static $settings_tab = 'wcys';

		/**
		 * Constructor
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'init_front' ) );
			add_action( 'wp_ajax_wcys_fare_lat_long', array( $this, 'wcys_fare_lat_long' ) );
			add_action( 'wp_ajax_nopriv_wcys_fare_lat_long', array( $this, 'wcys_fare_lat_long' ) );
			add_filter( 'woocommerce_package_rates', array( $this, 'wcys_shipping_cost_based_on_api' ), 10, 1 );

			add_action( 'wp_ajax_get_latest_total', array( $this, 'get_latest_total' ) );
			add_action( 'wp_ajax_get_latest_total', array( $this, 'get_latest_total' ) );
		}

		public function init_front() {

			add_action( 'wp_enqueue_scripts', array( $this, 'wcys_custom_script' ) );
			add_filter( 'woocommerce_after_shipping_rate', array( $this, 'wcys_find_all_available_shipping_rates' ), 10, 2 );
			add_action( 'woocommerce_checkout_process', array( $this, 'wcys_checkout_process' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'wcys_checkout_field_update_order_meta' ), 30, 1 );
			add_action( 'woocommerce_thankyou', array( $this, 'wcys_shipping_thankyou' ) );
			add_filter( 'woocommerce_checkout_fields', array( $this,'wcys_remove_fields'), 10,1 );

			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
 
		public function wcys_remove_fields( $woo_checkout_fields_array ) {

			$chosen_shipping_method = '';
			if ( WC()->session->get( 'chosen_shipping_methods' ) ) {
				$chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' )[0];
			}
		 
		if ($chosen_shipping_method == 'wcys_shipping') {
			unset( $woo_checkout_fields_array['billing']['billing_address_1'] );
			unset( $woo_checkout_fields_array['billing']['billing_address_2'] );
			unset( $woo_checkout_fields_array['billing']['billing_city'] );
			unset( $woo_checkout_fields_array['billing']['billing_state'] ); // remove state field
			unset( $woo_checkout_fields_array['billing']['billing_postcode'] ); // remove zip code field
		}
			return $woo_checkout_fields_array;
		}

		public function get_latest_total() {
			global $woocommerce;
			$cart     = $woocommerce->cart;
			$wc_price = $cart->total;

			if ( ( $wc_price + $_REQUEST['cost'] ) < $wc_price ) {
				$wc_price = ( $_REQUEST['cost'] ) + $cart->total;
			}

			wp_die( wc_price( $wc_price ) );
		}

		public function wcys_shipping_thankyou( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order->has_shipping_method( 'wcys_shipping' ) && ! WC()->session->get( "order_delivery_api_{$order_id}" ) ) {
				WC()->session->set( "order_delivery_api_{$order_id}", true );
				$url  = 'https://integrations.yovoyenvios.com/api/delivery';
				$body = array(
					'pickup'   => array(
						'latitude'  => get_option( 'wcys_pickup_latitude' ),
						'longitude' => get_option( 'wcys_pickup_longitude' ),
						'email'     => get_option( 'wcys_email' ),
						'phone'     => get_option( 'wcys_phone' ),
						'name'      => get_option( 'wcys_name' ),
						'notes'     => get_option( 'wcys_notes' ),
						'reference' => get_option( 'wcys_reference' ),
						'vehicle'   => 0,
						'date'      => $order->get_meta( '_yovoy_pickup_date' ),
					),
					'delivery' => array(
						'latitude'  => WC()->session->get( 'wcys_delivery_latitude' ),
						'longitude' => WC()->session->get( 'wcys_delivery_longitude' ),
						'email'     => $order->get_billing_email(),
						'phone'     => $order->get_billing_phone(),
						'name'      => $order->get_shipping_first_name() ? $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'notes'     => 'Additional delivery notes or steps for an agent.',
						'reference' => $order->get_meta( '_wcys_delivery_reference' ),
						// "cashOnDelivery"=> 500,
						'date'      => $order->get_meta( '_yovoy_delivery_date' ), // "Thu Dec 23 2020 09:00:00 GMT-0600 (Central Standard Time)"
					),
					'apiToken' => get_option( 'wcys_api' ),
				);

				if( 'cod' === $order->get_payment_method() ){
					$body['delivery']['cashOnDelivery'] = $order->get_total();
				}

				$data = $this->wcys_send_post_request( $url, wp_json_encode( $body ) );
				if ( ! empty( $data ) && $data->success ) {
					update_post_meta( $order_id, 'trackingLink', $data->trackingLink );
					echo '<strong>Tracking Link:</strong> ' . $data->trackingLink;
				}
				return;
			}
			if ( $order->has_shipping_method( 'wcys_shipping' ) ) {
				echo '<strong>Tracking Link:</strong> ' . $order->get_meta( 'trackingLink' );
			}

			return;
		}

		public function wcys_shipping_cost_based_on_api( $rates ) {

			if ( WC()->session->get( 'wcys_fare_price' ) ) {
				foreach ( $rates as $rate_key => $rate_values ) {
					// Not for "Free Shipping method" (all others only)
					if ( 'wcys_shipping' == $rate_values->method_id ) {
						if ( empty( get_option( 'wcys_google_api' ) ) || empty( get_option( 'wcys_api' ) ) || empty( get_option( 'wcys_google_address' ) ) || empty( get_option( 'wcys_pickup_latitude' ) ) || empty( get_option( 'wcys_pickup_longitude' ) ) ) {
							unset( $rates[ $rate_key ] );
							break;
						}

						// Set the rate cost
						if ( WC()->session->get( 'wcys_fare_price' ) > 0 ) {
							$rates[ $rate_key ]->cost = WC()->session->get( 'wcys_fare_price' );
						}
					}
				}
			}
			return $rates;
		}

		public function wcys_checkout_field_update_order_meta( $order_id ) {
			if ( isset( $_POST['wcys_delivery_address'] ) ) {
				update_post_meta( $order_id, '_yovoy_delivery_address', sanitize_text_field( $_POST['wcys_delivery_address'] ) );

				update_post_meta( $order_id, '_latitude ', WC()->session->get( 'wcys_latitude ' ) );
				update_post_meta( $order_id, '_longitude', WC()->session->get( 'wcys_longitude' ) );
			}

			if ( isset( $_POST['wcys_vehicle'] ) ) {
				update_post_meta( $order_id, '_yovoy_vehicle', sanitize_text_field( $_POST['wcys_vehicle'] ) );
			}

			if ( isset( $_POST['wcys_delivery_reference'] ) ) {
				update_post_meta( $order_id, '_wcys_delivery_reference', sanitize_text_field( $_POST['wcys_delivery_reference'] ) );
			}

			if ( isset( $_POST['wcys_delivery_type'] ) ) {
				if ( 'schedule' == strtolower( $_POST['wcys_delivery_type'] ) ) {
					$date = $_POST['wcys_deliver_date'];
					update_post_meta( $order_id, '_yovoy_pickup_date', gmdate( 'D M d Y H:i:s O', strtotime( $date . '-20 minutes' ) ) );
					update_post_meta( $order_id, '_yovoy_delivery_date', gmdate( 'D M d Y H:i:s O', strtotime( $date ) ) );
					update_post_meta( $order_id, '_yovoy_delivery_type', sanitize_text_field( $_POST['wcys_delivery_type'] ) );
				} else {
					update_post_meta( $order_id, '_yovoy_pickup_date', gmdate( 'D M d Y H:i:s O', strtotime( '+20 minutes' ) ) );
					update_post_meta( $order_id, '_yovoy_delivery_date', gmdate( 'D M d Y H:i:s O', strtotime( '+30 minutes' ) ) );
					update_post_meta( $order_id, '_yovoy_delivery_type', sanitize_text_field( $_POST['wcys_delivery_type'] ) );
				}
			}
		}

		public function wcys_checkout_process() {

			if ( isset( $_POST['wcys_delivery_address'] ) && empty( $_POST['wcys_delivery_address'] ) ) {
				wc_add_notice( __( "Please don't forget to enter delivery address.", 'wcys' ), 'error' );
			}

			if ( isset( $_POST['wcys_delivery_type'] ) ) {
				if ( 'schedule' == strtolower( $_POST['wcys_delivery_type'] ) && empty( $_POST['wcys_deliver_date'] ) ) {
					wc_add_notice( __( "Please don't forget to enter delivery date and time", 'wcys' ), 'error' );
				}
			}

			if ( isset( $_POST['wcys_delivery_reference'] ) && empty( $_POST['wcys_delivery_reference'] ) ) {

					wc_add_notice( __( "Please provide a reference for your Delivery Agent.", 'wcys' ), 'error' );

			}
		}

		public function wcys_fare_lat_long() {
			if ( isset( $_POST['wcys_lat'] ) && isset( $_POST['wcys_long'] ) ) {
				$url  = 'https://integrations.yovoyenvios.com/api/delivery/fare-estimate';
				$body = array(
					'pickup'   => array(
						'latitude'  => get_option( 'wcys_pickup_latitude' ),
						'longitude' => get_option( 'wcys_pickup_longitude' ),
					),
					'delivery' => array(
						'latitude'  => $_POST['wcys_lat'],
						'longitude' => $_POST['wcys_long'],
					),
					'vechicle' => $_POST['wcys_vechicle'],
					'apiToken' => get_option( 'wcys_api' ),
				);
				$data = $this->wcys_send_post_request( $url, wp_json_encode( $body ) );

				if ( isset( $data->fare ) ) {
					WC()->session->set( 'wcys_fare_price', $data->fare );
					$cost = wc_price( $data->fare );
				} else {
					$cost = 0;
				}

				WC()->session->set( 'wcys_delivery_latitude', $_POST['wcys_lat'] );
				WC()->session->set( 'wcys_delivery_longitude', $_POST['wcys_long'] );
				WC()->session->set( 'wcys_google_address', $_POST['wcys_google_address'] );
				WC()->session->set( 'wcys_vechicle', $_POST['wcys_vechicle'] );

				global $woocommerce;
				$cart       = $woocommerce->cart;
				$total_cost = $cart->subtotal + $data->fare;

				return wp_send_json(
					array(
						'status'     => 'success',
						'data'       => $data,
						'cost'       => $cost,
						'total_cost' => wc_price( $total_cost ),
					)
				);
			}
			wp_die();
		}

		private function wcys_send_post_request( $url, $body ) {
			$response = wp_remote_post(
				$url,
				array(
					'body'    => $body,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
				)
			);

			$responseBody = wp_remote_retrieve_body( $response );
			return json_decode( $responseBody );
		}

		public function wcys_find_all_available_shipping_rates( $method, $index ) {
			if ( is_checkout() || is_cart() ) :
				// Only on checkout page
				$customer_carrier_method = 'wcys_shipping';

				if ( $method->id != $customer_carrier_method ) {
					return; // Only display for "local_pickup"
				}

				$chosen_method_id = WC()->session->chosen_shipping_methods[ $index ];

				// If the chosen shipping method is 'legacy_local_pickup' we display
				if ( $chosen_method_id == $customer_carrier_method ) :

					$option = get_option( 'wcys_vehicle', true );

					$vehicle = array();
					if ( is_array( $option ) ) {
						foreach ( $option as $val ) {
							switch ( $val ) {
								case $val == 1:
									array_push( $vehicle, __( 'Moto', 'wcys' ) );
									break;
								case $val == 2:
									array_push( $vehicle, __( 'Carro', 'wcys' ) );
									break;
								case $val == 3:
									array_push( $vehicle, __( 'Mini-camión', 'wcys' ) );
									break;
							}
						}
					}

					$yovoy_desc = get_option( 'woocommerce_wcys_shipping_settings', false );
					if ( $yovoy_desc && isset( $yovoy_desc['description'] ) ) {
						$yovoy_desc = $yovoy_desc['description'];
					} else {
						$yovoy_desc = __( 'Please located your location on the map.', 'wcys' );
					}

					echo "<div class='desc_yovoy'>" . $yovoy_desc . '</div>';

					echo '<div class="custom-carrier">';
					woocommerce_form_field(
						'wcys_delivery_address',
						array(
							'type'              => 'text',
							'class'             => array( 'form-row-wide' ),
							'id'                => 'wcys_google_address',
							'label'             => 'Dirección de Envío :',
							'required'          => true,
							'placeholder'       => 'Enter Delivery Address',
							'custom_attributes' => array(
								'data-lat'  => WC()->session->get( 'wcys_delivery_latitude' ) ? WC()->session->get( 'wcys_delivery_latitude' ) : 15.199999,
								'data-long' => WC()->session->get( 'wcys_delivery_longitude' ) ? WC()->session->get( 'wcys_delivery_longitude' ) : -86.241905,
							),
							'default'           => WC()->session->get( 'wcys_google_address' ),
						),
						WC()->checkout->get_value( 'wcys_delivery_address' )
					);

					echo '<div id="map-canvas"></div>';
					woocommerce_form_field(
						'wcys_delivery_reference',
						array(
							'type'        => 'text',
							'input_class' => array( 'form-row-wide wcys_delivery_reference' ),
							'id'          => 'wcys_delivery_reference',
							'required'    => true,
							'placeholder' => 'Referencias para el repartidor',
						),
						WC()->checkout->get_value( 'wcys_delivery_reference' )
					);

					woocommerce_form_field(
						'wcys_vehicle',
						array(
							'type'        => 'select',
							'class'       => array( 'form-row-last' ),
							'label'       => __( 'Tipo de Vehículo' ),
							'required'    => true,
							'placeholder' => __( '- Select Vehicle -' ),
							'options'     => $vehicle,
						),
						WC()->checkout->get_value( 'wcys_vehicle' )
					);

					woocommerce_form_field(
						'wcys_delivery_type',
						array(
							'type'        => 'radio',
							'required'    => 'true',
							'input_class' => array( 'wcys_delivery_type' ),
							'default'     => 'asap', // $delivery_type,
							'checked'     => 'checked',
							'options'     => array(
								'asap'     => __( 'Lo antes posible', 'wcys' ),
								'schedule' => __( 'Agendar', 'wcys' ),
							),
						),
						WC()->checkout->get_value( 'wcys_delivery_type' )
					);

					woocommerce_form_field(
						'wcys_deliver_date',
						array(
							'type'        => 'hidden',
							'input_class' => array( 'form-row-wide wcys_deliver_date' ),
							'required'    => true,
							'placeholder' => 'Choose Delivery Date',
							'style'       => 'display:none',
						),
						WC()->checkout->get_value( 'wcys_deliver_date' )
					);

					echo '</div>';
					?>
					<script type="text/javascript">
						initialize();
						jQuery("[name='wcys_delivery_type']").change(function () {
							if (jQuery(this).val().toLowerCase() == 'schedule') {
								jQuery(".wcys_deliver_date").attr('type', 'text');
								jQuery('.wcys_deliver_date').datepicker({
									isRTL: true,
									dateFormat: "yy/mm/dd 23:59:59",
									changeMonth: true,
									changeYear: true

								});
							} else {
								jQuery(".wcys_deliver_date").attr('type', 'hidden');
							}

						})
					</script>
					<?php

				endif;
			endif;
		}

		public function wcys_custom_script() {

			$shipping_methods = WC()->shipping->get_shipping_methods();
			$current_method   = $shipping_methods;

			$yovoy_title = get_option( 'woocommerce_wcys_shipping_settings', false )['title'];

			if ( ! $yovoy_title ) {
				__( 'YoVoy Shipping', 'wcys' );
			}

			if ( is_checkout() || is_cart() ) {
				wp_enqueue_script( 'wcys-autocomplete-polyfill', 'https://polyfill.io/v3/polyfill.min.js?features=default', array( 'jquery' ), '2.1' );
				wp_enqueue_script( 'wcys-autocomplete-search', 'https://maps.googleapis.com/maps/api/js?key=' . get_option( self::$settings_tab . '_google_api' ) . '&libraries=geometry,places&v=weekly', array( 'jquery' ), '2.1.3' );
			}
			wp_enqueue_script( 'wcys-select2-js', WCYS_PLUGIN_URL . 'includes/js/select2.full.min.js', array( 'jquery' ), true );
			wp_enqueue_script( 'wcys-script', WCYS_PLUGIN_URL . 'includes/js/script.js', array( 'jquery' ), '1.0' );
			wp_enqueue_script( 'jquery-ui-datepicker' );

			$chosen_shipping_method = '';
			if ( WC()->session->get( 'chosen_shipping_methods' ) ) {
				$chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' )[0];
			}

			wp_localize_script(
				'wcys-script',
				'ajax_object',
				array(
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
					'yovoy_title'            => $yovoy_title,
					'wcys_address'           => WC()->session->get( 'wcys_delivery_address' ),
					'chosen_shipping_method' => $chosen_shipping_method,
				)
			);

			wp_enqueue_style( 'jquery-ui-datepicker-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css' );
			wp_enqueue_style( 'wcys-custom-css', WCYS_PLUGIN_URL . 'includes/css/custom.css' );
			wp_enqueue_style( 'wcys-select2-css', WCYS_PLUGIN_URL . 'includes/css/select2.min.css' );
		}

	}

}
new WCYS_Customer_Checkout();
