jQuery(document).on("change","#wcck_mobile_network",function (e){
	myval = jQuery( this ).val();
	jQuery (".wcck_data_plan_div").hide();

	if( myval ){
		jQuery( "#wcck_plan_"+myval+"_div" ).show();
	}
})