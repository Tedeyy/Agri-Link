<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Handle Interest POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='interest'){
  header('Content-Type: application/json');
  $buyer_id = $_SESSION['user_id'] ?? null;
  $role = $_SESSION['role'] ?? '';
  $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
  $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
  if (!$buyer_id || $role!=='buyer' || !$listing_id){ echo json_encode(['ok'=>false]); exit; }
  $payload = [[ 'listing_id'=>$listing_id, 'buyer_id'=>(int)$buyer_id, 'message'=>$message!==''?$message:null ]];
  sb_rest('POST','listinginterest',[], $payload, ['Prefer: return=representation']);
  sb_rest('POST','listinginterest_log',[], $payload, ['Prefer: return=representation']);
  echo json_encode(['ok'=>true]);
  exit;
}

$listing_id = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;
if (!$listing_id){
  http_response_code(302);
  header('Location: market.php');
  exit;
}

// Load listing (from activelivestocklisting)
[$lrows,$lst,$lerr] = sb_rest('GET','activelivestocklisting',[
  'select'=>'listing_id,seller_id,livestock_type,breed,address,age,weight,price,created',
  'listing_id'=>'eq.'.$listing_id,
  'limit'=>1
]);
if (!($lst>=200 && $lst<300) || !is_array($lrows) || !isset($lrows[0])){
  http_response_code(404);
  echo 'Listing not found';
  exit;
}
$r = $lrows[0];

// Load seller
$seller = null;
[$sres,$sstatus,$serr] = sb_rest('GET','seller',[
  'select'=>'user_id,user_fname,user_mname,user_lname,location',
  'user_id'=>'eq.'.((int)$r['seller_id']),
  'limit'=>1
]);
if ($sstatus>=200 && $sstatus<300 && is_array($sres) && isset($sres[0])) $seller = $sres[0];

$sfname = $seller['user_fname'] ?? '';
$smname = $seller['user_mname'] ?? '';
$slname = $seller['user_lname'] ?? '';
$fullname = trim($sfname.' '.($smname?:'').' '.$slname);
$sanFull = strtolower(preg_replace('/[^a-z0-9]+/i','_', $fullname));
$sanFull = trim($sanFull, '_');
if ($sanFull===''){ $sanFull='user'; }
$newFolder = ((int)$r['seller_id']).'_'.$sanFull;
$legacyFolder = ((int)$r['seller_id']).'_'.((int)$r['listing_id']);
$createdKey = isset($r['created']) ? date('YmdHis', strtotime($r['created'])) : '';

$lat = null; $lng = null;
if ($seller && !empty($seller['location'])){
  $loc = json_decode($seller['location'], true);
  if (is_array($loc)) { $lat = $loc['lat'] ?? null; $lng = $loc['lng'] ?? null; }
}

// Build image sources
$imgs = [];
for ($i=1;$i<=3;$i++){
  $primary = ($createdKey!==''
    ? '../../bat/pages/storage_image.php?path=listings/verified/'.$newFolder.'/'.$createdKey.'_'.$i.'img.jpg'
    : '../../bat/pages/storage_image.php?path=listings/active/'.$legacyFolder.'/image'.$i);
  $fallback = '../../bat/pages/storage_image.php?path=listings/active/'.$legacyFolder.'/image'.$i;
  $imgs[] = ['src'=>$primary,'fallback'=>$fallback];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>View Listing #<?php echo (int)$r['listing_id']; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/dashboard.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    .wrap{max-width:1200px;margin:0 auto;padding:12px}
    .gallery{display:flex;gap:12px;flex-wrap:wrap}
    .gallery img{width:320px;height:320px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0}
    .meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:18px;line-height:1.4}
    .title{font-weight:700;font-size:24px}
    .muted{font-size:16px}
    .map{height:320px;border:1px solid #e2e8f0;border-radius:8px}
    .lightbox{position:fixed;inset:0;background:rgba(0,0,0,0.75);display:none;align-items:center;justify-content:center;z-index:1000}
    .lightbox img{max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);cursor:zoom-out}
    .cta{display:flex;justify-content:center}
    .cta .btn{font-size:18px;padding:14px 22px}
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-left"><div class="brand">Listing</div></div>
    <div class="nav-right"><a class="btn" href="market.php">Back to Marketplace</a></div>
  </nav>
  <div class="wrap">
    <div class="card">
      <div style="display:flex;gap:16px;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <div>
          <div class="title"><?php echo safe(($r['livestock_type']??'').' • '.($r['breed']??'')); ?></div>
          <div class="muted">Seller: <?php echo safe($fullname); ?> • Created <?php echo safe($r['created']??''); ?></div>
        </div>
      </div>
      <div class="gallery">
        <?php foreach ($imgs as $im): $src=$im['src']; $fb=$im['fallback']; ?>
          <img class="gimg" src="<?php echo $src; ?>" alt="image" onerror="if(this.src!=='<?php echo $fb; ?>'){this.src='<?php echo $fb; ?>';}else{this.style.display='none';}">
        <?php endforeach; ?>
      </div>
    </div>

    <div id="lightbox" class="lightbox"><img id="lightboxImg" src="" alt="preview"></div>

    <div class="card">
      <div class="meta">
        <div>
          <div><strong>Address:</strong> <?php echo safe($r['address']); ?></div>
          <div><strong>Age:</strong> <?php echo safe($r['age']); ?></div>
          <div><strong>Weight:</strong> <?php echo safe($r['weight']); ?> kg</div>
          <div><strong>Price:</strong> ₱<?php echo safe($r['price']); ?></div>
        </div>
        <div>
          <div class="map" id="map"></div>
        </div>
      </div>
    </div>

    <div class="card cta">
      <button id="interestBtn" class="btn" style="min-width:280px;">👀 I'm Interested</button>
    </div>
  </div>
  <script>
    (function(){
      var btn = document.getElementById('interestBtn');
      if (btn){
        btn.addEventListener('click', function(){
          var msg = prompt('Optional message to seller:');
          var fd = new FormData();
          fd.append('action','interest');
          fd.append('listing_id','<?php echo (int)$r['listing_id']; ?>');
          if (msg!=null) fd.append('message', msg);
          fetch('viewpost.php', { method:'POST', body: fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){ if (res && res.ok) { btn.textContent='Interested ✓'; btn.disabled=true; } });
        });
      }
      // Map init
      var lat = <?php echo $lat!==null ? json_encode((float)$lat) : 'null'; ?>;
      var lng = <?php echo $lng!==null ? json_encode((float)$lng) : 'null'; ?>;
      if (lat!=null && lng!=null && window.L){
        var map = L.map('map').setView([lat,lng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
        L.marker([lat,lng]).addTo(map);
      } else {
        var m = document.getElementById('map');
        if (m){ m.style.display='flex'; m.style.alignItems='center'; m.style.justifyContent='center'; m.style.color='#4a5568'; m.textContent='No location available'; }
      }
      // Lightbox
      var lb = document.getElementById('lightbox');
      var lbImg = document.getElementById('lightboxImg');
      Array.prototype.forEach.call(document.querySelectorAll('.gimg'), function(el){
        el.addEventListener('click', function(){ lbImg.src = el.src; lb.style.display='flex'; });
      });
      function closeLb(){ lb.style.display='none'; lbImg.src=''; }
      lb.addEventListener('click', closeLb);
      document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeLb(); });
    })();
  </script>
</body>
</html>
