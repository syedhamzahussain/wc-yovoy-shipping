var lati = 15.199999;
var longi = -86.241905;
jQuery(document).ready(function () {



    if (ajax_object.chosen_shipping_method == 'wcys_shipping') {
        initialize();
        jQuery('.woocommerce-shipping-fields').hide();

        if (jQuery('#wcys_google_address').val() == '0' || jQuery('#wcys_google_address').val() == 0) {
            jQuery('#wcys_google_address').click();
            jQuery('#wcys_google_address').val('Honduras');
        }

    }


    setTimeout(function ()
    {   
        if (ajax_object.chosen_shipping_method == 'wcys_shipping') {
            initialize();
        }
        jQuery('body').find('#map-canvas').attr('style',"visibility:visible!important");
    }, 4500);

    jQuery("#wcys_vehicle").select2();

    jQuery(document.body).on(
            "click",
            "button[name='update_cart'],input[name='update_cart']",
            function () {

                setTimeout(
                        function ()
                        {
                            initialize();
                            jQuery('body').find('#map-canvas').attr('style',"visibility:visible!important");
                        }, 4000);


            });


    jQuery(document.body).on(
            "click", ".shipping_method",
            function (event) {


                setTimeout(
                        function ( )
                        {
                            if (jQuery('.shipping_method:checked:checked').val() == 'wcys_shipping') {
                                initialize();
                                jQuery('.woocommerce-shipping-fields').hide();
                                location.reload();

                            }
                            else{
                                location.reload();
                            }

                        }, 1500);
            });
    jQuery(document.body).on(
            "change", "#wcys_vehicle",
            function (event) {

              lati = parseFloat(jQuery("#wcys_google_address").attr('data-lat'));
              longi = parseFloat(jQuery("#wcys_google_address").attr('data-long'));
              check = jQuery('#wcys_google_address').val()

              saveLatLong(lati, longi, check , jQuery(".wcys_shipping_type:checked").val());
              
            });

    jQuery(document.body).on(
            "change", "[name='wcys_delivery_type']",
            function (event) {
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

    jQuery(document.body).on(
            "change", "#wcys_addresses_list",
            function (event) {
            latlong =  jQuery(this).val().split('__');
            saveLatLong(latlong[0], latlong[1],0 , jQuery( this ).val() );
            
    });

    jQuery(document.body).on(
            "change", ".wcys_shipping_type",
            function (event) {
                checkSelection( jQuery( this ).val() );
            
    });


});


var map;
var marker;
var geocoder;
var infowindow = new google.maps.InfoWindow({
    size: new google.maps.Size(150, 50)
});

function initialize() {
    geocoder = new google.maps.Geocoder();
    lati = parseFloat(jQuery("#wcys_google_address").attr('data-lat'));
    longi = parseFloat(jQuery("#wcys_google_address").attr('data-long'));


    var mapOptions = {
        zoom: 8,
        center: {lat: lati, lng: longi}
    };
    map = new google.maps.Map(document.getElementById('map-canvas'),
            mapOptions);

    google.maps.event.addListener(map, 'click', function () {
        infowindow.close();
    });
    // Get GEOLOCATION
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            // var pos = new google.maps.LatLng(position.coords.latitude,
            var pos = new google.maps.LatLng(lati, longi);

            map.setCenter(pos);
            marker = new google.maps.Marker({
                position: pos,
                map: map,
                draggable: true
            });
            google.maps.event.addListener(marker, 'dragend', function (evt) {
                geocodePosition(marker.getPosition(), evt.latLng.lat(), evt.latLng.lng());

            })
        });
    }
    // get places auto-complete when user type in location-text-box
    var input = (document.getElementById('wcys_google_address'));


    var autocomplete = new google.maps.places.Autocomplete(input);

    var infowindow = new google.maps.InfoWindow();

    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        infowindow.close();
        //marker.setVisible(true);
        var place = autocomplete.getPlace();
        if (!place.geometry) {
            return;
        }

        // If the place has a geometry, then present it on a map.
        if (place.geometry.viewport) {
            map.fitBounds(place.geometry.viewport);
            map.setZoom(8); // Why 17? Because it looks good.
        } else {
            map.setCenter(place.geometry.location);
            map.setZoom(8); // Why 17? Because it looks good.
        }

        marker.setPosition(place.geometry.location);
        //marker.setVisible(true);

        var address = '';
        if (place.address_components) {
            address = [
                (place.address_components[0] && place.address_components[0].short_name || ''), (place.address_components[1] && place.address_components[1].short_name || ''), (place.address_components[2] && place.address_components[2].short_name || '')
            ].join(' ');
        }

        saveLatLong(place.geometry.location.lat(), place.geometry.location.lng(), jQuery('#wcys_google_address').val(), jQuery(".wcys_shipping_type:checked").val());

    });

}

//google.maps.event.addDomListener(window, 'load', initialize);


function geocodePosition(pos, lat, lng) {
    check = false;
    geocoder.geocode({
        latLng: pos
    }, function (responses) {
        if (responses && responses.length > 0) {
            check = responses[0].formatted_address
            marker.formatted_address = responses[0].formatted_address;
        } else {
            marker.formatted_address = 'Cannot determine address at this location.';
        }
        //console.log( marker.formatted_address)
        infowindow.setContent(marker.formatted_address + "<br>coordinates: " + marker.getPosition().toUrlValue(6));
        infowindow.open(map, marker);

        if (check) {
            jQuery("#wcys_google_address").val(check);
        }
        saveLatLong(lat, lng, check,jQuery(".wcys_shipping_type:checked").val());

    });
}

function saveLatLong(lat, lng, check , addresstype ="map" ) {
    jQuery("#wcys_google_address").attr('data-lat', lat);
    jQuery("#wcys_google_address").attr('data-long', lng);

    var data = {
        'action': 'wcys_fare_lat_long',
        'wcys_lat': lat,
        'wcys_long': lng,
        'wcys_vehicle': jQuery("#wcys_vehicle").val(),
        'wcys_google_address': check ? check : 0,
        "wcys_address_type" : addresstype
    };
    jQuery.post(
            ajax_object.ajax_url,
            data,
            function (response) {
                if (response.cost != 0) {

                    if(jQuery('.shipping_method[value="wcys_shipping"]').next().children().hasClass("woocommerce-Price-amount") == false){
                        jQuery('.shipping_method[value="wcys_shipping"]').next().append(' :'+response.cost_formated);
                    }
                    else{
                        jQuery('.shipping_method[value="wcys_shipping"]').next().find(".woocommerce-Price-amount").html(response.cost);
                    }
                    
                    jQuery('.order-total > td').html(response.total_cost);

                    if( addresstype == "map"){
                        setTimeout(function ()
                        {   
                            if (ajax_object.chosen_shipping_method == 'wcys_shipping') {
                                initialize();
                            }
                            jQuery('body').find('#map-canvas').attr('style',"visibility:visible!important");
                        }, 1500);
                    }
                     
                }
            }
    );
}


function checkSelection(type){
    if( type == 'list'){
        jQuery('.wcys_addresses_list').show();
        jQuery('#wcys_google_address').hide();
        jQuery('label[for=wcys_google_address]').hide()
        jQuery('#map-canvas').hide();
    }
    else{
        jQuery('#wcys_google_address').show();
        jQuery('label[for=wcys_google_address]').show()
        jQuery('#map-canvas').show();
        jQuery('.wcys_addresses_list').hide();

    }
}