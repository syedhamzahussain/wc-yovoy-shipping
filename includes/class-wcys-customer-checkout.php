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

			add_action( 'wp', array( $this, 'init_front' ) );
			add_action( 'wp_ajax_wcys_fare_lat_long', array( $this, 'wcys_fare_lat_long' ) );
			add_action( 'wp_ajax_nopriv_wcys_fare_lat_long', array( $this, 'wcys_fare_lat_long' ) );
			add_filter('woocommerce_package_rates', array( $this,'wcys_shipping_cost_based_on_api'), 10, 1);


		}

		public function init_front(){

			add_action( 'wp_enqueue_scripts', array( $this, 'wcys_custom_script' ) );
			add_filter( 'woocommerce_after_shipping_rate', array( $this, 'wcys_find_all_available_shipping_rates' ), 10, 2 );
			add_action('woocommerce_checkout_process', array( $this, 'wcys_checkout_process' ) );
			add_action( 'woocommerce_checkout_update_order_meta',array( $this, 'wcys_checkout_field_update_order_meta'), 30, 1 );
			
		}

		public function wcys_shipping_cost_based_on_api( $rates ) {

		    if ( WC()->session->get('wcys_fare_price' ) ){
		        foreach ( $rates as $rate_key => $rate_values ) {
		            // Not for "Free Shipping method" (all others only)
		            if ( 'wcys_shipping' == $rate_values->method_id ) {

		                // Set the rate cost
		                $rates[$rate_key]->cost = WC()->session->get('wcys_fare_price' );

		            }
		        }
		    }
		    return $rates;
		}


		public function wcys_checkout_field_update_order_meta(  $order_id ){
			if( isset( $_POST['wcys_delivery_address'] ) ){
        		update_post_meta( $order_id, '_yovoy_delivery_address', sanitize_text_field( $_POST['wcys_delivery_address'] ) );

        		update_post_meta( $order_id, '_latitude ', WC()->session->get( 'wcys_latitude ' ) );
        		update_post_meta( $order_id, '_longitude', WC()->session->get( 'wcys_longitude' ) );
        	}

        	if( isset( $_POST['wcys_vehicle_type'] ) ){
        		update_post_meta( $order_id, '_yovoy_vehicle_type', sanitize_text_field( $_POST['wcys_vehicle_type'] ) );
        	}

    		if( isset( $_POST['wcys_delivery_type'] ) ) {
    			if('schedule' == strtolower( $_POST['wcys_delivery_type'] ) ){
        			update_post_meta( $order_id, '_yovoy_delivery_type', sanitize_text_field( $_POST['wcys_delivery_type'] ) );
    			}
    			else {
    				update_post_meta( $order_id, '_yovoy_delivery_type', date("Y/m/d H:i:s", strtotime("+30 minutes")));
    			}

    		}

		}

		public function wcys_checkout_process(){
			if( isset( $_POST['wcys_delivery_address'] ) && empty( $_POST['wcys_delivery_address'] ) ){
        		wc_add_notice( ( "Please don't forget to enter delivery address." ), "error" );
			}

			if( isset( $_POST['wcys_delivery_type'] ) ){
		   		if( 'schedule' == strtolower( $_POST['wcys_delivery_type'] ) && empty( $_POST['wcys_deliver_date'] )){
        			wc_add_notice( ( "Please don't forget to enter delivery date and time" ), "error" );
		    	}
		    }
		}

		public function wcys_fare_lat_long(){
			if ( isset( $_POST['wcys_lat'] ) && isset( $_POST['wcys_long'] ) ) {
				$url = "https://integrations.yovoyenvios.com/api/delivery/fare-estimate";
				$response = wp_remote_post( $url, [
					'body'        => wp_json_encode([
						'pickup' => [
							'latitude' => get_option( 'wcys_pickup_latitude' ),
							'longitude' => get_option( 'wcys_pickup_longitude' ),
							],
						'delivery' => [
							'latitude' => $_POST['wcys_lat'],
							'longitude' => $_POST['wcys_long'],
							],
						'vechicle' => $_POST['wcys_vechicle'],
						'apiToken' => get_option( 'wcys_api' )	
						]),
				    'headers'     => [
				        'Content-Type' => 'application/json',
				    ],
				]);

				$responseBody = wp_remote_retrieve_body( $response );
				$data = json_decode( $responseBody );
				if (isset($data->fare)) {
					WC()->session->set('wcys_fare_price',$data->fare );
				}
				
				WC()->session->set('wcys_delivery_latitude', $_POST['wcys_lat'] );
				WC()->session->set('wcys_delivery_longitude', $_POST['wcys_long'] );
				WC()->session->set('wcys_vechicle', $_POST['wcys_vechicle'] );
				return wp_send_json( array( 'status' => 'success', 'data' => $data) );
				
			}
			wp_die();
		}

		public function wcys_find_all_available_shipping_rates( $method, $index ) {
			 if( is_checkout() || is_cart()):
			  // Only on checkout page

			    $customer_carrier_method = 'wcys_shipping';

			    if( $method->id != $customer_carrier_method ) return; // Only display for "local_pickup"

			    $chosen_method_id = WC()->session->chosen_shipping_methods[ $index ];

			    // If the chosen shipping method is 'legacy_local_pickup' we display
			    if($chosen_method_id == $customer_carrier_method ):

			    $option = get_option( 'wcys_vehicle', true);
			    $vehicle = [];
			    if( is_array( $option ) ){
			    	foreach( $option as $val){
			    		switch ( $val ) {
			    			case  $val == 1:
			    				array_push($vehicle, 'Moto (Bike)');
			    				break;	
			    			case  $val == 2:
			    				array_push($vehicle, 'Carro (Car)');
			    				break;	
			    			case  $val == 3:
			    				array_push($vehicle, 'Mini-camión (Light truck)');
			    				break;	
			    			
			    		}
			    	}
			    }

			    echo '<div class="custom-carrier">';
			    woocommerce_form_field( 'wcys_delivery_address' , array(
			        'type'          => 'text',
			        'class'         => array('form-row-wide'),
			        'id'         =>  'wcys_google_address',
			        'label'         => 'Delivery Address:',
			        'required'      => true,
			        'placeholder'   => 'Enter Delivery Address',
			    ), WC()->checkout->get_value( 'wcys_delivery_address' ));

			    woocommerce_form_field( 'wcys_vehicle', array(
			    'type'          => 'select',
			    'class'         => array('form-row-last'),
			    'label'         => __('Vehicle Type'),
			    'required'    => true,
			    'placeholder'       => __('- Select Vehicle -'),
			    'options'     => $vehicle 
			    ), WC()->checkout->get_value( 'wcys_vehicle' ));

			   	woocommerce_form_field( 'wcys_delivery_type', array(
					'type' => 'radio',
					'required' => 'true',
					'input_class' => array('wcys_delivery_type'),
					'default' => 'asap',//$delivery_type,
					'checked' => 'checked',
					'options' => array(
					'asap' => 'ASAP',
					'schedule' => 'Schedule')
					), WC()->checkout->get_value( 'wcys_delivery_type')
				);

			    woocommerce_form_field( 'wcys_deliver_date' , array(
			        'type'          => 'hidden',
			        'input_class'         => array('form-row-wide wcys_deliver_date'),
			        'required'      => true,
			        'placeholder'   => 'Choose Delivery Date',
			        'style'			=> 'display:none',
			    ), WC()->checkout->get_value( 'wcys_deliver_date' ));

			    echo '</div>';
			    ?>
			    <script type="text/javascript">
			    	initAutocomplete();
			    	jQuery(".wcys_delivery_type").change(function(){
						if( jQuery( this ).val() == 'schedule'){
							jQuery( ".wcys_deliver_date" ).attr('type','text');
							jQuery('.wcys_deliver_date').datepicker({
				                isRTL: true,
				                dateFormat: "yy/mm/dd 23:59:59",
				                changeMonth: true,
				                changeYear: true

				            });
						}else {
							jQuery( ".wcys_deliver_date" ).attr('type','hidden');
						}

			    	}) 
				</script>
			    <?php
			    endif;
			endif;

		}

		public function wcys_custom_script() {
			
			if( is_checkout() || is_cart() ){
				wp_enqueue_script( 'wcys-autocomplete-polyfill', 'https://polyfill.io/v3/polyfill.min.js?features=default', array( 'jquery' ), '2.1' );
				wp_enqueue_script( 'wcys-autocomplete-search', 'https://maps.googleapis.com/maps/api/js?key='.get_option(self::$settings_tab .'_google_api').'&libraries=places&v=weekly', array( 'jquery' ), '2.1.3' );
			}	
			wp_enqueue_script( 'wcys-select2-js', WCYS_PLUGIN_URL . 'includes/js/select2.full.min.js', array( 'jquery' ), true );
			wp_enqueue_script( 'wcys-script', WCYS_PLUGIN_URL . 'includes/js/admin-script.js', array( 'jquery' ), '1.0' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_localize_script( 'wcys-script','ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ),'ajax_action'=>'wcys_fare_lat_long' ]);
			
			 wp_enqueue_style( 'jquery-ui-datepicker-style' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
			wp_enqueue_style( 'wcys-select2-css', WCYS_PLUGIN_URL . 'includes/css/select2.min.css');
		}
		

	}

}
	new WCYS_Customer_Checkout();
