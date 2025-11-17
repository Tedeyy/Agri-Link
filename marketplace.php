<?php
session_start();
require_once __DIR__ . '/pages/authentication/lib/supabase_client.php';

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// AJAX data endpoint for infinite scroll
if (isset($_GET['ajax']) && $_GET['ajax']=='1'){
  $limit = max(1, min(30, (int)($_GET['limit'] ?? 10)));
  $offset = max(0, (int)($_GET['offset'] ?? 0));
  $filters = [];
  $andConds = [];
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

  // Add seller location and thumbnail
  $withSeller = [];
  foreach ($rows as $r){
    [$sres,$sstatus,$serr] = sb_rest('GET','seller',[ 'select'=>'user_id,user_fname,user_mname,user_lname,location', 'user_id'=>'eq.'.((int)$r['seller_id']), 'limit'=>1 ]);
    $seller = ($sstatus>=200 && $sstatus<300 && is_array($sres) && isset($sres[0])) ? $sres[0] : [];
    $loc = null; $lat = null; $lng = null;
    if (!empty($seller['location'])){ $loc = json_decode($seller['location'], true); if (is_array($loc)) { $lat = $loc['lat'] ?? null; $lng = $loc['lng'] ?? null; } }
    $sfname = $seller['user_fname'] ?? ''; $smname = $seller['user_mname'] ?? ''; $slname = $seller['user_lname'] ?? '';
    $fullname = trim($sfname.' '.($smname?:'').' '.$slname);
    $sanFull = strtolower(preg_replace('/[^a-z0-9]+/i','_', $fullname)); $sanFull = trim($sanFull, '_'); if ($sanFull===''){ $sanFull='user'; }
    $newFolder = ((int)$r['seller_id']).'_'.$sanFull;
    $legacyFolder = ((int)$r['seller_id']).'_'.((int)$r['listing_id']);
    $createdKey = isset($r['created']) ? date('YmdHis', strtotime($r['created'])) : '';
    $thumb = ($createdKey!==''
      ? 'pages/dashboard/bat/pages/storage_image.php?path=listings/verified/'.$newFolder.'/'.$createdKey.'_1img.jpg'
      : 'pages/dashboard/bat/pages/storage_image.php?path=listings/active/'.$legacyFolder.'/image1');
    $thumb_fallback = 'pages/dashboard/bat/pages/storage_image.php?path=listings/active/'.$legacyFolder.'/image1';
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
      'seller_name' => $fullname,
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

// Pins endpoint (no buyer pin here since public)
if (isset($_GET['pins']) && $_GET['pins']=='1'){
  $filters = [];
  $andConds = [];
  if (!empty($_GET['livestock_type'])){ $filters['livestock_type'] = 'eq.'.$_GET['livestock_type']; }
  if (!empty($_GET['breed'])){ $filters['breed'] = 'eq.'.$_GET['breed']; }
  if (isset($_GET['min_age']) && $_GET['min_age']!==''){ $andConds[] = 'age.gte.'.(int)$_GET['min_age']; }
  if (isset($_GET['max_age']) && $_GET['max_age']!==''){ $andConds[] = 'age.lte.'.(int)$_GET['max_age']; }
  if (count($andConds)) { $filters['and'] = '('.implode(',', $andConds).')'; }

  [$alist,$alst,$aerr] = sb_rest('GET','activelivestocklisting', array_merge(['select'=>'listing_id,livestock_type,breed'], $filters));
  if (!($alst>=200 && $alst<300) || !is_array($alist)) $alist = [];
  $activeIndex = [];
  foreach ($alist as $arow){ $activeIndex[(int)$arow['listing_id']] = ['type'=>$arow['livestock_type']??'', 'breed'=>$arow['breed']??'']; }

  [$apins,$apst,$aperr] = sb_rest('GET','activelocation_pins',[ 'select'=>'pin_id,location,listing_id,status' ]);
  if (!($apst>=200 && $apst<300) || !is_array($apins)) $apins = [];
  $activePins = [];
  foreach ($apins as $p){
    $lid = (int)($p['listing_id'] ?? 0);
    if (!isset($activeIndex[$lid])) continue;
    $locStr = (string)($p['location'] ?? '');
    $la = null; $ln = null;
    $j = json_decode($locStr, true);
    if (is_array($j)){
      if (isset($j['lat']) && isset($j['lng'])){ $la = (float)$j['lat']; $ln = (float)$j['lng']; }
      else if (isset($j[0]) && isset($j[1])){ $la = (float)$j[0]; $ln = (float)$j[1]; }
    } else if (strpos($locStr, ',') !== false){
      $parts = explode(',', $locStr, 2); $la = (float)trim($parts[0]); $ln = (float)trim($parts[1]);
    }
    if ($la!==null && $ln!==null){ $meta = $activeIndex[$lid]; $activePins[] = ['listing_id'=>$lid, 'lat'=>$la, 'lng'=>$ln, 'type'=>$meta['type'], 'breed'=>$meta['breed']]; }
  }
  header('Content-Type: application/json');
  echo json_encode(['activePins'=>$activePins], JSON_UNESCAPED_SLASHES);
  exit;
}

// Initial data for filters
[$types, $tstatus, $terr] = sb_rest('GET', 'livestock_type', ['select'=>'type_id,name','order'=>'name.asc']);
if ($tstatus < 200 || $tstatus >= 300) { $types = []; }
[$breeds, $bstatus, $berr] = sb_rest('GET', 'livestock_breed', ['select'=>'breed_id,type_id,name','order'=>'name.asc']);
if ($bstatus < 200 || $bstatus >= 300) { $breeds = []; }

$preType = isset($_GET['type']) ? (string)$_GET['type'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Marketplace</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link rel="stylesheet" href="pages/style/marketplace.css" />
  </head>
<body>
  <div id="market-root" class="wrap" data-pretype="<?php echo safe($preType); ?>">
    <div class="top">
      <div><h1>Marketplace</h1></div>
      <div>
        <a class="btn" href="index.html">Home</a>
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
      <div class="controls">
        <button id="apply" class="btn">Apply Filters</button>
        <button id="clear" class="btn btn-muted">Clear</button>
      </div>
    </div>

    <div id="feed" class="feed"></div>
    <div id="sentinel" class="sentinel"></div>
  </div>

  <div id="viewModal" class="modal"><div class="panel"><div class="modal-header"><h2>Listing</h2><button class="btn btn-danger" id="mClose">Close</button></div><div id="mBody"></div></div></div>
  <script src="pages/script/marketplace.js"></script>
</body>
</html>
