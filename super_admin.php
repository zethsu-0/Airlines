<?php
// super_admin.php - standalone (no templates). Airline-blue theme.
// Provides: Add/Edit/Delete Instructors (admins) + Add/Edit/Delete Students
// Added: Super Admin profile edit, image validation (client + server).
// Backup before replacing.

session_start();

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'airlines';   // students table
$acc_db_name = 'airlines'; // accounts + admins tables

// upload dir for avatars (students & teachers share same directory)
$uploads_dir = __DIR__ . '/uploads/avatars';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

// maximum allowed avatar size (bytes)
define('MAX_AVATAR_BYTES', 2 * 1024 * 1024); // 2 MB

// allowed MIME types
$ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// ---------- CONNECT ----------
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('DB Connection failed: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');

$acc_conn = @new mysqli($db_host, $db_user, $db_pass, $acc_db_name);
$acc_db_ok = !($acc_conn->connect_error);
if ($acc_db_ok) $acc_conn->set_charset('utf8mb4');

// ---------- AUTH HELPERS ----------
function require_login() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!empty($_SESSION['acc_id'])) return;
    header('Location: index.php'); // adjust if your login page is different
    exit;
}
function is_super_admin() { return (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'); }
function is_admin() { return (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'instructor')); }
function current_acc_id() { return $_SESSION['acc_id'] ?? null; }

// ensure user is super admin
require_login();
if (!is_super_admin()) { header('Location: index.php'); exit; }

// ---------- UTIL ----------
/**
 * Handle avatar upload with server-side validation.
 *
 * Returns:
 *  - string (relative path) on success
 *  - null if no file uploaded (keeps existing)
 *  - false if a file was uploaded but invalid (caller should abort action)
 */
function handle_avatar_upload($input_name, $existing = null) {
    global $uploads_dir, $ALLOWED_IMAGE_MIMES;
    if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['tmp_name']) || $_FILES[$input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing; // no file uploaded -> keep existing
    }
    $f = $_FILES[$input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['super_admin_errors'] = [ 'File upload error (code: ' . $f['error'] . ')' ];
        return false;
    }

    // server-side size check
    if ($f['size'] > MAX_AVATAR_BYTES) {
        $_SESSION['super_admin_errors'] = [ 'Avatar too large. Max allowed is ' . (MAX_AVATAR_BYTES / (1024*1024)) . ' MB.' ];
        return false;
    }

    // verify image type using getimagesize
    $info = @getimagesize($f['tmp_name']);
    if (!$info || empty($info['mime'])) {
        $_SESSION['super_admin_errors'] = [ 'Uploaded file is not a valid image.' ];
        return false;
    }
    $mime = $info['mime'];
    if (!in_array($mime, $ALLOWED_IMAGE_MIMES, true)) {
        $_SESSION['super_admin_errors'] = [ 'Unsupported image type. Allowed types: JPEG, PNG, GIF, WEBP.' ];
        return false;
    }

    if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0755, true);
    $ext_map = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp'];
    $ext = isset($ext_map[$mime]) ? $ext_map[$mime] : pathinfo($f['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    $filename = uniqid('avatar_', true) . '.' . ($safe_ext ?: 'jpg');
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        // optionally set permissions
        @chmod($dest, 0644);
        return 'uploads/avatars/' . $filename;
    }

    $_SESSION['super_admin_errors'] = [ 'Failed to move uploaded file.' ];
    return false;
}

function ensure_unique_acc_id($dbconn, $desired) {
    $candidate = $desired; $i = 0;
    $check = $dbconn->prepare("SELECT COUNT(*) FROM accounts WHERE acc_id = ?");
    if (!$check) return $desired;
    while (true) {
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->bind_result($cnt);
        $check->fetch();
        if ($cnt == 0) break;
        $i++;
        $candidate = $desired . '_' . $i;
    }
    $check->close();
    return $candidate;
}

// detect if admins table has avatar column
$admins_has_avatar = false;
if ($acc_db_ok) {
    $colRes = $acc_conn->query("SHOW COLUMNS FROM `admins` LIKE 'avatar'");
    if ($colRes && $colRes->num_rows > 0) $admins_has_avatar = true;
}

