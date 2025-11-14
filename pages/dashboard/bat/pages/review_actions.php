<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

if (($_SESSION['role'] ?? '') !== 'bat'){
  http_response_code(302);
  header('Location: ../dashboard.php');
  exit;
}
$bat_id = $_SESSION['user_id'] ?? null;
if (!$bat_id){
  http_response_code(302);
  header('Location: ../dashboard.php');
  exit;
}

$action = $_POST['action'] ?? '';
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
if (!$listing_id || !in_array($action, ['approve','deny'], true)){
  http_response_code(302);
  header('Location: review_listings.php');
  exit;
}

[$ires,$istatus,$ierr] = sb_rest('GET','reviewlivestocklisting',[
  'select'=>'listing_id,seller_id,livestock_type,breed,address,age,weight,price,created',
  'listing_id'=>'eq.'.$listing_id,
  'limit'=>1
]);
if (!($istatus>=200 && $istatus<300) || !is_array($ires) || !isset($ires[0])){
  $_SESSION['flash_error'] = 'Listing not found or cannot load.';
  header('Location: review_listings.php');
  exit;
}
$rec = $ires[0];

if ($action === 'approve'){
  $payload = [[
    'seller_id'=>(int)$rec['seller_id'],
    'livestock_type'=>$rec['livestock_type'],
    'breed'=>$rec['breed'],
    'address'=>$rec['address'],
    'age'=>(int)$rec['age'],
    'weight'=>(float)$rec['weight'],
    'price'=>(float)$rec['price'],
    'bat_id'=>(int)$bat_id,
    'status'=>'Verified'
  ]];
  [$ar,$as,$ae] = sb_rest('POST','livestocklisting',[], $payload, ['Prefer: return=representation']);
  if (!($as>=200 && $as<300)){
    $_SESSION['flash_error'] = 'Approve failed.';
  } else {
    $_SESSION['flash_message'] = 'Listing approved.';
  }
} else if ($action === 'deny'){
  $payload = [[
    'seller_id'=>(int)$rec['seller_id'],
    'livestock_type'=>$rec['livestock_type'],
    'breed'=>$rec['breed'],
    'address'=>$rec['address'],
    'age'=>(int)$rec['age'],
    'weight'=>(float)$rec['weight'],
    'price'=>(float)$rec['price'],
    'bat_id'=>(int)$bat_id,
    'status'=>'Denied'
  ]];
  [$dr,$ds,$de] = sb_rest('POST','deniedlivestocklisting',[], $payload, ['Prefer: return=representation']);
  if (!($ds>=200 && $ds<300)){
    $_SESSION['flash_error'] = 'Deny failed.';
  } else {
    $_SESSION['flash_message'] = 'Listing denied.';
  }
}

header('Location: review_listings.php');
