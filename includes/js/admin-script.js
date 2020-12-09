google.maps.event.addDomListener(window, 'load', initAutocomplete);
var autocomplete;
function initAutocomplete(){
    autocomplete = new google.maps.places.Autocomplete(
    document.getElementById('wcys_google_address'), {types: ['geocode']});
    autocomplete.addListener('place_changed', getAddressDetails);
}
function getAddressDetails(){
    var place = autocomplete.getPlace();   
    //window.lat = place.geometry.location.lat();
    //window.long = place.geometry.location.lng();
    //console.log(geolocation)
    var data = {
      'action': 'wcys_save_lat_long',
      'lat' : place.geometry.location.lat(),
      'long' : place.geometry.location.lng(),
    };
    jQuery.post(
      ajax_object.ajax_url,
      data,
      function (response) {
        
      }
    );

}

jQuery( document ).on(
  "focus",
  "#wcys_google_address",
  function() {

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((position) => {
        const geolocation = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };
        const circle = new google.maps.Circle({
        center: geolocation,
        radius: position.coords.accuracy,
      });
        autocomplete.setBounds(circle.getBounds());
        });
    }

  }
)
