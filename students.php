<?php
// students.php (patched to use "admins" table for teacher lookups)
// Requires: students table (airlines DB) and accounts table (account DB)
// Note: teachers (admins) are stored in the "admins" table in the accounts DB.
// Student user accounts are still created/managed in the "accounts" table.

session_start();

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'airlines';   // students DB
$acc_db_name = 'account'; // accounts DB (separate database)
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

// ---------- CONNECT ----------
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('DB Connection failed: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');

$acc_conn = @new mysqli($db_host, $db_user, $db_pass, $acc_db_name);
$acc_db_ok = !($acc_conn->connect_error);
if ($acc_db_ok) $acc_conn->set_charset('utf8mb4');

// ---------- AUTH HELPERS ----------
/**
 * Ensure session is started and map common session keys to the ones this page expects.
 */
function require_login() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!empty($_SESSION['acc_id'])) return;

    $accIdCandidates = ['acc_id','admin_id','user_id','ad_id','account_id','id'];
    foreach ($accIdCandidates as $c) {
        if (!empty($_SESSION[$c])) {
            $_SESSION['acc_id'] = $_SESSION[$c];
            break;
        }
    }

    $roleCandidates = ['acc_role','role','user_role','admin_role','ad_role'];
    foreach ($roleCandidates as $r) {
        if (!empty($_SESSION[$r]) && empty($_SESSION['acc_role'])) {
            $_SESSION['acc_role'] = $_SESSION[$r];
            break;
        }
    }

    if (empty($_SESSION['acc_name'])) {
        $nameCandidates = ['name','acc_name','username','user_name','admin_name'];
        foreach ($nameCandidates as $n) {
            if (!empty($_SESSION[$n])) {
                $_SESSION['acc_name'] = $_SESSION[$n];
                break;
            }
        }
    }

    if (!empty($_SESSION['acc_id'])) return;

    header('Location: login_page.php');
    exit;
}

function is_super_admin() {
    return (isset($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'super_admin');
}
function is_admin() {
    return (isset($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin');
}
function is_teacher() {
    return is_admin();
}
function current_acc_id() {
    return $_SESSION['acc_id'] ?? null;
}

// Protect page
require_login();

// ---------- HELPERS ----------
function handle_avatar_upload($input_name, $existing = null) {
    global $uploads_dir;
    if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['tmp_name'])) return $existing;
    $f = $_FILES[$input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) return $existing;
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    $filename = uniqid('avatar_', true) . '.' . ($safe_ext ?: 'jpg');
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($f['tmp_name'], $dest)) return 'uploads/' . $filename;
    return $existing;
}

function validate_date_not_future($date_str) {
    if ($date_str === '' || $date_str === null) return true;
    $d = DateTime::createFromFormat('Y-m-d', $date_str);
    if (!$d || $d->format('Y-m-d') !== $date_str) return false;
    $today = new DateTime('today');
    return $d <= $today;
}

function ensure_unique_acc_id($dbconn, $desired) {
    $candidate = $desired;
    $i = 0;
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
    $teacher = $st['teacher_id'] ? htmlspecialchars($st['teacher_id'], ENT_QUOTES) : '';

    $avatar = trim((string)($st['avatar'] ?? ''));
    if ($avatar === '' || strtolower($avatar) === 'null') $avatar = 'assets/avatar.png';
    else if (strpos($avatar, '/') === 0) $avatar = ltrim($avatar, '/');
    if (!is_file(__DIR__ . '/' . $avatar)) $avatar = 'assets/avatar.png';
    $avatar_html = htmlspecialchars($avatar, ENT_QUOTES);

    ob_start();
    ?>
<tr data-db-id="<?php echo $id; ?>">
  <td><label><input type="checkbox" class="filled-in chk" data-id="<?php echo $id; ?>"><span></span></label></td>
  <td><img src="<?php echo $avatar_html; ?>" alt="avatar" class="table-avatar-img" onerror="this.onerror=null;this.src='assets/avatar.png';" /></td>
  <td class="cell-last"><?php echo $last; ?></td>
  <td class="cell-first"><?php echo $first; ?></td>
  <td class="cell-middle"><?php echo $middle; ?></td>
  <td class="cell-suffix"><?php echo $suffix; ?></td>
  <td class="cell-studentid"><?php echo $student_id; ?></td>
  <td class="cell-section"><?php echo $section; ?></td>
  <td class="cell-birthday"><?php echo $birthday ?: '&mdash;'; ?></td>
  <td class="cell-sex"><?php echo $sex ?: '&mdash;'; ?></td>
  <td class="cell-teacher"><?php echo $teacher ?: '&mdash;'; ?></td>
  <td>
    <a class="btn-flat edit-btn tooltipped modal-trigger"
       href="#editStudentModal"
       data-db-id="<?php echo $id; ?>"
       data-last="<?php echo $last; ?>"
       data-first="<?php echo $first; ?>"
       data-middle="<?php echo $middle; ?>"
       data-suffix="<?php echo $suffix; ?>"
       data-studentid="<?php echo $student_id; ?>"
       data-section="<?php echo $section; ?>"
       data-avatar="<?php echo $avatar_html; ?>"
       data-birthday="<?php echo $birthday; ?>"
       data-sex="<?php echo $sex; ?>"
       data-teacher="<?php echo $teacher; ?>"
       data-position="top"
       data-tooltip="Edit">
      <i class="material-icons">edit</i>
    </a>
  </td>
</tr>
    <?php
    return ob_get_clean();
}

