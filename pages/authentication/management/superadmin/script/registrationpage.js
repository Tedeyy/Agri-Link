document.addEventListener('DOMContentLoaded', function(){
  // ID preview
  (function(){
    var input = document.getElementById('idphoto');
    var prev = document.getElementById('idPreview');
    if (!input || !prev) return;
    input.addEventListener('change', function(){
      while (prev.firstChild) prev.removeChild(prev.firstChild);
      var f = input.files && input.files[0];
      if (!f) return;
      if (f.type && f.type.startsWith('image/')){
        var reader = new FileReader();
        reader.onload = function(e){
          var img = document.createElement('img');
          img.src = e.target.result;
          img.alt = f.name;
          img.style.width = '160px';
          img.style.height = '120px';
          img.style.objectFit = 'cover';
          img.style.border = '1px solid #e2e8f0';
          img.style.borderRadius = '8px';
          img.style.background = '#f8fafc';
          prev.appendChild(img);
        };
        reader.readAsDataURL(f);
      } else {
        var note = document.createElement('div');
        note.textContent = 'Selected: '+f.name;
        note.style.color = '#4a5568';
        prev.appendChild(note);
      }
    });
  })();

  // Password validation and show toggle
  (function(){
    var form = document.querySelector('form');
    var pw = document.getElementById('password');
    var cpw = document.getElementById('confirm_password');
    var show = document.getElementById('showPw');
    function strong(s){ return /^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(s||''); }
    if (show){
      show.addEventListener('change', function(){
        var t = show.checked ? 'text' : 'password';
        if (pw) pw.type = t; if (cpw) cpw.type = t;
      });
    }
    if (form){
      form.addEventListener('submit', function(e){
        if (!pw || !cpw) return;
        if (!strong(pw.value)){
          e.preventDefault();
          alert('Password must be at least 8 characters and include letters, numbers, and symbols.');
          return;
        }
        if (pw.value !== cpw.value){
          e.preventDefault();
          alert('Passwords do not match.');
        }
      });
    }
  })();
});
