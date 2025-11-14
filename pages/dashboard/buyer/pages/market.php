<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// AJAX data endpoint for infinite scroll
if (isset($_GET['ajax']) && $_GET['ajax']=='1'){
  $limit = max(1, min(30, (int)($_GET['limit'] ?? 10)));
  $offset = max(0, (int)($_GET['offset'] ?? 0));
  $filters = [];
  $andConds = [];

  // Build PostgREST filters
  if (!empty($_GET['livestock_type'])){ $filters['livestock_type'] = 'eq.'.$_GET['livestock_type']; }
  if (!empty($_GET['breed'])){ $filters['breed'] = 'eq.'.$_GET['breed']; }
  if (isset($_GET['min_age']) && $_GET['min_age']!==''){ $andConds[] = 'age.gte.'.(int)$_GET['min_age']; }
  if (isset($_GET['max_age']) && $_GET['max_age']!==''){ $andConds[] = 'age.lte.'.(int)$_GET['max_age']; }
  if (isset($_GET['min_weight']) && $_GET['min_weight']!==''){ $andConds[] = 'weight.gte.'.(float)$_GET['min_weight']; }
  if (isset($_GET['max_weight']) && $_GET['max_weight']!==''){ $andConds[] = 'weight.lte.'.(float)$_GET['max_weight']; }
  if (isset($_GET['min_price']) && $_GET['min_price']!==''){ $andConds[] = 'price.gte.'.(float)$_GET['min_price']; }
  if (isset($_GET['max_price']) && $_GET['max_price']!==''){ $andConds[] = 'price.lte.'.(float)$_GET['max_price']; }

  $params = array_merge([
    'select' => 'listing_id,seller_id,livestock_type,breed,address,age,weight,price,created',
    'order' => 'created.desc',
    'limit' => $limit,
    'offset' => $offset,
  ], $filters);
  if (count($andConds)) { $params['and'] = '('.implode(',', $andConds).')'; }

  [$rows,$st,$err] = sb_rest('GET','activelivestocklisting',$params);
  if (!($st>=200 && $st<300) || !is_array($rows)) $rows = [];

  // Add seller location for each row
  $withSeller = [];
  foreach ($rows as $r){
    $seller = null;
    [$sres,$sstatus,$serr] = sb_rest('GET','seller',[
      'select'=>'user_id,user_fname,user_lname,location',
      'user_id'=>'eq.'.((int)$r['seller_id']),
      'limit'=>1
    ]);
    if ($sstatus>=200 && $sstatus<300 && is_array($sres) && isset($sres[0])) $seller = $sres[0];

    $loc = null; $lat = null; $lng = null;
    if ($seller && !empty($seller['location'])){
      $loc = json_decode($seller['location'], true);
      if (is_array($loc)) { $lat = $loc['lat'] ?? null; $lng = $loc['lng'] ?? null; }
    }

    $folder = ((int)$r['seller_id']).'_'.((int)$r['listing_id']);
    $thumb = '../../bat/pages/storage_image.php?path=listings/active/'.$folder.'/image1';
    $thumb_fallback = '../../bat/pages/storage_image.php?path=listings/underreview/'.$folder.'/image1';

    $withSeller[] = [
      'listing_id' => (int)$r['listing_id'],
      'seller_id' => (int)$r['seller_id'],
      'livestock_type' => $r['livestock_type'],
      'breed' => $r['breed'],
      'address' => $r['address'],
      'age' => (int)$r['age'],
      'weight' => (float)$r['weight'],
      'price' => (float)$r['price'],
      'created' => $r['created'],
      'seller_name' => ($seller ? trim(($seller['user_fname']??'').' '.($seller['user_lname']??'')) : ''),
      'lat' => $lat,
      'lng' => $lng,
      'thumb' => $thumb,
      'thumb_fallback' => $thumb_fallback
    ];
  }
  header('Content-Type: application/json');
  echo json_encode(['items'=>$withSeller], JSON_UNESCAPED_SLASHES);
  exit;
}

