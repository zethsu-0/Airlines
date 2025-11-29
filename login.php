<?php
// login.php (DEBUG-SAFE VERSION) -- remove/adjust debug features when finished

session_start();
header('Content-Type: application/json; charset=utf-8');

// Keep display_errors off so JSON stays clean in responses; enable only while debugging.
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
ini_set('display_errors', 0);
error_reporting(0);

// Include DB connector - must set $acc_conn (mysqli)
@include('config/db_connect.php'); // use @ to avoid immediate warning; we'll check below

// Safe dbg() implementation: logs to server error_log when DEBUG_MODE true.
// This prevents fatal "undefined function" errors if dbg() wasn't defined elsewhere.
if (!function_exists('dbg')) {
    function dbg($msg) {
        // Only write to error_log when DEBUG_MODE is true to avoid filling logs in production
        if (!empty($GLOBALS['DEBUG_MODE'])) {
            error_log('[login.php DEBUG] ' . $msg);
        }
    }
}

$response = ['success' => false, 'errors' => []];

// Ensure acc_conn exists and is a mysqli instance
if (!isset($acc_conn) || !($acc_conn instanceof mysqli)) {
    // Include may have failed or config file didn't create $acc_conn
    $response['errors']['general'] = 'Server configuration error (DB connection not available).';
    // Write helpful debug message to server log
    error_log('[login.php] DB connection missing or invalid. Check config/db_connect.php for errors.');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['general' => 'Invalid request (POST required)']]);
    exit;
}

// Determine debug mode early so dbg() will work
$debug = isset($_POST['debug']) && $_POST['debug'] === '1';
$GLOBALS['DEBUG_MODE'] = $debug; // make available to dbg()

$acc_id = trim((string)($_POST['acc_id'] ?? ''));
$password = $_POST['password'] ?? '';

dbg("Received login attempt for acc_id=" . $acc_id . " (debug=" . ($debug?1:0) . ")");

if ($acc_id === '') {
    $response['errors']['acc_id'] = 'Please enter Account ID';
}
if ($password === '') {
    $response['errors']['password'] = 'Please enter password';
}
if (!empty($response['errors'])) {
    if ($debug) dbg("Validation errors: " . json_encode($response['errors']));
    echo json_encode($response);
    exit;
}

// Prepare & execute
$sql = "SELECT acc_id, acc_name, password FROM accounts WHERE acc_id = ? LIMIT 1";
$stmt = mysqli_prepare($acc_conn, $sql);
if ($stmt) {
    // If acc_id is numeric in DB, you may change "s" to "i"
    mysqli_stmt_bind_param($stmt, "s", $acc_id);
    $exec = mysqli_stmt_execute($stmt);
    if (!$exec) {
        $err = mysqli_error($acc_conn);
        dbg("Statement execute error: " . $err);
        $response['errors']['general'] = 'Database error (exec).';
        echo json_encode($response);
        mysqli_stmt_close($stmt);
        exit;
    }

    mysqli_stmt_store_result($stmt); 
    $num = mysqli_stmt_num_rows($stmt);
    dbg("Statement executed. num_rows=" . $num);

    $db_acc_id = $db_acc_name = $db_password_hash = null;
    mysqli_stmt_bind_result($stmt, $db_acc_id, $db_acc_name, $db_password_hash);
    $fetched = mysqli_stmt_fetch($stmt);
    dbg("mysqli_stmt_fetch returned: " . ($fetched ? 'true' : 'false'));

    if ($fetched) {
        $pw_len = is_string($db_password_hash) ? strlen($db_password_hash) : 0;
        dbg("Found account: acc_id={$db_acc_id}, acc_name={$db_acc_name}, password_length={$pw_len}");

        $is_valid = false;

        // check if hash-like
        $pw_info = password_get_info($db_password_hash);
        $is_hash = ($pw_info && isset($pw_info['algo']) && $pw_info['algo'] !== 0);
        dbg("password_get_info algo=" . ($pw_info['algo'] ?? 'NULL') . ", is_hash=" . ($is_hash?1:0));

        if ($is_hash) {
            $verify = password_verify($password, $db_password_hash);
            dbg("password_verify result: " . ($verify?1:0));
            if ($verify) $is_valid = true;
        } else {
            // compare raw (trim both sides to avoid trailing spaces)
            $cmp = ($password === $db_password_hash);
            dbg("plain compare result: " . ($cmp?1:0) . " (len input=" . strlen($password) . ", len stored=" . $pw_len . ")");
            if ($cmp) $is_valid = true;
        }

        if ($is_valid) {
            session_regenerate_id(true);
            $_SESSION['acc_id'] = $db_acc_id;
            $_SESSION['acc_name'] = $db_acc_name;
            $response['success'] = true;
            $response['user'] = ['acc_id' => $db_acc_id, 'acc_name' => $db_acc_name];
            dbg("Login success for acc_id={$db_acc_id}");
        } else {
            $response['errors']['general'] = 'Incorrect Account ID or password.';
            dbg("Login failed: incorrect password for acc_id={$acc_id}");
        }
    } else {
        $response['errors']['general'] = 'Incorrect Account ID or password.';
        dbg("No account found for acc_id={$acc_id}");
    }

    mysqli_stmt_close($stmt);
} else {
    $err = mysqli_error($acc_conn);
    dbg("Prepare failed: " . $err);
    $response['errors']['general'] = 'Database error (prepare).';
    echo json_encode($response);
    exit;
}

if ($debug) {
    // include helpful debug keys in JSON for development only
    $response['_debug'] = [
        'received_acc_id' => $acc_id,
        // careful: we do not include password hash
    ];
}

echo json_encode($response);
exit;