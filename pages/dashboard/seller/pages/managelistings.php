<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

$firstname = isset($_SESSION['firstname']) && $_SESSION['firstname'] !== '' ? $_SESSION['firstname'] : 'User';
$sellerId = $_SESSION['user_id'] ?? null;
if (!$sellerId) {
  header('Location: ../dashboard.php');
  exit;
}

$tab = isset($_GET['tab']) ? strtolower($_GET['tab']) : 'review';
if (!in_array($tab, ['review','active','sold','denied'], true)) { $tab = 'review'; }

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

$reviewRows = ($tab==='review') ? fetch_list(['reviewlivestocklisting','reviewlivestocklistings'], $sellerId) : [];
$activeRows = ($tab==='active') ? fetch_list(['livestocklisting','livestocklistings'], $sellerId) : [];
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
</head>
<body>
  <div class="wrap">
    <div class="top" style="margin-bottom:8px;">
      <div><h1>Manage Listings</h1></div>
      <div>
        <a class="btn" href="../dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <a class="btn" href="?tab=review" style="background:<?php echo $tab==='review'?'#d69e2e':'#718096'; ?>">Review</a>
        <a class="btn" href="?tab=active" style="background:<?php echo $tab==='active'?'#d69e2e':'#718096'; ?>">Active</a>
        <a class="btn" href="?tab=sold" style="background:<?php echo $tab==='sold'?'#d69e2e':'#718096'; ?>">Sold</a>
        <a class="btn" href="?tab=denied" style="background:<?php echo $tab==='denied'?'#d69e2e':'#718096'; ?>">Denied</a>
      </div>

      <?php
        $rows = $reviewRows ?: ($activeRows ?: ($soldRows ?: $deniedRows));
      ?>
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid #e2e8f0;">
              <th style="padding:8px;">ID</th>
              <th style="padding:8px;">Type</th>
              <th style="padding:8px;">Breed</th>
              <th style="padding:8px;">Age</th>
              <th style="padding:8px;">Weight</th>
              <th style="padding:8px;">Price</th>
              <th style="padding:8px;">Status</th>
              <th style="padding:8px;">Created</th>
              <th style="padding:8px;">Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows || count($rows)===0): ?>
              <tr><td colspan="9" style="padding:12px;color:#4a5568;">No listings found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr style="border-bottom:1px solid #edf2f7;">
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['listing_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['livestock_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['breed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['age'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['weight'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)($r['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
