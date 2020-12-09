<?php
/**
 * This class handles all the settings functionality in admin area
 *
 * @package Settings
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCYS_Shipping' ) ) {

	/**
	 * Woo Bookings Settings Class
	 *
	 * @package Settings
	 */
	class WCYS_Shipping extends WC_Shipping_Method {
	    /**
	     * Constructor for your shipping class
	     *
	     * @access public
	     * @return void
	     */
	    public function __construct() {
	        $this->id                 = 'wcys_shipping'; // Id for your shipping method. Should be uunique.
	        $this->method_title       = __( 'YoVoy Shipping' );  // Title shown in admin
	        $this->method_description = __( 'YoVoy Shipping Description' ); // Description shown in admin

	        $this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
	        $this->title              = "YoVoy Shipping"; // This can be added as an setting but for this example its forced.

	        $this->init();
	    }

	    function init() {
	        // Load the settings API
	        $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
	        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

	        // Save settings in admin if you have any defined
	        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	    }
	    function init_form_fields() {

                $this->form_fields = array(

                    'enabled' => array(
                        'title'       => __( 'Enable', 'dc_raq' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Enable this shipping method.', 'dc_raq' ),
                        'default'     => 'yes'
                    ),

                    'title' => array(
                        'title'       => __( 'Title', 'dc_raq' ),
                        'type'        => 'text',
                        'description' => __( 'Title to be displayed on site', 'dc_raq' ),
                        'default'     => __( 'Request a Quote', 'dc_raq' )
                    ),

                );

            }

	    /**
	     * calculate_shipping function.
	     *
	     * @access public
	     * @param mixed $package
	     * @return void
	     */
	    public function calculate_shipping( $package = array() ) {
	        $rate = array(
	            'id' => $this->id,
	            'label' => $this->title,
	            'cost' => '10.99',
	            'calc_tax' => 'per_item'
	        );

	        // Register the rate
	        $this->add_rate( $rate , $package );
	    }
	}
}
//new WCYS_Shipping();