// ---------- POST HANDLING ----------
$allowed_sex = ['', 'M', 'F'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- EDIT SUPER ADMIN PROFILE ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'edit_super') {
        $cur_acc = current_acc_id();
        if (!$cur_acc) { $_SESSION['super_admin_errors'] = ['Session missing account id.']; header('Location: super_admin.php'); exit; }

        $new_name = trim($_POST['super_name'] ?? '');
        $new_password = $_POST['super_password'] ?? '';

        if ($new_name === '') {
            $_SESSION['super_admin_errors'] = ['Display name is required.'];
            header('Location: super_admin.php'); exit;
        }

        // fetch existing avatar (from admins if present)
        $existing_avatar = null;
        if ($acc_db_ok) {
            $s = $acc_conn->prepare("SELECT avatar FROM admins WHERE acc_id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('s', $cur_acc);
                $s->execute();
                $s->bind_result($existing_avatar);
                $s->fetch();
                $s->close();
            }
        }

        $uploaded = handle_avatar_upload('super_avatar', $existing_avatar);
        if ($uploaded === false) {
            // handle_avatar_upload placed an error message into session
            header('Location: super_admin.php'); exit;
        }
        // proceed to update accounts + admins
        if ($acc_db_ok) {
            // update accounts table name + password if provided
            if ($new_password !== '') {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $upd = $acc_conn->prepare("UPDATE accounts SET acc_name = ?, password = ? WHERE acc_id = ?");
                $upd->bind_param('sss', $new_name, $new_hash, $cur_acc);
            } else {
                $upd = $acc_conn->prepare("UPDATE accounts SET acc_name = ? WHERE acc_id = ?");
                $upd->bind_param('ss', $new_name, $cur_acc);
            }
            if (!$upd->execute()) {
                $_SESSION['super_admin_errors'] = ['Failed to update accounts table: ' . $acc_conn->error];
                $upd->close();
                header('Location: super_admin.php'); exit;
            }
            $upd->close();

            // update or insert admins row for super admin
            $chk = $acc_conn->prepare("SELECT id FROM admins WHERE acc_id = ? LIMIT 1");
            $chk->bind_param('s', $cur_acc);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                // exists -> update
                $chk->bind_result($admin_row_id);
                $chk->fetch();
                if ($admins_has_avatar) {
                    $u = $acc_conn->prepare("UPDATE admins SET name = ?, avatar = ? WHERE id = ?");
                    $u->bind_param('ssi', $new_name, $uploaded, $admin_row_id);
                } else {
                    $u = $acc_conn->prepare("UPDATE admins SET name = ? WHERE id = ?");
                    $u->bind_param('si', $new_name, $admin_row_id);
                }
                if (!$u->execute()) {
                    $_SESSION['super_admin_errors'] = ['Failed to update admins table: ' . $acc_conn->error];
                    $u->close();
                    header('Location: super_admin.php'); exit;
                }
                $u->close();
            } else {
                // insert a new admins row
                if ($admins_has_avatar) {
                    $ins = $acc_conn->prepare("INSERT INTO admins (acc_id, name, role, avatar) VALUES (?, ?, 'super_admin', ?)");
                    $ins->bind_param('sss', $cur_acc, $new_name, $uploaded);
                } else {
                    $ins = $acc_conn->prepare("INSERT INTO admins (acc_id, name, role) VALUES (?, ?, 'super_admin')");
                    $ins->bind_param('ss', $cur_acc, $new_name);
                }
                if (!$ins->execute()) {
                    $_SESSION['super_admin_errors'] = ['Failed to create super_admin row in admins table: ' . $acc_conn->error];
                    $ins->close();
                    header('Location: super_admin.php'); exit;
                }
                $ins->close();
            }
            $chk->close();
        } else {
            $_SESSION['super_admin_errors'] = ['Accounts DB unavailable - cannot update profile.'];
            header('Location: super_admin.php'); exit;
        }

        $_SESSION['super_admin_success'] = 'Your profile has been updated.';
        header('Location: super_admin.php'); exit;
    }

    // RESET PASSWORD to Birthday (student)
    if (!empty($_POST['action']) && $_POST['action'] === 'reset_password') {
        $db_id = intval($_POST['db_id'] ?? 0);
        if ($db_id <= 0) { $_SESSION['super_admin_errors'] = ['Invalid student id.']; header('Location: super_admin.php'); exit; }
        $q = $conn->prepare("SELECT student_id, birthday FROM students WHERE id = ? LIMIT 1");
        if (!$q) { $_SESSION['super_admin_ERRORS'] = ['DB error while locating student.']; header('Location: super_admin.php'); exit; }
        $q->bind_param('i', $db_id);
        $q->execute();
        $q->bind_result($stu_acc_id, $stu_birthday);
        $found = $q->fetch();
        $q->close();
        if (!$found) { $_SESSION['super_admin_errors'] = ['Student not found.']; header('Location: super_admin.php'); exit; }
        if (!$acc_db_ok) { $_SESSION['super_admin_errors'] = ['Accounts DB not available.']; header('Location: super_admin.php'); exit; }
        $stu_birthday = trim((string)$stu_birthday);
        if ($stu_birthday === '') { $_SESSION['super_admin_errors'] = ['Student has no birthday recorded.']; header('Location: super_admin.php'); exit; }
        $chk = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
        if (!$chk) { $_SESSION['super_admin_errors'] = ['Accounts DB error.']; header('Location: super_admin.php'); exit; }
        $chk->bind_param('s', $stu_acc_id);
        $chk->execute();
        $chk->bind_result($exists_acc);
        $exists = $chk->fetch() ? true : false;
        $chk->close();
        if (!$exists) { $_SESSION['super_admin_errors'] = ['Account not found for this student.']; header('Location: super_admin.php'); exit; }
        $new_hash = password_hash($stu_birthday, PASSWORD_DEFAULT);
        $upd = $acc_conn->prepare("UPDATE accounts SET password = ? WHERE acc_id = ?");
        if (!$upd) { $_SESSION['super_admin_errors'] = ['Failed to prepare password update.']; header('Location: super_admin.php'); exit; }
        $upd->bind_param('ss', $new_hash, $stu_acc_id);
        $ok = $upd->execute();
        $upd->close();
        if (!$ok) { $_SESSION['super_admin_errors'] = ['Failed to update account password.']; header('Location: super_admin.php'); exit; }
        $_SESSION['account_info'] = ['acc_id' => $stu_acc_id, 'password' => $stu_birthday];
        $_SESSION['super_admin_success'] = 'Password reset to birthday.';
        header('Location: super_admin.php'); exit;
    }

    // ---------- ADD INSTRUCTOR ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'add_instructor') {
        $acc_id = trim($_POST['acc_id'] ?? '');
        $name = trim($_POST['acc_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $errors = [];
        if ($acc_id === '') $errors[] = 'Account ID required.';
        if ($name === '') $errors[] = 'Name required.';
        if ($password === '') $errors[] = 'Password required.';
        if (!empty($errors)) {
            $_SESSION['super_admin_errors'] = $errors;
            header('Location: super_admin.php'); exit;
        }
        // ensure unique in accounts table
        if ($acc_db_ok) {
            $q = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
            $q->bind_param('s', $acc_id);
            $q->execute();
            $q->store_result();
            if ($q->num_rows > 0) {
                $q->close();
                $_SESSION['super_admin_errors'] = ['Account ID already exists in accounts.'];
                header('Location: super_admin.php'); exit;
            }
            $q->close();
        }

        // handle avatar upload - if invalid -> abort
        $avatar = handle_avatar_upload('instructor_avatar', null);
        if ($avatar === false) { header('Location: super_admin.php'); exit; }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($acc_db_ok) {
            // ===== NOTE: removed created_at from accounts INSERT =====
            $ins = $acc_conn->prepare("INSERT INTO accounts (acc_id, acc_name, password, acc_role) VALUES (?, ?, ?, 'admin')");
            $ins->bind_param('sss', $acc_id, $name, $hash);
            $okAcc = $ins->execute();
            $ins->close();
            if (!$okAcc) {
                $_SESSION['super_admin_errors'] = ['Failed to create account: ' . $acc_conn->error];
                header('Location: super_admin.php'); exit;
            }
        }
        if ($acc_db_ok) {
            if ($admins_has_avatar) {
                $link = $acc_conn->prepare("INSERT INTO admins (acc_id, name, role, avatar) VALUES (?, ?, 'admin', ?)");
                $link->bind_param('sss', $acc_id, $name, $avatar);
            } else {
                $link = $acc_conn->prepare("INSERT INTO admins (acc_id, name, role) VALUES (?, ?, 'admin')");
                $link->bind_param('ss', $acc_id, $name);
            }
            if (!$link->execute()) {
                if ($acc_db_ok) $acc_conn->query("DELETE FROM accounts WHERE acc_id = '" . $acc_conn->real_escape_string($acc_id) . "'");
                $_SESSION['super_admin_errors'] = ['Failed to insert admins row: ' . $acc_conn->error];
                $link->close();
                header('Location: super_admin.php'); exit;
            }
            $link->close();
        }
        $_SESSION['super_admin_success'] = 'Instructor added.';
        header('Location: super_admin.php'); exit;
    }

    // ---------- EDIT INSTRUCTOR ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'edit_instructor') {
        $admin_id = intval($_POST['admin_id'] ?? 0);
        $acc_name = trim($_POST['acc_name'] ?? ''); // displayed acc_name in accounts table
        $display_name = trim($_POST['display_name'] ?? ''); // visible instructor name
        $new_acc_id = trim($_POST['edit_acc_id'] ?? '');
        $new_password = $_POST['edit_password'] ?? '';

        if ($admin_id <= 0 || $acc_name === '' || $display_name === '' || $new_acc_id === '') {
            $_SESSION['super_admin_errors'] = ['Invalid instructor fields.'];
            header('Location: super_admin.php'); exit;
        }

        // fetch current admin row
        $s = $acc_conn->prepare("SELECT acc_id, avatar FROM admins WHERE id = ? LIMIT 1");
        $s->bind_param('i', $admin_id);
        $s->execute();
        $s->bind_result($acc_id_row, $old_avatar);
        if (!$s->fetch()) { $s->close(); $_SESSION['super_admin_errors'] = ['Instructor not found.']; header('Location: super_admin.php'); exit; }
        $s->close();

        // if acc_id changed, ensure uniqueness in accounts table
        if ($acc_db_ok && $new_acc_id !== $acc_id_row) {
            $chk = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
            $chk->bind_param('s', $new_acc_id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $chk->close();
                $_SESSION['super_admin_errors'] = ['Account ID already exists in accounts.'];
                header('Location: super_admin.php'); exit;
            }
            $chk->close();
        }

        // handle avatar upload - if invalid -> abort
        $new_avatar = handle_avatar_upload('edit_instructor_avatar', $old_avatar);
        if ($new_avatar === false) { header('Location: super_admin.php'); exit; }

        // Update admins table (name, avatar, acc_id if admins has acc_id column)
        // Check if admins has 'acc_id' column
        $has_accid_col = false;
        if ($acc_db_ok) {
            $colq = $acc_conn->query("SHOW COLUMNS FROM `admins` LIKE 'acc_id'");
            if ($colq && $colq->num_rows > 0) $has_accid_col = true;
            if ($colq) $colq->free();
        }
        if ($has_accid_col) {
            $u = $acc_conn->prepare("UPDATE admins SET name = ?, avatar = ?, acc_id = ? WHERE id = ?");
            $u->bind_param('sssi', $display_name, $new_avatar, $new_acc_id, $admin_id);
        } else {
            $u = $acc_conn->prepare("UPDATE admins SET name = ?, avatar = ? WHERE id = ?");
            $u->bind_param('ssi', $display_name, $new_avatar, $admin_id);
        }
        if (!$u->execute()) {
            $_SESSION['super_admin_errors'] = ['Failed to update instructor: ' . $acc_conn->error];
            $u->close();
            header('Location: super_admin.php'); exit;
        }
        $u->close();

        // Update accounts table: possibly change acc_id, acc_name, password
        if ($acc_db_ok) {
            // get existing password hash if no new password provided
            if ($new_password !== '') {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            } else {
                $gethash = $acc_conn->prepare("SELECT password FROM accounts WHERE acc_id = ? LIMIT 1");
                $gethash->bind_param('s', $acc_id_row);
                $gethash->execute(); $gethash->bind_result($existing_hash); $gethash->fetch(); $gethash->close();
                $new_hash = $existing_hash ?? password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
            }

            // if acc_id changed, update acc_id as well; then propagate to students.teacher_id
            if ($new_acc_id !== $acc_id_row) {
                $accUpd = $acc_conn->prepare("UPDATE accounts SET acc_id = ?, acc_name = ?, password = ? WHERE acc_id = ?");
                $accUpd->bind_param('ssss', $new_acc_id, $acc_name, $new_hash, $acc_id_row);
            } else {
                $accUpd = $acc_conn->prepare("UPDATE accounts SET acc_name = ?, password = ? WHERE acc_id = ?");
                $accUpd->bind_param('sss', $acc_name, $new_hash, $acc_id_row);
            }
            $accOk = $accUpd->execute();
            $accUpd->close();
            if (!$accOk) {
                $_SESSION['super_admin_errors'] = ['Failed to update accounts DB: ' . $acc_conn->error];
                header('Location: super_admin.php'); exit;
            }

            // Update students.teacher_id to new acc_id if changed
            if ($new_acc_id !== $acc_id_row) {
                $u2 = $conn->prepare("UPDATE students SET teacher_id = ? WHERE teacher_id = ?");
                $u2->bind_param('ss', $new_acc_id, $acc_id_row);
                $u2->execute();
                $u2->close();
            }
        }

        $_SESSION['super_admin_success'] = 'Instructor updated.';
        header('Location: super_admin.php'); exit;
    }

    // ---------- DELETE INSTRUCTOR ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'delete_instructor') {
        $admin_id = intval($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) { $_SESSION['super_admin_errors'] = ['Invalid instructor id.']; header('Location: super_admin.php'); exit; }
        $s = $acc_conn->prepare("SELECT acc_id, avatar FROM admins WHERE id = ? LIMIT 1");
        $s->bind_param('i', $admin_id);
        $s->execute();
        $s->bind_result($acc_id_row, $avatar_row);
        if (!$s->fetch()) { $s->close(); $_SESSION['super_admin_errors'] = ['Instructor not found.']; header('Location: super_admin.php'); exit; }
        $s->close();
        $uq = $conn->prepare("UPDATE students SET teacher_id = NULL WHERE teacher_id = ?");
        $uq->bind_param('s', $acc_id_row);
        $uq->execute();
        $uq->close();
        $d = $acc_conn->prepare("DELETE FROM admins WHERE id = ?");
        $d->bind_param('i', $admin_id);
        if (!$d->execute()) {
            $_SESSION['super_admin_errors'] = ['Failed to delete instructor: ' . $acc_conn->error];
            $d->close(); header('Location: super_admin.php'); exit;
        }
        $d->close();
        if (!empty($avatar_row) && strpos($avatar_row, 'uploads/avatars/') === 0) {
            $f = __DIR__ . '/' . ltrim($avatar_row, '/\\');
            if (is_file($f)) @unlink($f);
        }
        $_SESSION['super_admin_success'] = 'Instructor deleted (students unassigned).';
        header('Location: super_admin.php'); exit;
    }

    // ---------- ADD STUDENT ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'add_student') {
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $student_id_val = trim($_POST['student_id_val'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $sex = trim($_POST['sex'] ?? '');
        $assigned_teacher = trim($_POST['teacher_id'] ?? '') ?: null;

        $errors = [];
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $birthday);
            if (!$d || $d->format('Y-m-d') !== $birthday) $errors[] = 'Birthday invalid.';
        }
        if (!in_array($sex, $allowed_sex, true)) $errors[] = 'Sex must be M or F.';

        if ($assigned_teacher && $acc_db_ok) {
            $chk = $acc_conn->prepare("SELECT role FROM admins WHERE acc_id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('s', $assigned_teacher);
                $chk->execute();
                $chk->bind_result($roleFound);
                $ok = $chk->fetch();
                $chk->close();
                if (!$ok || $roleFound !== 'admin') $errors[] = 'Assigned teacher is invalid.';
            } else {
                $errors[] = 'Accounts DB error while validating teacher.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['super_admin_errors'] = $errors;
            header('Location: super_admin.php'); exit;
        }

        // handle optional avatar; if present but invalid -> abort
        $avatar = handle_avatar_upload('student_photo', null);
        if ($avatar === false) { header('Location: super_admin.php'); exit; }

        $stmt = $conn->prepare("INSERT INTO students (student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) { $_SESSION['super_admin_errors'] = ['Failed to prepare student insertion.']; header('Location: super_admin.php'); exit; }
        $stmt->bind_param('ssssssssss', $student_id_val, $last, $first, $middle, $suffix, $section, $avatar, $birthday, $sex, $assigned_teacher);
        $ok = $stmt->execute();
        if (!$ok) { $stmt->close(); $_SESSION['super_admin_errors'] = ['Failed to insert student (DB error).']; header('Location: super_admin.php'); exit; }
        $student_row_id = $stmt->insert_id;
        $stmt->close();

        if ($acc_db_ok) {
            $desired_acc_id = $student_id_val;
            $final_acc_id = ensure_unique_acc_id($acc_conn, $desired_acc_id);
            $acc_name = trim($first . ' ' . $last);
            $raw_password = $birthday !== '' ? $birthday : bin2hex(random_bytes(4));
            $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
            $acc_role = 'student';
            $insAcc = $acc_conn->prepare("INSERT INTO accounts (acc_id, acc_name, password, acc_role) VALUES (?, ?, ?, ?)");
            if (!$insAcc) {
                $conn->query("DELETE FROM students WHERE id = " . intval($student_row_id));
                $_SESSION['super_admin_errors'] = ['Failed to prepare account insertion in accounts DB. Student insertion rolled back.'];
                header('Location: super_admin.php'); exit;
            }
            $insAcc->bind_param('ssss', $final_acc_id, $acc_name, $password_hash, $acc_role);
            $insOk = $insAcc->execute();
            $insAcc->close();
            if (!$insOk) {
                $conn->query("DELETE FROM students WHERE id = " . intval($student_row_id));
                $_SESSION['super_admin_errors'] = ['Failed to create account in accounts DB. Student insertion rolled back.'];
                header('Location: super_admin.php'); exit;
            }
            $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => $raw_password];
        }

        $_SESSION['super_admin_success'] = 'Student added.';
        header('Location: super_admin.php'); exit;
    }

    // ---------- EDIT STUDENT ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'edit_student') {
        $db_id = intval($_POST['db_id'] ?? 0);
        $last = trim($_POST['edit_last_name'] ?? '');
        $first = trim($_POST['edit_first_name'] ?? '');
        $middle = trim($_POST['edit_middle_name'] ?? '');
        $suffix = trim($_POST['edit_suffix'] ?? '');
        $student_id_val = trim($_POST['edit_student_id'] ?? '');
        $section = trim($_POST['edit_section'] ?? '');
        $birthday = trim($_POST['edit_birthday'] ?? '');
        $sex = trim($_POST['edit_sex'] ?? '');
        $assigned_teacher = trim($_POST['edit_teacher_id'] ?? '') ?: null;

        $errors = [];
        if ($db_id <= 0) $errors[] = 'Invalid student id.';
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $birthday);
            if (!$d || $d->format('Y-m-d') !== $birthday) $errors[] = 'Birthday invalid.';
        }
        if (!in_array($sex, $allowed_sex, true)) $errors[] = 'Sex must be M or F.';

        if ($assigned_teacher && $acc_db_ok) {
            $chk = $acc_conn->prepare("SELECT role FROM admins WHERE acc_id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('s', $assigned_teacher);
                $chk->execute();
                $chk->bind_result($roleFound);
                $ok = $chk->fetch();
                $chk->close();
                if (!$ok || $roleFound !== 'admin') $errors[] = 'Assigned teacher is invalid.';
            } else $errors[] = 'Accounts DB error while validating teacher.';
        }

        if (!empty($errors)) { $_SESSION['super_admin_errors'] = $errors; header('Location: super_admin.php'); exit; }

        $cur_avatar = null; $old_student_id = null; $old_first = null; $old_last = null;
        $q = $conn->prepare("SELECT avatar, student_id, first_name, last_name FROM students WHERE id = ? LIMIT 1");
        $q->bind_param('i', $db_id); $q->execute(); $q->bind_result($cur_avatar, $old_student_id, $old_first, $old_last); $q->fetch(); $q->close();

        // handle avatar upload; if invalid -> abort
        $new_avatar = handle_avatar_upload('edit_student_photo', $cur_avatar);
        if ($new_avatar === false) { header('Location: super_admin.php'); exit; }

        if ($new_avatar !== null && $new_avatar !== $cur_avatar) {
            if (!empty($cur_avatar)) {
                $realOld = realpath(__DIR__ . '/' . ltrim($cur_avatar, '/\\'));
                $allowedDir = realpath($uploads_dir);
                if ($realOld && $allowedDir && strpos($realOld, $allowedDir) === 0 && is_file($realOld)) {
                    @unlink($realOld);
                }
            }
        }

        $upd = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, middle_name = ?, suffix = ?, section = ?, avatar = ?, birthday = ?, sex = ?, teacher_id = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param('ssssssssssi', $student_id_val, $last, $first, $middle, $suffix, $section, $new_avatar, $birthday, $sex, $assigned_teacher, $db_id);
        $ok = $upd->execute(); $upd->close();
        if (!$ok) { $_SESSION['super_admin_errors'] = ['Failed to update student.']; header('Location: super_admin.php'); exit; }

        // sync accounts DB
        if ($acc_db_ok) {
            $check = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
            $check->bind_param('s', $old_student_id); $check->execute(); $check->bind_result($found_acc); $exists = $check->fetch() ? true : false; $check->close();

            if ($exists) {
                $final_acc_id = $student_id_val;
                if ($old_student_id !== $student_id_val) $final_acc_id = ensure_unique_acc_id($acc_conn, $student_id_val);
                if ($birthday !== '') {
                    $new_hash = password_hash($birthday, PASSWORD_DEFAULT);
                } else {
                    $gethash = $acc_conn->prepare("SELECT password FROM accounts WHERE acc_id = ? LIMIT 1");
                    $gethash->bind_param('s', $old_student_id);
                    $gethash->execute(); $gethash->bind_result($existing_hash); $gethash->fetch(); $gethash->close();
                    $new_hash = $existing_hash ?? password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                }
                $new_acc_name = trim($first . ' ' . $last);
                $accUpd = $acc_conn->prepare("UPDATE accounts SET acc_id = ?, acc_name = ?, password = ? WHERE acc_id = ?");
                $accUpd->bind_param('ssss', $final_acc_id, $new_acc_name, $new_hash, $old_student_id);
                $accOk = $accUpd->execute(); $accUpd->close();
                if (!$accOk) {
                    $rb = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, avatar = ? WHERE id = ?");
                    $rb->bind_param('ssssi', $old_student_id, $old_last, $old_first, $cur_avatar, $db_id);
                    $rb->execute(); $rb->close();
                    $_SESSION['super_admin_errors'] = ['Failed to update account in accounts DB. Student update rolled back.'];
                    header('Location: super_admin.php'); exit;
                }
                if ($final_acc_id !== $old_student_id) {
                    $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => ($birthday !== '' ? $birthday : '(password unchanged)')];
                } elseif ($birthday !== '') {
                    $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => $birthday];
                }
            } else {
                $final_acc_id = ensure_unique_acc_id($acc_conn, $student_id_val);
                $raw_password = $birthday !== '' ? $birthday : bin2hex(random_bytes(4));
                $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
                $acc_role = 'student';
                $insAcc = $acc_conn->prepare("INSERT INTO accounts (acc_id, acc_name, password, acc_role) VALUES (?, ?, ?, ?)");
                $insAcc->bind_param('ssss', $final_acc_id, trim($first . ' ' . $last), $password_hash, $acc_role);
                $insOk = $insAcc->execute(); $insAcc->close();
                if (!$insOk) {
                    $rb = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, avatar = ? WHERE id = ?");
                    $rb->bind_param('ssssi', $old_student_id, $old_last, $old_first, $cur_avatar, $db_id);
                    $rb->execute(); $rb->close();
                    $_SESSION['super_admin_errors'] = ['Failed to create account in accounts DB. Student update rolled back.'];
                    header('Location: super_admin.php'); exit;
                }
                $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => $raw_password];
            }
        }

        $_SESSION['super_admin_success'] = 'Student updated.';
        header('Location: super_admin.php'); exit;
    }

    // ---------- DELETE STUDENT (bulk) ----------
    if (!empty($_POST['action']) && $_POST['action'] === 'delete_selected') {
        $ids = $_POST['delete_ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $clean = array_map('intval', $ids);
            $in = implode(',', $clean);

            $res = $conn->query("SELECT id, avatar, student_id FROM students WHERE id IN ($in)");
            $accIdsToDelete = [];
            if ($res) {
                $idsToDelete = [];
                while ($r = $res->fetch_assoc()) {
                    $idsToDelete[] = intval($r['id']);
                    if (!empty($r['avatar']) && strpos($r['avatar'], 'uploads/avatars/') === 0) {
                        $f = __DIR__ . '/' . $r['avatar'];
                        if (is_file($f)) @unlink($f);
                    }
                    if (!empty($r['student_id'])) $accIdsToDelete[] = "'" . ($acc_db_ok ? $acc_conn->real_escape_string($r['student_id']) : $conn->real_escape_string($r['student_id'])) . "'";
                }
                $res->free();

                if (!empty($idsToDelete)) {
                    $in2 = implode(',', $idsToDelete);
                    $conn->query("DELETE FROM students WHERE id IN ($in2)");
                }

                if (!empty($accIdsToDelete) && $acc_db_ok) {
                    $accIn = implode(',', $accIdsToDelete);
                    $acc_conn->query("DELETE FROM accounts WHERE acc_id IN ($accIn)");
                }
            }
        }
        $_SESSION['super_admin_success'] = 'Selected students deleted.';
        header('Location: super_admin.php'); exit;
    }

} // POST end

