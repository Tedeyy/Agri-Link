(function(){
  function qs(s, ctx){ return (ctx||document).querySelector(s); }
  function qsa(s, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(s)); }
  function el(tag, cls){ var e=document.createElement(tag); if (cls) e.className=cls; return e; }
  function escapeHtml(s){ if (s==null) return ''; return String(s).replace(/[&<>"]+/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  var root = qs('#market-root');
  var preType = root ? (root.getAttribute('data-pretype')||'') : '';
  var feed = qs('#feed');
  var sentinel = qs('#sentinel');
  var topMapEl = qs('#top-map');
  if (!topMapEl){ return; }
  var map = L.map(topMapEl).setView([8.314209 , 124.859425], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
  var markerLayer = L.layerGroup().addTo(map);
  var state = { offset: 0, limit: 10, loading: false, done: false };

  function currentFilters(){
    return {
      livestock_type: qs('#f-type').value || '',
      breed: qs('#f-breed').value || '',
      min_age: qs('#f-min-age').value,
      max_age: qs('#f-max-age').value,
      min_price: qs('#f-min-price').value,
      max_price: qs('#f-max-price').value,
      min_weight: qs('#f-min-weight').value,
      max_weight: qs('#f-max-weight').value
    };
  }
  function buildQuery(params){ var q = new URLSearchParams(); Object.keys(params).forEach(function(k){ if (params[k]!=='' && params[k]!=null) q.append(k, params[k]); }); return q.toString(); }

  function loadPins(){
    var q = new URLSearchParams(currentFilters()); q.append('pins','1');
    fetch('marketplace.php?'+q.toString())
      .then(function(r){ return r.json(); })
      .then(function(data){
        markerLayer.clearLayers();
        (data.activePins||[]).forEach(function(p){
          var m = L.circleMarker([p.lat, p.lng], { radius:7, color:'#16a34a', fillColor:'#22c55e', fillOpacity:0.9 }).addTo(markerLayer);
          m.bindTooltip((p.type||'')+' • '+(p.breed||''));
        });
      });
  }

  function renderItems(items){
    var frag = document.createDocumentFragment();
    items.forEach(function(it){
      var card = el('div','item');
      var img = el('img','thumb'); img.src = it.thumb; img.alt='thumb'; img.onerror=function(){ if (img.src!==it.thumb_fallback) img.src = it.thumb_fallback; else img.style.display='none'; };
      var info = el('div','info');
      info.innerHTML = '<div><strong>'+escapeHtml(it.livestock_type)+' • '+escapeHtml(it.breed)+'</strong></div>'+
        '<div>'+escapeHtml(it.address)+'</div>'+
        '<div>Age: '+escapeHtml(it.age)+' • Weight: '+escapeHtml(it.weight)+'kg • Price: ₱'+escapeHtml(it.price)+'</div>'+
        '<div class="muted">Listing #'+it.listing_id+' • Seller #'+it.seller_id+' • '+escapeHtml(it.created||'')+'</div>'+
        (it.seller_name?('<div>Seller: '+escapeHtml(it.seller_name)+'</div>'):'');
      var actions = el('div','actions');
      var showBtn = el('button','btn'); showBtn.textContent='Show'; showBtn.addEventListener('click', function(){ openModal(it); });
      var loginBtn = el('a','btn btn-muted'); loginBtn.textContent='Login to Express Interest'; loginBtn.href='pages/authentication/login.php'; loginBtn.style.textAlign='center';
      actions.appendChild(showBtn); actions.appendChild(loginBtn);
      card.appendChild(img); card.appendChild(info); card.appendChild(actions);
      frag.appendChild(card);
    });
    feed.appendChild(frag);
  }

  function loadMore(){ if (state.loading || state.done) return; state.loading = true; var params = Object.assign({ ajax: 1, limit: state.limit, offset: state.offset }, currentFilters()); var q = buildQuery(params);
    fetch('marketplace.php?'+q).then(function(r){ return r.json(); }).then(function(data){ var items=(data&&data.items)?data.items:[]; if(items.length===0){ state.done=true; return; } renderItems(items); state.offset += items.length; }).finally(function(){ state.loading=false; }); }

  var io = new IntersectionObserver(function(entries){ entries.forEach(function(e){ if (e.isIntersecting) loadMore(); }); }); io.observe(sentinel);

  qs('#apply').addEventListener('click', function(){ feed.innerHTML=''; markerLayer.clearLayers(); state.offset=0; state.done=false; loadMore(); loadPins(); });
  qs('#clear').addEventListener('click', function(){ ['f-type','f-breed','f-min-age','f-max-age','f-min-price','f-max-price','f-min-weight','f-max-weight'].forEach(function(id){ var el=document.getElementById(id); if(el.tagName==='SELECT') el.value=''; else el.value=''; }); feed.innerHTML=''; markerLayer.clearLayers(); state.offset=0; state.done=false; loadMore(); loadPins(); });

  if (preType){ var tSel=document.getElementById('f-type'); Array.from(tSel.options).forEach(function(o){ if ((o.value||'').toLowerCase()===String(preType).toLowerCase()) o.selected=true; }); }

  function openModal(it){
    var m = qs('#viewModal'); var b = qs('#mBody');
    b.innerHTML = ''+
      '<div class="modal-body-row">'+
        '<img class="thumb-lg" src="'+it.thumb+'" onerror="this.onerror=null;this.src=\''+it.thumb_fallback+'\'" alt="thumb" />'+
        '<div class="modal-info">'+
          '<div><strong>'+escapeHtml(it.livestock_type)+' • '+escapeHtml(it.breed)+'</strong></div>'+
          '<div>'+escapeHtml(it.address)+'</div>'+
          '<div>Age: '+escapeHtml(it.age)+' • Weight: '+escapeHtml(it.weight)+'kg • Price: ₱'+escapeHtml(it.price)+'</div>'+
          '<div class="muted">Listing #'+it.listing_id+' • Seller #'+it.seller_id+' • '+escapeHtml(it.created||'')+'</div>'+
          (it.seller_name?('<div>Seller: '+escapeHtml(it.seller_name)+'</div>'):'')+
          '<div class="modal-actions"><a class="btn btn-muted" href="pages/authentication/login.php">Login to Express Interest</a></div>'+
        '</div>'+
      '</div>'+
      '<div id="mMap" class="map-embed"></div>';
    m.style.display='flex';
    setTimeout(function(){ var el=document.getElementById('mMap'); if (!el || !window.L) return; var mm = L.map(el).setView([8.314209 , 124.859425], 12); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mm); if (it.lat!=null && it.lng!=null){ mm.setView([it.lat,it.lng], 12); L.marker([it.lat,it.lng]).addTo(mm); } },0);
  }
  qs('#mClose').addEventListener('click', function(){ qs('#viewModal').style.display='none'; });
  qs('#viewModal').addEventListener('click', function(ev){ if (ev.target.id==='viewModal') qs('#viewModal').style.display='none'; });

  // initial load
  loadPins();
  loadMore();
})();
