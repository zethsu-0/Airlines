<?php
// login.php - Finalized JSON login handler for accounts + admins tables
// Now using ONLY the airlines DB (accounts migrated)

// Force JSON response by default (but we may redirect for non-AJAX requests)
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();

// If your frontend is on a different origin, set these appropriately.
// Replace https://example.com with your frontend origin and uncomment.
// header('Access-Control-Allow-Origin: https://example.com');
// header('Access-Control-Allow-Credentials: true');
// header('Access-Control-Allow-Methods: POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Respond to preflight (if you enable CORS above)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // If CORS is enabled above, just exit for preflight
    http_response_code(204);
    exit;
}

// Convert fatal errors to JSON so fetch() never breaks
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err !== null) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            'success' => false,
            'error'   => 'Server error.',
            'debug'   => [
                'type'    => $err['type'] ?? null,
                'message' => $err['message'] ?? null,
                'file'    => $err['file'] ?? null,
                'line'    => $err['line'] ?? null
            ]
        ]);
    }
});

// Important: set cookie params BEFORE session_start()
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// PHP >= 7.3 accepts an array for session_set_cookie_params
session_set_cookie_params([
    'lifetime' => 0,          // session cookie (until browser close)
    'path'     => '/',        // site-wide
    'domain'   => '',         // default (current host). Set explicitly if needed.
    'secure'   => $secure,    // only send over HTTPS when possible
    'httponly' => true,       // not accessible to JS
    'samesite' => 'Lax'       // 'Lax' works for same-site forms; use 'None' and Secure for cross-site
]);

if (session_status() === PHP_SESSION_NONE) session_start();

// Helper for clean JSON response
function json_out($arr) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Detect whether client expects JSON (AJAX) or a normal browser form submit
function client_expects_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (stripos($accept, 'application/json') !== false) return true;
    if (strtolower($xhr) === 'xmlhttprequest') return true;
    return false;
}
$expectsJson = client_expects_json();

// Ensure POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // For non-JSON clients, send a redirect to login page; for JSON clients, return JSON error
    if ($expectsJson) {
        json_out(['success' => false, 'error' => 'Invalid request method (use POST).']);
    } else {
        while (ob_get_level()) ob_end_clean();
        header('Location: index.php');
        exit;
    }
}

// Load DB (airlines DB ONLY)
$db_path = __DIR__ . '/config/db_connect.php';
if (!file_exists($db_path)) {
    if ($expectsJson) json_out(['success' => false, 'error' => 'DB config missing.']);
    // Non-AJAX: redirect back to login with a generic message (you can improve this)
    while (ob_get_level()) ob_end_clean();
    header('Location: index.php');
    exit;
}
require_once $db_path;

// After migration, ensure $conn exists and is mysqli
if (!isset($conn) || !($conn instanceof mysqli)) {
    if ($expectsJson) json_out(['success' => false, 'error' => 'Database connection missing.']);
    while (ob_get_level()) ob_end_clean();
    header('Location: index.php');
    exit;
}

