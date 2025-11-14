document.addEventListener('DOMContentLoaded', function(){
  var clicks = 0;
  var span = document.querySelector('#trigger-text .brand');
  if (!span) return;
  span.addEventListener('click', function(){
    clicks++;
    if (clicks >= 3) {
      window.location.href = 'management/managementregistration.php';
    }
    setTimeout(function(){ clicks = 0; }, 1000);
  });
});
