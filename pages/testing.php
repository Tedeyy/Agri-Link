<?php
session_start();

function loadEnv($path)
{
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            if (str_starts_with($val, '"') && str_ends_with($val, '"')) {
                $val = substr($val, 1, -1);
            }
            $env[$key] = $val;
        }
    }
    return $env;
}

$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
$env = loadEnv($projectRoot . DIRECTORY_SEPARATOR . '.env');

$anonKey = $env['SUPABASE_ANON_KEY'] ?? '';
$dbHost = $env['DB_HOST'] ?? '';
$projectRef = '';
if ($dbHost && str_contains($dbHost, '.supabase.co')) {
    $projectRef = explode('.supabase.co', $dbHost)[0];
    $projectRef = preg_replace('/^db\./', '', $projectRef); // remove leading db.
}
$baseUrl = $projectRef ? ("https://" . $projectRef . ".supabase.co") : '';

$errors = [];
$messages = [];

function supabaseRequest($method, $url, $anonKey, $payload = null, $extraHeaders = [])
{
    $ch = curl_init();
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $anonKey,
        'Authorization: Bearer ' . $anonKey,
    ];
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    if (!is_null($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    return [$httpcode, $response, $curlErr];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$anonKey || !$baseUrl) {
        $errors[] = 'Supabase configuration missing. Please set SUPABASE_ANON_KEY and DB_HOST in .env';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'register') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            if (!$errors) {
                // Sign up via Supabase Auth (triggers email verification if enabled)
                [$code, $body, $err] = supabaseRequest(
                    'POST',
                    $baseUrl . '/auth/v1/signup',
                    $anonKey,
                    [
                        'email' => $email,
                        'password' => $password,
                    ]
                );
                if ($err) {
                    $errors[] = 'Network error during signup: ' . htmlspecialchars($err);
                } else if ($code >= 400) {
                    $errors[] = 'Signup failed (' . $code . '): ' . $body;
                } else {
                    // Insert into testing_auth via REST with hashed password
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    [$code2, $body2, $err2] = supabaseRequest(
                        'POST',
                        $baseUrl . '/rest/v1/testing_auth',
                        $anonKey,
                        [
                            [
                                'email' => $email,
                                'password' => $hashed,
                            ]
                        ],
                        [
                            'Prefer: return=minimal',
                        ]
                    );
                    if ($err2) {
                        $messages[] = 'Registered. Warning: could not store in testing_auth (network).';
                    } else if ($code2 >= 400) {
                        $messages[] = 'Registered. Warning: could not store in testing_auth (HTTP ' . $code2 . ').';
                    } else {
                        $messages[] = 'Registered successfully. Please check your email for verification.';
                    }
                }
            }
        }
        if (isset($_POST['action']) && $_POST['action'] === 'login') {
            $email = trim($_POST['email_login'] ?? '');
            $password = $_POST['password_login'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }
            if (!$errors) {
                [$code, $body, $err] = supabaseRequest(
                    'POST',
                    $baseUrl . '/auth/v1/token?grant_type=password',
                    $anonKey,
                    [
                        'email' => $email,
                        'password' => $password,
                    ]
                );
                if ($err) {
                    $errors[] = 'Network error during login: ' . htmlspecialchars($err);
                } else if ($code >= 400) {
                    $errors[] = 'Login failed (' . $code . '): ' . $body;
                } else {
                    $data = json_decode($body, true);
                    $_SESSION['supabase_access_token'] = $data['access_token'] ?? null;
                    $_SESSION['supabase_user'] = $data['user'] ?? null;
                    header('Location: contact.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Testing Auth</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#f6f7fb; margin:0; padding:24px; }
    .container { max-width: 480px; margin: 0 auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:24px; box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
    h1 { margin:0 0 16px; font-size: 22px; }
    h2 { margin:24px 0 12px; font-size: 18px; }
    form { display:flex; flex-direction:column; gap:12px; }
    label { font-weight:600; font-size: 14px; }
    input { padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; }
    button { padding:10px 12px; background:#111827; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
    .muted { color:#6b7280; font-size:12px; }
    .sep { height:1px; background:#e5e7eb; margin:20px 0; }
    .error { background:#fef2f2; color:#991b1b; padding:10px; border:1px solid #fecaca; border-radius:8px; }
    .msg { background:#ecfdf5; color:#065f46; padding:10px; border:1px solid #a7f3d0; border-radius:8px; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Testing Auth</h1>
    <?php if ($errors): ?>
      <div class="error">
        <?php foreach ($errors as $e): ?>
          <div><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <div class="msg">
        <?php foreach ($messages as $m): ?>
          <div><?php echo htmlspecialchars($m); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2>Registration</h2>
    <form method="post">
      <input type="hidden" name="action" value="register" />
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required />
      </div>
      <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required />
      </div>
      <button type="submit">Register</button>
      <div class="muted">A verification email will be sent to this address.</div>
    </form>

    <div class="sep"></div>

    <h2>Login</h2>
    <form method="post">
      <input type="hidden" name="action" value="login" />
      <div>
        <label for="email_login">Email</label>
        <input type="email" id="email_login" name="email_login" required />
      </div>
      <div>
        <label for="password_login">Password</label>
        <input type="password" id="password_login" name="password_login" required />
      </div>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
