<?php
// Ad_log.php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'account';

function json_response($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = stripos($contentType, 'application/json') !== false;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || $isJson;

$input = $isJson ? json_decode(file_get_contents('php://input'), true) ?? [] : $_POST;

$acc_id = trim($input['acc_id'] ?? '');
$password = $input['password'] ?? '';
$require_role = $input['require_role'] ?? '';

if ($acc_id === '') {
    if ($isAjax) json_response(['success'=>false,'field'=>'acc_id','msg'=>'Account ID required']);
    $_SESSION['login_error'] = 'Account ID required';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}
if ($password === '') {
    if ($isAjax) json_response(['success'=>false,'field'=>'password','msg'=>'Password required']);
    $_SESSION['login_error'] = 'Password required';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    if ($isAjax) json_response(['success'=>false,'msg'=>'DB connection error']);
    $_SESSION['login_error'] = 'DB connection error';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}

// expected admins columns: id, acc_id, password_hash, name, role
$stmt = $mysqli->prepare("SELECT id, acc_id, password_hash, name, role FROM admins WHERE acc_id = ? LIMIT 1");
if (!$stmt) {
    if ($isAjax) json_response(['success'=>false,'msg'=>'DB query error']);
    $_SESSION['login_error'] = 'DB query error'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}
$stmt->bind_param('s', $acc_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    if ($isAjax) json_response(['success'=>false,'field'=>'acc_id','msg'=>'Account not found']);
    $_SESSION['login_error'] = 'Account not found'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}
$row = $res->fetch_assoc();
$stored_hash = $row['password_hash'] ?? '';
$db_role = $row['role'] ?? '';

if ($require_role === 'admin' && strtolower($db_role) !== 'admin') {
    if ($isAjax) json_response(['success'=>false,'msg'=>'Insufficient privileges','field'=>'acc_id']);
    $_SESSION['login_error'] = 'Insufficient privileges'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}

$pw_ok = false;
if ($stored_hash && (strpos($stored_hash,'$2y$')===0 || strpos($stored_hash,'$2a$')===0 || strpos($stored_hash,'$argon2')!==false)) {
    if (password_verify($password, $stored_hash)) $pw_ok = true;
} else {
    if ($password === $stored_hash) $pw_ok = true;
}
if (!$pw_ok) {
    if ($isAjax) json_response(['success'=>false,'field'=>'password','msg'=>'Incorrect password']);
    $_SESSION['login_error'] = 'Incorrect password'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php')); exit;
}

$_SESSION['acc_id']   = $row['acc_id'];
$_SESSION['acc_role'] = 'admin';
$_SESSION['acc_name'] = $row['name'] ?? null;
session_regenerate_id(true);

if ($isAjax) json_response(['success'=>true,'msg'=>'Logged in']);
header('Location: admin.php'); exit;