// ---------- FETCH Super Admin info ----------
$super_admin = ['acc_id' => current_acc_id(), 'name' => '', 'avatar' => 'assets/avatar.png'];
if ($acc_db_ok && $super_admin['acc_id']) {
    // get name from accounts table
    $s = $acc_conn->prepare("SELECT acc_name FROM accounts WHERE acc_id = ? LIMIT 1");
    if ($s) {
        $s->bind_param('s', $super_admin['acc_id']);
        $s->execute();
        $s->bind_result($acc_name_tmp);
        if ($s->fetch()) $super_admin['name'] = $acc_name_tmp;
        $s->close();
    }
    // try to get avatar from admins table if available
    $t = $acc_conn->prepare("SELECT name, avatar FROM admins WHERE acc_id = ? LIMIT 1");
    if ($t) {
        $t->bind_param('s', $super_admin['acc_id']);
        $t->execute();
        $t->bind_result($adm_name, $adm_avatar);
        if ($t->fetch()) {
            if (!empty($adm_name)) $super_admin['name'] = $adm_name;
            if (!empty($adm_avatar) && is_file(__DIR__ . '/' . $adm_avatar)) $super_admin['avatar'] = $adm_avatar;
        }
        $t->close();
    }
}

// ---------- FETCH instructors (admins) - exclude super_admin role ----------
$teachers = [];
if ($acc_db_ok) {
    $sql = $admins_has_avatar
        ? "SELECT id, acc_id, name, role, avatar FROM admins WHERE role = 'admin' ORDER BY name ASC"
        : "SELECT id, acc_id, name, role FROM admins WHERE role = 'admin' ORDER BY name ASC";
    $res = $acc_conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) $teachers[] = $r;
        $res->free();
    }
}