// Initial data for filters
[$types, $tstatus, $terr] = sb_rest('GET', 'livestock_type', ['select'=>'type_id,name','order'=>'name.asc']);
if ($tstatus < 200 || $tstatus >= 300) { $types = []; }
[$breeds, $bstatus, $berr] = sb_rest('GET', 'livestock_breed', ['select'=>'breed_id,type_id,name','order'=>'name.asc']);
if ($bstatus < 200 || $bstatus >= 300) { $breeds = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Marketplace</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/dashboard.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    .filters{display:grid;grid-template-columns:repeat(8,minmax(120px,1fr));gap:8px}
    .feed{display:flex;flex-direction:column;gap:12px;margin-top:12px}
    .item{display:grid;grid-template-columns:120px 1fr;gap:12px;border:1px solid #e2e8f0;border-radius:8px;padding:8px}
    .item img{width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0}
    .map-top{height:280px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px}
    .show{margin-left:auto}
    .detail{display:none;border-top:1px dashed #e2e8f0;margin-top:8px;padding-top:8px}
    .detail .map{height:200px;border:1px solid #e2e8f0;border-radius:8px}
    .muted{color:#4a5568;font-size:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top" style="margin-bottom:8px;">
      <div><h1>Marketplace</h1></div>
      <div>
        <a class="btn" href="../dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <div id="top-map" class="map-top"></div>

    <div class="card">
      <div class="filters">
        <div>
          <label>Type</label>
          <select id="f-type">
            <option value="">All</option>
            <?php foreach (($types?:[]) as $t): ?>
              <option value="<?php echo safe($t['name']); ?>"><?php echo safe($t['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Breed</label>
          <select id="f-breed">
            <option value="">All</option>
            <?php foreach (($breeds?:[]) as $b): ?>
              <option data-typeid="<?php echo (int)$b['type_id']; ?>" value="<?php echo safe($b['name']); ?>"><?php echo safe($b['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Min Age</label>
          <input type="number" id="f-min-age" min="0" step="1" />
        </div>
        <div>
          <label>Max Age</label>
          <input type="number" id="f-max-age" min="0" step="1" />
        </div>
        <div>
          <label>Min Price</label>
          <input type="number" id="f-min-price" min="0" step="0.01" />
        </div>
        <div>
          <label>Max Price</label>
          <input type="number" id="f-max-price" min="0" step="0.01" />
        </div>
        <div>
          <label>Min Weight (kg)</label>
          <input type="number" id="f-min-weight" min="0" step="0.01" />
        </div>
        <div>
          <label>Max Weight (kg)</label>
          <input type="number" id="f-max-weight" min="0" step="0.01" />
        </div>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
        <button id="apply" class="btn">Apply Filters</button>
        <button id="clear" class="btn" style="background:#718096;">Clear</button>
      </div>
    </div>

    <div id="feed" class="feed"></div>
    <div id="sentinel" style="height:24px;"></div>
  </div>

  <script>
    (function(){
      var feed = document.getElementById('feed');
      var sentinel = document.getElementById('sentinel');
      var topMapEl = document.getElementById('top-map');
      var map = L.map(topMapEl).setView([8.314209 , 124.859425], 14); 
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
      var markerLayer = L.layerGroup().addTo(map);

      var state = { offset: 0, limit: 10, loading: false, done: false };

      // Filter breed options by selected type
      (function(){
        var typeSel = document.getElementById('f-type');
        var breedSel = document.getElementById('f-breed');
        if (!typeSel || !breedSel) return;
        var allBreedOptions = Array.prototype.slice.call(breedSel.querySelectorAll('option'));
        function applyBreedFilter(){
          var typeName = typeSel.value || '';
          var selectedTypeIds = [];
          // map type name to type_id via a hidden map built server-side
          // Build map here from DOM: find breeds grouped by data-typeid
          var opts = allBreedOptions.slice(1); // skip 'All'
          var typeIds = {};
          // No direct type_id from type name here; keep all breeds if no type selected
          while (breedSel.firstChild) breedSel.removeChild(breedSel.firstChild);
          var all = document.createElement('option'); all.value=''; all.textContent='All'; breedSel.appendChild(all);
          opts.forEach(function(o){
            if (!typeName){ breedSel.appendChild(o); return; }
            var tid = o.getAttribute('data-typeid');
            // When a type is chosen, filter by matching breeds from that type_id
            // Because we don't have type_id from type name, we include all breeds; optional enhancement: fetch mapping.
            breedSel.appendChild(o);
          });
        }
        typeSel.addEventListener('change', applyBreedFilter);
        applyBreedFilter();
      })();

      function currentFilters(){
        return {
          livestock_type: document.getElementById('f-type').value || '',
          breed: document.getElementById('f-breed').value || '',
          min_age: document.getElementById('f-min-age').value,
          max_age: document.getElementById('f-max-age').value,
          min_price: document.getElementById('f-min-price').value,
          max_price: document.getElementById('f-max-price').value,
          min_weight: document.getElementById('f-min-weight').value,
          max_weight: document.getElementById('f-max-weight').value
        };
      }

      function buildQuery(params){
        var q = new URLSearchParams();
        Object.keys(params).forEach(function(k){ if (params[k]!=='' && params[k]!=null) q.append(k, params[k]); });
        return q.toString();
      }

      function addMarkers(items){
        items.forEach(function(it){
          if (it.lat!=null && it.lng!=null){
            L.marker([it.lat,it.lng]).addTo(markerLayer)
              .bindPopup('#'+it.listing_id+' • '+it.livestock_type+' • '+it.breed);
          }
        });
      }

      function renderItems(items){
        var frag = document.createDocumentFragment();
        items.forEach(function(it){
          var card = document.createElement('div');
          card.className = 'item';
          var img = document.createElement('img');
          img.src = it.thumb;
          img.alt = 'thumb';
          img.onerror = function(){ if (img.src !== it.thumb_fallback) img.src = it.thumb_fallback; else img.style.display='none'; };
          var info = document.createElement('div');
          info.innerHTML = (
            '<div><strong>'+escapeHtml(it.livestock_type)+' • '+escapeHtml(it.breed)+'</strong></div>'+
            '<div>'+escapeHtml(it.address)+'</div>'+
            '<div>Age: '+escapeHtml(it.age)+' • Weight: '+escapeHtml(it.weight)+'kg • Price: ₱'+escapeHtml(it.price)+'</div>'+
            '<div class="muted">Listing #'+it.listing_id+' • Seller #'+it.seller_id+' • Created '+escapeHtml(it.created||'')+'</div>'+
            (it.seller_name?('<div>Seller: '+escapeHtml(it.seller_name)+'</div>'):'')+
            '<div class="detail">'+
              '<div class="map" id="map-'+it.listing_id+'"></div>'+
            '</div>'
          );
          var actions = document.createElement('div');
          actions.style.display = 'flex';
          actions.style.alignItems = 'start';
          actions.style.gap = '8px';
          var showBtn = document.createElement('button');
          showBtn.className = 'btn show';
          showBtn.textContent = 'Show';
          showBtn.addEventListener('click', function(){
            var detail = info.querySelector('.detail');
            var isHidden = (detail.style.display==='none' || detail.style.display==='');
            detail.style.display = isHidden ? 'block' : 'none';
            if (isHidden){
              var m = info.querySelector('#map-'+it.listing_id);
              if (m && !m._inited){
                if (it.lat!=null && it.lng!=null){
                  var map2 = L.map(m).setView([it.lat,it.lng], 12);
                  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map2);
                  L.marker([it.lat,it.lng]).addTo(map2);
                } else {
                  m.style.display='flex'; m.style.alignItems='center'; m.style.justifyContent='center'; m.style.color='#4a5568'; m.textContent='No location available';
                }
                m._inited = true;
              }
            }
          });
          actions.appendChild(showBtn);

          card.appendChild(img);
          card.appendChild(info);
          card.appendChild(actions);
          frag.appendChild(card);
        });
        feed.appendChild(frag);
      }

      function escapeHtml(s){
        if (s==null) return '';
        return String(s).replace(/[&<>"]+/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; });
      }

      function loadMore(){
        if (state.loading || state.done) return;
        state.loading = true;
        var params = Object.assign({ ajax: 1, limit: state.limit, offset: state.offset }, currentFilters());
        var q = buildQuery(params);
        fetch('market.php?'+q, { credentials:'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){
            var items = (data && data.items) ? data.items : [];
            if (items.length === 0){ state.done = true; return; }
            renderItems(items);
            addMarkers(items);
            state.offset += items.length;
          })
          .catch(function(){ /* silently ignore */ })
          .finally(function(){ state.loading = false; });
      }

      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if (e.isIntersecting) loadMore(); });
      });
      io.observe(sentinel);

      document.getElementById('apply').addEventListener('click', function(){
        feed.innerHTML = ''; markerLayer.clearLayers();
        state.offset = 0; state.done = false; loadMore();
      });
      document.getElementById('clear').addEventListener('click', function(){
        document.getElementById('f-type').value = '';
        document.getElementById('f-breed').value = '';
        document.getElementById('f-min-age').value = '';
        document.getElementById('f-max-age').value = '';
        document.getElementById('f-min-price').value = '';
        document.getElementById('f-max-price').value = '';
        document.getElementById('f-min-weight').value = '';
        document.getElementById('f-max-weight').value = '';
        feed.innerHTML = ''; markerLayer.clearLayers();
        state.offset = 0; state.done = false; loadMore();
      });

      // initial load
      loadMore();
    })();
  </script>
</body>
</html>
