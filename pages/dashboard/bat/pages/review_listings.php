<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

$bat_id = $_SESSION['user_id'] ?? null;
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'bat' || !$bat_id){
  http_response_code(302);
  header('Location: ../dashboard.php');
  exit;
}

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

[$rows, $status, $err] = sb_rest('GET', 'reviewlivestocklisting', [
  'select'=>'listing_id,seller_id,livestock_type,breed,address,age,weight,price,created',
  'order'=>'created.desc'
]);
if (!($status>=200 && $status<300) || !is_array($rows)) { $rows = []; }

function fetch_seller($seller_id){
  [$sres,$sstatus,$serr] = sb_rest('GET','seller',[
    'select'=>'user_id,firstname,lastname,address,latitude,longitude,location_lat,location_lng,location',
    'user_id'=>'eq.'.$seller_id,
    'limit'=>1
  ]);
  if ($sstatus>=200 && $sstatus<300 && is_array($sres) && isset($sres[0])) return $sres[0];
  return null;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Listings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/dashboard.css">
  <style>
    .card{margin-bottom:14px}
    .thumbs img{width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;margin-right:8px}
    .row{display:grid;grid-template-columns:160px 1fr 260px;gap:12px;align-items:start}
    .map{height:160px;border-radius:8px;border:1px solid #e2e8f0}
    .muted{color:#4a5568;font-size:12px}
  </style>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
  <div class="wrap">
    <div class="top" style="margin-bottom:8px;">
      <div><h1>Review Livestock Listings</h1></div>
      <div>
        <a class="btn" href="../dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <?php foreach ($rows as $r): $seller = fetch_seller((int)$r['seller_id']); $folder = ((int)$r['seller_id']).'_'.((int)$r['listing_id']); ?>
      <div class="card">
        <div class="row">
          <div class="thumbs">
            <?php for ($i=1;$i<=3;$i++): $img="/pages/dashboard/bat/pages/storage_image.php?path=".rawurlencode("listings/underreview/$folder/image$i"); ?>
              <img src="<?php echo $img; ?>" alt="image<?php echo $i; ?>" onerror="this.style.display='none'" />
            <?php endfor; ?>
          </div>
          <div>
            <div><strong><?php echo safe($r['livestock_type'].' • '.$r['breed']); ?></strong></div>
            <div>Address: <?php echo safe($r['address']); ?></div>
            <div>Age: <?php echo safe($r['age']); ?> • Weight: <?php echo safe($r['weight']); ?>kg • Price: ₱<?php echo safe($r['price']); ?></div>
            <div class="muted">Listing #<?php echo (int)$r['listing_id']; ?> • Seller #<?php echo (int)$r['seller_id']; ?> • Created <?php echo safe($r['created']); ?></div>
            <?php if ($seller): ?>
              <div>Seller: <?php echo safe(($seller['firstname']??'').' '.($seller['lastname']??'')); ?></div>
            <?php endif; ?>
          </div>
          <div>
            <div id="map-<?php echo (int)$r['listing_id']; ?>" class="map"></div>
            <form method="post" action="review_actions.php" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <input type="hidden" name="listing_id" value="<?php echo (int)$r['listing_id']; ?>" />
              <button class="btn" name="action" value="approve" type="submit">Approve</button>
              <button class="btn" name="action" value="deny" type="submit" style="background:#e53e3e">Deny</button>
            </form>
          </div>
        </div>
      </div>
      <script>
        (function(){
          var lat=null,lng=null;
          <?php
            $lat = $seller['latitude'] ?? ($seller['location_lat'] ?? null);
            $lng = $seller['longitude'] ?? ($seller['location_lng'] ?? null);
            if (!$lat && !$lng && !empty($seller['location'])){
              $loc = json_decode($seller['location'], true);
              if (is_array($loc)) { $lat=$loc['lat']??null; $lng=$loc['lng']??null; }
            }
            if ($lat!==null && $lng!==null){
              echo 'lat='.json_encode((float)$lat).'; lng='.json_encode((float)$lng).';';
            }
          ?>
          var el = document.getElementById('map-<?php echo (int)$r['listing_id']; ?>');
          if (!el || lat===null || lng===null) return;
          var map = L.map(el).setView([lat,lng], 12);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
          L.marker([lat,lng]).addTo(map);
        })();
      </script>
    <?php endforeach; ?>

    <?php if (!count($rows)): ?>
      <div class="card">No listings to review.</div>
    <?php endif; ?>
  </div>
</body>
</html>