// ---------- FETCH students ----------
$students = [];
$sql = "SELECT id, student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id FROM students ORDER BY COALESCE(NULLIF(section,''),'~'), last_name, first_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($sid_pk, $sid_val, $nlast, $nfirst, $nmiddle, $nsuffix, $ssection, $savatar, $sbirthday, $ssex, $steacher);
while ($stmt->fetch()) {
    $students[] = [
        'id' => $sid_pk,
        'student_id' => $sid_val,
        'last_name' => $nlast ?? '',
        'first_name' => $nfirst ?? '',
        'middle_name' => $nmiddle ?? '',
        'suffix' => $nsuffix ?? '',
        'section' => $ssection ?? '',
        'avatar' => $savatar ?? '',
        'birthday' => $sbirthday ?? '',
        'sex' => $ssex ?? '',
        'teacher_id' => $steacher ?? ''
    ];
}
$stmt->close();

// normalize avatars for students
foreach ($students as &$s) {
    $a = trim((string)($s['avatar'] ?? ''));
    if ($a === '' || strtolower($a) === 'null') $a = 'assets/avatar.png';
    else if (strpos($a, '/') === 0) $a = ltrim($a, '/');
    if (!is_file(__DIR__ . '/' . $a)) $a = 'assets/avatar.png';
    $s['avatar'] = $a;
}
unset($s);

// prepare teacher map (acc_id => [name, avatar]) for display and selects
$teachers_map = [];
$teachers_list = []; // used for <select> lists (acc_id,name,avatar)
if ($acc_db_ok) {
    foreach ($teachers as $t) {
        $aid = $t['acc_id'];
        $teachers_map[$aid] = [
            'name' => $t['name'] ?? $aid,
            'avatar' => isset($t['avatar']) && $t['avatar'] ? $t['avatar'] : 'assets/avatar.png',
            'id' => $t['id']
        ];
        $teachers_list[] = [
            'acc_id' => $aid,
            'name' => $t['name'] ?? $aid,
            'avatar' => isset($t['avatar']) && $t['avatar'] ? $t['avatar'] : 'assets/avatar.png',
        ];
    }
}

// ---------- Group by instructor -> section -> students ----------
$instructor_groups = []; // acc_id OR 'UNASSIGNED' => ['teacher_name','teacher_avatar','sections' => [sectionName => ['count','students'=>[]]]]
foreach ($students as $st) {
    $teacher_acc = trim((string)$st['teacher_id']);
    if ($teacher_acc === '') $teacher_acc = 'UNASSIGNED';
    $section = trim((string)$st['section']);
    if ($section === '') $section = '(No section)';

    if (!isset($instructor_groups[$teacher_acc])) {
        $displayName = isset($teachers_map[$teacher_acc]) ? $teachers_map[$teacher_acc]['name'] : ($teacher_acc === 'UNASSIGNED' ? 'Unassigned' : $teacher_acc);
        $displayAvatar = isset($teachers_map[$teacher_acc]) ? $teachers_map[$teacher_acc]['avatar'] : 'assets/avatar.png';
        $instructor_groups[$teacher_acc] = ['teacher_name' => $displayName, 'teacher_avatar' => $displayAvatar, 'sections' => []];
    }
    if (!isset($instructor_groups[$teacher_acc]['sections'][$section])) {
        $instructor_groups[$teacher_acc]['sections'][$section] = ['count' => 0, 'students' => []];
    }
    $instructor_groups[$teacher_acc]['sections'][$section]['students'][] = $st;
    $instructor_groups[$teacher_acc]['sections'][$section]['count']++;
}

