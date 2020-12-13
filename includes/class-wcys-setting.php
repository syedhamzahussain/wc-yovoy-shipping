<?php
/**
 * This class handles all the settings functionality in admin area
 *
 * @package Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCYS_Settings' ) ) {

	/**
	 * Woo Bookings Settings Class
	 *
	 * @package Settings
	 */
	class WCYS_Settings {

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

			if ( is_admin() ) {
				add_filter( 'woocommerce_settings_tabs_array', array( $this, 'wcys_add_settings_tab' ), 50 );
				add_action( 'woocommerce_settings_tabs_' . self::$settings_tab, array( $this, 'wcys_tab_settings' ) );
				add_action( 'woocommerce_update_options_' . self::$settings_tab, array( $this, 'wcys_update_settings' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'wcys_custom_script' ) );
				add_action( 'wp_ajax_wcys_save_lat_long', array( $this, 'wcys_save_lat_long' ) );
			}
		}

		public function wcys_save_lat_long() {
			if ( isset( $_POST['wcys_lat'] ) && isset( $_POST['wcys_long'] ) ) {
				update_option( 'wcys_pickup_latitude', $_POST['wcys_lat'] );
				update_option( 'wcys_pickup_longitude', $_POST['wcys_long'] );
				return wp_send_json( array( 'status' => 'success' ) );
				wp_die();
			}
		}

		public function wcys_custom_script() {
			
			if( isset( $_GET['tab'] ) && $_GET['tab'] == 'wcys'){
				wp_enqueue_script( 'wcys-autocomplete-polyfill', 'https://polyfill.io/v3/polyfill.min.js?features=default', array( 'jquery' ), '2.1' );
				wp_enqueue_script( 'wcys-autocomplete-search', 'https://maps.googleapis.com/maps/api/js?key='.get_option(self::$settings_tab .'_google_api').'&libraries=places&v=weekly', array( 'jquery' ), '2.1.3' );
				wp_enqueue_script( 'wcys-select2-js', WCYS_PLUGIN_URL . 'includes/js/select2.full.min.js', array( 'jquery' ), true );
				wp_enqueue_script( 'wcys-script', WCYS_PLUGIN_URL . 'includes/js/admin-script.js', array( 'jquery' ), '1.0' );
				wp_localize_script( 'wcys-script','ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ),'ajax_action'=>'wcys_save_lat_long' ]);
			
				wp_enqueue_style( 'wcys-select2-css', WCYS_PLUGIN_URL . 'includes/css/select2.min.css');
			}
		}
		/**
		 * Add a new settings tab "Filters" to the WooCommerce settings tabs array.
		 *
		 * @param  array $settings_tabs Array of WooCommerce setting tabs.
		 * @return array $settings_tabs Array of WooCommerce setting tabs.
		 */
		public function wcys_add_settings_tab( $settings_tabs ) {
			$settings_tabs[ self::$settings_tab ] = __( 'YoVoy', 'wcys' );
			return $settings_tabs;
		}

		public function wcys_tab_settings() {
			woocommerce_admin_fields( $this->get_settings() );
		}

		 /**
		  * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
		  *
		  * @uses woocommerce_update_options()
		  * @uses self::get_settings()
		  */
		public function wcys_update_settings() {
			woocommerce_update_options( $this->get_settings() );
		}

		public function get_settings() {
		    $settings = array(
		        'section_title' => array(
		            'name'     => __( 'Set Pick-up Location & Api key', 'wcys' ),
		            'type'     => 'title',
		            'desc'     => '',
		            'id'       => self::$settings_tab .'_section_title'
		        ),
		        'section_manual_confirm' => array(
		            'name' => __( 'Manual Confirmation', 'wcys' ),
		            'type' => 'checkbox',
		            'id'   => self::$settings_tab .'_manual_confirm',
		            'is_checked' => get_option( self::$settings_tab .'_manual_confirm' ),
		        ),
		        'section_google_api' => array(
		            'name' => __( 'Google Map Api', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Enter Google Map APi to run map',
		            'id'   => self::$settings_tab .'_google_api'
		        ),
		        'section_api' => array(
		            'name' => __( 'Api Key', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Set api token here',
		            'id'   => self::$settings_tab .'_api'
		        ),
		        'section_name' => array(
		            'name' => __( 'Name', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Name of the dispatcher',
		            'id'   => self::$settings_tab .'_name'
		        ),
		        'section_google_address' => array(
		            'name' => __( 'Pick-up Address', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Pick-up Address to get lattitude and longitude',
		            'id'   => self::$settings_tab .'_google_address'
		        ),
		        'section_reference' => array(
		            'name' => __( 'Reference', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'References of the address to facilitate the agent locating the pick up point.',
		            'id'   => self::$settings_tab .'_reference'
		        ),
		        'section_email' => array(
		            'name' => __( 'Email', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Email of the dispatcher',
		            'id'   => self::$settings_tab .'_email'
		        ),
		        'section_phone' => array(
		            'name' => __( 'Phone', 'wcys' ),
		            'type' => 'text',
		            'desc_tip' => 'Phone number of the dispatcher',
		            'id'   => self::$settings_tab .'_phone'
		        ),
		        'section_vehicle' => array(
		            'name' => __( 'Vehicle Types ', 'wcys' ),
		            'type' => 'multiselect',
		            'default' =>'',
		            'options' =>['1'=>'Moto (Bike)','2'=>'Carro (Car)','3' => 'Mini-camión (Light truck)'],
		            'desc_tip' => 'Add vehcile to show on checkout',
		            'id'   => self::$settings_tab .'_vehicle'
		        ),
		        
		        'section_end' => array(
		             'type' => 'sectionend',
		             'id' => self::$settings_tab .'_section_end'
		        ),
		    );

			return apply_filters( 'wc_settings_tab_' . self::$settings_tab, $settings );
		}

	}

}
	new WCYS_Settings();
