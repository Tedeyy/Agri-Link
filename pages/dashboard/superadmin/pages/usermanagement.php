<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

// Sign and fetch supporting document URL for admin/bat
if (isset($_GET['doc']) && $_GET['doc'] === '1'){
  header('Content-Type: application/json');
  $role = $_GET['role'] ?? '';
  $fname = $_GET['fname'] ?? '';
  $mname = $_GET['mname'] ?? '';
  $lname = $_GET['lname'] ?? '';
  $email = $_GET['email'] ?? '';
  if (!in_array($role, ['admin','bat'], true)) { echo json_encode(['ok'=>false,'error'=>'invalid role']); exit; }
  $fullname = trim($fname.' '.($mname?:'').' '.$lname);
  $san = strtolower(preg_replace('/[^a-z0-9]+/i','_', $fullname));
  $san = trim($san, '_');
  $base = function_exists('sb_base_url') ? sb_base_url() : (getenv('SUPABASE_URL') ?: '');
  $key  = function_exists('sb_env') ? (sb_env('SUPABASE_SERVICE_ROLE_KEY') ?: '') : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: (getenv('SUPABASE_KEY') ?: ''));
  $objectName = null; $bucket = 'reviewusers';
  // Try reviewusers first
  $listUrl = rtrim($base,'/').'/storage/v1/object/list/reviewusers';
  $ch = curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL => $listUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key, 'Content-Type: application/json' ],
    CURLOPT_POSTFIELDS => json_encode(['prefix'=>($role.'/')])
  ]);
  $raw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($http>=200 && $http<300) {
    $items = json_decode($raw, true);
    if (is_array($items)){
      foreach ($items as $it){ $name = $it['name'] ?? ''; if ($name==='') continue; $noext = pathinfo($name, PATHINFO_FILENAME); if ($noext === $san){ $objectName = $role.'/'.$name; break; } }
    }
  }
  // Fallback to users bucket
  if (!$objectName){
    $listUrl2 = rtrim($base,'/').'/storage/v1/object/list/users';
    $ch2 = curl_init();
    curl_setopt_array($ch2,[
      CURLOPT_URL => $listUrl2,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key, 'Content-Type: application/json' ],
      CURLOPT_POSTFIELDS => json_encode(['prefix'=>($role.'/')])
    ]);
    $raw2 = curl_exec($ch2); $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
    if ($http2>=200 && $http2<300){
      $items2 = json_decode($raw2, true);
      if (is_array($items2)){
        foreach ($items2 as $it){ $name = $it['name'] ?? ''; if ($name==='') continue; $noext = pathinfo($name, PATHINFO_FILENAME); if ($noext === $san){ $objectName = $role.'/'.$name; $bucket='users'; break; } }
      }
    }
  }
  if (!$objectName){ echo json_encode(['ok'=>false,'error'=>'not found']); exit; }
  $signUrl = rtrim($base,'/').'/storage/v1/object/sign/'.rawurlencode($bucket).'/'.rawurlencode($objectName);
  $body = json_encode(['expiresIn'=>300]);
  $chs = curl_init();
  curl_setopt_array($chs,[
    CURLOPT_URL => $signUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key, 'Content-Type: application/json' ],
    CURLOPT_POSTFIELDS => $body
  ]);
  $signRaw = curl_exec($chs); $sh = curl_getinfo($chs, CURLINFO_HTTP_CODE); curl_close($chs);
  if (!($sh>=200 && $sh<300)) { echo json_encode(['ok'=>false,'error'=>'sign http '.$sh]); exit; }
  $sign = json_decode($signRaw, true);
  $signedUrl = isset($sign['signedURL']) ? $sign['signedURL'] : (isset($sign['signedUrl'])?$sign['signedUrl']:null);
  if (!$signedUrl){ echo json_encode(['ok'=>false,'error'=>'no signed url']); exit; }
  echo json_encode(['ok'=>true,'url'=> rtrim($base,'/').$signedUrl, 'name'=>$objectName]);
  exit;
}

