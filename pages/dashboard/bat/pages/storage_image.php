<?php
session_start();
require_once __DIR__ . '/../../../authentication/lib/supabase_client.php';

$path = isset($_GET['path']) ? $_GET['path'] : null;
if (!$path){ http_response_code(400); echo 'Missing path'; exit; }

$base = function_exists('sb_base_url') ? sb_base_url() : (getenv('SUPABASE_URL') ?: '');
$service = function_exists('sb_env') ? (sb_env('SUPABASE_SERVICE_ROLE_KEY') ?: '') : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
$auth = $_SESSION['supa_access_token'] ?? ($service ?: (getenv('SUPABASE_KEY') ?: ''));
$url = rtrim($base,'/').'/storage/v1/object/'.ltrim($path,'/');

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'apikey: '.(function_exists('sb_anon_key')? sb_anon_key() : (getenv('SUPABASE_KEY') ?: '')),
    'Authorization: Bearer '.$auth,
  ],
]);
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$err = curl_error($ch);
curl_close($ch);

if (!($code>=200 && $code<300) || $data===false){
  http_response_code(404);
  echo 'Not found';
  exit;
}
if ($ct){ header('Content-Type: '.$ct); } else { header('Content-Type: image/jpeg'); }
echo $data;