// ---------- PROCESS POSTS ----------
$allowed_sex = ['', 'M', 'F', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // RESET PASSWORD to Birthday (separate safe action)
    if (!empty($_POST['action']) && $_POST['action'] === 'reset_password') {
        $db_id = intval($_POST['db_id'] ?? 0);

        // load student row
        $q = $conn->prepare("SELECT student_id, birthday FROM students WHERE id = ? LIMIT 1");
        if (!$q) {
            $_SESSION['students_errors'] = ['Database error while locating student.'];
            header('Location: students.php'); exit;
        }
        $q->bind_param('i', $db_id);
        $q->execute();
        $q->bind_result($stu_acc_id, $stu_birthday);
        $found = $q->fetch();
        $q->close();

        if (!$found) {
            $_SESSION['students_errors'] = ['Student record not found.'];
            header('Location: students.php'); exit;
        }

        // require accounts DB available
        if (!$acc_db_ok) {
            $_SESSION['students_errors'] = ['Accounts database not available. Cannot reset password.'];
            header('Location: students.php'); exit;
        }

        // require a non-empty birthday
        $stu_birthday = trim((string)$stu_birthday);
        if ($stu_birthday === '') {
            $_SESSION['students_errors'] = ['Cannot reset password: student has no birthday recorded.'];
            header('Location: students.php'); exit;
        }

        // ensure account exists
        $chk = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
        if (!$chk) {
            $_SESSION['students_errors'] = ['Accounts DB error.'];
            header('Location: students.php'); exit;
        }
        $chk->bind_param('s', $stu_acc_id);
        $chk->execute();
        $chk->bind_result($exists_acc);
        $exists = $chk->fetch() ? true : false;
        $chk->close();

        if (!$exists) {
            $_SESSION['students_errors'] = ['Account not found for this student (cannot reset).'];
            header('Location: students.php'); exit;
        }

        // perform reset: set password to the birthday string (YYYY-MM-DD)
        $new_hash = password_hash($stu_birthday, PASSWORD_DEFAULT);
        $upd = $acc_conn->prepare("UPDATE accounts SET password = ? WHERE acc_id = ?");
        if (!$upd) {
            $_SESSION['students_errors'] = ['Failed to prepare password update.'];
            header('Location: students.php'); exit;
        }
        $upd->bind_param('ss', $new_hash, $stu_acc_id);
        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            $_SESSION['students_errors'] = ['Failed to update account password.'];
            header('Location: students.php'); exit;
        }

        // success — show the new password (birthday) once
        $_SESSION['account_info'] = ['acc_id' => $stu_acc_id, 'password' => $stu_birthday];
        header('Location: students.php'); exit;
    }

    // ADD student
    if (!empty($_POST['action']) && $_POST['action'] === 'add_student') {
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $student_id_val = trim($_POST['student_id_val'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $sex = trim($_POST['sex'] ?? '');

        if (is_super_admin()) {
            $assigned_teacher = trim($_POST['teacher_id'] ?? '') ?: null;
        } elseif (is_teacher()) {
            $assigned_teacher = current_acc_id();
        } else {
            $assigned_teacher = null;
        }

        $errors = [];
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '' && !validate_date_not_future($birthday)) $errors[] = 'Birthday invalid or in the future.';
        if ($assigned_teacher && $acc_db_ok && is_super_admin()) {
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

        if (empty($errors)) {
            $avatar = handle_avatar_upload('student_photo', null);

            $stmt = $conn->prepare("INSERT INTO students (student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                $_SESSION['students_errors'] = ['Failed to prepare student insertion.'];
                header('Location: students.php'); exit;
            }
            $stmt->bind_param('ssssssssss', $student_id_val, $last, $first, $middle, $suffix, $section, $avatar, $birthday, $sex, $assigned_teacher);
            $ok = $stmt->execute();
            if (!$ok) {
                $stmt->close();
                $_SESSION['students_errors'] = ['Failed to insert student (DB error).'];
                header('Location: students.php'); exit;
            }
            $student_row_id = $stmt->insert_id;
            $stmt->close();

            if ($acc_db_ok) {
                try {
                    $desired_acc_id = $student_id_val;
                    $final_acc_id = ensure_unique_acc_id($acc_conn, $desired_acc_id);

                    $acc_name = trim($first . ' ' . $last);
                    $raw_password = $birthday !== '' ? $birthday : bin2hex(random_bytes(4));
                    $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
                    $acc_role = 'student';

                    $insAcc = $acc_conn->prepare("INSERT INTO accounts (acc_id, acc_name, password, acc_role) VALUES (?, ?, ?, ?)");
                    if (!$insAcc) {
                        $conn->query("DELETE FROM students WHERE id = " . intval($student_row_id));
                        $_SESSION['students_errors'] = ['Failed to prepare account insertion in accounts DB. Student insertion rolled back.'];
                        header('Location: students.php'); exit;
                    }
                    $insAcc->bind_param('ssss', $final_acc_id, $acc_name, $password_hash, $acc_role);
                    $insOk = $insAcc->execute();
                    $insAcc->close();

                    if (!$insOk) {
                        $conn->query("DELETE FROM students WHERE id = " . intval($student_row_id));
                        $_SESSION['students_errors'] = ['Failed to create account in accounts DB. Student insertion rolled back.'];
                        header('Location: students.php'); exit;
                    }

                    $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => $raw_password];

                    header('Location: students.php'); exit;

                } catch (Exception $e) {
                    $conn->query("DELETE FROM students WHERE id = " . intval($student_row_id));
                    $_SESSION['students_errors'] = ['Unexpected error while creating account. Student insertion rolled back.'];
                    header('Location: students.php'); exit;
                }
            } else {
                header('Location: students.php'); exit;
            }
        } else {
            $_SESSION['students_errors'] = $errors;
            header('Location: students.php'); exit;
        }
    }

    // EDIT student
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

        if (is_super_admin()) {
            $assigned_teacher = trim($_POST['edit_teacher_id'] ?? '') ?: null;
        } else if (is_teacher()) {
            $check = $conn->prepare("SELECT teacher_id FROM students WHERE id = ? LIMIT 1");
            $check->bind_param('i', $db_id);
            $check->execute();
            $check->bind_result($row_teacher);
            if (!$check->fetch() || $row_teacher !== current_acc_id()) {
                $check->close();
                $_SESSION['students_errors'] = ['Unauthorized action.'];
                header('Location: students.php'); exit;
            }
            $check->close();
            $assigned_teacher = current_acc_id();
        } else {
            $assigned_teacher = null;
        }

        $errors = [];
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '' && !validate_date_not_future($birthday)) $errors[] = 'Birthday invalid or in the future.';
        if ($assigned_teacher && $acc_db_ok && is_super_admin()) {
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

        if (empty($errors)) {
            $cur_avatar = null;
            $q = $conn->prepare("SELECT avatar, student_id, last_name, first_name FROM students WHERE id = ?");
            $q->bind_param('i', $db_id);
            $q->execute();
            $q->bind_result($cur_avatar, $old_student_id, $old_last, $old_first);
            $q->fetch();
            $q->close();

            $new_avatar = handle_avatar_upload('edit_student_photo', $cur_avatar);

            $upd = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, middle_name = ?, suffix = ?, section = ?, avatar = ?, birthday = ?, sex = ?, teacher_id = ?, updated_at = NOW() WHERE id = ?");
            $upd->bind_param('ssssssssssi', $student_id_val, $last, $first, $middle, $suffix, $section, $new_avatar, $birthday, $sex, $assigned_teacher, $db_id);
            $ok = $upd->execute();
            $upd->close();

            if (!$ok) {
                $_SESSION['students_errors'] = ['Failed to update student (DB error).'];
                header('Location: students.php'); exit;
            }

            if ($acc_db_ok) {
                try {
                    $check = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
                    $check->bind_param('s', $old_student_id);
                    $check->execute();
                    $check->bind_result($found_acc);
                    $exists = $check->fetch() ? true : false;
                    $check->close();

                    if ($exists) {
                        $final_acc_id = $student_id_val;
                        if ($old_student_id !== $student_id_val) {
                            $final_acc_id = ensure_unique_acc_id($acc_conn, $student_id_val);
                        }
                        if ($birthday !== '') {
                            $new_hash = password_hash($birthday, PASSWORD_DEFAULT);
                        } else {
                            $gethash = $acc_conn->prepare("SELECT password FROM accounts WHERE acc_id = ? LIMIT 1");
                            $gethash->bind_param('s', $old_student_id);
                            $gethash->execute();
                            $gethash->bind_result($existing_hash);
                            $gethash->fetch();
                            $gethash->close();
                            $new_hash = $existing_hash ?? password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                        }

                        $new_acc_name = trim($first . ' ' . $last);
                        $accUpd = $acc_conn->prepare("UPDATE accounts SET acc_id = ?, acc_name = ?, password = ? WHERE acc_id = ?");
                        $accUpd->bind_param('ssss', $final_acc_id, $new_acc_name, $new_hash, $old_student_id);
                        $accOk = $accUpd->execute();
                        $accUpd->close();

                        if (!$accOk) {
                            $rb = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, avatar = ? WHERE id = ?");
                            $rb->bind_param('ssssi', $old_student_id, $old_last, $old_first, $cur_avatar, $db_id);
                            $rb->execute();
                            $rb->close();

                            $_SESSION['students_errors'] = ['Failed to update account in accounts DB. Student update rolled back.'];
                            header('Location: students.php'); exit;
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
                        $insOk = $insAcc->execute();
                        $insAcc->close();

                        if (!$insOk) {
                            $rb = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, avatar = ? WHERE id = ?");
                            $rb->bind_param('ssssi', $old_student_id, $old_last, $old_first, $cur_avatar, $db_id);
                            $rb->execute();
                            $rb->close();

                            $_SESSION['students_errors'] = ['Failed to create account in accounts DB. Student update rolled back.'];
                            header('Location: students.php'); exit;
                        }
                        $_SESSION['account_info'] = ['acc_id' => $final_acc_id, 'password' => $raw_password];
                    }

                    header('Location: students.php'); exit;

                } catch (Exception $e) {
                    $rb = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, avatar = ? WHERE id = ?");
                    $rb->bind_param('ssssi', $old_student_id, $old_last, $old_first, $cur_avatar, $db_id);
                    $rb->execute();
                    $rb->close();

                    $_SESSION['students_errors'] = ['Unexpected error while syncing with accounts DB. Student update rolled back.'];
                    header('Location: students.php'); exit;
                }
            } else {
                header('Location: students.php'); exit;
            }
        } else {
            $_SESSION['students_errors'] = $errors;
            header('Location: students.php'); exit;
        }
    }

    // DELETE selected (bulk)
    if (!empty($_POST['action']) && $_POST['action'] === 'delete_selected') {
        $ids = $_POST['delete_ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $clean = array_map('intval', $ids);
            $in = implode(',', $clean);

            if (is_teacher()) {
                $teacher_esc = $conn->real_escape_string(current_acc_id());
                $res = $conn->query("SELECT id, avatar, student_id FROM students WHERE id IN ($in) AND teacher_id = '{$teacher_esc}'");
            } elseif (is_super_admin()) {
                $res = $conn->query("SELECT id, avatar, student_id FROM students WHERE id IN ($in)");
            } else {
                $res = false;
            }

            $accIdsToDelete = [];
            if ($res) {
                $idsToDelete = [];
                while ($r = $res->fetch_assoc()) {
                    $idsToDelete[] = intval($r['id']);
                    if (!empty($r['avatar']) && strpos($r['avatar'], 'uploads/') === 0) {
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

        header('Location: students.php'); exit;
    }
}

// ---------- FETCH teachers list (only for super_admin) ----------
$teachers_list = [];
if (is_super_admin() && $acc_db_ok) {
    $r = $acc_conn->query("SELECT acc_id, name FROM admins WHERE role = 'admin' ORDER BY name");
    if ($r) {
        while ($row = $r->fetch_assoc()) $teachers_list[] = $row;
        $r->free();
    }
}

// ---------- FETCH students ----------
$students = [];
if (is_super_admin()) {
    $sql = "SELECT id, student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id FROM students ORDER BY COALESCE(NULLIF(section,''),'~'), last_name, first_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
} else if (is_teacher()) {
    $teacher = current_acc_id();
    $sql = "SELECT id, student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id FROM students WHERE teacher_id = ? ORDER BY last_name, first_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $teacher);
    $stmt->execute();
} else {
    $students = [];
    $stmt = null;
}

if ($stmt) {
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
}

// normalize avatars
foreach ($students as &$s) {
    $a = trim((string)($s['avatar'] ?? ''));
    if ($a === '' || strtolower($a) === 'null') $a = 'assets/avatar.png';
    else if (strpos($a, '/') === 0) $a = ltrim($a, '/');
    if (!is_file(__DIR__ . '/' . $a)) $a = 'assets/avatar.png';
    $s['avatar'] = $a;
}
unset($s);

// group by section
$groups = [];
foreach ($students as $st) {
    $sec = trim((string)$st['section']);
    if ($sec === '') $sec = 'Unassigned';
    if (!isset($groups[$sec])) $groups[$sec] = ['count' => 0, 'students' => []];
    $groups[$sec]['students'][] = $st;
    $groups[$sec]['count']++;
}

// flash messages
$errors_flash = $_SESSION['students_errors'] ?? [];
unset($_SESSION['students_errors']);
$account_flash = $_SESSION['account_info'] ?? null;
unset($_SESSION['account_info']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Students</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <style>
    html,body{margin:0;padding:0;background:#f5f5f5}
    .page-wrap{padding:18px 0 48px}
    .small-btn{border-radius:40px;padding:10px 20px;background:#4a74ff;color:#fff;font-weight:700;text-transform:uppercase;border:none;cursor:pointer;transition:transform .12s,box-shadow .18s}
    .small-btn:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(74,116,255,.16)}
    .small-btn.delete{background:#ff5252}
    .small-btn.ghost{background:transparent;color:#4a74ff;border:2px solid #4a74ff;padding:8px 14px;border-radius:8px;font-weight:700}
    .table-avatar-img{width:64px;height:64px;border-radius:50%;object-fit:cover;display:block}
    .delete-list-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;margin-right:10px}
    .edit-btn{background:#00d1ff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;color:#fff}
    .section-wrap{margin-bottom:18px}
    .section-header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px 6px 10px;color:#6b6b6b;text-transform:uppercase;font-weight:700;letter-spacing:.6px;font-size:14px;cursor:pointer}
    .section-count{color:#9a9a9a;font-weight:600;font-size:12px;text-transform:none}
    .section-hr{border:0;height:1px;background:linear-gradient(to right, rgba(0,0,0,.06), rgba(0,0,0,.12), rgba(0,0,0,.06));margin:6px 0 12px}
    .section-body{transition:max-height .28s cubic-bezier(.4,0,.2,1),opacity .22s;overflow:hidden}
    .section-body.collapsed{max-height:0!important;opacity:0;padding:0;margin:0}
    .collapse-icon{transition:transform .25s;color:#6b6b6b}
    .top-controls{display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
    .confirm-row{display:flex;gap:8px;align-items:center;margin-top:12px}
    #deleteNamesList{max-height:240px;overflow:auto;padding-left:0;margin-top:8px;list-style:none}
    #deleteNamesList li{display:flex;align-items:center;gap:10px;margin:6px 0;font-size:14px;color:#333;padding:6px 8px;border-radius:6px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.03)}
    table.section-table td{vertical-align:middle;padding-top:6px;padding-bottom:6px}
    @media(max-width:600px){.table-avatar-img{width:48px;height:48px}}
  </style>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper">
    <a href="admin.php" class="brand-logo center">
      <i class="material-icons hide-on-med-and-down bold" style="vertical-align:middle">flight_takeoff</i>&nbsp;TOURS
    </a>
  </div>
</nav>

<div class="container page-wrap">
  <div class="row" style="margin-bottom:6px;">
    <div class="col s12 m12">
      <div class="top-controls">
        <button id="addBtn" class="small-btn modal-trigger">Add Student</button>
        <button id="deleteSelectedBtn" class="small-btn delete">Delete Selected</button>
        <button id="toggleCollapseBtn" class="small-btn ghost" type="button">Collapse All</button>
      </div>
    </div>
  </div>

  <?php if (!empty($errors_flash)): ?>
    <div class="card-panel red lighten-4 red-text text-darken-4">
      <?php foreach ($errors_flash as $err): ?><div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($account_flash): ?>
    <div class="card-panel green lighten-5" style="border-left:4px solid #2e7d32;">
      <strong>Account created/updated for admin:</strong>
      <div><strong>Account ID:</strong> <?php echo htmlspecialchars($account_flash['acc_id'], ENT_QUOTES); ?></div>
      <div><strong>Initial password (show to student):</strong> <code><?php echo htmlspecialchars($account_flash['password'], ENT_QUOTES); ?></code></div>
      <small class="grey-text">The password is shown only once here — it is stored hashed in the database. Make sure the student changes it after first login.</small>
    </div>
  <?php endif; ?>

  <form id="deleteForm" method="post" style="display:none;"><input type="hidden" name="action" value="delete_selected"></form>

  <?php if (empty($groups)): ?>
    <div class="card-panel">No students found.</div>
  <?php else: foreach ($groups as $sectionName => $grp):
      $safeSection = htmlspecialchars($sectionName, ENT_QUOTES);
      $count = (int)$grp['count'];
      $domId = 'sec_' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($sectionName));
  ?>
    <div class="section-wrap" id="<?php echo $domId; ?>_wrap">
      <div class="section-header" data-section="<?php echo $domId; ?>">
        <div class="section-title">
          <span><?php echo $safeSection; ?></span>
          <span class="section-count"><?php echo $count . ' student' . ($count === 1 ? '' : 's'); ?></span>
        </div>
        <i class="material-icons collapse-icon">expand_less</i>
      </div>
      <hr class="section-hr">
      <div class="section-body" id="<?php echo $domId; ?>_body">
        <table class="highlight responsive-table section-table">
          <thead>
            <tr>
              <th style="width:48px;"><label><input type="checkbox" class="check-section-all" data-section="<?php echo $domId; ?>"><span></span></label></th>
              <th>Photo</th>
              <th>Last Name</th>
              <th>First Name</th>
              <th>Middle Name</th>
              <th>Suffix</th>
              <th>Student ID</th>
              <th>Section</th>
              <th>Birthday</th>
              <th>Sex</th>
              <th>Teacher</th>
              <th style="width:120px;">Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grp['students'] as $st): echo render_student_row_html($st); endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; endif; ?>

</div>

<!-- Add modal -->
<div id="addStudentModal" class="modal">
  <div class="modal-content">
    <h5>Add Student</h5>
    <form method="post" enctype="multipart/form-data" id="addForm">
      <input type="hidden" name="action" value="add_student">
      <div class="file-field input-field"><div class="btn blue"><span>Upload Photo</span><input type="file" name="student_photo" accept="image/*"></div><div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Optional photo"></div></div>

      <div class="input-field"><input id="lastName" name="last_name" type="text" required><label for="lastName">Last Name</label></div>
      <div class="input-field"><input id="firstName" name="first_name" type="text" required><label for="firstName">First Name</label></div>
      <div class="input-field"><input id="middleName" name="middle_name" type="text"><label for="middleName">Middle Name (optional)</label></div>
      <div class="input-field"><input id="suffix" name="suffix" type="text"><label for="suffix">Suffix (optional)</label></div>

      <div class="input-field"><input id="studentID" name="student_id_val" type="text" required><label for="studentID">Student ID</label></div>
      <div class="input-field"><input id="sectionInput" name="section" type="text"><label for="sectionInput">Section</label></div>

      <div class="input-field">
        <input id="birthday" name="birthday" type="date" pattern="\d{4}-\d{2}-\d{2}">
        <label class="active" for="birthday">Birthday</label>
      </div>

      <div class="input-field">
        <select id="sex" name="sex">
          <option value="" selected>Prefer not to say</option>
          <option value="M">Male</option>
          <option value="F">Female</option>
          <option value="Other">Other</option>
        </select>
        <label for="sex">Sex</label>
      </div>

      <?php if (is_super_admin()): ?>
      <div class="input-field">
        <select name="teacher_id" id="teacherSelect">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Assign Teacher</label>
      </div>
      <?php endif; ?>

      <div class="right-align"><button class="btn blue" type="submit">Add</button></div>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div id="editStudentModal" class="modal">
  <div class="modal-content">
    <h5>Edit Student</h5>
    <form method="post" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" id="edit_db_id" name="db_id" value="">
      <div class="file-field input-field"><div class="btn blue"><span>Change Photo</span><input type="file" name="edit_student_photo" accept="image/*"></div><div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Change photo (optional)"></div></div>

      <div class="input-field"><input id="editLastName" name="edit_last_name" type="text" required><label for="editLastName">Last Name</label></div>
      <div class="input-field"><input id="editFirstName" name="edit_first_name" type="text" required><label for="editFirstName">First Name</label></div>
      <div class="input-field"><input id="editMiddleName" name="edit_middle_name" type="text"><label for="editMiddleName">Middle Name (optional)</label></div>
      <div class="input-field"><input id="editSuffix" name="edit_suffix" type="text"><label for="editSuffix">Suffix (optional)</label></div>

      <div class="input-field"><input id="editStudentID" name="edit_student_id" type="text" required><label for="editStudentID">Student ID</label></div>
      <div class="input-field"><input id="editSectionInput" name="edit_section" type="text"><label for="editSectionInput">Section</label></div>

      <div class="input-field">
        <input id="editBirthday" name="edit_birthday" type="date" pattern="\d{4}-\d{2}-\d{2}">
        <label class="active" for="editBirthday">Birthday</label>
      </div>

      <div class="input-field">
        <select id="editSex" name="edit_sex">
          <option value="">Prefer not to say</option>
          <option value="M">Male</option>
          <option value="F">Female</option>
          <option value="Other">Other</option>
        </select>
        <label for="editSex">Sex</label>
      </div>

      <?php if (is_super_admin()): ?>
      <div class="input-field">
        <select name="edit_teacher_id" id="editTeacherSelect">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Assign Teacher</label>
      </div>
      <?php endif; ?>

      <div class="confirm-row"><label><input type="checkbox" id="editConfirmCheckbox"><span>I confirm I want to save these changes</span></label></div>

      <div style="margin-top:12px;" class="divider"></div>

      <!-- Reset password to birthday: protected by explicit enable checkbox -->
      <div style="margin-top:12px;">
        <label><input type="checkbox" id="enableResetCheckbox"><span>Enable password reset</span></label>
        <div style="margin-top:8px;">
          <!-- NOTE: NOT a nested form. We'll submit a small dynamic form via JS -->
          <input type="hidden" id="reset_db_id" name="reset_db_id" value="">
          <button type="button" id="resetPwdBtn" class="btn red" disabled>Reset password to birthday</button>

          <div style="margin-top:6px;"><small class="grey-text">This will set the student's account password to their birthday (YYYY-MM-DD). Only use when necessary.</small></div>
        </div>
      </div>

      <div class="right-align" style="margin-top:12px;"><button class="btn blue" type="submit" id="saveEditBtn" disabled>Save</button></div>
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
      <div class="right-align" style="margin-top:16px;"><button type="button" class="btn grey modal-close" id="cancelDeleteBtn">Cancel</button> <button type="submit" class="btn red" id="confirmDeleteBtn" disabled>Delete</button></div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  M.AutoInit();
  document.querySelectorAll('select').forEach(function(s){ if (!M.FormSelect.getInstance(s)) M.FormSelect.init(s); });
  M.Tooltip.init(document.querySelectorAll('.tooltipped'));

  document.getElementById('addBtn').addEventListener('click', function(){ M.Modal.getInstance(document.getElementById('addStudentModal')).open(); });

  function openEditModal(btn){
    var dbId = btn.getAttribute('data-db-id');
    document.getElementById('edit_db_id').value = dbId;
    document.getElementById('editLastName').value = btn.getAttribute('data-last') || '';
    document.getElementById('editFirstName').value = btn.getAttribute('data-first') || '';
    document.getElementById('editMiddleName').value = btn.getAttribute('data-middle') || '';
    document.getElementById('editSuffix').value = btn.getAttribute('data-suffix') || '';
    document.getElementById('editStudentID').value = btn.getAttribute('data-studentid') || '';
    document.getElementById('editSectionInput').value = btn.getAttribute('data-section') || '';

    var b = btn.getAttribute('data-birthday') || '';
    var s = btn.getAttribute('data-sex') || '';
    var t = btn.getAttribute('data-teacher') || '';

    document.getElementById('editBirthday').value = b;

    var editSexEl = document.getElementById('editSex');
    if (editSexEl) {
      editSexEl.value = s;
      try { M.FormSelect.getInstance(editSexEl)?.destroy(); } catch(e){}
      try { M.FormSelect.init(editSexEl); } catch(e){}
    }

    var editTeacherEl = document.getElementById('editTeacherSelect');
    if (editTeacherEl) {
      editTeacherEl.value = t;
      try { M.FormSelect.getInstance(editTeacherEl)?.destroy(); } catch(e){}
      try { M.FormSelect.init(editTeacherEl); } catch(e){}
    }

    M.updateTextFields();
    document.getElementById('editConfirmCheckbox').checked = false;
    document.getElementById('saveEditBtn').disabled = true;

    // reset-control initial state
    var resetDbInput = document.getElementById('reset_db_id');
    if (resetDbInput) resetDbInput.value = dbId;
    var enReset = document.getElementById('enableResetCheckbox');
    if (enReset) enReset.checked = false;
    var resetBtn = document.getElementById('resetPwdBtn');
    if (resetBtn) resetBtn.disabled = true;

    M.Modal.getInstance(document.getElementById('editStudentModal')).open();
    setTimeout(function(){ try{ document.getElementById('editFirstName').focus(); }catch(e){} }, 200);
  }
  document.querySelectorAll('.edit-btn').forEach(function(b){ b.addEventListener('click', function(){ openEditModal(this); }); });

  // Save button enable toggle
  document.getElementById('editConfirmCheckbox').addEventListener('change', function(){ document.getElementById('saveEditBtn').disabled = !this.checked; });
  document.getElementById('editForm').addEventListener('submit', function(e){ if (!document.getElementById('editConfirmCheckbox').checked) { e.preventDefault(); M.toast({html:'Please confirm before saving.'}); return; } });

  document.getElementById('deleteSelectedBtn').addEventListener('click', function(e){
    e.preventDefault();
    var checkedEls = Array.from(document.querySelectorAll('.chk:checked'));
    if (!checkedEls.length) { M.toast({html:'Select at least one student to delete.'}); return; }
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

    document.getElementById('deleteConfirmCheckbox').checked = false;
    document.getElementById('confirmDeleteBtn').disabled = true;
    M.Modal.getInstance(document.getElementById('deleteConfirmModal')).open();
    setTimeout(function(){ try{ document.getElementById('deleteConfirmCheckbox').focus(); }catch(e){} }, 220);
  });

  document.getElementById('deleteConfirmCheckbox').addEventListener('change', function(){ document.getElementById('confirmDeleteBtn').disabled = !this.checked; });
  document.getElementById('deleteConfirmForm').addEventListener('submit', function(e){ if (!document.getElementById('deleteConfirmCheckbox').checked) { e.preventDefault(); M.toast({html:'Please confirm before deleting.'}); return; } });

  // enable/disable reset button (checkbox)
  var enableResetCheckbox = document.getElementById('enableResetCheckbox');
  var resetPwdBtn = document.getElementById('resetPwdBtn');
  if (enableResetCheckbox && resetPwdBtn) {
    enableResetCheckbox.addEventListener('change', function() {
      resetPwdBtn.disabled = !this.checked;
    });
  }

  // Reset button behavior - builds a temporary POST form to avoid nested forms
  if (resetPwdBtn) {
    resetPwdBtn.addEventListener('click', function(e) {
      if (!enableResetCheckbox.checked) {
        M.toast({html: 'Please check the enable box to reset password.'});
        return;
      }
      var birthdayField = document.getElementById('editBirthday');
      if (!birthdayField || (birthdayField.value || '').trim() === '') {
        M.toast({html: 'Cannot reset password: student has no birthday recorded in the edit form.'});
        return;
      }

      var confirmMsg = 'Reset this student password to their birthday (' + birthdayField.value + ')? This will overwrite the existing password.';
      if (!confirm(confirmMsg)) return;

      var dbId = (document.getElementById('reset_db_id') && document.getElementById('reset_db_id').value) || (document.getElementById('edit_db_id') && document.getElementById('edit_db_id').value);
      if (!dbId) { M.toast({html:'Student ID missing; cannot reset.'}); return; }

      var f = document.createElement('form');
      f.method = 'post';
      f.action = 'students.php';

      var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='reset_password'; f.appendChild(a);
      var i = document.createElement('input'); i.type='hidden'; i.name='db_id'; i.value = dbId; f.appendChild(i);

      // If you use CSRF tokens in this page for POSTs, uncomment and include it:
      // var csrfEl = document.querySelector('input[name="csrf_token"]');
      // if (csrfEl) { var cs = document.createElement('input'); cs.type='hidden'; cs.name='csrf_token'; cs.value = csrfEl.value; f.appendChild(cs); }

      document.body.appendChild(f);
      f.submit();
    });
  }

  var headers = document.querySelectorAll('.section-header');
  var COLL_KEY = 'students_sections_collapsed_v1';
  var collapsedState = {};
  try{ collapsedState = JSON.parse(localStorage.getItem(COLL_KEY)) || {}; }catch(e){ collapsedState = {}; }
  var DEFAULT_COLLAPSED = true;
  var hasSavedState = Object.keys(collapsedState).length > 0;
  if (!hasSavedState) headers.forEach(function(h){ collapsedState[h.getAttribute('data-section')] = DEFAULT_COLLAPSED; });

  headers.forEach(function(h){
    var sec = h.getAttribute('data-section');
    var body = document.getElementById(sec + '_body');
    var wrap = document.getElementById(sec + '_wrap');
    var icon = h.querySelector('.collapse-icon');
    if (collapsedState[sec]) { body.classList.add('collapsed'); wrap.classList.add('collapsed'); if (icon) icon.style.transform='rotate(-90deg)'; }
    h.addEventListener('click', function(){
      var isCollapsed = body.classList.toggle('collapsed');
      wrap.classList.toggle('collapsed', isCollapsed);
      if (icon) icon.style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
      try{ collapsedState[sec] = isCollapsed; localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); }catch(e){}
      updateToggleBtnLabel();
    });

    var sectionCheck = wrap.querySelector('.check-section-all');
    if (sectionCheck) sectionCheck.addEventListener('change', function(){ var checked=this.checked; wrap.querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; }); });
  });

  function areAllCollapsed(){ var any=false, all=true; headers.forEach(function(h){ any=true; if(!collapsedState[h.getAttribute('data-section')]) all=false; }); if(!any) return false; return all; }
  var toggleBtn = document.getElementById('toggleCollapseBtn');
  function updateToggleBtnLabel(pulse){ var allCollapsed = areAllCollapsed(); toggleBtn.textContent = allCollapsed ? 'Expand All' : 'Collapse All'; if(pulse){ toggleBtn.classList.remove('btn-pulse'); void toggleBtn.offsetWidth; toggleBtn.classList.add('btn-pulse'); } }
  function setAllSectionsCollapsed(collapsed){ headers.forEach(function(h){ var sec=h.getAttribute('data-section'); var body=document.getElementById(sec + '_body'); var wrap=document.getElementById(sec + '_wrap'); var icon=h.querySelector('.collapse-icon'); if(collapsed){ body.classList.add('collapsed'); wrap.classList.add('collapsed'); if(icon)icon.style.transform='rotate(-90deg)'; } else { body.classList.remove('collapsed'); wrap.classList.remove('collapsed'); if(icon)icon.style.transform='rotate(0deg)'; } collapsedState[sec]=collapsed; }); try{ localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); }catch(e){} updateToggleBtnLabel(true); }
  updateToggleBtnLabel(false);
  toggleBtn.addEventListener('click', function(){ var toCollapse = !areAllCollapsed(); setAllSectionsCollapsed(toCollapse); toggleBtn.classList.remove('btn-pulse'); void toggleBtn.offsetWidth; toggleBtn.classList.add('btn-pulse'); });

  var globalCheck = document.getElementById('checkAll');
  if (globalCheck) globalCheck.addEventListener('change', function(){ var checked=this.checked; document.querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; }); });

});
</script>
</body>
</html>

<?php
// close accounts connection when script ends
if ($acc_db_ok && isset($acc_conn) && $acc_conn instanceof mysqli) $acc_conn->close();
if (isset($conn) && $conn instanceof mysqli) $conn->close();
?>
