(function(){
  function $(s){ return document.querySelector(s); }
  function $all(s){ return Array.prototype.slice.call(document.querySelectorAll(s)); }
  function openModal(id){ var el=document.getElementById(id); if(el) el.style.display='flex'; }
  function closeModal(id){ var el=document.getElementById(id); if(el) el.style.display='none'; }
  var currentTxMap = null;
  var currentTxMarker = null;
  function destroyTxMap(){ try{ if (currentTxMap){ currentTxMap.remove(); } }catch(e){} currentTxMap=null; currentTxMarker=null; }

  $all('.close-btn').forEach(function(b){ b.addEventListener('click', function(){ destroyTxMap(); var body=document.getElementById('txBody'); if(body) body.innerHTML=''; closeModal(b.getAttribute('data-close')); }); });

  document.addEventListener('click', function(e){
    if (e.target && e.target.classList.contains('btn-show')){
      var data = {};
      try{ data = JSON.parse(e.target.getAttribute('data-row')||'{}'); }catch(_){ data={}; }
      var isOngoing = !!(data && (data.bat_id || data.Bat_id || data.transaction_date || data.Transaction_date));
      document.getElementById('txTitle').textContent = (isOngoing? 'Ongoing' : (data.completed_transaction? 'Completed' : 'Started')) + ' Transaction #'+(data.transaction_id||'');
      var txBody = document.getElementById('txBody');
      var locVal = (data.transaction_location || data.Transaction_location || '').toString().trim();
      var whenVal = data.transaction_date || data.Transaction_date || '';

      // Fetch details for listing + seller/buyer
      var detailUrl = 'transaction_monitoring.php?action=details&listing_id='+(data.listing_id||'')+'&seller_id='+(data.seller_id||'')+'&buyer_id='+(data.buyer_id||'');
      fetch(detailUrl, { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(info){
          var listing = info && info.listing ? info.listing : {};
          var seller = info && info.seller ? info.seller : {};
          var buyer  = info && info.buyer  ? info.buyer  : {};
          var thumb  = info && info.thumb  ? info.thumb  : '';
          function fullname(p){ var f=p.user_fname||'', m=p.user_mname||'', l=p.user_lname||''; return (f+' '+(m?m+' ':'')+l).trim(); }
          var bodyHtml = ''+
            '<div class="card">'+
              '<div class="modal-body-row">'+
                '<img class="thumb" src="'+thumb+'" alt="thumb" />'+
                '<div class="modal-info">'+
                  '<div style="font-weight:600;margin-bottom:6px;">'+(listing.livestock_type||'')+' • '+(listing.breed||'')+'</div>'+
                  '<div>Price: ₱'+(listing.price||'')+'</div>'+
                  '<div>Address: '+(listing.address||'')+'</div>'+
                  '<div class="subtle">Listing #'+(listing.listing_id||'')+' • '+(listing.created||'')+'</div>'+
                '</div>'+
              '</div>'+
              '<hr style="margin:12px 0;border:none;border-top:1px solid #e2e8f0" />'+
              '<div class="grid-2">'+
                '<div><div style="font-weight:600;">Seller</div><div>'+fullname(seller)+'</div><div>Email: '+(seller.email||'')+'</div><div>Contact: '+(seller.contact||'')+'</div></div>'+
                '<div><div style="font-weight:600;">Buyer</div><div>'+fullname(buyer)+'</div><div>Email: '+(buyer.email||'')+'</div><div>Contact: '+(buyer.contact||'')+'</div></div>'+
              '</div>'+
            '</div>'+
            '<div class="card" style="margin-top:10px;">'+
              '<div class="meta-row">'+
                '<div><strong>Date & Time:</strong> <span>'+(whenVal||'—')+'</span></div>'+
                '<div><strong>Location:</strong> <span>'+(locVal||'—')+'</span></div>'+
              '</div>'+
              '<div id="txMap" class="map-embed"></div>'+
            '</div>';
          txBody.innerHTML = bodyHtml;
          // Open modal first so map can compute size
          openModal('txModal');
          // Initialize map after modal is visible
          setTimeout(function(){
            if (!window.L){ return; }
            var mEl = document.getElementById('txMap'); if (!mEl) return;
            destroyTxMap();
            currentTxMap = L.map(mEl).setView([8.314209 , 124.859425], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(currentTxMap);
            currentTxMap.on('tileload', function(){ try{ currentTxMap.invalidateSize(); }catch(e){} });
            if (locVal && locVal.indexOf(',')>0){
              var parts = locVal.split(',');
              var la = parseFloat((parts[0]||'').trim()); var ln = parseFloat((parts[1]||'').trim());
              if (!isNaN(la) && !isNaN(ln)){
                var ll=[la,ln]; currentTxMarker = L.marker(ll).addTo(currentTxMap); try{ currentTxMap.setView(ll,14);}catch(_){ }
              }
            }
            setTimeout(function(){ try{ currentTxMap.invalidateSize(); }catch(e){} }, 50);
          }, 50);
        });
    }
  });
})();