// Approve or deny admin/bat
if (isset($_GET['decide'])){
  header('Content-Type: application/json');
  $action = $_GET['decide'];
  if (!in_array($action, ['approve','deny'], true)) { echo json_encode(['ok'=>false,'error'=>'invalid action']); exit; }
  $req = json_decode(file_get_contents('php://input'), true);
  if (!is_array($req)) { echo json_encode(['ok'=>false,'error'=>'invalid body']); exit; }
  $role = $req['role'] ?? '';
  $id = $req['id'] ?? null;
  $fname = $req['fname'] ?? '';
  $mname = $req['mname'] ?? '';
  $lname = $req['lname'] ?? '';
  $email = $req['email'] ?? '';
  if (!in_array($role, ['admin','bat'], true)) { echo json_encode(['ok'=>false,'error'=>'invalid role']); exit; }
  if (!is_numeric($id)) { echo json_encode(['ok'=>false,'error'=>'missing id']); exit; }
  $superadmin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['superadmin_id']) ? $_SESSION['superadmin_id'] : null);
  if (!$superadmin_id) { echo json_encode(['ok'=>false,'error'=>'No superadmin id in session']); exit; }

  $srcTable = $role === 'admin' ? 'reviewadmin' : 'preapprovalbat';
  $destTable = null;
  if ($action === 'approve'){
    $destTable = $role === 'admin' ? 'admin' : 'bat';
  }

  // Fetch source row
  [$rows,$st,$er] = sb_rest('GET', $srcTable, ['select'=>'*','user_id'=>'eq.'.$id]);
  if (!($st>=200 && $st<300 && is_array($rows) && count($rows)===1)){
    $detail = is_array($rows)? json_encode($rows) : (string)$rows;
    echo json_encode(['ok'=>false,'error'=>'source fetch failed (http '.$st.')','detail'=>$detail]); exit;
  }
  $row = $rows[0];

  // Build destination payload
  $payload = [];
  if ($action === 'approve'){
    if ($role === 'admin'){
      $payload = [[
        'user_fname' => $row['user_fname'] ?? '',
        'user_mname' => $row['user_mname'] ?? '',
        'user_lname' => $row['user_lname'] ?? '',
        'bdate' => $row['bdate'] ?? '',
        'contact' => $row['contact'] ?? '',
        'address' => $row['address'] ?? '',
        'email' => $row['email'] ?? '',
        'office' => $row['office'] ?? '',
        'role' => $row['role'] ?? '',
        'doctype' => $row['doctype'] ?? '',
        'docnum' => $row['docnum'] ?? '',
        'username' => $row['username'] ?? '',
        'password' => $row['password'] ?? '',
        'superadmin_id' => $superadmin_id
      ]];
    } else { // bat
      $payload = [[
        'user_fname' => $row['user_fname'] ?? '',
        'user_mname' => $row['user_mname'] ?? '',
        'user_lname' => $row['user_lname'] ?? '',
        'bdate' => $row['bdate'] ?? '',
        'contact' => $row['contact'] ?? '',
        'address' => $row['address'] ?? '',
        'email' => $row['email'] ?? '',
        'assigned_barangay' => $row['assigned_barangay'] ?? '',
        'doctype' => $row['doctype'] ?? '',
        'docnum' => $row['docnum'] ?? '',
        'username' => $row['username'] ?? '',
        'password' => $row['password'] ?? '',
        'admin_id' => $row['admin_id'] ?? null,
        'superadmin_id' => $superadmin_id
      ]];
    }
  }

  if ($action === 'approve'){
    [$ires,$ist,$ier] = sb_rest('POST', $destTable, [], $payload, ['Prefer: return=representation']);
    if (!($ist>=200 && $ist<300)){
      $detail = is_array($ires)? json_encode($ires) : (string)$ires;
      echo json_encode(['ok'=>false,'error'=>'insert failed (http '.$ist.')','detail'=>$detail]); exit;
    }
  }

  // Try to move image from reviewusers/<role>/<name> to users/<role>/<name> on approve
  $moved = false;
  if ($action === 'approve'){
    $base = function_exists('sb_base_url') ? sb_base_url() : (getenv('SUPABASE_URL') ?: '');
    $key  = function_exists('sb_env') ? (sb_env('SUPABASE_SERVICE_ROLE_KEY') ?: '') : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: (getenv('SUPABASE_KEY') ?: ''));
    $fullname = trim(($fname).' '.($mname?:'').' '.($lname));
    $san = strtolower(preg_replace('/[^a-z0-9]+/i','_', $fullname));
    $san = trim($san, '_');
    $listUrl = rtrim($base,'/').'/storage/v1/object/list/reviewusers';
    $objectName = null;
    $ch = curl_init();
    curl_setopt_array($ch,[
      CURLOPT_URL => $listUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key, 'Content-Type: application/json' ],
      CURLOPT_POSTFIELDS => json_encode(['prefix'=>$role.'/'])
    ]);
    $listRaw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($http>=200 && $http<300){
      $items = json_decode($listRaw, true);
      if (is_array($items)){
        foreach ($items as $it){ $name = $it['name'] ?? ''; if ($name==='') continue; $noext = pathinfo($name, PATHINFO_FILENAME); if ($noext === $san){ $objectName = $role.'/'.$name; break; } }
      }
    }
    if ($objectName){
      // Download source
      $srcUrl = rtrim($base,'/').'/storage/v1/object/reviewusers/'.rawurlencode($objectName);
      $chd = curl_init();
      curl_setopt_array($chd,[
        CURLOPT_URL => $srcUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key ]
      ]);
      $bytes = curl_exec($chd); $httpd = curl_getinfo($chd, CURLINFO_HTTP_CODE); curl_close($chd);
      if ($httpd>=200 && $httpd<300 && $bytes !== false){
        $destPath = $role.'/'.basename($objectName);
        $upUrl = rtrim($base,'/').'/storage/v1/object/users/'.$destPath;
        $chu = curl_init();
        curl_setopt_array($chu,[
          CURLOPT_URL => $upUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_CONNECTTIMEOUT => 10,
          CURLOPT_TIMEOUT => 120,
          CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key, 'Content-Type: application/octet-stream', 'x-upsert: true' ],
          CURLOPT_POSTFIELDS => $bytes
        ]);
        $upr = curl_exec($chu); $uph = curl_getinfo($chu, CURLINFO_HTTP_CODE); curl_close($chu);
        if ($uph>=200 && $uph<300){
          // Remove source
          $rmUrl = rtrim($base,'/').'/storage/v1/object/reviewusers/'.rawurlencode($objectName);
          $chr = curl_init();
          curl_setopt_array($chr,[
            CURLOPT_URL => $rmUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [ 'apikey: '.$key, 'Authorization: Bearer '.$key ]
          ]);
          $rmr = curl_exec($chr); $rmh = curl_getinfo($chr, CURLINFO_HTTP_CODE); curl_close($chr);
          $moved = ($rmh>=200 && $rmh<300);
        }
      }
    }
  }

  // Delete source row
  [$dr,$dh,$de] = sb_rest('DELETE', $srcTable, ['user_id'=>'eq.'.$id]);
  if (!($dh>=200 && $dh<300)){
    echo json_encode(['ok'=>false,'error'=>'cleanup failed (http '.$dh.')']); exit;
  }

  echo json_encode(['ok'=>true,'moved'=>$moved]);
  exit;
}

