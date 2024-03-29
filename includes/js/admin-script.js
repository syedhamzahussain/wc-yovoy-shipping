jQuery(document).ready(function () {

    setTimeout(function ()
    {
    jQuery('body').find('#map-canvas').attr('style',"visibility:visible!important");
    }, 1500);
    
    jQuery("#wcys_vehicle").select2();


    var map;
    var marker;
    var geocoder;
    var infowindow = new google.maps.InfoWindow({
        size: new google.maps.Size(150, 50)
    });

    function initialize() {
        geocoder = new google.maps.Geocoder();
        var lati = parseFloat(jQuery("#wcys_google_address").attr('data-lat'));
        var longi = parseFloat(jQuery("#wcys_google_address").attr('data-long'));

        var mapOptions = {
            zoom: 8,
            center: {lat: lati, lng: longi}
        };
        jQuery("#wcys_google_address").parent().parent().next().html('<th></th><td style="width:50px;height:300px"><div id="map-canvas"></div></td>');
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
            marker.setVisible(false);
            var place = autocomplete.getPlace();
            if (!place.geometry) {
                return;
            }

            // If the place has a geometry, then present it on a map.
            if (place.geometry.viewport) {
                map.fitBounds(place.geometry.viewport);
            } else {
                map.setCenter(place.geometry.location);
                map.setZoom(8); // Why 17? Because it looks good.
            }

            marker.setPosition(place.geometry.location);
            marker.setVisible(true);

            var address = '';
            if (place.address_components) {
                address = [
                    (place.address_components[0] && place.address_components[0].short_name || ''), (place.address_components[1] && place.address_components[1].short_name || ''), (place.address_components[2] && place.address_components[2].short_name || '')
                ].join(' ');
            }

            saveLatLong(place.geometry.location.lat(), place.geometry.location.lng());

        });

    }

    google.maps.event.addDomListener(window, 'load', initialize);

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
            saveLatLong(lat, lng, check);

        });
    }

    function saveLatLong(lat, lng, check = false) {
        var data = {
            'action': 'wcys_save_lat_long',
            'wcys_lat': lat,
            'wcys_long': lng,
            'wcys_google_address': check ? check : 0
        };
        jQuery.post(
                ajax_object.ajax_url,
                data,
                function (response) {

                }
        );
    }

});