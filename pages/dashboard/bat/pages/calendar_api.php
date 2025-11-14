<?php
session_start();
header('Content-Type: application/json');

function json_fail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function json_ok($extra=[]){ echo json_encode(array_merge(['ok'=>true], $extra)); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) json_fail('Missing action');

function have_tokens(){ return isset($_SESSION['google_tokens']['access_token']); }
function access_token(){ return $_SESSION['google_tokens']['access_token'] ?? null; }
function token_expired(){ return !isset($_SESSION['google_tokens']['expiry']) || time() >= (int)$_SESSION['google_tokens']['expiry']; }
function refresh_token_if_needed(){
  if (!token_expired()) return true;
  $rt = $_SESSION['google_tokens']['refresh_token'] ?? null;
  if (!$rt) return false;
  $client_id = getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
  $client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
  if (!$client_id || !$client_secret) return false;
  $ch = curl_init('https://oauth2.googleapis.com/token');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
      'client_id' => $client_id,
      'client_secret' => $client_secret,
      'refresh_token' => $rt,
      'grant_type' => 'refresh_token',
    ]),
  ]);
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if (!($code>=200 && $code<300) || !$res) return false;
  $data = json_decode($res, true);
  if (!is_array($data) || empty($data['access_token'])) return false;
  $_SESSION['google_tokens']['access_token'] = $data['access_token'];
  $_SESSION['google_tokens']['expiry'] = time() + (int)($data['expires_in'] ?? 3600) - 60;
  return true;
}

function ensure_auth(){ if (!have_tokens()) json_ok(['events'=>[]]); if (!refresh_token_if_needed()) json_fail('Not connected',401); }

function gcal_request($method, $url, $body=null){
  $headers = [ 'Authorization: Bearer '.access_token(), 'Content-Type: application/json' ];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
  ]);
  if ($body !== null){ curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $res];
}

function normalize_time($v){ if (!$v) return null; if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v; $t=strtotime($v); return $t?date('c',$t):$v; }

$calendarId = urlencode('primary');

if ($action === 'disconnect'){
  unset($_SESSION['google_tokens']);
  json_ok();
}

if ($action === 'list'){
  ensure_auth();
  $start = $_GET['start'] ?? null;
  $end = $_GET['end'] ?? null;
  $params = [
    'timeMin' => normalize_time($start),
    'timeMax' => normalize_time($end),
    'singleEvents' => 'true',
    'orderBy' => 'startTime',
  ];
  $qs = http_build_query(array_filter($params));
  [$code,$res] = gcal_request('GET', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events?'.$qs);
  if (!($code>=200 && $code<300)) json_ok(['events'=>[]]);
  $data = json_decode($res, true);
  $items = $data['items'] ?? [];
  $events = [];
  foreach ($items as $it){
    $start = $it['start']['dateTime'] ?? ($it['start']['date'] ?? null);
    $end = $it['end']['dateTime'] ?? ($it['end']['date'] ?? null);
    $events[] = [
      'id' => $it['id'] ?? '',
      'summary' => $it['summary'] ?? '(no title)',
      'start' => $start,
      'end' => $end,
      'allDay' => isset($it['start']['date'])
    ];
  }
  json_ok(['events'=>$events]);
}

$input = null;
if (in_array($action, ['create','update'])){
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input)) $input = [];
}

if ($action === 'create'){
  ensure_auth();
  $summary = $input['summary'] ?? 'Untitled';
  $start = normalize_time($input['start'] ?? null) ?: date('c');
  $end = normalize_time($input['end'] ?? null) ?: $start;
  $isAllDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start);
  $body = [ 'summary'=>$summary, 'start'=> $isAllDay? ['date'=>$start] : ['dateTime'=>$start], 'end'=> $isAllDay? ['date'=>$end] : ['dateTime'=>$end] ];
  [$code,$res] = gcal_request('POST', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events', $body);
  if (!($code>=200 && $code<300)) json_fail('Create failed', 500);
  json_ok();
}

if ($action === 'update'){
  ensure_auth();
  $id = $_GET['id'] ?? '';
  if (!$id) json_fail('Missing id');
  $body = [];
  if (isset($input['summary'])) $body['summary'] = $input['summary'];
  if (isset($input['start'])){ $s=normalize_time($input['start']); $body['start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)? ['date'=>$s] : ['dateTime'=>$s]; }
  if (isset($input['end'])){ $e=normalize_time($input['end']); $body['end'] = $e? (preg_match('/^\d{4}-\d{2}-\d{2}$/',$e)? ['date'=>$e] : ['dateTime'=>$e]) : null; }
  [$code,$res] = gcal_request('PATCH', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events/'.urlencode($id), $body);
  if (!($code>=200 && $code<300)) json_fail('Update failed', 500);
  json_ok();
}

if ($action === 'delete'){
  ensure_auth();
  $id = $_GET['id'] ?? '';
  if (!$id) json_fail('Missing id');
  [$code,$res] = gcal_request('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events/'.urlencode($id));
  if (!($code>=200 && $code<300)) json_fail('Delete failed', 500);
  json_ok();
}

if ($action === 'done'){
  ensure_auth();
  $id = $_GET['id'] ?? '';
  if (!$id) json_fail('Missing id');
  [$code,$res] = gcal_request('GET', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events/'.urlencode($id));
  if (!($code>=200 && $code<300)) json_fail('Fetch failed', 500);
  $item = json_decode($res, true);
  $title = $item['summary'] ?? '';
  if (strpos($title, '[Done]') === false){ $title = '[Done] '.$title; }
  [$uc,$ur] = gcal_request('PATCH', 'https://www.googleapis.com/calendar/v3/calendars/'.$calendarId.'/events/'.urlencode($id), ['summary'=>$title]);
  if (!($uc>=200 && $uc<300)) json_fail('Done failed', 500);
  json_ok();
}

json_fail('Unknown action');