// Get POST inputs
$acc_id   = trim((string)($_POST['acc_id'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($acc_id === '' || $password === '') {
    if ($expectsJson) json_out(['success' => false, 'error' => 'Please enter account ID and password.']);
    // Non-AJAX: go back to login (optionally you can append a query param for error)
    while (ob_get_level()) ob_end_clean();
    header('Location: index.php');
    exit;
}

// Password verification logic
function verify_password_candidate($plain, $stored) {
    if ($stored === null) return false;
    // hashed passwords from password_hash() typically start with $
    if ($stored !== '' && $stored[0] === '$') {
        return password_verify($plain, $stored);
    }
    // fallback to exact match (constant-time)
    return hash_equals((string)$stored, $plain);
}

$account = null;
$errors  = [];

/* ---------------------------------------------------------
   1) CHECK ACCOUNTS TABLE (Students)
----------------------------------------------------------*/

$availCols = [];
$colCheck = @mysqli_prepare($conn, "SHOW COLUMNS FROM `accounts`");
if ($colCheck) {
    mysqli_stmt_execute($colCheck);
    $cols = mysqli_stmt_get_result($colCheck);
    while ($c = mysqli_fetch_assoc($cols)) $availCols[] = $c['Field'];
    mysqli_stmt_close($colCheck);
} else {
    $availCols = ['acc_id','acc_name','password','acc_role'];
}

$want = ['acc_id','acc_name'];
if (in_array('password_hash', $availCols)) $want[] = 'password_hash';
if (in_array('password',      $availCols)) $want[] = 'password';
if (in_array('acc_role',      $availCols)) $want[] = 'acc_role';

$sql = "SELECT " . implode(", ", array_map(fn($c)=>"`$c`", $want)) .
       " FROM `accounts` WHERE `acc_id` = ? LIMIT 1";

$stmt = @mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $acc_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        $stored = $row['password_hash'] ?? $row['password'] ?? null;

        if (verify_password_candidate($password, $stored)) {
            $account = [
                'source'   => 'accounts',
                'acc_id'   => $row['acc_id'],
                'acc_name' => $row['acc_name'],
                'role'     => $row['acc_role'] ?? 'student'
            ];
        }
    }
    mysqli_stmt_close($stmt);
} else {
    $errors[] = 'accounts prepare failed: ' . mysqli_error($conn);
}

/* ---------------------------------------------------------
   2) CHECK ADMINS TABLE (Admins + Super Admins)
----------------------------------------------------------*/

if (!$account) {
    $availAdmins = [];
    $stmtCols = @mysqli_prepare($conn, "SHOW COLUMNS FROM `admins`");
    if ($stmtCols) {
        mysqli_stmt_execute($stmtCols);
        $rcols = mysqli_stmt_get_result($stmtCols);
        while ($c = mysqli_fetch_assoc($rcols)) $availAdmins[] = $c['Field'];
        mysqli_stmt_close($stmtCols);
    } else {
        $availAdmins = ['acc_id','name','password','role'];
    }

    $wantA = ['acc_id','name'];
    if (in_array('password_hash', $availAdmins)) $wantA[] = 'password_hash';
    if (in_array('password',      $availAdmins)) $wantA[] = 'password';
    if (in_array('role',          $availAdmins)) $wantA[] = 'role';

    $sql2 = "SELECT " . implode(", ", array_map(fn($c)=>"`$c`", $wantA)) .
            " FROM `admins` WHERE `acc_id` = ? LIMIT 1";

    $stmt2 = @mysqli_prepare($conn, $sql2);

    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 's', $acc_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);

        if ($row = mysqli_fetch_assoc($res2)) {
            $stored = $row['password_hash'] ?? $row['password'] ?? null;

            if (verify_password_candidate($password, $stored)) {

                // Try to fetch nicer display name from accounts (optional)
                $displayName = $row['name'] ?? $acc_id;
                $roleRawAdmin = $row['role'] ?? null;

                $q = @mysqli_prepare($conn,
                    "SELECT acc_name, acc_role FROM accounts WHERE acc_id = ? LIMIT 1"
                );

                if ($q) {
                    mysqli_stmt_bind_param($q, 's', $acc_id);
                    mysqli_stmt_execute($q);
                    $r = mysqli_stmt_get_result($q);

                    if ($u = mysqli_fetch_assoc($r)) {
                        if (!empty($u['acc_name'])) $displayName = $u['acc_name'];
                        if (!empty($u['acc_role'])) $roleRawAdmin = $u['acc_role'];
                    }

                    mysqli_stmt_close($q);
                }

                $account = [
                    'source'   => 'admins',
                    'acc_id'   => $acc_id,
                    'acc_name' => $displayName,
                    'role'     => $roleRawAdmin
                ];
            }
        }

        mysqli_stmt_close($stmt2);
    } else {
        $errors[] = 'admins prepare failed: ' . mysqli_error($conn);
    }
}

/* ---------------------------------------------------------
   LOGIN FAILED?
----------------------------------------------------------*/

if (!$account) {
    $msg = 'Invalid account ID or password.';
    if (!empty($errors)) $msg .= ' (DB: '.implode(' | ', $errors).')';
    if ($expectsJson) {
        json_out(['success' => false, 'error' => $msg]);
    } else {
        // Non-AJAX: redirect back to login (you can append error via query if desired)
        while (ob_get_level()) ob_end_clean();
        // For security, avoid echoing DB errors to user; you may log them server-side instead
        header('Location: index.php');
        exit;
    }
}

/* ---------------------------------------------------------
   NORMALIZE ROLE
----------------------------------------------------------*/

$roleRaw = strtolower((string)($account['role'] ?? ''));
$roleClean = str_replace([' ', '_', '-'], '', $roleRaw);

if ($account['source'] === 'admins') {
    if (strpos($roleClean, 'superadmin') !== false) {
        $roleNorm = 'super_admin';
    } elseif (strpos($roleClean, 'admin') !== false ||
              strpos($roleClean, 'instructor') !== false) {
        $roleNorm = 'admin';
    } else {
        $roleNorm = 'admin';
    }
} else {
    $roleNorm = 'student';
}

/* ---------------------------------------------------------
   SET SESSION + SUCCESS
----------------------------------------------------------*/

$_SESSION['acc_id']   = $account['acc_id'];
$_SESSION['acc_name'] = $account['acc_name'];
$_SESSION['role']     = $roleNorm;
$_SESSION['acc_role']  = $roleNorm;  

session_regenerate_id(true);

// Build redirect target (client will perform redirect)
$redirect =
    ($roleNorm === 'super_admin') ? 'super_admin.php' :
    ($roleNorm === 'admin'       ? 'admin.php'       :
                                   'index.php');

// If client expects JSON (AJAX), return JSON as before
if ($expectsJson) {
    json_out([
        'success'  => true,
        'role'     => $roleNorm,
        'redirect' => $redirect
    ]);
}

// Otherwise perform a server-side redirect so regular form submits work
while (ob_get_level()) ob_end_clean();
// Use 303 See Other after POST to redirect to GET page
header('HTTP/1.1 303 See Other');
header('Location: ' . $redirect);
exit;
