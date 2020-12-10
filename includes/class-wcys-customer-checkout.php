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

			if (! is_admin() ) {
				add_filter( 'woocommerce_after_shipping_rate', array( $this, 'wcys_find_all_available_shipping_rates' ), 10, 2 );

				add_action( 'wp_enqueue_scripts', array( $this, 'wcys_custom_script' ) );
			}
		}

		public function wcys_find_all_available_shipping_rates( $method, $index ) {
			 if( ! is_checkout()) return; // Only on checkout page

			    $customer_carrier_method = 'wcys_shipping';

			    if( $method->id != $customer_carrier_method ) return; // Only display for "local_pickup"

			    $chosen_method_id = WC()->session->chosen_shipping_methods[ $index ];

			    // If the chosen shipping method is 'legacy_local_pickup' we display
			    if($chosen_method_id == $customer_carrier_method ):

			    $option = get_option( 'wcys_vehicle', true);
			    $vehicle = [];
			    if( $option ){
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

			    woocommerce_form_field( 'wcys_vehicle_type', array(
			    'type'          => 'select',
			    'class'         => array('form-row-last'),
			    'label'         => __('Vehicle Type'),
			    'required'    => true,
			    'placeholder'       => __('- Select Vehicle -'),
			    'options'     => $vehicle 
			    ), WC()->checkout->get_value( 'wcys_vehicle_type' ));

			   	woocommerce_form_field( 'wcys_delivery_type', array(
					'type' => 'radio',
					'required' => 'true',
					'class' => array('wcys_delivery_type'),
					'default' => 'test',//$delivery_type,
					'checked' => 'checked',
					'options' => array(
					'delivery' => 'ASAP',
					'pickup' => 'Schedule')
					), WC()->checkout->get_value( 'wcys_delivery_type')
				);

			    woocommerce_form_field( 'wcys_deliver_date' , array(
			        'type'          => 'text',
			        'class'         => array('form-row-wide wcys_deliver_date'),
			        'required'      => true,
			        'placeholder'   => 'Choose Delivery Date',
			    ), WC()->checkout->get_value( 'wcys_deliver_date' ));

			    echo '</div>';
			    endif;
		}

		public function wcys_custom_script() {
			
			if( is_checkout() ){
				wp_enqueue_script( 'wcys-autocomplete-polyfill', 'https://polyfill.io/v3/polyfill.min.js?features=default', array( 'jquery' ), '2.1' );
				wp_enqueue_script( 'wcys-autocomplete-search', 'https://maps.googleapis.com/maps/api/js?key='.get_option(self::$settings_tab .'_google_api').'&libraries=places&v=weekly', array( 'jquery' ), '2.1.3' );
			}	
			wp_enqueue_script( 'wcys-select2-js', WCYS_PLUGIN_URL . 'includes/js/select2.full.min.js', array( 'jquery' ), true );
			wp_enqueue_script( 'wcys-script', WCYS_PLUGIN_URL . 'includes/js/admin-script.js', array( 'jquery' ), '1.0' );
			wp_localize_script( 'wcys-script','ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ),'ajax_action'=>'wcys_fare_lat_long' ]);
		
			wp_enqueue_style( 'wcys-select2-css', WCYS_PLUGIN_URL . 'includes/css/select2.min.css');
		}
		

	}

}
	new WCYS_Customer_Checkout();
