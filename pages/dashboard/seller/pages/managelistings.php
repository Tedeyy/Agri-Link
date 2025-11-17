<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

$firstname = isset($_SESSION['firstname']) && $_SESSION['firstname'] !== '' ? $_SESSION['firstname'] : 'User';
$sellerId = $_SESSION['user_id'] ?? null;
if (!$sellerId) {
  header('Location: ../dashboard.php');
  exit;
}

// Inline AJAX endpoints
if (isset($_GET['action']) && $_GET['action'] === 'interests'){
  header('Content-Type: application/json');
  $listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;
  if (!$listingId) { echo json_encode(['ok'=>false,'error'=>'missing listing_id']); exit; }
  // Join listinginterest with buyer
  [$rows,$st,$err] = sb_rest('GET','listinginterest',[
    'select' => 'interest_id,listing_id,buyer_id,message,created,buyer:buyer(user_id,user_fname,user_mname,user_lname,bdate,email)',
    'listing_id' => 'eq.'.$listingId,
    'order' => 'created.desc'
  ]);
  if (!($st>=200 && $st<300) || !is_array($rows)) { echo json_encode(['ok'=>false,'error'=>'load_failed']); exit; }
  echo json_encode(['ok'=>true,'data'=>$rows]);
  exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'buyer_profile'){
  header('Content-Type: application/json');
  $buyerId = isset($_GET['buyer_id']) ? (int)$_GET['buyer_id'] : 0;
  if (!$buyerId) { echo json_encode(['ok'=>false,'error'=>'missing buyer_id']); exit; }
  [$rows,$st,$err] = sb_rest('GET','buyer',[
    'select'=>'user_id,user_fname,user_mname,user_lname,bdate,contact,address,barangay,municipality,province,email',
    'user_id'=>'eq.'.$buyerId,
    'limit'=>1
  ]);
  if (!($st>=200 && $st<300) || !is_array($rows) || !isset($rows[0])){ echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  echo json_encode(['ok'=>true,'data'=>$rows[0]]);
  exit;
}
if (isset($_POST['action']) && $_POST['action'] === 'start_transaction'){
  header('Content-Type: application/json');
  $listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
  $buyerId = isset($_POST['buyer_id']) ? (int)$_POST['buyer_id'] : 0;
  if (!$listingId || !$buyerId) { echo json_encode(['ok'=>false,'error'=>'missing params']); exit; }
  $payload = [[
    'listing_id'=>$listingId,
    'seller_id'=>(int)$sellerId,
    'buyer_id'=>$buyerId,
    'status'=>'Started'
  ]];
  [$res,$st,$err] = sb_rest('POST','starttransactions',[], $payload, ['Prefer: return=representation']);
  if (!($st>=200 && $st<300)){
    $detail = '';
    if (is_array($res) && isset($res['message'])) { $detail = $res['message']; }
    elseif (is_string($res) && $res!=='') { $detail = $res; }
    echo json_encode(['ok'=>false,'error'=>'start_failed','code'=>$st,'detail'=>$detail]);
    exit;
  }
  // Also write to transactions_logs
  $logPayload = [[
    'listing_id'=>$listingId,
    'seller_id'=>(int)$sellerId,
    'buyer_id'=>$buyerId,
    'status'=>'Started'
  ]];
  [$lr,$ls,$le] = sb_rest('POST','transactions_logs',[], $logPayload, ['Prefer: return=representation']);
  $warning = null;
  if (!($ls>=200 && $ls<300)){
    $ldetail = '';
    if (is_array($lr) && isset($lr['message'])) { $ldetail = $lr['message']; }
    elseif (is_string($lr) && $lr!=='') { $ldetail = $lr; }
    $warning = 'Log insert failed (code '.(string)$ls.'). '.($ldetail?:'');
  }
  echo json_encode(['ok'=>true,'data'=>$res[0] ?? null, 'warning'=>$warning]);
  exit;
}

$tab = isset($_GET['tab']) ? strtolower($_GET['tab']) : 'pending';
if (!in_array($tab, ['pending','active','sold','denied'], true)) { $tab = 'pending'; }

function fetch_list($tables, $sellerId){
  $select = 'listing_id,livestock_type,breed,address,age,weight,price,status,created';
  foreach ($tables as $t){
    [$res,$st,$err] = sb_rest('GET', $t, [
      'select' => $select,
      'seller_id' => 'eq.'.$sellerId,
      'order' => 'created.desc'
    ]);
    if ($st>=200 && $st<300 && is_array($res)) return $res;
  }
  return [];
}

$pendingRows = ($tab==='pending') ? fetch_list(['reviewlivestocklisting','reviewlivestocklistings','livestocklisting','livestocklistings'], $sellerId) : [];
$activeRows = ($tab==='active') ? fetch_list(['activelivestocklisting','activelivestocklistings'], $sellerId) : [];
$soldRows   = ($tab==='sold')   ? fetch_list(['soldlivestocklisting','soldlivestocklistings'], $sellerId) : [];
$deniedRows = ($tab==='denied') ? fetch_list(['deniedlivestocklisting','deniedlivestocklistings'], $sellerId) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Listings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/dashboard.css">
  <link rel="stylesheet" href="../style/managelistings.css">
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div><h1>Manage Listings</h1></div>
    </div>

    <div class="card">
      <div class="flex-row">
        <a class="btn tab <?php echo $tab==='pending'?'is-active':''; ?>" href="?tab=pending">Pending</a>
        <a class="btn tab <?php echo $tab==='active'?'is-active':''; ?>" href="?tab=active">Active</a>
        <a class="btn tab <?php echo $tab==='sold'?'is-active':''; ?>" href="?tab=sold">Sold</a>
        <a class="btn tab <?php echo $tab==='denied'?'is-active':''; ?>" href="?tab=denied">Denied</a>
        <span class="flex-spacer"></span>
        <a class="btn" href="createlisting.php">Create Listing</a>
      </div>

      <?php
        $rows = $pendingRows ?: ($activeRows ?: ($soldRows ?: $deniedRows));
      ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Breed</th>
              <th>Age</th>
              <th>Weight</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
              <th>Address</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows || count($rows)===0): ?>
              <tr><td colspan="9" class="subtle">No listings found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr class="row-divider">
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['listing_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['livestock_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['breed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['age'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['weight'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>&nbsp;<?php echo htmlspecialchars((string)($r['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <button class="btn show-listing" data-id="<?php echo (int)($r['listing_id']??0); ?>" data-type="<?php echo htmlspecialchars((string)($r['livestock_type']??''),ENT_QUOTES,'UTF-8'); ?>" data-breed="<?php echo htmlspecialchars((string)($r['breed']??''),ENT_QUOTES,'UTF-8'); ?>" data-age="<?php echo htmlspecialchars((string)($r['age']??''),ENT_QUOTES,'UTF-8'); ?>" data-weight="<?php echo htmlspecialchars((string)($r['weight']??''),ENT_QUOTES,'UTF-8'); ?>" data-price="<?php echo htmlspecialchars((string)($r['price']??''),ENT_QUOTES,'UTF-8'); ?>" data-status="<?php echo htmlspecialchars((string)($r['status']??''),ENT_QUOTES,'UTF-8'); ?>" data-created="<?php echo htmlspecialchars((string)($r['created']??''),ENT_QUOTES,'UTF-8'); ?>" data-address="<?php echo htmlspecialchars((string)($r['address']??''),ENT_QUOTES,'UTF-8'); ?>">Show</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Listing Details Modal -->
  <div id="listingModal" class="modal">
    <div class="panel">
      <div class="modal-head">
        <h2>Listing Details</h2>
        <button class="close-btn" data-close="listingModal">Close</button>
      </div>
      <div id="listingBasics" class="mt-6"></div>
      <div class="imgs" id="listingImages"></div>
      <div class="subtle" id="imgNotice"></div>
      <div class="mt-12">
        <h3 class="mt-8">Interested Buyers</h3>
        <table class="table" id="interestTable">
          <thead>
            <tr>
              <th>Fullname</th>
              <th>Email</th>
              <th>Birthdate</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Buyer Profile Modal -->
  <div id="buyerModal" class="modal">
    <div class="panel">
      <div class="modal-head">
        <h2>Buyer Profile</h2>
        <button class="close-btn" data-close="buyerModal">Back</button>
      </div>
      <div id="buyerDetails" class="mt-8"></div>
      <div class="grid2 mt-12">
        <div class="counter">Recent Violations (placeholder)</div>
        <div class="counter">Rating (placeholder)</div>
      </div>
    </div>
  </div>
  <div id="seller-data" data-seller="<?php echo (int)$sellerId; ?>" hidden></div>
  <script src="../script/managelistings.js"></script>
</body>
</html>
