<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$state = $_GET['state'] ?? '';
$code  = $_GET['code'] ?? '';
if (!$code || !$state || !isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $state){
  http_response_code(400);
  echo 'Invalid OAuth state or missing code';
  exit;
}
unset($_SESSION['google_oauth_state']);

$client_id = getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
$redirect_uri = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
if (!$client_id || !$client_secret || !$redirect_uri){
  http_response_code(500);
  echo 'Missing GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET/GOOGLE_REDIRECT_URI';
  exit;
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query([
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code',
  ]),
]);
$res = curl_exec($ch);
$code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
if (!($code_http>=200 && $code_http<300) || !$res){
  http_response_code(500);
  echo 'Token exchange failed';
  exit;
}
$data = json_decode($res, true);
if (!is_array($data) || empty($data['access_token'])){
  http_response_code(500);
  echo 'Invalid token response';
  exit;
}
$_SESSION['google_tokens'] = [
  'access_token' => $data['access_token'],
  'refresh_token' => $data['refresh_token'] ?? null,
  'scope' => $data['scope'] ?? '',
  'token_type' => $data['token_type'] ?? 'Bearer',
  'expiry' => time() + (int)($data['expires_in'] ?? 3600) - 60,
];
header('Location: ../dashboard.php');
