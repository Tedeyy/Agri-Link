<?php
session_start();
$firstname = isset($_SESSION['firstname']) && $_SESSION['firstname'] !== '' ? $_SESSION['firstname'] : 'User';
// Compute verification status from role and source table
$role = $_SESSION['role'] ?? '';
$src  = $_SESSION['source_table'] ?? '';
$isVerified = false;
if ($role === 'admin') {
    $isVerified = ($src === 'admin');
}
$statusLabel = $isVerified ? 'Verified' : 'Under review';
require_once __DIR__ . '/../../authentication/lib/supabase_client.php';

// Prepare data for sales chart (last 3 months and next month)
$labels = [];
$monthKeys = [];
$now = new DateTime('first day of this month 00:00:00');
for ($i=-3; $i<=1; $i++){
  $d = (clone $now)->modify(($i>=0?'+':'').$i.' month');
  $labels[] = $d->format('M');
  $monthKeys[] = $d->format('Y-m');
}

// Get livestock types
[$typesRes,$typesStatus,$typesErr] = sb_rest('GET','livestock_type',['select'=>'name']);
$typeNames = [];
if ($typesStatus>=200 && $typesStatus<300 && is_array($typesRes)){
  foreach ($typesRes as $row){ if (!empty($row['name'])) $typeNames[] = $row['name']; }
}
// Fallback default labels if table empty
if (count($typeNames)===0){ $typeNames = ['Cattle','Goat','Pigs']; }

// Fetch sold listings; filter in PHP to avoid complex PostgREST params here
[$soldRes,$soldStatus,$soldErr] = sb_rest('GET','soldlivestocklisting',['select'=>'livestock_type,price,created']);
$series = [];
foreach ($typeNames as $tn){ $series[$tn] = array_fill(0, count($monthKeys), 0); }
if ($soldStatus>=200 && $soldStatus<300 && is_array($soldRes)){
  foreach ($soldRes as $r){
    $lt = $r['livestock_type'] ?? null;
    $price = (float)($r['price'] ?? 0);
    $created = isset($r['created']) ? substr($r['created'],0,7) : null; // YYYY-MM
    if (!$lt || !$created) continue;
    $idx = array_search($created, $monthKeys, true);
    if ($idx===false) continue;
    if (!isset($series[$lt])) continue;
    $series[$lt][$idx] += $price;
  }
}

// Build datasets with colors
$palette = ['#8B4513','#16a34a','#ec4899','#2563eb','#f59e0b','#10b981','#ef4444','#6b7280'];
$datasets = [];
foreach ($typeNames as $i=>$tn){
  $datasets[] = [
    'label' => $tn,
    'data' => $series[$tn],
    'borderColor' => $palette[$i % count($palette)],
    'backgroundColor' => 'transparent',
    'tension' => 0.3,
    'spanGaps' => true,
    'pointRadius' => 0
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/dashboard.css">
    </head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="brand">Dashboard</div>
            <form class="search" method="get" action="#">
                <input type="search" name="q" placeholder="Search" />
            </form>
        </div>
        <div class="nav-center" style="display:flex;gap:16px;align-items:center;">
            <a class="btn" href="pages/usermanagement.php" style="background:#4a5568;">Users</a>
            <a class="btn" href="pages/listingmanagement.php" style="background:#4a5568;">Listings</a>
            <a class="btn" href="pages/report_review.php" style="background:#4a5568;">Report Review & Penalty</a>
            <a class="btn" href="pages/price_management.php" style="background:#4a5568;">Price Management</a>
            <a class="btn" href="pages/analytics.php" style="background:#4a5568;">Analytics</a>
        </div>
        <div class="nav-right">
            <div class="greeting">hello <?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            <a class="btn" href="../logout.php">Logout</a>
            <a class="notify" href="#" aria-label="Notifications" title="Notifications" style="position:relative;">
                <span class="avatar">🔔</span>
                <span id="notifBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:999px;padding:0 6px;font-size:10px;line-height:16px;min-width:16px;text-align:center;">0</span>
            </a>
            <a class="profile" href="pages/profile.php" aria-label="Profile">
                <span class="avatar">👤</span>
            </a>
        </div>
    </nav>
    <div id="notifPane" style="display:none;position:fixed;top:56px;right:16px;width:300px;max-height:50vh;overflow:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 10px 20px rgba(0,0,0,.08);z-index:10000;">
        <div style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-weight:600;">Notifications (<span id=\"notifCount\">0</span>)</div>
        <div id="notifList" style="padding:8px 0;">
            <div style="padding:10px 12px;color:#6b7280;">No notifications</div>
        </div>
    </div>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>Admin Dashboard</h1>
            </div>
        </div>
        <div class="card">
            <p>Use this space to manage users, view system stats, and oversee platform content.</p>
        </div>

        <div class="card">
            <h3>Sales by Livestock Type</h3>
            <div class="chartbox"><canvas id="adminSalesChart"></canvas></div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <div id="admin-sales-data"
         data-labels='<?php echo json_encode($labels, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>'
         data-datasets='<?php echo json_encode($datasets, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>'></div>
    <script src="script/dashboard.js"></script>
    <script>
      (function(){
        var btn = document.querySelector('.notify');
        var pane = document.getElementById('notifPane');
        var badge = document.getElementById('notifBadge');
        var listEl = document.getElementById('notifList');
        var countEl = document.getElementById('notifCount');
        function render(list){
          var n = Array.isArray(list) ? list.length : 0;
          if (badge){ badge.textContent = String(n); badge.style.display = n>0 ? 'inline-block' : 'none'; }
          if (countEl){ countEl.textContent = String(n); }
          if (!listEl) return;
          listEl.innerHTML = '';
          if (n === 0){
            var empty = document.createElement('div');
            empty.style.cssText = 'padding:10px 12px;color:#6b7280;';
            empty.textContent = 'No notifications';
            listEl.appendChild(empty);
            return;
          }
          list.forEach(function(item){
            var row = document.createElement('div');
            row.style.cssText = 'padding:10px 12px;border-bottom:1px solid #f3f4f6;';
            row.textContent = item && item.text ? item.text : String(item);
            listEl.appendChild(row);
          });
        }
        window.updateNotifications = function(list){ render(list||[]); };
        if (btn){ btn.addEventListener('click', function(e){ e.preventDefault(); if (!pane) return; pane.style.display = (pane.style.display==='none'||pane.style.display==='') ? 'block' : 'none'; }); }
        document.addEventListener('click', function(e){ if (!pane || !btn) return; if (!pane.contains(e.target) && !btn.contains(e.target)) { pane.style.display = 'none'; } });
        render(window.NOTIFS || []);
      })();
    </script>
  </body>
</html>