// helper to render student row
function render_student_row_html($st) {
    $id = (int)$st['id'];
    $student_id = htmlspecialchars($st['student_id'] ?? '', ENT_QUOTES);
    $last = htmlspecialchars($st['last_name'] ?? '', ENT_QUOTES);
    $first = htmlspecialchars($st['first_name'] ?? '', ENT_QUOTES);
    $middle = htmlspecialchars($st['middle_name'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($st['suffix'] ?? '', ENT_QUOTES);
    $section = htmlspecialchars($st['section'] ?? '', ENT_QUOTES);
    $birthday = $st['birthday'] ? htmlspecialchars($st['birthday'], ENT_QUOTES) : '';
    $sex = $st['sex'] ? htmlspecialchars($st['sex'], ENT_QUOTES) : '';
    $teacher_name = htmlspecialchars($st['teacher_id'] ?? '', ENT_QUOTES);
    $avatar = htmlspecialchars($st['avatar'] ?? 'assets/avatar.png', ENT_QUOTES);

    ob_start();
    ?>
<tr data-db-id="<?php echo $id; ?>">
  <td style="width:48px;"><label><input type="checkbox" class="filled-in chk" data-id="<?php echo $id; ?>"><span></span></label></td>
  <td><img src="<?php echo $avatar; ?>" alt="avatar" class="table-avatar-img" onerror="this.onerror=null;this.src='assets/avatar.png';" /></td>
  <td class="cell-last"><?php echo $last; ?></td>
  <td class="cell-first"><?php echo $first; ?></td>
  <td class="cell-middle"><?php echo $middle; ?></td>
  <td class="cell-suffix"><?php echo $suffix; ?></td>
  <td class="cell-studentid"><?php echo $student_id; ?></td>
  <td class="cell-section"><?php echo $section; ?></td>
  <td class="cell-birthday"><?php echo $birthday ?: '&mdash;'; ?></td>
  <td class="cell-sex"><?php echo $sex ?: '&mdash;'; ?></td>
  <td class="cell-teacher"><?php echo $teacher_name ?: '&mdash;'; ?></td>
  <td style="width:120px;">
    <a class="edit-btn tooltipped" href="#editStudentModal"
       data-db-id="<?php echo $id; ?>"
       data-last="<?php echo $last; ?>"
       data-first="<?php echo $first; ?>"
       data-middle="<?php echo $middle; ?>"
       data-suffix="<?php echo $suffix; ?>"
       data-studentid="<?php echo $student_id; ?>"
       data-section="<?php echo $section; ?>"
       data-avatar="<?php echo $avatar; ?>"
       data-birthday="<?php echo $birthday; ?>"
       data-sex="<?php echo $sex; ?>"
       data-teacher="<?php echo htmlspecialchars($st['teacher_id'] ?? '', ENT_QUOTES); ?>"
       data-position="top"
       data-tooltip="Edit">
      <i class="material-icons">edit</i>
    </a>
  </td>
</tr>
    <?php
    return ob_get_clean();
}

// flash messages
$errors_flash = $_SESSION['super_admin_errors'] ?? [];
unset($_SESSION['super_admin_errors']);
$success_flash = $_SESSION['super_admin_success'] ?? null;
unset($_SESSION['super_admin_success']);
$account_flash = $_SESSION['account_info'] ?? null;
unset($_SESSION['account_info']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Super Admin — Instructors & Students</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <style>
    :root{
      --air-blue: #0b59d8;
      --air-sky: #2e7ef7;
      --muted: #7b89a6;
    }
    html,body{margin:0;padding:0;background:linear-gradient(180deg,#eaf4ff 0%, #f5f9ff 40%);font-family:Inter,Roboto,Arial,sans-serif;color:#20314b}
    .page-wrap{padding:20px 0 48px}
    nav.blue{background:var(--air-blue) !important}
    header.banner{
      background:linear-gradient(90deg,var(--air-blue),var(--air-sky));
      color:#fff;padding:18px;border-radius:8px;margin:18px 0;display:flex;align-items:center;gap:18px;box-shadow:0 8px 30px rgba(11,89,216,0.12)
    }
    .banner h1{margin:0;font-size:20px;letter-spacing:0.6px}
    .banner .sub{opacity:0.92;font-size:13px}

    .top-controls{display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
    .btn-air{
      background:linear-gradient(180deg,var(--air-sky),var(--air-blue));
      color:#fff;border-radius:28px;padding:10px 18px;font-weight:700;border:none;box-shadow:0 8px 24px rgba(11,89,216,0.18);cursor:pointer
    }
    .btn-air.ghost{background:transparent;border:2px solid var(--air-blue);color:var(--air-blue);padding:8px 12px}
    .btn-danger{background:#ff5252;color:#fff;border-radius:8px;padding:8px 12px}
    .table-avatar-img{width:64px;height:64px;border-radius:50%;object-fit:cover;display:block;border:2px solid rgba(0,0,0,0.04)}
    table.section-table thead th{background:rgba(11,89,216,0.06);color:#0b3b7a}
    .edit-btn{background:var(--air-sky);border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;color:#fff}

    .section-wrap{margin-bottom:18px;background:#fff;padding:10px;border-radius:10px;box-shadow:0 6px 24px rgba(16,39,77,0.06)}
    .section-header{display:flex;align-items:center;justify-content:space-between;padding:8px 6px;color:#3b4b6b;text-transform:uppercase;font-weight:700;letter-spacing:.6px;font-size:13px;cursor:pointer}
    .section-count{color:#6b7aa0;font-weight:600;font-size:12px;text-transform:none}
    .section-hr{border:0;height:1px;background:linear-gradient(to right, rgba(0,0,0,.02), rgba(0,0,0,.06));margin:8px 0 12px}

    .teacher-card { display:flex; gap:12px; align-items:center; padding:10px; border-radius:10px; background:#fff; box-shadow:0 6px 20px rgba(16,39,77,0.04); }
    .teacher-avatar { width:64px;height:64px;border-radius:8px;object-fit:cover; }

    #deleteNamesList li{display:flex;align-items:center;gap:10px;margin:6px 0;font-size:14px;color:#333;padding:8px;border-radius:8px;background:#fafcff;border:1px solid rgba(11,89,216,0.04)}
    .delete-list-avatar{width:36px;height:36px;max-width:36px;max-height:36px;border-radius:50%;object-fit:cover;margin-right:10px}

    /* ID-card modal style with rounded corners */
    .id-card {
      display:flex;gap:18px;align-items:center;background:linear-gradient(90deg,#fff,#f7fbff);padding:14px;border-radius:12px;border:0;
      box-shadow:0 8px 30px rgba(16,39,77,0.06)
    }
    .id-photo{width:110px;height:110px;border-radius:50%;object-fit:cover;border:0;background:#fff}
    /* make file input clickable inside the visible button area */
    .id-photo-button{display:inline-block;padding:6px 10px;border-radius:8px;background:var(--air-blue);color:#fff;cursor:pointer;position:relative;overflow:hidden}
    .id-photo-button input[type=file]{ position:absolute; left:0; top:0; width:100%; height:100%; opacity:0; cursor:pointer; }
    .id-info{flex:1}
    .id-info h3{margin:0;color:var(--air-blue);font-size:18px}
    .id-info p{margin:6px 0;color:#475b7a}
    .field-row{display:flex;gap:10px;flex-wrap:wrap}
    .input-field .required-star:after{content:" *";color:#d32f2f}

    /* small inline validation message for file inputs */
    .file-note { font-size:12px;color:#b71c1c;margin-top:6px;display:none; }

    /* collapse animation */
    .section-body { transition: max-height .28s cubic-bezier(.4,0,.2,1), opacity .22s; overflow: hidden; max-height: 2000px; opacity:1; }
    .section-body.collapsed { max-height: 0 !important; opacity:0; padding:0; margin:0; }

    .modal { border-radius:12px; }
    .modal .modal-content { padding: 18px 24px; }

    /* fix select label overlap: ensure labels above selects have spacing */
    .browser-default + label, label[for] { display:block; margin-bottom:6px; }
    select.browser-default { min-height:40px; padding:6px 10px; border-radius:6px; border:1px solid rgba(0,0,0,0.06); background:#fff; }

    @media(max-width:700px){
      .id-card{flex-direction:column;align-items:flex-start}
      .id-photo{width:90px;height:90px}
      .table-avatar-img{width:48px;height:48px}
    }
  </style>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper" style="padding:0 12px;">
    
    <!-- Left side (logo) -->
    <a href="super_admin.php" class="brand-logo center" style="display:flex;align-items:center;gap:8px;">
      <img src="assets/logo.png" alt="logo" style="height:34px;vertical-align:middle;"> 
      Account Management
    </a>

    <!-- Right side -->
    <ul id="nav-mobile" class="right">
      <li>
        <a href="logout.php" style="display:flex;align-items:center;gap:6px;">
          <i class="material-icons">exit_to_app</i>
          Logout
        </a>
      </li>
    </ul>

  </div>
</nav>


<div class="container page-wrap">
  <header class="banner">
    <div style="flex:0 0 auto"><img src="assets/logo.png" alt="logo" style="height:46px;width:72px;object-fit:cover;border-radius:6px"></div>
    <div>
      <h1>Instructors & Students</h1>
      <div class="sub">Manage instructors and students</div>
    </div>
  </header>

  <div class="row" style="margin-bottom:6px;">
    <div class="col s12 m12">
      <div class="top-controls">
        <button id="btnAddInstructor" class="btn-air modal-trigger">+ Add Instructor</button>
        <button id="btnAddStudent" class="btn-air">+ Add Student</button>
        <button id="deleteSelectedBtn" class="btn-danger">Delete Selected Students</button>
        <button id="toggleCollapseBtn" class="btn-air ghost" type="button">Collapse All</button>
      </div>
    </div>
  </div>

  <?php if (!empty($errors_flash)): ?>
    <div class="card-panel red lighten-4 red-text text-darken-4">
      <?php foreach ($errors_flash as $err): ?><div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($success_flash): ?><div class="card-panel green lighten-5"><?php echo htmlspecialchars($success_flash); ?></div><?php endif; ?>
  <?php if ($account_flash): ?>
    <div class="card-panel green lighten-5" style="border-left:4px solid #2e7d32;">
      <strong>Account created/updated:</strong>
      <div><strong>Account ID:</strong> <?php echo htmlspecialchars($account_flash['acc_id'], ENT_QUOTES); ?></div>
      <div><strong>Initial password:</strong> <code><?php echo htmlspecialchars($account_flash['password'], ENT_QUOTES); ?></code></div>
      <small class="grey-text">Shown only once — password is stored hashed in the DB.</small>
    </div>
  <?php endif; ?>

  <!-- Super Admin profile card -->
  <div style="margin-bottom:18px;">
    <h5 style="margin:6px 0 8px;">Admin Information</h5>
    <div class="teacher-card" style="align-items:center;">
      <img src="<?php echo htmlspecialchars($super_admin['avatar']); ?>" onerror="this.onerror=null;this.src='assets/avatar.png';" class="teacher-avatar" alt="avatar">
      <div style="flex:1;">
        <div style="font-weight:700;color:var(--air-blue)"><?php echo htmlspecialchars($super_admin['name'] ?: $super_admin['acc_id']); ?></div>
        <div style="color:var(--muted);font-size:13px"><?php echo htmlspecialchars($super_admin['acc_id']); ?></div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn-flat" onclick="try{ M.Modal.getInstance(document.getElementById('modalEditSuper')).open(); }catch(e){ document.getElementById('modalEditSuper').style.display='block'; }"><i class="material-icons">edit</i></button>
      </div>
    </div>
  </div>

  <!-- Instructors list -->
  <div style="margin-bottom:18px;">
    <h5 style="margin:6px 0 12px;">Instructors</h5>
    <?php if (empty($teachers)): ?>
      <div class="card-panel">No instructors found.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($teachers as $t): ?>
          <div class="teacher-card">
            <img src="<?php echo htmlspecialchars($t['avatar'] ?? 'assets/avatar.png', ENT_QUOTES); ?>" onerror="this.onerror=null;this.src='assets/avatar.png';" class="teacher-avatar" alt="avatar">
            <div style="flex:1;">
              <div style="font-weight:700;color:var(--air-blue)"><?php echo htmlspecialchars($t['name'] ?? $t['acc_id']); ?></div>
              <div style="color:var(--muted);font-size:13px"><?php echo htmlspecialchars($t['acc_id']); ?></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
              <button class="btn-flat" onclick="openEditInstructorModal(<?php echo (int)$t['id']; ?>,'<?php echo addslashes(htmlspecialchars($t['name'])); ?>','<?php echo addslashes(htmlspecialchars($t['acc_id'])); ?>')"><i class="material-icons">edit</i></button>
              <button class="btn-flat red-text" onclick="confirmDeleteInstructor(<?php echo (int)$t['id']; ?>,'<?php echo addslashes(htmlspecialchars($t['name'])); ?>')"><i class="material-icons">delete</i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <form id="deleteForm" method="post" style="display:none;"><input type="hidden" name="action" value="delete_selected"></form>

  <!-- Students grouped by instructor -> sections -->
  <?php if (empty($instructor_groups)): ?>
    <div class="card-panel">No students found.</div>
  <?php else: ?>
    <?php foreach ($instructor_groups as $teachKey => $data):
        $teacherName = htmlspecialchars($data['teacher_name'], ENT_QUOTES);
        $teacherAvatar = htmlspecialchars($data['teacher_avatar'] ?? 'assets/avatar.png', ENT_QUOTES);
        $teachDom = 'teach_' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($teachKey));
    ?>
      <div class="section-wrap" id="<?php echo $teachDom; ?>_wrap">
        <div class="section-header" data-section="<?php echo $teachDom; ?>">
          <div style="display:flex;align-items:center;gap:12px">
            <img src="<?php echo $teacherAvatar; ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover" onerror="this.onerror=null;this.src='assets/avatar.png'">
            <div>
              <div class="section-title" style="text-transform:none;font-weight:700;color:#0b59d8"><?php echo $teacherName; ?></div>
              <div class="section-count" style="font-weight:600;color:#6b7aa0;margin-top:4px"><?php
                $total = 0; foreach ($data['sections'] as $sname => $sinfo) $total += $sinfo['count'];
                echo $total . ' student' . ($total === 1 ? '' : 's'); ?></div>
            </div>
          </div>
          <i class="material-icons collapse-icon">expand_less</i>
        </div>

        <hr class="section-hr">
        <div class="section-body" id="<?php echo $teachDom; ?>_body">
          <!-- for each section under this instructor -->
          <?php foreach ($data['sections'] as $secName => $secInfo):
              $secDom = $teachDom . '_sec_' . preg_replace('/[^a-z0-9_-]/i','_', strtolower($secName));
          ?>
            <div style="margin-bottom:14px;">
              <div class="section-header" data-section="<?php echo $secDom; ?>" style="text-transform:none;cursor:pointer;padding:6px;border-radius:8px;background:#fbfdff;border:1px solid rgba(11,89,216,0.03);align-items:center;">
                <div style="display:flex;align-items:center;gap:12px">
                  <strong style="color:#0b3b7a"><?php echo htmlspecialchars($secName, ENT_QUOTES); ?></strong>
                  <span class="section-count">&nbsp;&nbsp;•&nbsp;&nbsp;<?php echo $secInfo['count'] . ' student' . ($secInfo['count']===1 ? '' : 's'); ?></span>
                </div>
                <i class="material-icons collapse-icon">expand_less</i>
              </div>

              <div class="section-body" id="<?php echo $secDom; ?>_body" style="margin-top:8px;">
                <table class="highlight responsive-table section-table">
                  <thead>
                    <tr>
                      <th style="width:48px;"><label><input type="checkbox" class="check-section-all" data-section="<?php echo $secDom; ?>"><span></span></label></th>
                      <th>Photo</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Suffix</th>
                      <th>Student ID</th><th>Section</th><th>Birthday</th><th>Sex</th><th>Teacher</th><th style="width:120px;">Edit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($secInfo['students'] as $st): echo render_student_row_html($st); endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Modal: Edit Super Admin -->
<div id="modalEditSuper" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;">Edit Your Profile</h5>
    <div style="height:4px;background:var(--air-blue);width:100%;border-radius:4px;margin:8px 0 14px;"></div>
    <form method="post" enctype="multipart/form-data" id="formEditSuper">
      <input type="hidden" name="action" value="edit_super">
      <div class="field-row" style="align-items:center;margin-bottom:10px;">
        <div style="flex:0 0 120px; text-align:center;">
          <img id="superPreview" src="<?php echo htmlspecialchars($super_admin['avatar']); ?>" class="id-photo" alt="photo" style="width:90px;height:90px;border-radius:50%;">
          <div style="margin-top:8px;">
            <label class="id-photo-button" style="border-radius:8px;">
              Upload
              <input id="super_avatar" name="super_avatar" type="file" accept="image/*">
            </label>
            <div id="superFileNote" class="file-note">Allowed: JPG, PNG, GIF, WEBP — max 2 MB.</div>
          </div>
        </div>
        <div style="flex:1;">
          <div class="input-field">
            <input id="super_name" name="super_name" value="<?php echo htmlspecialchars($super_admin['name']); ?>" required>
            <label class="active" for="super_name">Display Name</label>
          </div>
          <div class="input-field">
            <input id="super_password" name="super_password" type="password" autocomplete="new-password">
            <label for="super_password">New Password (leave blank to keep)</label>
          </div>
        </div>
      </div>
      <div class="right-align"><button class="btn-air" type="submit" id="saveSuperBtn">Save Profile</button></div>
    </form>
  </div>
</div>

<!-- Add Instructor Modal -->
<div id="modalAddInstructor" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;">Add Instructor</h5>
    <div style="height:4px;background:var(--air-blue);width:100%;border-radius:4px;margin:8px 0 14px;"></div>
    <form method="post" enctype="multipart/form-data" id="formAddInstructor">
      <input type="hidden" name="action" value="add_instructor">
      <div class="field-row" style="align-items:center;margin-bottom:10px;">
        <div style="flex:0 0 120px; text-align:center;">
          <img id="instructorAddPreview" src="assets/avatar.png" class="id-photo" alt="photo" style="width:90px;height:90px;border-radius:50%;">
          <div style="margin-top:8px;">
            <label class="id-photo-button" style="border-radius:8px;">
              Upload
              <input id="instructor_avatar" name="instructor_avatar" type="file" accept="image/*">
            </label>
            <div id="inFileNote" class="file-note">Allowed: JPG, PNG, GIF, WEBP — max 2 MB.</div>
          </div>
        </div>
        <div style="flex:1;">
          <div class="input-field">
            <input id="in_acc_id" name="acc_id" required>
            <label class="active" for="in_acc_id">Account ID</label>
          </div>
          <div class="input-field">
            <input id="in_acc_name" name="acc_name" required>
            <label class="active" for="in_acc_name">Full Name</label>
          </div>
          <div class="input-field">
            <input id="in_password" name="password" type="password" required>
            <label for="in_password">Password</label>
          </div>
        </div>
      </div>
      <div class="right-align"><button class="btn-air" type="submit">Create Instructor</button></div>
    </form>
  </div>
</div>

<!-- Edit Instructor Modal (unchanged except file-note) -->
<div id="modalEditInstructor" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;">Edit Instructor</h5>
    <div style="height:4px;background:var(--air-blue);width:100%;border-radius:4px;margin:8px 0 14px;"></div>
    <form method="post" enctype="multipart/form-data" id="formEditInstructor">
      <input type="hidden" name="action" value="edit_instructor">
      <input type="hidden" name="admin_id" id="edit_admin_id">
      <div class="field-row" style="align-items:center;margin-bottom:10px;">
        <div style="flex:0 0 120px; text-align:center;">
          <img id="instructorEditPreview" src="assets/avatar.png" class="id-photo" alt="photo" style="width:90px;height:90px;border-radius:50%;">
          <div style="margin-top:8px;">
            <label class="id-photo-button" style="border-radius:8px;">
              Change
              <input id="edit_instructor_avatar" name="edit_instructor_avatar" type="file" accept="image/*">
            </label>
            <div id="editInFileNote" class="file-note">Allowed: JPG, PNG, GIF, WEBP — max 2 MB.</div>
          </div>
        </div>
        <div style="flex:1;">
          <div class="input-field">
            <input id="edit_acc_id" name="edit_acc_id" required>
            <label class="active" for="edit_acc_id">Account ID</label>
          </div>
          <div class="input-field">
            <input id="edit_acc_name" name="acc_name" required>
            <label class="active" for="edit_acc_name">Account Display Name</label>
          </div>
          <div class="input-field">
            <input id="edit_display_name" name="display_name" required>
            <label class="active" for="edit_display_name">Instructor Name</label>
          </div>
          <div class="input-field">
            <input id="edit_password" name="edit_password" type="password" autocomplete="new-password">
            <label for="edit_password">New Password (leave blank to keep)</label>
          </div>
        </div>
      </div>
      <div class="right-align"><button class="btn-air" type="submit">Save</button></div>
    </form>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addStudentModal" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;">Add Student</h5>
    <div style="height:4px;background:var(--air-blue);width:100%;border-radius:4px;margin:8px 0 14px;"></div>
    <form method="post" enctype="multipart/form-data" id="addForm">
      <input type="hidden" name="action" value="add_student">
      <div class="field-row" style="align-items:center;margin-bottom:10px;">
        <div style="flex:0 0 120px; text-align:center;">
          <img id="addPreview" src="assets/avatar.png" class="id-photo" alt="photo">
          <div style="margin-top:8px;">
            <label class="id-photo-button" style="border-radius:8px;">
              Upload
              <input id="addStudentPhoto" type="file" name="student_photo" accept="image/*">
            </label>
            <div id="addFileNote" class="file-note">Allowed: JPG, PNG, GIF, WEBP — max 2 MB.</div>
          </div>
        </div>
        <div style="flex:0 0 240px; margin-left:8px;">
          <div class="input-field">
            <input id="addStudentID" name="student_id_val" type="text" required>
            <label for="addStudentID" class="required-star">Student ID</label>
          </div>
        </div>
        <div style="flex:1; margin-left:8px;">
          <div class="input-field"><input id="addLastName" name="last_name" type="text" required><label for="addLastName">Last Name</label></div>
        </div>
        <div style="flex:1; margin-left:8px;">
          <div class="input-field"><input id="addFirstName" name="first_name" type="text" required><label for="addFirstName">First Name</label></div>
        </div>
      </div>

      <div class="field-row" style="margin-bottom:10px;">
        <div style="flex:1"><div class="input-field"><input id="addMiddleName" name="middle_name" type="text"><label for="addMiddleName">Middle Name (optional)</label></div></div>
        <div style="flex:0 0 180px"><div class="input-field"><input id="addSuffix" name="suffix" type="text"><label for="addSuffix">Suffix</label></div></div>
      </div>

      <div class="input-field" style="margin-bottom:10px;">
        <input id="addSection" name="section" type="text"><label for="addSection">Section</label>
      </div>

      <div class="field-row" style="gap:12px;align-items:center;margin-bottom:8px;">
        <div style="flex:0 0 280px;">
          <div class="input-field"><input id="addBirthday" name="birthday" type="date"><label class="active" for="addBirthday">Birthday</label></div>
        </div>
        <div style="flex:0 0 160px;">
          <label for="addSex" style="display:block;margin-bottom:6px;color:#475b7a;font-weight:600">Sex <span style="color:#d32f2f">*</span></label>
          <select id="addSex" name="sex" class="browser-default" required>
            <option value="">Select</option>
            <option value="M">M</option>
            <option value="F">F</option>
          </select>
        </div>
      </div>

      <div style="margin-bottom:8px;">
        <label class="active" for="addTeacherSelect" style="display:block;margin-bottom:6px;color:#475b7a;font-weight:600">Assign Teacher</label>
        <select id="addTeacherSelect" name="teacher_id" class="browser-default">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="right-align" style="margin-top:12px;">
        <button class="btn-air" type="submit">Add Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Student Modal (unchanged except file-note) -->
<div id="editStudentModal" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;">Edit Student Information</h5>
    <div style="height:4px;background:var(--air-blue);width:100%;border-radius:4px;margin:8px 0 14px;"></div>
    <div class="id-card" style="margin-bottom:12px;">
      <img id="editPreview" src="assets/avatar.png" class="id-photo" alt="photo">
      <div class="id-info">
        <h3 id="editName">Student Name</h3>
        <p><strong>ID:</strong> <span id="editIDLabel">—</span></p>
        <p id="editSectionLabel" style="margin-top:6px;color:var(--muted)"></p>
        <div style="margin-top:8px;">
          <span class="id-photo-button" style="border-radius:8px;cursor:pointer;display:inline-block;">
            Change Photo
            <input id="editStudentPhoto" name="edit_student_photo" type="file" accept="image/*">
          </span>
          <div id="editStudentFileNote" class="file-note">Allowed: JPG, PNG, GIF, WEBP — max 2 MB.</div>
          <small style="display:block;margin-top:6px;color:#666;">Choose a new avatar below after opening the form.</small>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" id="edit_db_id" name="db_id" value="">
      <input type="hidden" id="reset_db_id" name="reset_db_id" value="">

      <div class="input-field" style="margin-top:6px;">
        <input id="editStudentID" name="edit_student_id" type="text" required>
        <label for="editStudentID" class="required-star">Student ID</label>
      </div>
      <div class="input-field">
        <input id="editLastName" name="edit_last_name" type="text" required>
        <label for="editLastName">Last Name</label>
      </div>
      <div class="input-field">
        <input id="editFirstName" name="edit_first_name" type="text" required>
        <label for="editFirstName">First Name</label>
      </div>
      <div class="field-row" style="gap:12px;align-items:flex-start;">
        <div style="flex:1;"><div class="input-field"><input id="editMiddleName" name="edit_middle_name" type="text"><label for="editMiddleName">Middle Name (optional)</label></div></div>
        <div style="flex:0 0 180px;"><div class="input-field"><input id="editSuffix" name="edit_suffix" type="text"><label for="editSuffix">Suffix</label></div></div>
      </div>

      <div class="input-field">
        <input id="editSectionInput" name="edit_section" type="text">
        <label for="editSectionInput">Section</label>
      </div>

      <div class="field-row" style="gap:12px;align-items:center;">
        <div style="flex:0 0 220px;">
          <div class="input-field"><input id="editBirthday" name="edit_birthday" type="date"><label class="active" for="editBirthday">Birthday</label></div>
        </div>
        <div style="flex:0 0 140px;">
          <label for="editSex" style="display:block;margin-bottom:6px;color:#475b7a;font-weight:600">Sex <span style="color:#d32f2f">*</span></label>
          <select id="editSex" name="edit_sex" class="browser-default" required>
            <option value="">Select</option>
            <option value="M">M</option>
            <option value="F">F</option>
          </select>
        </div>
      </div>

      <div class="input-field" style="margin-top:12px;">
        <label class="active" for="editTeacherSelect" style="display:block;margin-bottom:10px;color:#475b7a;font-weight:600">Assign Teacher</label>
        <select id="editTeacherSelect" name="edit_teacher_id" class="browser-default">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="confirm-row" style="margin-top:12px;"><label><input type="checkbox" id="editConfirmCheckbox"><span>I confirm I want to save these changes</span></label></div>

      <div style="margin-top:12px;" class="divider"></div>

      <div style="margin-top:12px;">
        <label><input type="checkbox" id="enableResetCheckbox"><span>Enable password reset</span></label>
        <div style="margin-top:8px;">
          <button type="button" id="resetPwdBtn" class="btn-danger" style="display:none;">Reset password to birthday</button>
          <div style="margin-top:6px;"><small class="grey-text">Sets account password to birthday (YYYY-MM-DD).</small></div>
        </div>
      </div>

      <div class="right-align" style="margin-top:12px;"><button class="btn-air" type="submit" id="saveEditBtn" disabled>Save changes</button></div>
    </form>
  </div>
</div>

<!-- Delete modal -->
<div id="deleteConfirmModal" class="modal">
  <div class="modal-content">
    <h5>Delete Selected Students</h5>
    <p id="deleteConfirmText">You are about to delete <strong id="deleteCount">0</strong> student(s).</p>
    <div id="deleteNamesContainer"><small class="grey-text">Selected students:</small><ul id="deleteNamesList"></ul></div>
    <div class="confirm-row"><label><input type="checkbox" id="deleteConfirmCheckbox"><span>I confirm I want to permanently delete the selected student(s)</span></label></div>

    <form method="post" id="deleteConfirmForm"><input type="hidden" name="action" value="delete_selected"><div id="deleteHiddenInputs"></div>
      <div class="right-align" style="margin-top:16px;"><button type="button" class="btn grey modal-close" id="cancelDeleteBtn">Cancel</button> <button type="submit" class="btn-danger" id="confirmDeleteBtn" disabled>Delete</button></div>
    </form>
  </div>
</div>

<!-- scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  try { M.Modal.init(document.querySelectorAll('.modal'), {preventScrolling:true}); } catch(e) {}
  try { M.Tooltip.init(document.querySelectorAll('.tooltipped')); } catch(e) {}

  // initialize selects (browser-default used mostly)
  try {
    var selectEls = document.querySelectorAll('select:not(.browser-default)');
    selectEls.forEach(function(s){ if (!M.FormSelect.getInstance(s)) M.FormSelect.init(s); });
  } catch(e){}

  // collapse behavior (for teacher cards and each section)
  var headers = Array.from(document.querySelectorAll('.section-header'));
  var COLL_KEY = 'students_sections_collapsed_v3';
  var collapsedState = {};
  try{ collapsedState = JSON.parse(localStorage.getItem(COLL_KEY)) || {}; }catch(e){ collapsedState = {}; }
  var DEFAULT_COLLAPSED = true;
  if (Object.keys(collapsedState).length === 0) headers.forEach(function(h){ collapsedState[h.getAttribute('data-section')] = DEFAULT_COLLAPSED; });

  headers.forEach(function(h){
    var sec = h.getAttribute('data-section');
    var body = document.getElementById(sec + '_body');
    var wrap = document.getElementById(sec + '_wrap');
    var icon = h.querySelector('.collapse-icon');
    if (!body) return;
    if (collapsedState[sec]) { body.classList.add('collapsed'); if (wrap) wrap.classList.add('collapsed'); if (icon) icon.style.transform='rotate(-90deg)'; }
    h.addEventListener('click', function(){
      try {
        var isCollapsed = body.classList.toggle('collapsed');
        if (wrap) wrap.classList.toggle('collapsed', isCollapsed);
        if (icon) icon.style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        collapsedState[sec] = isCollapsed;
        localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState));
        updateToggleBtnLabel();
      } catch (err) {}
    });
    var sectionCheck = wrap ? wrap.querySelector('.check-section-all') : null;
    if (sectionCheck) sectionCheck.addEventListener('change', function(){ var checked=this.checked; (wrap||document).querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; }); });
  });

  function areAllCollapsed(){ if (!headers.length) return false; var all=true; headers.forEach(function(h){ if (!collapsedState[h.getAttribute('data-section')]) all=false; }); return all; }
  var toggleBtn = document.getElementById('toggleCollapseBtn');
  function updateToggleBtnLabel(pulse){ var allCollapsed = areAllCollapsed(); if (toggleBtn) toggleBtn.textContent = allCollapsed ? 'Expand All' : 'Collapse All'; if(pulse && toggleBtn){ toggleBtn.classList.remove('btn-pulse'); void toggleBtn.offsetWidth; toggleBtn.classList.add('btn-pulse'); } }
  function setAllSectionsCollapsed(collapsed){ headers.forEach(function(h){ var sec=h.getAttribute('data-section'); var body=document.getElementById(sec + '_body'); var wrap=document.getElementById(sec + '_wrap'); var icon=h.querySelector('.collapse-icon'); if (!body) return; if(collapsed){ body.classList.add('collapsed'); if (wrap) wrap.classList.add('collapsed'); if(icon)icon.style.transform='rotate(-90deg)'; } else { body.classList.remove('collapsed'); if (wrap) wrap.classList.remove('collapsed'); if(icon)icon.style.transform='rotate(0deg)'; } collapsedState[sec]=collapsed; }); try{ localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); }catch(e){} updateToggleBtnLabel(true); }
  updateToggleBtnLabel(false);
  if (toggleBtn) toggleBtn.addEventListener('click', function(){ var toCollapse = !areAllCollapsed(); setAllSectionsCollapsed(toCollapse); });

  // open modals
  document.getElementById('btnAddInstructor').addEventListener('click', function(){
    try { M.Modal.getInstance(document.getElementById('modalAddInstructor')).open(); } catch(e){ document.getElementById('modalAddInstructor').style.display='block'; }
  });
  document.getElementById('btnAddStudent').addEventListener('click', function(){
    try { M.Modal.getInstance(document.getElementById('addStudentModal')).open(); } catch(e){ document.getElementById('addStudentModal').style.display='block'; }
  });

  // CLIENT-SIDE IMAGE VALIDATION SETTINGS (mirrors server)
  var MAX_BYTES = <?php echo MAX_AVATAR_BYTES; ?>;
  var ALLOWED_PREFIX = ['image/']; // accept any image/* but server limits to types

  function clientValidateFile(file) {
    if (!file) return { ok: true }; // no file -> okay
    if (file.size > MAX_BYTES) return { ok: false, msg: 'File is too large. Max ' + (MAX_BYTES/(1024*1024)) + ' MB.' };
    if (!file.type || !ALLOWED_PREFIX.some(function(p){ return file.type.indexOf(p) === 0; })) return { ok:false, msg: 'File is not an image.' };
    // allow, but note server restricts to certain image types (jpeg/png/gif/webp)
    return { ok: true };
  }

  // preview + validation helper
  function attachPreviewAndValidation(fileInputId, previewImgId, noteId, submitBtnSelector) {
    var fi = document.getElementById(fileInputId);
    var preview = document.getElementById(previewImgId);
    var note = document.getElementById(noteId);
    var submitBtn = submitBtnSelector ? document.querySelector(submitBtnSelector) : null;
    if (!fi) return;
    fi.addEventListener('change', function(){
      var f = this.files && this.files[0];
      var res = clientValidateFile(f);
      if (!res.ok) {
        if (note) { note.style.display = 'block'; note.textContent = res.msg; }
        try { M.toast({html: res.msg}); } catch(e) {}
        this.value = '';
        if (preview) preview.src = preview.getAttribute('data-original') || 'assets/avatar.png';
        if (submitBtn) submitBtn.disabled = true;
        return;
      } else {
        if (note) { note.style.display = 'none'; }
        if (submitBtn) submitBtn.disabled = false;
      }
      if (!f) return;
      if (!f.type.startsWith('image/')) { this.value=''; if (note){note.style.display='block'; note.textContent='Not an image';} return; }
      var r = new FileReader();
      r.onload = function(e){
        if (preview) preview.src = e.target.result;
      };
      r.readAsDataURL(f);
    }, false);
  }

  // Setup previews + validation for all avatar inputs
  attachPreviewAndValidation('super_avatar', 'superPreview', 'superFileNote', '#saveSuperBtn');
  attachPreviewAndValidation('instructor_avatar', 'instructorAddPreview', 'inFileNote', null);
  attachPreviewAndValidation('edit_instructor_avatar', 'instructorEditPreview', 'editInFileNote', null);
  attachPreviewAndValidation('addStudentPhoto', 'addPreview', 'addFileNote', null);
  attachPreviewAndValidation('editStudentPhoto', 'editPreview', 'editStudentFileNote', null);

  // instructor avatar preview handlers (redundant safe)
  var inAvatar = document.getElementById('instructor_avatar');
  if (inAvatar) inAvatar.addEventListener('change', function(){ /* handled above */ });
  var editInAvatar = document.getElementById('edit_instructor_avatar');
  if (editInAvatar) editInAvatar.addEventListener('change', function(){ /* handled above */ });

  // Add Student image preview (handled above)
  var addPhoto = document.getElementById('addStudentPhoto');

  // edit student open/populate
  function openEditModal(btn){
    try {
      var dbId = btn.getAttribute('data-db-id');
      var last = btn.getAttribute('data-last') || '';
      var first = btn.getAttribute('data-first') || '';
      var middle = btn.getAttribute('data-middle') || '';
      var suffix = btn.getAttribute('data-suffix') || '';
      var studentid = btn.getAttribute('data-studentid') || '';
      var section = btn.getAttribute('data-section') || '';
      var birthday = btn.getAttribute('data-birthday') || '';
      var sex = btn.getAttribute('data-sex') || '';
      var teacher = btn.getAttribute('data-teacher') || '';
      var avatar = btn.getAttribute('data-avatar') || 'assets/avatar.png';

      var setIf = function(id, value){ var el=document.getElementById(id); if (!el) return; el.value = value; };

      setIf('edit_db_id', dbId);
      setIf('editLastName', last);
      setIf('editFirstName', first);
      setIf('editMiddleName', middle);
      setIf('editSuffix', suffix);
      setIf('editStudentID', studentid);
      setIf('editSectionInput', section || '');
      setIf('editBirthday', birthday);

      var editPreview = document.getElementById('editPreview');
      if (editPreview) editPreview.src = avatar;

      var editNameEl = document.getElementById('editName');
      if (editNameEl) editNameEl.textContent = (last || first) ? (last + (first ? ', ' + first : '')) : 'Student Name';

      var editIdEl = document.getElementById('editIDLabel');
      if (editIdEl) editIdEl.textContent = studentid || '—';

      var editSectionLabel = document.getElementById('editSectionLabel');
      if (editSectionLabel) editSectionLabel.textContent = section ? ('Section: ' + section) : '';

      var editSexEl = document.getElementById('editSex');
      if (editSexEl) editSexEl.value = (sex === 'M' || sex === 'F') ? sex : '';

      var editTeacherEl = document.getElementById('editTeacherSelect');
      if (editTeacherEl) editTeacherEl.value = teacher || '';

      try { M.updateTextFields(); } catch(e){}

      var confirm = document.getElementById('editConfirmCheckbox'); if (confirm) confirm.checked = false;
      var saveBtn = document.getElementById('saveEditBtn'); if (saveBtn) saveBtn.disabled = true;

      var resetDbInput = document.getElementById('reset_db_id'); if (resetDbInput) resetDbInput.value = dbId;
      var enReset = document.getElementById('enableResetCheckbox'); if (enReset) enReset.checked = false;
      var resetBtn = document.getElementById('resetPwdBtn'); if (resetBtn) { resetBtn.style.display = 'none'; resetBtn.disabled = true; }

      var modal = document.getElementById('editStudentModal');
      try { M.Modal.getInstance(modal).open(); } catch(e){ if (modal) modal.style.display = 'block'; }
      setTimeout(function(){ try{ document.getElementById('editFirstName').focus(); }catch(e){} }, 200);
    } catch (err) { console.error('openEditModal error', err); }
  }

  Array.from(document.querySelectorAll('.edit-btn')).forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); openEditModal(this); }); });

  // edit/instructor modal helpers
  window.openEditInstructorModal = function(admin_id, acct_name, acc_id) {
    document.getElementById('edit_admin_id').value = admin_id;
    document.getElementById('edit_acc_id').value = acc_id;
    document.getElementById('edit_acc_name').value = acct_name;
    document.getElementById('edit_display_name').value = acct_name;
    try { M.updateTextFields(); M.Modal.getInstance(document.getElementById('modalEditInstructor')).open(); } catch(e){ document.getElementById('modalEditInstructor').style.display='block'; }
  };

  window.confirmDeleteInstructor = function(admin_id, name) {
    if (confirm('Delete instructor \"' + name + '\"? This will unassign their students.')) {
      var f = document.createElement('form'); f.method='post'; f.style.display='none';
      var a = document.createElement('input'); a.name='action'; a.value='delete_instructor'; f.appendChild(a);
      var i = document.createElement('input'); i.name='admin_id'; i.value=admin_id; f.appendChild(i);
      document.body.appendChild(f); f.submit();
    }
  };

  // delete selected students flow
  var delBtn = document.getElementById('deleteSelectedBtn');
  if (delBtn) delBtn.addEventListener('click', function(e){
    e.preventDefault();
    var checkedEls = Array.from(document.querySelectorAll('.chk:checked'));
    if (!checkedEls.length) { try{ M.toast({html:'Select at least one student to delete.'}); }catch(e){} return; }
    var items = checkedEls.map(function(ch){
      var row = ch.closest('tr');
      var last = row.querySelector('.cell-last') ? row.querySelector('.cell-last').textContent.trim() : '';
      var first = row.querySelector('.cell-first') ? row.querySelector('.cell-first').textContent.trim() : '';
      var middle = row.querySelector('.cell-middle') ? row.querySelector('.cell-middle').textContent.trim() : '';
      var suffix = row.querySelector('.cell-suffix') ? row.querySelector('.cell-suffix').textContent.trim() : '';
      var imgEl = row.querySelector('.table-avatar-img');
      var avatar = (imgEl && imgEl.src) ? imgEl.src : 'assets/avatar.png';
      var displayName = last + (first ? ', ' + first : '') + (middle ? ' ' + middle : '') + (suffix ? ' ' + suffix : '');
      return { id: ch.getAttribute('data-id'), name: displayName || '(unknown)', avatar: avatar };
    });

    document.getElementById('deleteCount').textContent = items.length;
    var namesList = document.getElementById('deleteNamesList'); namesList.innerHTML = '';
    items.forEach(function(it){ var li=document.createElement('li'); var img=document.createElement('img'); img.src=it.avatar; img.className='delete-list-avatar'; img.onerror=function(){this.src='assets/avatar.png'}; var span=document.createElement('span'); span.textContent=it.name; li.appendChild(img); li.appendChild(span); namesList.appendChild(li); });

    var hid = document.getElementById('deleteHiddenInputs'); hid.innerHTML = '';
    items.forEach(function(it){ var i=document.createElement('input'); i.type='hidden'; i.name='delete_ids[]'; i.value=it.id; hid.appendChild(i); });

    var chk = document.getElementById('deleteConfirmCheckbox'); if (chk) chk.checked = false;
    var confirmBtn = document.getElementById('confirmDeleteBtn'); if (confirmBtn) confirmBtn.disabled = true;
    try { M.Modal.getInstance(document.getElementById('deleteConfirmModal')).open(); } catch (err) { document.getElementById('deleteConfirmModal').style.display='block'; }
    setTimeout(function(){ try{ document.getElementById('deleteConfirmCheckbox').focus(); }catch(e){} }, 220);
  });

  // delete confirm checkbox
  var delChk = document.getElementById('deleteConfirmCheckbox');
  if (delChk) delChk.addEventListener('change', function(){ var btn = document.getElementById('confirmDeleteBtn'); if (btn) btn.disabled = !this.checked; });
  var delForm = document.getElementById('deleteConfirmForm');
  if (delForm) delForm.addEventListener('submit', function(e){ if (!document.getElementById('deleteConfirmCheckbox').checked) { e.preventDefault(); try{ M.toast({html:'Please confirm before deleting.'}); }catch(e){} return; } });

  // edit student confirm enabling
  var editConfirm = document.getElementById('editConfirmCheckbox');
  if (editConfirm) editConfirm.addEventListener('change', function(){ var sb=document.getElementById('saveEditBtn'); if (sb) sb.disabled = !this.checked; });

  // reset password toggle + action
  var enableResetCheckbox = document.getElementById('enableResetCheckbox');
  var resetPwdBtn = document.getElementById('resetPwdBtn');
  if (enableResetCheckbox && resetPwdBtn) {
    enableResetCheckbox.addEventListener('change', function() {
      if (this.checked) { resetPwdBtn.style.display = ''; resetPwdBtn.disabled = false; } else { resetPwdBtn.style.display = 'none'; resetPwdBtn.disabled = true; }
    });
  }
  if (resetPwdBtn) {
    resetPwdBtn.addEventListener('click', function(e) {
      var birthdayField = document.getElementById('editBirthday');
      if (!birthdayField || (birthdayField.value || '').trim() === '') { try{ M.toast({html: 'Cannot reset password: student has no birthday recorded.'}); }catch(e){} return; }
      var confirmMsg = 'Reset this student password to their birthday (' + birthdayField.value + ')? This will overwrite the existing password.';
      if (!confirm(confirmMsg)) return;
      var dbId = (document.getElementById('reset_db_id') && document.getElementById('reset_db_id').value) || (document.getElementById('edit_db_id') && document.getElementById('edit_db_id').value);
      if (!dbId) { try{ M.toast({html:'Student ID missing; cannot reset.'}); }catch(e){} return; }
      var f = document.createElement('form'); f.method='post'; f.action='super_admin.php';
      var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='reset_password'; f.appendChild(a);
      var i = document.createElement('input'); i.type='hidden'; i.name='db_id'; i.value = dbId; f.appendChild(i);
      document.body.appendChild(f); f.submit();
    });
  }

  // helper to clear and attach edit file input (keeps existing behavior safe)
  window.clearAndAttachEditFileInput = function() {
    try {
      var existing = document.getElementById('editStudentPhoto');
      if (existing) {
        // already inline in visible button area; nothing to replace
        existing.addEventListener('change', function(){
          var f = this.files && this.files[0];
          if (!f) return;
          var validation = clientValidateFile(f);
          if (!validation.ok) { this.value=''; try { M.toast({html:validation.msg}); } catch(e){} return; }
          if (!f.type.startsWith('image/')) { this.value=''; return; }
          var r = new FileReader();
          r.onload = function(ev){ var preview = document.getElementById('editPreview'); if (preview) preview.src = ev.target.result; };
          r.readAsDataURL(f);
        }, false);
      }
    } catch (err) { console.warn('clearAndAttachEditFileInput error', err); }
  };

});
</script>
</body>
</html>

<?php
// close DB connections
if ($acc_db_ok && isset($acc_conn) && $acc_conn instanceof mysqli) $acc_conn->close();
if (isset($conn) && $conn instanceof mysqli) $conn->close();
?>

