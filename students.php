<?php
// students.php - DARK THEME (super_admin style) applied
// Full corrected version (includes POST handlers, UI fixes, JS fixes)
// Make a backup of your existing file before replacing.

session_start();

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'airlines';
$acc_db_name = 'airlines';

// unified upload folder to match students_edit.php
$uploads_dir = __DIR__ . '/uploads/avatars';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

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

    $accIdCandidates = ['acc_id','admin_id','user_id','ad_id','account_id','id'];
    foreach ($accIdCandidates as $c) {
        if (!empty($_SESSION[$c])) { $_SESSION['acc_id'] = $_SESSION[$c]; break; }
    }

    $roleCandidates = ['acc_role','role','user_role','admin_role','ad_role'];
    foreach ($roleCandidates as $r) {
        if (!empty($_SESSION[$r]) && empty($_SESSION['acc_role'])) { $_SESSION['acc_role'] = $_SESSION[$r]; break; }
    }

    if (empty($_SESSION['acc_name'])) {
        $nameCandidates = ['name','acc_name','username','user_name','admin_name'];
        foreach ($nameCandidates as $n) { if (!empty($_SESSION[$n])) { $_SESSION['acc_name'] = $_SESSION[$n]; break; } }
    }

    if (!empty($_SESSION['acc_id'])) return;
    header('Location: login_page.php');
    exit;
}
function is_super_admin() { return (isset($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'super_admin'); }
function is_admin() { return (isset($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin'); }
function is_teacher() { return is_admin(); }
function current_acc_id() { return $_SESSION['acc_id'] ?? null; }

require_login();

// ---------- HELPERS ----------
function handle_avatar_upload($input_name, $existing = null) {
    global $uploads_dir;
    if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['tmp_name'])) return $existing;
    $f = $_FILES[$input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) return $existing;

    if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0755, true);

    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    $filename = uniqid('avatar_', true) . '.' . ($safe_ext ?: 'jpg');
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        return 'uploads/avatars/' . $filename;
    }
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
  <td style="width:48px;"><label><input type="checkbox" class="filled-in chk" data-id="<?php echo $id; ?>"><span></span></label></td>
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
  <td style="width:120px;">
    <a class="edit-btn tooltipped" href="#editStudentModal"
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
$allowed_sex = ['', 'M', 'F'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // RESET PASSWORD to Birthday
    if (!empty($_POST['action']) && $_POST['action'] === 'reset_password') {
        $db_id = intval($_POST['db_id'] ?? 0);
        $q = $conn->prepare("SELECT student_id, birthday FROM students WHERE id = ? LIMIT 1");
        if (!$q) { $_SESSION['students_errors'] = ['Database error while locating student.']; header('Location: students.php'); exit; }
        $q->bind_param('i', $db_id);
        $q->execute();
        $q->bind_result($stu_acc_id, $stu_birthday);
        $found = $q->fetch();
        $q->close();
        if (!$found) { $_SESSION['students_errors'] = ['Student record not found.']; header('Location: students.php'); exit; }
        if (!$acc_db_ok) { $_SESSION['students_errors'] = ['Accounts database not available. Cannot reset password.']; header('Location: students.php'); exit; }
        $stu_birthday = trim((string)$stu_birthday);
        if ($stu_birthday === '') { $_SESSION['students_errors'] = ['Cannot reset password: student has no birthday recorded.']; header('Location: students.php'); exit; }
        $chk = $acc_conn->prepare("SELECT acc_id FROM accounts WHERE acc_id = ? LIMIT 1");
        if (!$chk) { $_SESSION['students_errors'] = ['Accounts DB error.']; header('Location: students.php'); exit; }
        $chk->bind_param('s', $stu_acc_id);
        $chk->execute();
        $chk->bind_result($exists_acc);
        $exists = $chk->fetch() ? true : false;
        $chk->close();
        if (!$exists) { $_SESSION['students_errors'] = ['Account not found for this student (cannot reset).']; header('Location: students.php'); exit; }
        $new_hash = password_hash($stu_birthday, PASSWORD_DEFAULT);
        $upd = $acc_conn->prepare("UPDATE accounts SET password = ? WHERE acc_id = ?");
        if (!$upd) { $_SESSION['students_errors'] = ['Failed to prepare password update.']; header('Location: students.php'); exit; }
        $upd->bind_param('ss', $new_hash, $stu_acc_id);
        $ok = $upd->execute();
        $upd->close();
        if (!$ok) { $_SESSION['students_errors'] = ['Failed to update account password.']; header('Location: students.php'); exit; }
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

        if (is_super_admin()) { $assigned_teacher = trim($_POST['teacher_id'] ?? '') ?: null; }
        elseif (is_teacher()) { $assigned_teacher = current_acc_id(); }
        else { $assigned_teacher = null; }

        $errors = [];
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '' && !validate_date_not_future($birthday)) $errors[] = 'Birthday invalid or in the future.';
        if (!in_array($sex, $allowed_sex, true)) $errors[] = 'Sex must be M or F.';

        if ($assigned_teacher && $acc_db_ok && is_super_admin()) {
            $chk = $acc_conn->prepare("SELECT role FROM admins WHERE acc_id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('s', $assigned_teacher);
                $chk->execute();
                $chk->bind_result($roleFound);
                $ok = $chk->fetch();
                $chk->close();
                if (!$ok || $roleFound !== 'admin') $errors[] = 'Assigned teacher is invalid.';
            } else { $errors[] = 'Accounts DB error while validating teacher.'; }
        }

        if (empty($errors)) {
            $avatar = handle_avatar_upload('student_photo', null);

            $stmt = $conn->prepare("INSERT INTO students (student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) { $_SESSION['students_errors'] = ['Failed to prepare student insertion.']; header('Location: students.php'); exit; }
            $stmt->bind_param('ssssssssss', $student_id_val, $last, $first, $middle, $suffix, $section, $avatar, $birthday, $sex, $assigned_teacher);
            $ok = $stmt->execute();
            if (!$ok) { $stmt->close(); $_SESSION['students_errors'] = ['Failed to insert student (DB error).']; header('Location: students.php'); exit; }
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

    // EDIT student (keeps avatar deletion safe)
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

        if (is_super_admin()) $assigned_teacher = trim($_POST['edit_teacher_id'] ?? '') ?: null;
        else if (is_teacher()) {
            $check = $conn->prepare("SELECT teacher_id FROM students WHERE id = ? LIMIT 1");
            $check->bind_param('i', $db_id);
            $check->execute();
            $check->bind_result($row_teacher);
            if (!$check->fetch() || $row_teacher !== current_acc_id()) { $check->close(); $_SESSION['students_errors'] = ['Unauthorized action.']; header('Location: students.php'); exit; }
            $check->close();
            $assigned_teacher = current_acc_id();
        } else $assigned_teacher = null;

        $errors = [];
        if ($student_id_val === '') $errors[] = 'Student ID required.';
        if ($last === '') $errors[] = 'Last name required.';
        if ($first === '') $errors[] = 'First name required.';
        if ($birthday !== '' && !validate_date_not_future($birthday)) $errors[] = 'Birthday invalid or in the future.';
        if (!in_array($sex, $allowed_sex, true)) $errors[] = 'Sex must be M or F.';

        if ($assigned_teacher && $acc_db_ok && is_super_admin()) {
            $chk = $acc_conn->prepare("SELECT role FROM admins WHERE acc_id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('s', $assigned_teacher);
                $chk->execute();
                $chk->bind_result($roleFound);
                $ok = $chk->fetch();
                $chk->close();
                if (!$ok || $roleFound !== 'admin') $errors[] = 'Assigned teacher is invalid.';
            } else { $errors[] = 'Accounts DB error while validating teacher.'; }
        }

        if (empty($errors)) {
            $cur_avatar = null;
            $q = $conn->prepare("SELECT avatar, student_id, last_name, first_name FROM students WHERE id = ?");
            $q->bind_param('i', $db_id);
            $q->execute();
            $q->bind_result($cur_avatar, $old_student_id, $old_last, $old_first);
            $q->fetch();
            $q->close();

            // IMPORTANT: name of file input must match handle_avatar_upload usage
            $new_avatar = handle_avatar_upload('edit_student_photo', $cur_avatar);

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
            $ok = $upd->execute();
            $upd->close();
            if (!$ok) { $_SESSION['students_errors'] = ['Failed to update student (DB error).']; header('Location: students.php'); exit; }

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
    $stmt = $conn->prepare($sql); $stmt->execute();
} else if (is_teacher()) {
    $teacher = current_acc_id();
    $sql = "SELECT id, student_id, last_name, first_name, middle_name, suffix, section, avatar, birthday, sex, teacher_id FROM students WHERE teacher_id = ? ORDER BY last_name, first_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $teacher); $stmt->execute();
} else {
    $students = []; $stmt = null;
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
  <title>Students — TOURS (Admin)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <style>
    /* ========== DARK THEME (super_admin style) ========== */
    :root{
      --navy-900: #071428;
      --navy-800: #0b1830;
      --card-bg: rgba(255,255,255,0.03);
      --glass: rgba(255,255,255,0.04);
      --accent-1: #0d47a1;
      --accent-2: #1976d2;
      --air-blue: #0b59d8;
      --air-sky: #2e7ef7;
      --muted: #9fc6ff;
      --text: #e9f1ff;
      --muted-2: #a8bedf;
      --danger-red: #ff5252;
      --max-width: 1200px;
      --card-radius: 12px;
    }

    html,body{
      margin:0;padding:0;
      background: linear-gradient(180deg, var(--navy-900), var(--navy-800));
      font-family: Inter, Roboto, Arial, sans-serif;
      color: var(--text);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      min-height:100vh;
    }

    /* NAVBAR */
    nav.blue{
      background: linear-gradient(90deg, #0052cc, #1e90ff) !important;
      box-shadow: 0 6px 26px rgba(0,0,0,0.6);
    }
    .nav-wrapper .brand-logo { font-weight:700; color: #fff; letter-spacing:0.6px; }

    .page-wrap { padding: 20px 0 48px; max-width: calc(var(--max-width) + 48px); margin: 0 auto; }

    header.banner{
      background: linear-gradient(90deg, var(--navy-800), rgba(15,31,58,0.9));
      color: var(--text);
      padding:18px;border-radius:8px;margin:18px 0;
      display:flex;align-items:center;gap:18px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.45);
      border: 1px solid rgba(255,255,255,0.03);
    }
    .banner h1{margin:0;font-size:20px}
    .banner .sub{opacity:0.9;color:var(--muted-2)}

    .top-controls{display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
    .btn-air{
      position: relative;
      overflow: hidden;
      display: inline-block;
      background: linear-gradient(135deg, var(--air-sky) 0%, var(--air-blue) 100%);
      color: #fff;
      border-radius: 28px;
      padding: 10px 18px;
      font-weight: 700;
      border: none;
      box-shadow: 0 12px 36px rgba(11,89,216,0.16);
      cursor: pointer;
      transition: transform .14s ease, box-shadow .14s ease;
    }
    .btn-air:hover { transform: translateY(-3px); box-shadow: 0 18px 46px rgba(11,89,216,0.22); }
    .btn-air.ghost{
      background: transparent;
      border: 2px solid rgba(46,126,247,0.16);
      color: var(--muted-2);
      padding: 8px 12px;
      border-radius: 28px;
    }

    /* ensure logout / reset (ghost/secondary) are blue-highlighted */
    .btn-ghost, .btn.btn-ghost {
      background: transparent !important;
      color: var(--air-sky) !important;
      border: 1px solid rgba(46,126,247,0.18) !important;
      box-shadow: none !important;
      padding: 8px 12px !important;
      border-radius: 10px !important;
      height:44px !important;
      display:inline-flex !important;
      align-items:center !important;
      justify-content:center !important;
    }
    /* Make small plain buttons (cancel) use muted-blue */
    .btn-plain {
      background: transparent;
      color: var(--muted-2);
      border-radius: 8px;
      padding: 8px 12px;
      border: 1px solid rgba(255,255,255,0.03);
    }

    /* Danger button (delete) - remains red */
    .btn-danger {
      background: linear-gradient(180deg,#ff6b6b,#ff5252);
      color: #fff;
      border-radius: 10px;
      padding: 8px 12px;
      border: none;
      box-shadow: 0 8px 24px rgba(255,82,82,0.16);
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(255,82,82,0.22); }

    /* card / section */
    .section-wrap{
      margin-bottom:18px;
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
      padding:10px;border-radius:var(--card-radius);
      box-shadow: 0 8px 30px rgba(0,0,0,0.55);
      border: 1px solid rgba(255,255,255,0.03);
    }

    .section-header{
      display:flex;align-items:center;justify-content:space-between;padding:8px 6px 6px 6px;color:var(--muted-2);
      text-transform:uppercase;font-weight:700;letter-spacing:.6px;font-size:13px;cursor:pointer;
      background: transparent;
    }
    .section-count{color: #89b6ff;font-weight:600;font-size:12px;text-transform:none}
    .section-hr{border:0;height:1px;background:linear-gradient(to right, rgba(255,255,255,0.02), rgba(255,255,255,0.03));margin:8px 0 12px}

    /* table */
    table.section-table thead th{
      background: rgba(255,255,255,0.02);
      color: #9fc6ff;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    table.section-table tbody tr { color: var(--text); border-bottom:1px solid rgba(255,255,255,0.02); background: transparent; }
    table.section-table tbody tr:hover { background: rgba(255,255,255,0.01); }

    .table-avatar-img{width:64px;height:64px;border-radius:50%;object-fit:cover;display:block;border:2px solid rgba(255,255,255,0.04)}
    .delete-list-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;margin-right:10px}

    /* id-card & avatar styling */
    .id-card { display:flex;gap:18px;align-items:center;background: rgba(255,255,255,0.02); padding:14px;border-radius:12px;border:0; box-shadow: 0 12px 30px rgba(0,0,0,0.5) }
    .id-photo{width:110px;height:110px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.04);background:transparent}

    /* badges / notices */
    .card-panel { background: rgba(255,255,255,0.02); color: var(--text); border-left: 4px solid rgba(255,255,255,0.02); padding:10px; border-radius:8px; }
    .card-panel.green { border-left-color: #2e7d32; background: rgba(46,125,50,0.06); color: #d7ffdf; }

    /* modals */
    .modal { border-radius:12px; background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); color:var(--text); box-shadow: 0 24px 80px rgba(0,0,0,0.7); border:1px solid rgba(255,255,255,0.03); }
    .modal .modal-content { padding: 18px 24px; }

    /* global round edit button style (blue circular) */
    .edit-btn,
    a.edit-btn,
    button.edit-btn {
        background: transparent !important;
        width: auto !important;
        height: auto !important;
        min-width: 40px !important;
        min-height: 40px !important;
        border-radius: 50% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: none !important;
        color: #fff !important;
        cursor: pointer;
        padding: 0 !important;
        border: none !important;
    }
    .edit-btn i.material-icons {
        background: linear-gradient(135deg, var(--air-sky), var(--air-blue));
        color: #fff;
        border-radius: 50%;
        padding: 10px;
        font-size: 20px !important;
        width: 40px; height:40px; line-height:40px;
        display:inline-flex; align-items:center; justify-content:center;
        box-shadow: 0 8px 22px rgba(11,89,216,0.18);
    }

    /* ensure delete icons remain red & non-circular */
    .btn-flat.red-text i.material-icons,
    a.btn-flat.red-text i.material-icons,
    .delete-btn i.material-icons {
        background: transparent !important;
        color: #ff6b6b !important;
        box-shadow: none !important;
        width: auto !important;
        height: auto !important;
        border-radius: 4px !important;
        font-size: 20px !important;
    }

    /* inputs and labels */
    .input-field input { color: var(--text) !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; }
    .input-field label { color: var(--muted-2) !important; }

    /* responsive */
    @media(max-width:900px){
      .table-avatar-img{width:48px;height:48px}
      .id-photo{width:90px;height:90px}
      .section-wrap { padding: 12px; }
    }

    /* small helpers */
    .muted { color: var(--muted-2); }
    .right-align { text-align: right; }

/* ===== Patch: solid edit modal, blue input focus, dark select ===== */

/* Make edit modal solid (no transparency) and slightly elevated */
#editStudentModal.modal,
#editStudentModal .modal-content {
  background: linear-gradient(180deg, rgba(6,18,36,0.98), rgba(8,18,34,0.99)) !important;
  color: var(--text) !important;
  border-radius: 12px !important;
  border: 1px solid rgba(46,126,247,0.08) !important; /* subtle blue outline */
  box-shadow: 0 30px 80px rgba(0,0,0,0.75) !important;
}

/* If your .modal rule is global, ensure the edit modal specifically is fully opaque */
#editStudentModal { background: none; } /* keep container clean */
#editStudentModal .modal-content { background: none; padding: 20px 24px; }

/* Blue highlight for focused text inputs (works with Materialize-like structure) */
.input-field input:focus {
  border-bottom: 2px solid var(--air-sky) !important; /* blue underline */
  box-shadow: 0 4px 14px rgba(46,126,247,0.14) !important;
  color: var(--text) !important;
}

/* Move the label to blue when input is focused or active */
.input-field input:focus + label,
.input-field input.active + label,
.input-field input.valid + label {
  color: var(--air-sky) !important;
}

/* Also make textarea same (if any) */
textarea:focus {
  border-bottom: 2px solid var(--air-sky) !important;
  box-shadow: 0 4px 14px rgba(46,126,247,0.14) !important;
  color: var(--text) !important;
}

/* Browser-default select (sex dropdown): dark background + blue outline + white text */
select.browser-default {
  background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  color: var(--text) !important;
  border: 1px solid rgba(46,126,247,0.14) !important;
  min-height: 40px;
  padding: 8px 10px;
  border-radius: 8px;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}

/* Focus state for select */
select.browser-default:focus {
  outline: none;
  border-color: var(--air-sky) !important;
  box-shadow: 0 8px 28px rgba(46,126,247,0.12) !important;
}

/* Make the select arrow visible and white (works in many blink engines) */
select.browser-default::-ms-expand { display: none; } /* hide default on IE */
.select-wrapper.browser-default { position: relative; }
.select-wrapper.browser-default::after {
  content: "▾";
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: var(--muted-2);
  font-size: 12px;
}

/* Ensure the select width & text alignment */
select.browser-default { width: 100%; box-sizing: border-box; }

/* Make the small "Reset password" plain button use blue highlight (if you use .btn-plain) */
.btn-plain {
  color: var(--air-sky) !important;
  border-color: rgba(46,126,247,0.18) !important;
  background: rgba(46,126,247,0.02);
}

/* Safety: ensure the edit modal body text uses high contrast */
#editStudentModal .id-info, #editStudentModal .input-field label, #editStudentModal .muted {
  color: var(--muted-2) !important;
}

/* Slightly increase z-index of modal content in case overlays were causing translucency */
#editStudentModal.modal { z-index: 10010 !important; }
#editStudentModal .modal-content { z-index: 10011 !important; }

/* FORCE dark dropdown list for native selects */
select.browser-default option {
  background-color: #0e1a2b !important; /* deep navy */
  color: #ffffff !important;            /* white text */
  padding: 8px 10px;
}

/* Hover highlight (works on Chrome & Edge) */
select.browser-default option:hover {
  background-color: #173255 !important;
  color: #fff !important;
}

/* Selected item highlight inside dropdown */
select.browser-default option:checked {
  background-color: #1976d2 !important; /* your blue */
  color: #fff !important;
}


  </style>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper">
    <a href="admin.php" class="brand-logo center" style="display:flex;align-items:center;gap:8px;padding-left:12px;">
      <img src="assets/logo.png" alt="logo" style="height:34px;vertical-align:middle;margin-right:4px">STUDENT MANAGEMENT
    </a>
  </div>
</nav>

<div class="container page-wrap">
  <header class="banner">
    <div style="flex:0 0 auto"><img src="assets/logo.png" alt="logo" style="height:46px;width:72px;object-fit:cover;border-radius:6px"></div>
    <div>
      <h1>Students</h1>
      <div class="sub">Manage students</div>
    </div>
  </header>

  <div class="row" style="margin-bottom:6px;">
    <div class="col s12 m12">
      <div class="top-controls">
        <button id="addBtn" class="btn-air modal-trigger">+ Add Student</button>
        <button id="deleteSelectedBtn" class="btn-danger">Delete Selected</button>
        <button id="toggleCollapseBtn" class="btn-air ghost" type="button">Collapse All</button>
      </div>
    </div>
  </div>

  <?php if (!empty($errors_flash)): ?>
    <div class="card-panel" style="background: rgba(198,40,40,0.06); color: #ffd6d6; border-left: 4px solid rgba(198,40,40,0.14);">
      <?php foreach ($errors_flash as $err): ?><div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($account_flash): ?>
    <div class="card-panel green" style="margin-bottom:12px;">
      <strong>Account created/updated:</strong>
      <div style="margin-top:6px;"><strong>Account ID:</strong> <?php echo htmlspecialchars($account_flash['acc_id'], ENT_QUOTES); ?></div>
      <div><strong>Initial password:</strong> <code style="background: rgba(255,255,255,0.03); padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($account_flash['password'], ENT_QUOTES); ?></code></div>
      <small class="muted">Shown only once — password is stored hashed in the DB.</small>
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
          <span class="section-count">&nbsp;&nbsp;•&nbsp;&nbsp;<?php echo $count . ' student' . ($count === 1 ? '' : 's'); ?></span>
        </div>
        <i class="material-icons collapse-icon" style="color:var(--muted-2);">expand_less</i>
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
    <h5 style="margin-top:0;color:var(--text)">Add Student</h5>
    <div style="height:4px;background:linear-gradient(90deg,var(--air-blue),var(--air-sky));width:100%;border-radius:4px;margin:8px 0 14px;"></div>

    <form method="post" enctype="multipart/form-data" id="addForm">
      <input type="hidden" name="action" value="add_student">

      <!-- Photo + Student ID + Last + First -->
      <div class="field-row" style="align-items:center;margin-bottom:10px;">
        <div style="flex:0 0 120px; text-align:center;">
          <img id="addPreview" src="assets/avatar.png" class="id-photo" alt="photo">
          <div style="margin-top:8px;">
            <label class="id-photo-button" style="border-radius:8px;cursor:pointer;background:linear-gradient(135deg,var(--air-sky),var(--air-blue));color:#fff;padding:8px 12px;display:inline-block">
              Upload
              <input id="addStudentPhoto" type="file" name="student_photo" accept="image/*" style="display:none">
            </label>
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

      <!-- Middle Name + Suffix -->
      <div class="field-row" style="margin-bottom:10px;">
        <div style="flex:1"><div class="input-field"><input id="addMiddleName" name="middle_name" type="text"><label for="addMiddleName">Middle Name (optional)</label></div></div>
        <div style="flex:0 0 180px"><div class="input-field"><input id="addSuffix" name="suffix" type="text"><label for="addSuffix">Suffix</label></div></div>
      </div>

      <!-- Section -->
      <div class="input-field" style="margin-bottom:10px;">
        <input id="addSection" name="section" type="text"><label for="addSection">Section</label>
      </div>

      <!-- Birthday + Sex (native select) -->
      <div class="field-row" style="gap:12px;align-items:center;margin-bottom:8px;">
        <div style="flex:0 0 280px;">
          <div class="input-field"><input id="addBirthday" name="birthday" type="date"><label class="active" for="addBirthday">Birthday</label></div>
        </div>
        <div style="flex:0 0 160px;">
          <label for="addSex" style="display:block;margin-bottom:6px;color:var(--muted-2);font-weight:600">Sex <span style="color:#d32f2f">*</span></label>
          <select id="addSex" name="sex" class="browser-default" required>
            <option value="">Select</option>
            <option value="M">M</option>
            <option value="F">F</option>
          </select>
        </div>
      </div>

      <?php if (is_super_admin()): ?>
      <div style="margin-bottom:8px;">
        <label for="addTeacherSelect" style="display:block;margin-bottom:6px;color:var(--muted-2);font-weight:600">Assign Teacher</label>
        <select id="addTeacherSelect" name="teacher_id" class="browser-default">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="right-align" style="margin-top:12px;">
        <button class="btn-air" type="submit">Add Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div id="editStudentModal" class="modal">
  <div class="modal-content">
    <h5 style="margin-top:0;color:var(--text)">Edit Student Information</h5>
    <div style="height:4px;background:linear-gradient(90deg,var(--air-blue),var(--air-sky));width:100%;border-radius:4px;margin:8px 0 14px;"></div>

    <div class="id-card" style="margin-bottom:12px;">
      <img id="editPreview" src="assets/avatar.png" class="id-photo" alt="photo">
      <div class="id-info">
        <h3 id="editName" style="color:var(--text)">Student Name</h3>
        <p><strong>ID:</strong> <span id="editIDLabel">—</span></p>
        <p id="editSectionLabel" style="margin-top:6px;color:var(--muted-2)"></p>

        <div style="margin-top:8px;">
          <span class="id-photo-button" style="border-radius:8px;cursor:pointer;display:inline-block;background:linear-gradient(135deg,var(--air-sky),var(--air-blue));color:#fff;padding:8px 12px;">Change Photo</span>
          <small style="display:block;margin-top:6px;color:var(--muted-2);">Choose a new avatar below after opening the form.</small>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" id="edit_db_id" name="db_id" value="">
      <input type="hidden" id="reset_db_id" name="reset_db_id" value="">

      <!-- file input is INSIDE the form and visually hidden; clicking the "Change Photo" button will trigger it -->
      <label style="display:none;" id="hiddenEditPhotoLabel">
        <input id="editStudentPhoto" name="edit_student_photo" type="file" accept="image/*" style="display:none">
      </label>

      <!-- Student ID -->
      <div class="input-field" style="margin-top:6px;">
        <input id="editStudentID" name="edit_student_id" type="text" required>
        <label for="editStudentID" class="required-star">Student ID</label>
      </div>

      <!-- Last Name -->
      <div class="input-field">
        <input id="editLastName" name="edit_last_name" type="text" required>
        <label for="editLastName">Last Name</label>
      </div>

      <!-- First Name -->
      <div class="input-field">
        <input id="editFirstName" name="edit_first_name" type="text" required>
        <label for="editFirstName">First Name</label>
      </div>

      <!-- Middle Name and Suffix on one row -->
      <div class="field-row" style="gap:12px;align-items:flex-start;">
        <div style="flex:1;">
          <div class="input-field"><input id="editMiddleName" name="edit_middle_name" type="text"><label for="editMiddleName">Middle Name (optional)</label></div>
        </div>
        <div style="flex:0 0 180px;">
          <div class="input-field"><input id="editSuffix" name="edit_suffix" type="text"><label for="editSuffix">Suffix</label></div>
        </div>
      </div>

      <!-- Section -->
      <div class="input-field">
        <input id="editSectionInput" name="edit_section" type="text">
        <label for="editSectionInput">Section</label>
      </div>

      <!-- Birthday and Sex in one row -->
      <div class="field-row" style="gap:12px;align-items:center;">
        <div style="flex:0 0 220px;">
          <div class="input-field"><input id="editBirthday" name="edit_birthday" type="date"><label class="active" for="editBirthday">Birthday</label></div>
        </div>

        <div style="flex:0 0 140px;">
          <label for="editSex" style="display:block;margin-bottom:6px;color:var(--muted-2);font-weight:600">Sex <span style="color:#d32f2f">*</span></label>
          <select id="editSex" name="edit_sex" class="browser-default" required>
            <option value="">Select</option>
            <option value="M">M</option>
            <option value="F">F</option>
          </select>
        </div>
      </div>

      <?php if (is_super_admin()): ?>
      <div class="input-field" style="margin-top:12px;">
        <label for="editTeacherSelect" style="display:block;margin-bottom:6px;color:var(--muted-2);font-weight:600">Assign Teacher</label>
        <select id="editTeacherSelect" name="edit_teacher_id" class="browser-default">
          <option value="">Unassigned</option>
          <?php foreach ($teachers_list as $t): ?>
            <option value="<?php echo htmlspecialchars($t['acc_id']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="confirm-row" style="margin-top:12px;"><label><input type="checkbox" id="editConfirmCheckbox"><span style="color:var(--muted-2)">I confirm I want to save these changes</span></label></div>

      <div style="margin-top:12px;" class="divider"></div>

      <div style="margin-top:12px;">
        <label><input type="checkbox" id="enableResetCheckbox"><span style="color:var(--muted-2)">Enable password reset</span></label>
        <div style="margin-top:8px;">
          <button type="button" id="resetPwdBtn" class="btn btn-plain" style="display:none;color:var(--air-sky);border:1px solid rgba(46,126,247,0.16)">Reset password to birthday</button>
          <div style="margin-top:6px;"><small class="muted">Sets account password to birthday (YYYY-MM-DD).</small></div>
        </div>
      </div>

      <div class="right-align" style="margin-top:12px;"><button class="btn-air" type="submit" id="saveEditBtn" disabled>Save changes</button></div>
    </form>
  </div>
</div>

<!-- Delete modal -->
<div id="deleteConfirmModal" class="modal">
  <div class="modal-content">
    <h5 style="color:var(--text)">Delete Selected Students</h5>
    <p id="deleteConfirmText">You are about to delete <strong id="deleteCount">0</strong> student(s).</p>
    <div id="deleteNamesContainer"><small class="muted">Selected students:</small><ul id="deleteNamesList" style="list-style:none;padding-left:0;margin-top:6px;"></ul></div>
    <div class="confirm-row" style="margin-top:8px;"><label><input type="checkbox" id="deleteConfirmCheckbox"><span style="color:var(--muted-2)">I confirm I want to permanently delete the selected student(s)</span></label></div>

    <form method="post" id="deleteConfirmForm"><input type="hidden" name="action" value="delete_selected"><div id="deleteHiddenInputs"></div>
      <div class="right-align" style="margin-top:16px;"><button type="button" class="btn btn-plain modal-close" id="cancelDeleteBtn">Cancel</button> <button type="submit" class="btn-danger" id="confirmDeleteBtn" disabled>Delete</button></div>
    </form>
  </div>
</div>

<!-- scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Materialize modals (do NOT use AutoInit)
  try {
    var modalEls = document.querySelectorAll('.modal');
    M.Modal.init(modalEls, {preventScrolling:true});
  } catch (e) { console.warn('Materialize modal init failed', e); }

  // Initialize Materialize selects ONLY for non-browser-default selects
  try {
    var selectEls = document.querySelectorAll('select:not(.browser-default)');
    selectEls.forEach(function(s){
      if (!M.FormSelect.getInstance(s)) M.FormSelect.init(s);
    });
  } catch (e) { console.warn('Select init failed', e); }

  // Tooltips
  try { M.Tooltip.init(document.querySelectorAll('.tooltipped')); } catch (e) {}

  // collapse toggle
  var headers = Array.from(document.querySelectorAll('.section-header'));
  var COLL_KEY = 'students_sections_collapsed_v3';
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
    if (!body) return;
    if (collapsedState[sec]) { body.classList.add('collapsed'); wrap.classList.add('collapsed'); if (icon) icon.style.transform='rotate(-90deg)'; }
    h.addEventListener('click', function(){
      try {
        var isCollapsed = body.classList.toggle('collapsed');
        wrap.classList.toggle('collapsed', isCollapsed);
        if (icon) icon.style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        collapsedState[sec] = isCollapsed;
        localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState));
        updateToggleBtnLabel();
      } catch (err) { console.error('Collapse toggle error', err); }
    });

    var sectionCheck = wrap.querySelector('.check-section-all');
    if (sectionCheck) sectionCheck.addEventListener('change', function(){ var checked=this.checked; wrap.querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; }); });
  });

  function areAllCollapsed(){ if (!headers.length) return false; var all=true; headers.forEach(function(h){ if (!collapsedState[h.getAttribute('data-section')]) all=false; }); return all; }
  var toggleBtn = document.getElementById('toggleCollapseBtn');
  function updateToggleBtnLabel(pulse){ var allCollapsed = areAllCollapsed(); if (toggleBtn) toggleBtn.textContent = allCollapsed ? 'Expand All' : 'Collapse All'; if(pulse && toggleBtn){ toggleBtn.classList.remove('btn-pulse'); void toggleBtn.offsetWidth; toggleBtn.classList.add('btn-pulse'); } }
  function setAllSectionsCollapsed(collapsed){ headers.forEach(function(h){ var sec=h.getAttribute('data-section'); var body=document.getElementById(sec + '_body'); var wrap=document.getElementById(sec + '_wrap'); var icon=h.querySelector('.collapse-icon'); if (!body) return; if(collapsed){ body.classList.add('collapsed'); wrap.classList.add('collapsed'); if(icon)icon.style.transform='rotate(-90deg)'; } else { body.classList.remove('collapsed'); wrap.classList.remove('collapsed'); if(icon)icon.style.transform='rotate(0deg)'; } collapsedState[sec]=collapsed; }); try{ localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); }catch(e){} updateToggleBtnLabel(true); }
  updateToggleBtnLabel(false);
  if (toggleBtn) toggleBtn.addEventListener('click', function(){ var toCollapse = !areAllCollapsed(); setAllSectionsCollapsed(toCollapse); toggleBtn.classList.remove('btn-pulse'); void toggleBtn.offsetWidth; toggleBtn.classList.add('btn-pulse'); });

  // open Edit modal and populate
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

      // editSex is native browser select
      var editSexEl = document.getElementById('editSex');
      if (editSexEl) editSexEl.value = (sex === 'M' || sex === 'F') ? sex : '';

      var editTeacherEl = document.getElementById('editTeacherSelect');
      if (editTeacherEl) editTeacherEl.value = teacher || '';

      try { M.updateTextFields(); } catch(e){}

      var confirm = document.getElementById('editConfirmCheckbox');
      if (confirm) confirm.checked = false;
      var saveBtn = document.getElementById('saveEditBtn');
      if (saveBtn) saveBtn.disabled = true;

      var resetDbInput = document.getElementById('reset_db_id');
      if (resetDbInput) resetDbInput.value = dbId;
      var enReset = document.getElementById('enableResetCheckbox');
      if (enReset) enReset.checked = false;
      var resetBtn = document.getElementById('resetPwdBtn');
      if (resetBtn) { resetBtn.style.display = 'none'; resetBtn.disabled = true; }

      // clear and reattach file input handlers (the small IIFE below does replacement too)
      try { clearAndAttachEditFileInput(); } catch(e){}

      var modal = document.getElementById('editStudentModal');
      try { M.Modal.getInstance(modal).open(); } catch(e){ if (modal) modal.style.display = 'block'; }
      setTimeout(function(){ try{ document.getElementById('editFirstName').focus(); }catch(e){} }, 200);
    } catch (err) { console.error('openEditModal error', err); }
  }

  Array.from(document.querySelectorAll('.edit-btn')).forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); openEditModal(this); }); });

  // Add modal open: reset preview/fields
  var addBtn = document.getElementById('addBtn');
  if (addBtn) addBtn.addEventListener('click', function(){
    try {
      var inst = M.Modal.getInstance(document.getElementById('addStudentModal'));
      if (document.getElementById('addPreview')) document.getElementById('addPreview').src = 'assets/avatar.png';
      var addForm = document.getElementById('addForm');
      if (addForm) addForm.reset();
      if (inst) inst.open();
      setTimeout(function(){ try{ document.getElementById('addLastName')?.focus(); }catch(e){} }, 200);
    } catch (err) { console.error('open add modal error', err); }
  });

  // image preview handlers for add
  var addPhoto = document.getElementById('addStudentPhoto');
  if (addPhoto) addPhoto.addEventListener('change', function(){
    var f = this.files && this.files[0];
    if (!f) return;
    if (!f.type.startsWith('image/')) { try { M.toast({html:'Please select an image file.'}); } catch(e){} this.value=''; return; }
    var r=new FileReader(); r.onload=function(e){ var p=document.getElementById('addPreview'); if(p) p.src=e.target.result; }; r.readAsDataURL(f);
  });

  // Edit form: enable Save via confirm checkbox and enforce sex selection
  var editConfirm = document.getElementById('editConfirmCheckbox');
  if (editConfirm) editConfirm.addEventListener('change', function(){ var sb=document.getElementById('saveEditBtn'); if (sb) sb.disabled = !this.checked; });
  var editForm = document.getElementById('editForm');
  if (editForm) editForm.addEventListener('submit', function(e){
    var s = document.getElementById('editSex');
    if (s && (s.value !== 'M' && s.value !== 'F')) { e.preventDefault(); try{ M.toast({html:'Sex must be M or F.'}); }catch(e){} return; }
    var conf = document.getElementById('editConfirmCheckbox');
    if (!conf || !conf.checked) { e.preventDefault(); try{ M.toast({html:'Please confirm before saving.'}); }catch(e){} return; }
  });

  // Add form: ensure sex chosen
  var addFormEl = document.getElementById('addForm');
  if (addFormEl) addFormEl.addEventListener('submit', function(e){
    var s = document.getElementById('addSex');
    if (s && (s.value !== 'M' && s.value !== 'F')) { e.preventDefault(); try{ M.toast({html:'Sex must be M or F.'}); }catch(e){} return; }
  });

  // delete selected logic
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
    items.forEach(function(it){ var li=document.createElement('li'); li.style.display='flex'; li.style.alignItems='center'; li.style.gap='10px'; li.style.margin='6px 0'; var img=document.createElement('img'); img.src=it.avatar; img.className='delete-list-avatar'; img.onerror=function(){this.src='assets/avatar.png'}; var span=document.createElement('span'); span.textContent=it.name; span.style.color='var(--text)'; li.appendChild(img); li.appendChild(span); namesList.appendChild(li); });

    var hid = document.getElementById('deleteHiddenInputs'); hid.innerHTML = '';
    items.forEach(function(it){ var i=document.createElement('input'); i.type='hidden'; i.name='delete_ids[]'; i.value=it.id; hid.appendChild(i); });

    var chk = document.getElementById('deleteConfirmCheckbox'); if (chk) chk.checked = false;
    var confirmBtn = document.getElementById('confirmDeleteBtn'); if (confirmBtn) confirmBtn.disabled = true;
    try { M.Modal.getInstance(document.getElementById('deleteConfirmModal')).open(); } catch (err) { document.getElementById('deleteConfirmModal').style.display='block'; }
    setTimeout(function(){ try{ document.getElementById('deleteConfirmCheckbox').focus(); }catch(e){} }, 220);
  });

  var delChk = document.getElementById('deleteConfirmCheckbox');
  if (delChk) delChk.addEventListener('change', function(){ var btn = document.getElementById('confirmDeleteBtn'); if (btn) btn.disabled = !this.checked; });
  var delForm = document.getElementById('deleteConfirmForm');
  if (delForm) delForm.addEventListener('submit', function(e){ if (!document.getElementById('deleteConfirmCheckbox').checked) { e.preventDefault(); try{ M.toast({html:'Please confirm before deleting.'}); }catch(e){} return; } });

  // reset password toggle: show button only when checked
  var enableResetCheckbox = document.getElementById('enableResetCheckbox');
  var resetPwdBtn = document.getElementById('resetPwdBtn');
  if (enableResetCheckbox && resetPwdBtn) {
    enableResetCheckbox.addEventListener('change', function() {
      if (this.checked) { resetPwdBtn.style.display = ''; resetPwdBtn.disabled = false; } else { resetPwdBtn.style.display = 'none'; resetPwdBtn.disabled = true; }
    });
  }

  // Reset button behavior
  if (resetPwdBtn) {
    resetPwdBtn.addEventListener('click', function(e) {
      var birthdayField = document.getElementById('editBirthday');
      if (!birthdayField || (birthdayField.value || '').trim() === '') { try{ M.toast({html: 'Cannot reset password: student has no birthday recorded.'}); }catch(e){} return; }
      var confirmMsg = 'Reset this student password to their birthday (' + birthdayField.value + ')? This will overwrite the existing password.';
      if (!confirm(confirmMsg)) return;
      var dbId = (document.getElementById('reset_db_id') && document.getElementById('reset_db_id').value) || (document.getElementById('edit_db_id') && document.getElementById('edit_db_id').value);
      if (!dbId) { try{ M.toast({html:'Student ID missing; cannot reset.'}); }catch(e){} return; }
      var f = document.createElement('form'); f.method='post'; f.action='students.php';
      var a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='reset_password'; f.appendChild(a);
      var i = document.createElement('input'); i.type='hidden'; i.name='db_id'; i.value = dbId; f.appendChild(i);
      document.body.appendChild(f); f.submit();
    });
  }

  // global checkboxes
  var toggleAll = document.getElementById('checkAll');
  if (toggleAll) toggleAll.addEventListener('change', function(){ var checked=this.checked; document.querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; }); });

  // small helpers to manage the edit file input (clear + attach preview)
  window.clearAndAttachEditFileInput = function() {
    try {
      var existing = document.getElementById('editStudentPhoto');
      if (existing) {
        // replace node to clear value reliably across browsers
        var parent = existing.parentNode;
        var clone = existing.cloneNode();
        clone.id = 'editStudentPhoto';
        clone.name = 'edit_student_photo';
        clone.type = 'file';
        clone.accept = existing.accept || 'image/*';
        clone.style.display = 'none';
        parent.replaceChild(clone, existing);
      }
      var input = document.getElementById('editStudentPhoto');
      var preview = document.getElementById('editPreview');
      if (input) {
        input.addEventListener('change', function(){
          var f = this.files && this.files[0];
          if (!f) return;
          if (!f.type.startsWith('image/')) { try{ M.toast({html:'Please select an image file.'}); }catch(e){} this.value=''; return; }
          var r = new FileReader();
          r.onload = function(ev){ if (preview) preview.src = ev.target.result; };
          r.readAsDataURL(f);
        }, false);
      }
    } catch (err) { console.warn('clearAndAttachEditFileInput error', err); }
  };

  // wire the visible Change Photo button to the hidden input inside the form
  (function(){
    var visibleBtn = document.querySelector('.id-card .id-photo-button');
    var hiddenInput = document.getElementById('editStudentPhoto');
    if (visibleBtn && hiddenInput) {
      visibleBtn.style.cursor = 'pointer';
      visibleBtn.addEventListener('click', function(e){
        e.preventDefault();
        // ensure input present
        if (!document.getElementById('editStudentPhoto')) clearAndAttachEditFileInput();
        var input = document.getElementById('editStudentPhoto');
        if (input) input.click();
      }, false);
    }
  })();

  // initialize edit file input handlers now (in case modal opened via keyboard)
  try { clearAndAttachEditFileInput(); } catch(e){}

});
</script>

</body>
</html>

<?php
// close DB connections
if ($acc_db_ok && isset($acc_conn) && $acc_conn instanceof mysqli) $acc_conn->close();
if (isset($conn) && $conn instanceof mysqli) $conn->close();
?>

