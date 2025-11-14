<?php
session_start();

$client_id = getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$redirect_uri = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
$scope = 'https://www.googleapis.com/auth/calendar';

if (!$client_id || !$redirect_uri){
  http_response_code(500);
  echo 'Missing GOOGLE_CLIENT_ID or GOOGLE_REDIRECT_URI in environment.';
  exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = [
  'client_id' => $client_id,
  'redirect_uri' => $redirect_uri,
  'response_type' => 'code',
  'scope' => $scope,
  'access_type' => 'offline',
  'include_granted_scopes' => 'true',
  'prompt' => 'consent',
  'state' => $state,
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
header('Location: '.$url);
exit;
