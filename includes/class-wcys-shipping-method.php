<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function WCYS_Shipping_Method() {
	if ( ! class_exists( 'WCYS_Shipping' ) ) {

		class WCYS_Shipping extends WC_Shipping_Method {

			public function __construct() {
				$this->id                 = 'wcys_shipping';
				$this->method_title       = __( 'YoVoy Shipping', 'wcys' );  // Title shown in admin
				$this->method_description = __( 'Please located your location on the map.', 'wcys' ); // Description shown in admin
				// Availability & Countries
				$this->availability = 'including';
				$this->countries    = array(
					'US', // United States of America
					'CA', // Canada
					'DE', // Germany
					'GB', // United Kingdom
					'IT', // Italy
					'ES', // Spain
					'HR', // Croatia
					'HN',
				);

				$this->init();

				$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
				$this->title   = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'YoVoy Shipping', 'wcys' );
			}

			function init() {
				// Load the settings API
				$this->init_form_fields();
				$this->init_settings();

				// Save settings in admin if you have any defined
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			function init_form_fields() {

				$this->form_fields = array(
					'enabled'     => array(
						'title'       => __( 'Enable', 'wcys' ),
						'type'        => 'checkbox',
						'description' => __( 'Enable this shipping.', 'wcys' ),
						'default'     => 'yes',
					),
					'title'       => array(
						'title'       => __( 'Title', 'wcys' ),
						'type'        => 'text',
						'description' => __( 'Title to be display on site', 'wcys' ),
						'default'     => __( 'YoVoy Shipping', 'wcys' ),
					),
					'description' => array(
						'title'       => __( 'Description', 'wcys' ),
						'type'        => 'text',
						'description' => __( 'Description to be display on site', 'wcys' ),
						'default'     => __( 'Please located your location on the map.', 'wcys' ),
					),
				);
			}

			public function calculate_shipping( $package = array() ) {
				$rate = array(
					'id'    => $this->id,
					'label' => $this->title,
					'cost'  => 0,
				);

				$this->add_rate( $rate, $package );
			}

		}

	}
}

add_action( 'woocommerce_shipping_init', 'WCYS_Shipping_Method' );

function wcys_add_my_shipping_method( $methods ) {
	$methods['wcys_shipping'] = 'wcys_shipping';

	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'wcys_add_my_shipping_method' );


