<?php
// login.php - Finalized JSON login handler for accounts + admins tables
// Works with your config/db_connect.php ($acc_conn)
// Safe against fatal errors, missing columns, mixed roles, plain or hashed passwords.

// Force JSON response
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0); // hide fatal errors from output
error_reporting(E_ALL);

// Capture output to avoid HTML leaking into JSON
ob_start();

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

if (session_status() === PHP_SESSION_NONE) session_start();

// Helper to return JSON cleanly
function json_out($arr) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'error' => 'Invalid request method (use POST).']);
}

// Load DB
$db_path = __DIR__ . '/config/db_connect.php';
if (!file_exists($db_path)) json_out(['success' => false, 'error' => 'DB config missing.']);
require_once $db_path;

// Your config uses $acc_conn (mysqli procedural)
if (!isset($acc_conn) || !($acc_conn instanceof mysqli)) {
    if (isset($conn) && ($conn instanceof mysqli)) {
        $acc_conn = $conn;
    } else {
        json_out(['success'=>false,'error'=>'Database connection missing.']);
    }
}

// Read inputs
$acc_id   = trim($_POST['acc_id'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($acc_id === '' || $password === '') {
    json_out(['success'=>false,'error'=>'Please enter account ID and password.']);
}

// Password verification helper
function verify_password_candidate($plain, $stored) {
    if ($stored === null) return false;
    if ($stored !== '' && $stored[0] === '$') {
        return password_verify($plain, $stored); // hashed pw
    }
    return hash_equals((string)$stored, $plain); // plain pw
}

$account = null;
$errors  = [];

/* ---------------------------------------------------------
   1) CHECK ACCOUNTS TABLE (STUDENTS)
----------------------------------------------------------*/

$availCols = [];
$colCheck = @mysqli_prepare($acc_conn, "SHOW COLUMNS FROM `accounts`");
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

$stmt = @mysqli_prepare($acc_conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $acc_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        $stored = $row['password_hash'] ?? $row['password'] ?? null;
        if (verify_password_candidate($password, $stored)) {
            $account = [
                'source'  => 'accounts',
                'acc_id'  => $row['acc_id'],
                'acc_name'=> $row['acc_name'],
                'role'    => $row['acc_role'] ?? 'student'
            ];
        }
    }

    mysqli_stmt_close($stmt);
} else {
    $errors[] = 'accounts prepare failed: ' . mysqli_error($acc_conn);
}

/* ---------------------------------------------------------
   2) CHECK ADMINS TABLE (INSTRUCTORS + SUPER ADMINS)
----------------------------------------------------------*/

if (!$account) {
    $availAdmins = [];
    $stmtCols = @mysqli_prepare($acc_conn, "SHOW COLUMNS FROM `admins`");
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

    $stmt2 = @mysqli_prepare($acc_conn, $sql2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 's', $acc_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);

        if ($row = mysqli_fetch_assoc($res2)) {
            $stored = $row['password_hash'] ?? $row['password'] ?? null;
            if (verify_password_candidate($password, $stored)) {

                // Try to load friendlier name from accounts table (optional)
                $displayName = $row['name'] ?? $acc_id;
                $roleRawAdmin = $row['role'] ?? null;

                $q = @mysqli_prepare($acc_conn,
                    "SELECT acc_name, acc_role FROM accounts WHERE acc_id = ? LIMIT 1"
                );
                if ($q) {
                    mysqli_stmt_bind_param($q, 's', $acc_id);
                    mysqli_stmt_execute($q);
                    $r = mysqli_stmt_get_result($q);
                    if ($u = mysqli_fetch_assoc($r)) {
                        if (!empty($u['acc_name'])) $displayName = $u['acc_name'];
                        if (!empty($u['acc_role'])) $roleRawAdmin = $u['acc_role']; // fallback
                    }
                    mysqli_stmt_close($q);
                }

                $account = [
                    'source'   => 'admins',
                    'acc_id'   => $acc_id,
                    'acc_name' => $displayName,
                    'role'     => $roleRawAdmin // will be normalized below
                ];
            }
        }

        mysqli_stmt_close($stmt2);
    } else {
        $errors[] = 'admins prepare failed: ' . mysqli_error($acc_conn);
    }
}

/* ---------------------------------------------------------
   LOGIN FAILED?
----------------------------------------------------------*/

if (!$account) {
    $msg = 'Invalid account ID or password.';
    if (!empty($errors)) $msg .= ' (DB: '.implode(' | ', $errors).')';
    json_out(['success'=>false, 'error'=>$msg]);
}

/* ---------------------------------------------------------
   NORMALIZE ROLE
----------------------------------------------------------*/

$roleRaw = strtolower((string)($account['role'] ?? ''));

// remove spaces / underscores / hyphens
$roleClean = str_replace([' ', '_', '-'], '', $roleRaw);

if ($account['source'] === 'admins') {

    if (strpos($roleClean, 'superadmin') !== false) {
        $roleNorm = 'super_admin';
    } elseif (strpos($roleClean, 'admin') !== false || strpos($roleClean, 'instructor') !== false) {
        $roleNorm = 'admin';
    } else {
        $roleNorm = 'admin'; // fallback
    }

} else {
    // accounts table = students
    $roleNorm = 'student';
}

/* ---------------------------------------------------------
   SET SESSION + SUCCESS
----------------------------------------------------------*/

$_SESSION['acc_id']   = $account['acc_id'];
$_SESSION['acc_name'] = $account['acc_name'];
$_SESSION['role']     = $roleNorm;

session_regenerate_id(true);

$redirect =
    ($roleNorm === 'super_admin') ? 'super_admin.php' :
    ($roleNorm === 'admin'       ? 'admin.php'       :
                                   'index.php');

json_out([
    'success'  => true,
    'role'     => $roleNorm,
    'redirect' => $redirect
]);
