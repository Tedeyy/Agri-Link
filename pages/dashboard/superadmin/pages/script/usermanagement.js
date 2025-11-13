document.addEventListener('DOMContentLoaded', function(){
  var modal = document.getElementById('detailModal');
  var closeBtn = document.getElementById('closeModal');
  var bodyEl = document.getElementById('detailBody');
  var titleEl = document.getElementById('modalTitle');
  var docStatus = document.getElementById('docStatus');
  var docPreview = document.getElementById('docPreview');
  var approveBtn = document.getElementById('approveBtn');
  var denyBtn = document.getElementById('denyBtn');
  var actionStatus = document.getElementById('actionStatus');

  var current = null;

  function openModal(row){
    current = row;
    titleEl.textContent = 'Details';
    bodyEl.innerHTML = '';
    docStatus.textContent = 'Loading document...';
    docPreview.innerHTML = '';
    actionStatus.textContent = '';

    var grid = document.createElement('div');
    grid.className = 'grid';

    var left = document.createElement('div');
    var right = document.createElement('div');

    function add(label, value){
      if (value === undefined || value === null) value = '';
      var row = document.createElement('div'); row.className='row';
      var l = document.createElement('div'); l.className='label'; l.textContent = label;
      var v = document.createElement('div'); v.textContent = value;
      row.appendChild(l); row.appendChild(v);
      left.appendChild(row);
    }

    var d = row.data;
    add('Full name', [d.user_fname, d.user_mname, d.user_lname].filter(Boolean).join(' '));
    add('Email', d.email);
    add('Contact', d.contact);
    add('Birthdate', d.bdate);
    add('Address', d.address);
    if (row.role === 'bat') add('Assigned Barangay', d.assigned_barangay);
    if (row.role === 'admin') { add('Office', d.office); add('Role', d.role); }
    add('Document Type', d.doctype);
    add('Document No.', d.docnum);

    grid.appendChild(left);
    grid.appendChild(right);
    bodyEl.appendChild(grid);

    // Fetch doc
    fetch('usermanagement.php?doc=1&role='+encodeURIComponent(row.role)+'&fname='+encodeURIComponent(d.user_fname||'')+'&mname='+encodeURIComponent(d.user_mname||'')+'&lname='+encodeURIComponent(d.user_lname||'')+'&email='+encodeURIComponent(d.email||''))
      .then(r=>r.json()).then(j=>{
        if (j && j.ok && j.url){
          docStatus.textContent = 'Document preview:';
          var img = document.createElement('img');
          img.src = j.url; img.alt = 'Document'; img.style.maxWidth='100%'; img.style.border='1px solid #e5e7eb'; img.style.borderRadius='8px';
          docPreview.innerHTML = ''; docPreview.appendChild(img);
        } else {
          docStatus.textContent = 'Document not found';
        }
      }).catch(()=>{ docStatus.textContent = 'Failed to load document'; });

    modal.style.display = 'flex';
  }

  function closeModal(){ modal.style.display = 'none'; current=null; }

  document.querySelectorAll('button.show').forEach(function(btn){
    btn.addEventListener('click', function(){
      var role = this.getAttribute('data-role');
      var json = this.getAttribute('data-json');
      var id = this.getAttribute('data-id');
      var data = {};
      try{ data = JSON.parse(json||'{}'); }catch(_){}
      openModal({ role: role, id: id, data: data });
    });
  });

  closeBtn && closeBtn.addEventListener('click', closeModal);
  modal && modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  approveBtn && approveBtn.addEventListener('click', function(){
    if (!current) return;
    actionStatus.textContent = 'Approving...';
    fetch('usermanagement.php?decide=approve', {
      method: 'POST', headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({
        role: current.role,
        id: current.id,
        fname: current.data.user_fname||'',
        mname: current.data.user_mname||'',
        lname: current.data.user_lname||'',
        email: current.data.email||''
      })
    }).then(r=>r.json()).then(j=>{
      if (j && j.ok){
        actionStatus.textContent = 'Approved';
        // remove row
        var tr = document.querySelector('tr[data-id="'+current.id+'"][data-role="'+current.role+'"]');
        if (tr) tr.remove();
        setTimeout(closeModal, 600);
      } else {
        actionStatus.textContent = (j && j.error) || 'Failed';
      }
    }).catch(()=>{ actionStatus.textContent = 'Failed'; });
  });

  denyBtn && denyBtn.addEventListener('click', function(){
    if (!current) return;
    actionStatus.textContent = 'Denying...';
    fetch('usermanagement.php?decide=deny', {
      method: 'POST', headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({
        role: current.role,
        id: current.id,
        fname: current.data.user_fname||'',
        mname: current.data.user_mname||'',
        lname: current.data.user_lname||'',
        email: current.data.email||''
      })
    }).then(r=>r.json()).then(j=>{
      if (j && j.ok){
        actionStatus.textContent = 'Denied';
        var tr = document.querySelector('tr[data-id="'+current.id+'"][data-role="'+current.role+'"]');
        if (tr) tr.remove();
        setTimeout(closeModal, 600);
      } else {
        actionStatus.textContent = (j && j.error) || 'Failed';
      }
    }).catch(()=>{ actionStatus.textContent = 'Failed'; });
  });
});
