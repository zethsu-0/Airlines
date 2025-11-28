<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'account';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acc_id = trim($_POST['acc_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = 'admin';

    if ($acc_id === '' || $password === '') {
        $msg = 'Account ID and Password are required.';
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM admins WHERE acc_id=? LIMIT 1");
        $stmt->bind_param('s', $acc_id);
        $stmt->execute();
        $r = $stmt->get_result();

        if ($r->num_rows > 0) {
            $msg = 'Account ID already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $mysqli->prepare("INSERT INTO admins (acc_id, password_hash, name, role) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param('ssss', $acc_id, $hash, $name, $role);
            $stmt2->execute();
            $msg = 'Admin created successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Admin</title>
    <link rel="stylesheet" href="materialize/css/materialize.min.css">
</head>
<body class="grey lighten-3">
<div class="container" style="margin-top:40px; max-width:500px;">
    <div class="card">
        <div class="card-content">
            <h4 class="center">Create Admin</h4>
            <?php if ($msg): ?>
                <div class="card-panel blue white-text center" style="font-weight:bold;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-field">
                    <input type="text" name="acc_id" id="acc_id">
                    <label for="acc_id">Account ID</label>
                </div>

                <div class="input-field">
                    <input type="password" name="password" id="password">
                    <label for="password">Password</label>
                </div>

                <div class="input-field">
                    <input type="text" name="name" id="name">
                    <label for="name">Name (optional)</label>
                </div>

                <button class="btn blue waves-effect waves-light" style="width:100%;">Create Admin</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
