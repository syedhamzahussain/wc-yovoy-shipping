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
      'action': ajax_object.ajax_action,
      'wcys_lat' : place.geometry.location.lat(),
      'wcys_long' : place.geometry.location.lng(),
      'wcys_vehicle' : jQuery("#wcys_vehicle").val()
    };
    jQuery.post(
      ajax_object.ajax_url,
      data,
      function (response) {
        
      }
    );

}


jQuery( document ).ready( function(){
  jQuery("#wcys_vehicle").select2();

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

})
