<?php
/**
 * This class handles all the settings functionality in admin area
 *
 * @package Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCCK_Front' ) ) {

	/**
	 * Woo Bookings Settings Class
	 *
	 * @package Settings
	 */
	class WCCK_Front {

		/**
		 * Constructor
		 */
		public function __construct() {

			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'wcck_custom_script' ) );
				add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'wcck_validate_custom_field' ), 10, 3 );
				add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'wcck_display_custom_fields' ) );
				add_filter( 'woocommerce_add_cart_item_data', array( $this, 'wcck_add_club_konnect_network' ), 10, 4 );

				// Lets change the product price in cart
				add_action( 'woocommerce_before_calculate_totals', array( $this, 'wcck_change_price_of_product' ), 20, 1 );
				// Display custom fields in cart
				add_action( 'woocommerce_cart_item_name', array( $this, 'wcck_customizing_cart_item_name' ), 10, 3 );
				// Hide quantity.
				add_action( 'woocommerce_is_sold_individually', array( $this, 'wcck_remove_all_quantity_fields' ), 10, 2 );
				//Remove Shipping from pakcage products.
				add_action( 'woocommerce_cart_ready_to_calc_shipping', array( $this, 'wcck_disable_shipping_calc_on_cart'), 99 );
				// Save / Display custom field value as custom order item meta data
				add_action('woocommerce_checkout_create_order_line_item', array( $this, 'wcck_add_club_konnect_data_to_order' ), 10, 4);
				// Save custom fields values as custom order meta data
				add_action( 'woocommerce_checkout_create_order',array( $this, 'wcck_checkout_field_update_order_meta'), 20, 2 );

			}
		}

		public function wcck_disable_shipping_calc_on_cart( $show_shipping ) {
			$type = false;
			foreach ( WC()->cart->get_cart() as $item_key => $item )  {
				if( isset( $item[ 'wcck_mobile_network' ] ) ) {
					$type = true;
					break;
				}
			}

			if( $type ) {
		        return false;
		    }
		    return $show_shipping;
		}
		
		/**
		 * Plugins Scripts.
		 */
		public function wcck_custom_script() {

			wp_enqueue_script( 'wcck-script', WCCK_PLUGIN_URL . 'includes/js/script.js', array( 'jquery'), '1.0' );

		}

		public function wcck_remove_all_quantity_fields($return, $product) {
			$type = $product->get_meta( '_wcck_select_type' );
			if( 'databundle' == $type || 'airtime' == $type) {
				return true;
			}
			return false;
		}

		public function wcck_checkout_field_update_order_meta( $order, $data ) {
			//echo "<pre>";print_r( $data );die();
		    $wcck_club_konnect_data = array();

		    // Loop through order items
		    foreach( $order->get_items() as $key => $item ){
		        // Set each order item '1e box abonnement' in an array
		        if( $item->get_meta( 'WCCK Mobile Network' ) && $item->get_meta( 'WCCK Mobile Number' ) ) {
		        	$product_id = $item->get_product_id();
		        	$wcck_club_konnect_data[ $product_id ]['wcck_mobile_network'] = $item->get_meta( 'WCCK Mobile Network' );
		        	$wcck_club_konnect_data[ $product_id ]['wcck_mobile_number'] = $item->get_meta( 'WCCK Mobile Number' );
		        	$wcck_club_konnect_data[ $product_id ]['wcck_api_type'] = $item->get_meta( 'WCCK Api Type' );

		        	if( $item->get_meta( 'WCCK Data Plan' ) ){
		        		$wcck_club_konnect_data[ $product_id ]['wcck_data_plan'] = $item->get_meta( 'WCCK Data Plan' );
					}

					$wcck_club_konnect_data = $this->runAPi( $wcck_club_konnect_data,$product_id,$item->get_subtotal() );

		        }
		    }

		    // Save the data as a coma separated string in order meta data
		    if( sizeof($wcck_club_konnect_data) > 0 ){
		        foreach($wcck_club_konnect_data as $key => $val ){
		    		$product = wc_get_product( $key );
		    		$order_response = json_decode( $val['wcck_order_response'] );
		    		if(isset( $order_response->orderid )  &&  "100" == $order_response->statuscode ){
		        		$order->update_meta_data( 'Kalex Product orderid - '.$product->get_title() , $order_response->orderid );

		    		}
		        	$order->update_meta_data( 'Kalex Product Status - '.$product->get_title() , $order_response->status );
		        }
		        $order->update_meta_data( 'wcck_club_konnect', $wcck_club_konnect_data );
		    }
		}

		/**
		 * Run club konnect api.
		 * @since 1.0.0
		 * @param Array $wcck_club_konnect_data club connect data.
		 * @param Integer $product_id Product ID.
		 * @param Integer $amount item amount.
		 */
		public function runApi( $wcck_club_konnect_data,$product_id,$amount ) {
			$api_type = 'APIAirtimeV1.asp';
			$amount = '&Amount='. floatval($amount);
			$mobile_network = $wcck_club_konnect_data[ $product_id ][ 'wcck_mobile_network' ];

			if ( 'databundle' == $wcck_club_konnect_data[ $product_id ][ 'wcck_api_type' ] ){
				$api_type = 'APIDatabundleV1.asp';
				$plan = $wcck_club_konnect_data[ $product_id ]['wcck_data_plan'];
				$plan_key = trim( substr($plan, 0, strpos($plan, "-") ) );

				if (strpos($plan_key, 'GB') !== false) {
				   $plan_key = str_replace( 'GB', '', $plan_key );
				   $plan = floatval( $plan_key ) * 1000; 
				}

				if (strpos($plan_key, 'MB') !== false) {
				   $plan_key = str_replace( 'MB', '', $plan_key );
				   $plan = floatval( $plan_key ); 
				}

				$amount = '&DataPlan='.$plan;
			}

			$apiUrl = 'https://www.nellobytesystems.com/'.$api_type.'?UserID='.get_option( 'wcck_userid' ).'&APIKey='.get_option( 'wcck_api' ).'&MobileNetwork='.trim( substr($mobile_network, 0, strpos($mobile_network, "-") ) ) .'&MobileNumber='.$wcck_club_konnect_data[ $product_id ][ 'wcck_mobile_number' ].$amount;

			$response = wp_remote_get($apiUrl);
			$responseBody = wp_remote_retrieve_body( $response );
			$result = json_decode( $responseBody );
			$wcck_club_konnect_data[ $product_id ]['wcck_order_response'] = $responseBody;

			return $wcck_club_konnect_data;
		}

		/**
		 * Add custom field to order object
		 */
		public function wcck_add_club_konnect_data_to_order( $item, $cart_item_key, $values, $order ) {

	 		if( isset( $values['wcck_mobile_network'] ) ) {
		 		$item->add_meta_data( 'WCCK Mobile Network',  $this->wcck_get_mobile_networks( $values['wcck_mobile_network'] ) , true );
		 		$item->add_meta_data( 'WCCK Mobile Number', $values['wcck_mobile_number'], true );
		 		$item->add_meta_data( 'WCCK Api Type', $values['wcck_api_type'], true );

		 		if( isset( $values['wcck_data_plan'] ) ){
		 			$item->add_meta_data( 'WCCK Data Plan', $this->wcck_get_data_plans( $values['wcck_mobile_network'], $values['wcck_data_plan'] ), true );

		 		} 
		 	}
		 	
		}

		public function wcck_customizing_cart_item_name( $product_name, $cart_item, $cart_item_key ){
						
			if ( isset( $cart_item['wcck_api_type'] ) ) {
		        $product_name .= '<br><small>Type : '. ucfirst( $cart_item['wcck_api_type'] ) .'</small>';
		        $product_name .= '<br><small>Mobile Network : '. $this->wcck_get_mobile_networks( $cart_item['wcck_mobile_network'] ) .'</small>';

		        if( 'databundle' == $cart_item['wcck_api_type'] ) {
		        	$product_name .= '<br><small>Data Plan : '. $this->wcck_get_data_plans( $cart_item['wcck_mobile_network'], $cart_item['wcck_data_plan'] ).'</small>';
		   		}
		        $product_name .= '<br><small>Mobile Number : '. $cart_item['wcck_mobile_number'] .'</small>';
		    }
		    return $product_name;
		}

		public function wcck_change_price_of_product( $cart ){

			
			foreach ( $cart->get_cart() as $item ) {
				$price = 0;
				if( isset( $item['wcck_api_type'] ) ) {

					if ( 'databundle' == $item['wcck_api_type'] ) {
						$price =  $item[ 'wcck_data_plan' ];
				 	}
				 	else if( "airtime" == $item['wcck_api_type']) {
				 		$price = $item[ 'wcck_product_amount' ];
				 	}

				}

				if( $price ) {
			        $item['data']->set_price( $price );
				}
		    }
			
		}

		/**
		 * Add the text field as item data to the cart object
		 * @since 1.0.0
		 * @param Array $cart_item_data Cart item meta data.
		 * @param Integer $product_id Product ID.
		 * @param Integer $variation_id Variation ID.
		 * @param Boolean $quantity Quantity
		 */
		public function wcck_add_club_konnect_network( $cart_item_data, $product_id, $variation_id, $quantity ) {
		 	if( ! empty( $_POST['wcck_mobile_network'] ) ) {
				// Add the item data
			 	$cart_item_data['wcck_mobile_network'] = $_POST['wcck_mobile_network'];
			 	$cart_item_data['wcck_mobile_number'] = $_POST['wcck_mobile_number'];
			 	$cart_item_data['wcck_api_type'] = $_POST['wcck_clubkonnect_type'];

			 	if ( 'databundle' == $_POST['wcck_clubkonnect_type'] ) {
			 		$cart_item_data['wcck_data_plan'] = $_POST['wcck_plan_'.$_POST['wcck_mobile_network'] ];

			 	}

			 	if( "airtime" == $_POST['wcck_clubkonnect_type']) {
				 	$cart_item_data['wcck_product_amount'] = $_POST['wcck_product_amount'];
			 	}
		
		 	}

		 	return $cart_item_data;
		}

		/**
		 * Validate the text field
		 * @since 1.0.0
		 * @param Array $passed Validation status.
		 * @param Integer $product_id Product ID.
		 * @param Boolean $quantity Quantity
		 */
		public function wcck_validate_custom_field( $passed, $product_id, $quantity ) {
			$product = wc_get_product( $product_id );
			$type = $product->get_meta( '_wcck_select_type' );
			// Fails validation
	 		if( isset( $_POST['wcck_mobile_network'] ) &&  ( empty( $_POST['wcck_mobile_network'] ) || empty( $_POST['wcck_mobile_number'] ) || empty( $_POST['wcck_product_amount'] ) || 100 > $_POST['wcck_product_amount']  ) && $type == 'airtime')
	 		{
	 			wc_add_notice( __( 'Please select the mobile network, enter amount and enter a mobile number', 'wc-club-konnect' ), 'error' );
			 	$passed = false;
		 	}
		 	else if ( isset( $_POST['wcck_mobile_network'] ) &&  ( empty( $_POST['wcck_mobile_network'] ) || empty( $_POST['wcck_mobile_number'] ) )  && $type == 'databundle' && empty( $_POST['wcck_plan_'.$_POST['wcck_mobile_network'] ] ) )
		 	{
		 		wc_add_notice( __( 'Please select the mobile network, data plan and enter a mobile number', 'wc-club-konnect' ), 'error' );
			 	$passed = false;
		 	}
		 	else if( ! isset( $_POST['wcck_mobile_network'] ) &&  ! empty( $type)  && ( $type == 'airtime' || $type == 'databundle') )
		 	{
		 		wc_add_notice( __( 'Please select the mobile network, enter amount and enter a mobile number', 'wc-club-konnect' ), 'error' );
			 	$passed = false;
		 	}
		 	
		 	return $passed;
		}

		/**
		 * Display custom field on the front end
		 * @since 1.0.0
		 */
		public function wcck_display_custom_fields() {
		 	global $post;
			// Check for the custom field value
			$product = wc_get_product( $post->ID );
			$type = $product->get_meta( '_wcck_select_type' );

			if( !empty( $type ) && ( $type == 'airtime' || $type == 'databundle') ) {
				// Only display our field if we've got a value for the field title
			 	echo '<div class="wcck_div"> <input type="hidden" name="wcck_clubkonnect_type" id="wcck_clubkonnect_type" value="'.$type.'">';
			 	echo '<select name="wcck_mobile_network" id="wcck_mobile_network">
			 	<option value="">Select Mobile Network </option>';
			 	
			 	$wcck_mobile_network = $this->wcck_get_mobile_networks();
			 	foreach( $wcck_mobile_network as $net_key => $net_val)
			 	{
			 		echo '<option value="'.$net_key.'">'.$net_val.'</option>';
			 	}
			 	
			 	echo '</select> <br><br>';

			 	if( $type == 'databundle') {
			 		echo '<div style="display:none" class="wcck_data_plan_div" id="wcck_plan_01_div"> <select name="wcck_plan_01" class="wcck_data_plan" id="wcck_plan_01">
				 	<option value="">Select MTN Plan </option>';

				 	$wcck_data_plans = $this->wcck_get_data_plans();
				 	foreach( $wcck_data_plans["01"] as $data_key => $data_val)
				 	{
				 		echo '<option value="'.$data_key.'">'.$data_val.'</option>';
				 	}
				 	echo '</select> <br><br></div>';

				 	echo '<div style="display:none" class="wcck_data_plan_div" id="wcck_plan_02_div"> <select name="wcck_plan_02" class="wcck_data_plan" id="wcck_plan_02">
				 	<option value="">Select GLO Network </option>';
				 	foreach( $wcck_data_plans["02"] as $data_key => $data_val)
				 	{
				 		echo '<option value="'.$data_key.'">'.$data_val.'</option>';
				 	}
				 	
				 	echo '</select> <br><br></div>';

				 	echo '<div style="display:none" class="wcck_data_plan_div" id="wcck_plan_03_div"> <select name="wcck_plan_03" class="wcck_data_plan" id="wcck_plan_03">
				 	<option value="">Select Etisalat Plan </option>';
				 	foreach( $wcck_data_plans["03"] as $data_key => $data_val)
				 	{
				 		echo '<option value="'.$data_key.'">'.$data_val.'</option>';
				 	}
				 	
				 	echo '</select> <br><br></div>';

				 	echo '<div style="display:none" class="wcck_data_plan_div" id="wcck_plan_04_div"> <select name="wcck_plan_04" class="wcck_data_plan" id="wcck_plan_04">
				 	<option value="">Select Airtel Plan</option>';
				 	foreach( $wcck_data_plans["04"] as $data_key => $data_val)
				 	{
				 		echo '<option value="'.$data_key.'">'.$data_val.'</option>';
				 	}
				 	
				 	echo '</select> <br><br></div>';
			 	}

			 	echo '<input type="text" id="wcck_mobile_number" name="wcck_mobile_number" value="" placeholder="Enter Mobile Number">';
			 	if( $type == 'airtime') {
				 	echo '<br><br><input type="number" min="100" max="50000" id="wcck_product_amount" name="wcck_product_amount" placeholder="Enter Amount min:100" style="width: 47%;">';
			 	}
				echo '<br><br></div>';
			
			}
		}

		private function wcck_get_mobile_networks( $key='' ) {
			$array = array( "01" => "01 - MTN", "02" => "02 - Glo", "03" => "03 - Etisalat", "04"=> "04 - Airtel");
			
			if( $key && isset($array[$key]) ) {
				return $array[$key];
			}
			return $array;
		}

		private function wcck_get_data_plans( $key = '', $innerKey = '' ) {
			$array = array( 
				"01" => array(
					"330.00" => "1GB - N330.00",
					"96.00" => "1GB - N96.00 (direct)",
					"660.00" => "2GB - N660.00",
					"192.00" => "2.5GB - N192.00 (direct)",
					"650.00" => "5GB - N1,650.00",
					"288.00" => "10GB - N288.00 (direct)",
					"288.00" => "22GB - N288.00 (direct)",
					),
				"02" => array (
					"N930.00" => "2GB - N930.00",
				 	"1,860.00" => "4.5GB - N1,860.00",
				 	"2,325.00" => "7.2GB - N2,325.00",
				 	"2,790.00" => "8.75GB - N2,790.00",
				 	"3,720.00" => "12.5GB - N3,720.00",
				 	"4,650.00" => "15.6GB - N4,650.00",
				 	"7,440.00" => "25GB - N7,440.00",
				 	"13,950.00" => "52.5GB - N13,950.00",
				 	"16,740.00" => "62.5GB - N16,740.00",
					),
				"03" => array(
					"477.50" => "500MB - N477.50",
				 	"955.00" => "1GB - N955.00",
				 	"1,146.00" => "1.5GB - N1,146.00",
				 	"1,432.50" => "2.5GB - N1,432.50",
				 	"1,910.00" => "4GB - N1,910.00",
				 	"3,820.00" => "5.5GB - N3,820.00",
				 	"4,775.00" => "11.5GB - N4,775.00",
				 	"9,550.00" => "15GB - N9,550.00",
				 	"14,325.00" => "27GB - N14,325.00",
					),
				"04"=> array(
					"47.75" => "1.5GB - N47.75",
				 	"95.50" => "3.5GB - N95.50",
				 	"286.50" => "7GB - N286.50",
				 	"4,750.00" => "10GB - N4,750.00",
				 	"7,600.00" => "16GB - N7,600.00",
				 	"9,500.00" => "22GB - N9,500.00",
					)
				);
			
			if( $key && isset($array[$key][$innerKey]) ) {
				return $array[$key][$innerKey];
			}
			return $array;
		}
	}

	new WCCK_Front();
}