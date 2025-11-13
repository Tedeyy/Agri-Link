document.addEventListener('DOMContentLoaded', function(){
  // Breeds population by type
  (function(){
    var dataEl = document.getElementById('breed-data');
    if (!dataEl) return;
    var breeds = [];
    try { breeds = JSON.parse(dataEl.getAttribute('data-breeds') || '[]'); } catch(e) { breeds = []; }
    var current = dataEl.getAttribute('data-current') || '';
    var byType = {};
    (breeds || []).forEach(function(b){
      var k = String(b.type_id);
      if (!byType[k]) byType[k] = [];
      byType[k].push(b);
    });
    function populateBreeds(){
      var typeSel = document.getElementById('livestock_type');
      var breedSel = document.getElementById('breed');
      if (!typeSel || !breedSel) return;
      var opt = typeSel.options[typeSel.selectedIndex];
      var tid = opt ? opt.getAttribute('data-typeid') : null;
      var list = tid && byType[tid] ? byType[tid] : [];
      breedSel.innerHTML = '<option value="">Select breed</option>';
      list.forEach(function(b){
        var o = document.createElement('option');
        o.value = b.name; o.textContent = b.name; if (current && current===b.name) o.selected = true;
        breedSel.appendChild(o);
      });
    }
    var typeSel = document.getElementById('livestock_type');
    if (typeSel) typeSel.addEventListener('change', populateBreeds);
    populateBreeds();
  })();

  // Image previews
  (function(){
    var fileInput = document.getElementById('photos');
    var preview = document.getElementById('photoPreview');
    if (fileInput && preview){
      fileInput.addEventListener('change', function(){
        while (preview.firstChild) preview.removeChild(preview.firstChild);
        var files = Array.prototype.slice.call(fileInput.files || []);
        files.forEach(function(f){
          if (!f.type || !f.type.startsWith('image/')) return;
          var reader = new FileReader();
          reader.onload = function(e){
            var img = document.createElement('img');
            img.src = e.target.result;
            img.alt = f.name;
            img.style.width = '110px';
            img.style.height = '110px';
            img.style.objectFit = 'cover';
            img.style.border = '1px solid #e2e8f0';
            img.style.borderRadius = '8px';
            img.style.background = '#f8fafc';
            preview.appendChild(img);
          };
          reader.readAsDataURL(f);
        });
      });
    }
  })();
});
