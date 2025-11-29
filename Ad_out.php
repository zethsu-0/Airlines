
<?php
// Ad_out.php (logout)

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie if it exists
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

// Destroy session on server
session_destroy();

// Always redirect back to admin.php
header('Location: admin.php');
exit;
