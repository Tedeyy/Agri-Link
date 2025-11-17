(function(){
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }
  function openModal(id){ var el = document.getElementById(id); if(el){ el.style.display='flex'; } }
  function closeModal(id){ var el = document.getElementById(id); if(el){ el.style.display='none'; } }
  $all('.close-btn').forEach(function(btn){ btn.addEventListener('click', function(){ var id = btn.getAttribute('data-close'); closeModal(id); }); });

  function rootForStatus(status){
    var s = (status||'').toLowerCase();
    if (s==='pending' || s==='under review' || s==='review') return 'listings/underreview';
    if (s==='active' || s==='verified') return 'listings/verified';
    if (s==='denied') return 'listings/denied';
    if (s==='sold') return 'listings/sold';
    return 'listings/underreview';
  }

  function getSellerId(){
    var el = document.getElementById('seller-data');
    var id = el ? parseInt(el.getAttribute('data-seller')||'0',10) : 0;
    return isNaN(id)?0:id;
  }

  function renderImages(listingId, status, created){
    var imgs = $('#listingImages'); var note = $('#imgNotice');
    imgs.innerHTML=''; note.textContent='';
    var root = rootForStatus(status);
    var legacyFolder = getSellerId() + '_' + listingId;
    var fails=0; var total=3;
    for (var i=1;i<=3;i++){
      var src = '../../bat/pages/storage_image.php?path=' + root + '/' + legacyFolder + '/image' + i;
      var im = new Image(); im.width=150; im.height=150; im.alt='image'+i; im.src = src;
      im.onerror = function(){ fails++; if (fails===total){ note.textContent='No images found in '+root+'/'+legacyFolder; } };
      imgs.appendChild(im);
    }
  }

  function fetchInterests(listingId){
    return fetch('managelistings.php?action=interests&listing_id='+encodeURIComponent(listingId), { credentials:'same-origin' })
      .then(function(r){ return r.json(); });
  }
  function fetchBuyer(buyerId){
    return fetch('managelistings.php?action=buyer_profile&buyer_id='+encodeURIComponent(buyerId), { credentials:'same-origin' })
      .then(function(r){ return r.json(); });
  }
  function startTransaction(listingId, buyerId){
    var fd = new FormData(); fd.append('action','start_transaction'); fd.append('listing_id', listingId); fd.append('buyer_id', buyerId);
    return fetch('managelistings.php', { method:'POST', body: fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
  }

  function fullname(b){
    var f=(b.user_fname||''), m=(b.user_mname||''), l=(b.user_lname||'');
    return (f+' '+(m?m+' ':'')+l).trim();
  }

  function openListingModal(data){
    var basics = $('#listingBasics'); var tbody = $('#interestTable tbody');
    basics.innerHTML = '<div><strong>'+data.type+' • '+data.breed+'</strong> <span class="pill">'+data.status+'</span></div>'+
      '<div>Age: '+data.age+' • Weight: '+data.weight+'kg • Price: ₱'+data.price+'</div>'+
      '<div class="subtle">Listing #'+data.id+' • '+data.created+'</div>'+
      '<div>Address: '+data.address+'</div>';
    renderImages(data.id, data.status, data.created);
    tbody.innerHTML = '<tr><td colspan="4" class="subtle">Loading...</td></tr>';
    fetchInterests(data.id).then(function(res){
      if (!res.ok){ tbody.innerHTML = '<tr><td colspan="4" class="subtle">Failed to load interests</td></tr>'; return; }
      var rows = res.data || [];
      if (!rows.length){ tbody.innerHTML = '<tr><td colspan="4" class="subtle">No interested buyers yet.</td></tr>'; return; }
      tbody.innerHTML = '';
      rows.forEach(function(row){
        var b = row.buyer || {}; var name = fullname(b);
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>'+name+'</td>'+
          '<td>'+(b.email||'')+'</td>'+
          '<td>'+(b.bdate||'')+'</td>'+
          '<td>'+
            '<button class="btn btn-start" data-buyer="'+(b.user_id||'')+'" data-listing="'+data.id+'">Initiate Transaction</button>\n'+
            '<button class="btn btn-profile" data-buyer="'+(b.user_id||'')+'">View Profile</button>'+
          '</td>';
        tbody.appendChild(tr);
      });
    });
    openModal('listingModal');
  }

  document.addEventListener('click', function(e){
    if (e.target && e.target.classList.contains('show-listing')){
      var btn = e.target;
      var data = {
        id: btn.getAttribute('data-id'),
        type: btn.getAttribute('data-type')||'',
        breed: btn.getAttribute('data-breed')||'',
        age: btn.getAttribute('data-age')||'',
        weight: btn.getAttribute('data-weight')||'',
        price: btn.getAttribute('data-price')||'',
        status: btn.getAttribute('data-status')||'',
        created: btn.getAttribute('data-created')||'',
        address: btn.getAttribute('data-address')||''
      };
      openListingModal(data);
    }
    if (e.target && e.target.classList.contains('btn-start')){
      var btn = e.target;
      if (btn.disabled) return;
      var buyerId = btn.getAttribute('data-buyer');
      var listingId = btn.getAttribute('data-listing');
      btn.disabled = true; btn.textContent = 'Starting...';
      startTransaction(listingId, buyerId).then(function(res){
        if (!res.ok){
          alert('Failed to start transaction (code '+(res.code||'?')+'): '+(res.detail||''));
          btn.disabled = false; btn.textContent = 'Initiate Transaction';
        } else {
          alert('Transaction started');
          btn.disabled = true; btn.textContent = 'Started';
        }
      }).catch(function(){
        btn.disabled = false; btn.textContent = 'Initiate Transaction';
      });
    }
    if (e.target && e.target.classList.contains('btn-profile')){
      var buyerId = e.target.getAttribute('data-buyer');
      var details = $('#buyerDetails'); details.innerHTML='Loading...';
      fetchBuyer(buyerId).then(function(res){
        if (!res.ok){ details.innerHTML='Failed to load buyer profile'; }
        else {
          var b = res.data || {};
          var name = fullname(b);
          details.innerHTML = '<div><strong>'+name+'</strong></div>'+
            '<div>Email: '+(b.email||'')+'</div>'+
            '<div>Birthdate: '+(b.bdate||'')+'</div>'+
            '<div>Contact: '+(b.contact||'')+'</div>'+
            '<div>Address: '+(b.address||'')+'</div>'+
            '<div>'+[b.barangay,b.municipality,b.province].filter(Boolean).join(', ')+'</div>';
        }
      });
      openModal('buyerModal');
    }
  });
  ['listingModal','buyerModal'].forEach(function(id){
    var modal = document.getElementById(id);
    if (modal){ modal.addEventListener('click', function(ev){ if (ev.target===modal) closeModal(id); }); }
  });
})();