$bats = []; $admins = [];
[$tr,$ts,$te] = sb_rest('GET','preapprovalbat',['select'=>'*']); if ($ts>=200 && $ts<300 && is_array($tr)) $bats=$tr;
[$ar,$as,$ae] = sb_rest('GET','reviewadmin',['select'=>'*']); if ($as>=200 && $as<300 && is_array($ar)) $admins=$ar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management (Superadmin)</title>
  <link rel="stylesheet" href="style/usermanagement.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f8fafc;color:#111827}</style>
</head>
<body>
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
      <h2 style="margin:0">User Management</h2>
      <a class="btn" href="../dashboard.php">Back to Dashboard</a>
    </div>
    <div class="grid" style="margin-top:12px">
      <div class="card">
        <h3>BAT Pending (preapprovalbat)</h3>
        <div class="table-wrap">
          <table aria-label="BAT Pending">
            <thead><tr><th>Full name</th><th>Address</th><th>Email</th><th>Assigned Barangay</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($bats as $t): $full=trim(($t['user_fname']??'').' '.($t['user_mname']??'').' '.($t['user_lname']??'')); ?>
              <tr data-id="<?php echo (int)($t['user_id']??0); ?>" data-role="bat">
                <td><?php echo htmlspecialchars($full,ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($t['address']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($t['email']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($t['assigned_barangay']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td class="actions"><button class="btn show" data-role="bat" data-id="<?php echo (int)($t['user_id']??0); ?>" data-json='<?php echo json_encode($t, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>'>Show</button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h3>Admins Pending (reviewadmin)</h3>
        <div class="table-wrap">
          <table aria-label="Admins Pending">
            <thead><tr><th>Full name</th><th>Email</th><th>Office</th><th>Role</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): $full=trim(($a['user_fname']??'').' '.($a['user_mname']??'').' '.($a['user_lname']??'')); ?>
              <tr data-id="<?php echo (int)($a['user_id']??0); ?>" data-role="admin">
                <td><?php echo htmlspecialchars($full,ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($a['email']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($a['office']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($a['role']??'',ENT_QUOTES,'UTF-8'); ?></td>
                <td class="actions"><button class="btn show" data-role="admin" data-id="<?php echo (int)($a['user_id']??0); ?>" data-json='<?php echo json_encode($a, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>'>Show</button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div id="detailModal" class="modal" role="dialog" aria-modal="true" aria-label="User details">
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h3 id="modalTitle" style="margin:0">Details</h3>
        <button id="closeModal" class="btn secondary">Close</button>
      </div>
      <div id="detailBody"></div>
      <div class="doc">
        <div id="docStatus" style="color:#6b7280;font-size:14px">Loading document...</div>
        <div id="docPreview" style="margin-top:8px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:12px">
        <div style="display:flex;gap:8px">
          <button id="approveBtn" class="btn success">Approve</button>
          <button id="denyBtn" class="btn danger">Deny</button>
        </div>
        <div id="actionStatus" style="font-size:14px;color:#374151"></div>
      </div>
    </div>
  </div>

  <script src="script/usermanagement.js"></script>
</body>
</html>

