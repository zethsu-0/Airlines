<?php
// students_edit.php - full page (GET shows form; POST processes update)
// Save as UTF-8 without BOM

session_start();

// ---------- CONFIG ----------
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';

// DB names / table/column mapping (adjust if different)
$DB_ACCOUNTS = 'airlines';
$ACCOUNTS_TABLE = 'accounts';
$ACCOUNTS_ID_COL = 'acc_id';
$ACCOUNTS_PW_COL = 'password';
$ACCOUNTS_NAME_COL = 'acc_name';

$DB_AIRLINES = 'airlines';
$STUDENTS_TABLE = 'students';
$STUDENTS_ID_COL = 'student_id';
$STUDENTS_AVATAR_COL = 'avatar';

$maxFileSize = 2 * 1024 * 1024; // 2 MB
$allowedMimes = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];

$uploadDir = __DIR__ . '/uploads/avatars';
$publicUploadPath = 'uploads/avatars';

$default_avatar = 'assets/avatar.png';

// ---------- HELPERS ----------
function flash($k, $v){ $_SESSION[$k] = $v; }
function get_flash($k){ $v = $_SESSION[$k] ?? null; if (isset($_SESSION[$k])) unset($_SESSION[$k]); return $v; }
function redirect($url){ header('Location: '.$url); exit; }

// ---------- AUTH: require login ----------
if (empty($_SESSION['acc_id'])) {
    redirect('login.php');
}

$acc_id = (string) $_SESSION['acc_id'];

// ---------- Process POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_SESSION['csrf_token']) && empty($_POST['csrf_token'])) {
        flash('flash_error', 'Invalid request (CSRF).');
        redirect('students_edit.php');
    }
    if (!empty($_SESSION['csrf_token']) && !empty($_POST['csrf_token'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            flash('flash_error', 'Invalid CSRF token.');
            redirect('students_edit.php');
        }
    }

    $current_password = trim((string)($_POST['current_password'] ?? ''));
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $changingPassword = ($new_password !== '' || $confirm_password !== '');
    if ($changingPassword) {
        if ($new_password !== $confirm_password) {
            flash('flash_error','New passwords do not match.');
            redirect('students_edit.php');
        }
        if (strlen($new_password) < 6) {
            flash('flash_error','New password must be at least 6 characters.');
            redirect('students_edit.php');
        }
        if ($current_password === '') {
            flash('flash_error','Please enter your current password to change it.');
            redirect('students_edit.php');
        }
    }

    $avatarUploaded = isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name']);
    $avatarTmp = $avatarUploaded ? $_FILES['avatar']['tmp_name'] : null;
    $avatarErr = $avatarUploaded ? $_FILES['avatar']['error'] : UPLOAD_ERR_NO_FILE;
    $avatarSize = $avatarUploaded ? $_FILES['avatar']['size'] : 0;

    if ($avatarUploaded && $avatarErr !== UPLOAD_ERR_OK) {
        flash('flash_error','Avatar upload error (code '.intval($avatarErr).').' );
        redirect('students_edit.php');
    }
    if ($avatarUploaded && $avatarSize > $maxFileSize) {
        flash('flash_error','Avatar file too large (max 2 MB).');
        redirect('students_edit.php');
    }

    if ($avatarUploaded && !is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            flash('flash_error','Failed to create upload directory.');
            redirect('students_edit.php');
        }
    }

    $accDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_ACCOUNTS);
    if ($accDB->connect_errno) { flash('flash_error','Cannot connect to accounts DB.'); redirect('students_edit.php'); }
    $accDB->set_charset('utf8mb4');

    $airDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_AIRLINES);
    if ($airDB->connect_errno) { $accDB->close(); flash('flash_error','Cannot connect to airlines DB.'); redirect('students_edit.php'); }
    $airDB->set_charset('utf8mb4');

    try {
        $stmt = $accDB->prepare("SELECT {$ACCOUNTS_PW_COL} FROM {$ACCOUNTS_TABLE} WHERE {$ACCOUNTS_ID_COL} = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (accounts select).');
        $stmt->bind_param('s', $acc_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('Account not found.');

        $currentHash = $row[$ACCOUNTS_PW_COL] ?? '';

        if ($changingPassword) {
            if (!password_verify($current_password, $currentHash)) {
                throw new Exception('Current password is incorrect.');
            }
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($newHash === false) throw new Exception('Password hashing failed.');
        }

        $stmt2 = $airDB->prepare("SELECT {$STUDENTS_AVATAR_COL} FROM {$STUDENTS_TABLE} WHERE {$STUDENTS_ID_COL} = ? LIMIT 1");
        if (!$stmt2) throw new Exception('DB prepare failed (students select).');
        $stmt2->bind_param('s', $acc_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $srow = $res2->fetch_assoc();
        $stmt2->close();
        $oldAvatar = $srow[$STUDENTS_AVATAR_COL] ?? null;

        $newAvatarRelative = null;
        $movedFilePath = null;
        if ($avatarUploaded) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($avatarTmp);
            if (!isset($allowedMimes[$mime])) throw new Exception('Unsupported avatar image type. Allowed: JPG, PNG, WEBP.');
            $ext = $allowedMimes[$mime];
            $safeBase = preg_replace('/[^a-z0-9_\-]/i','_', $acc_id);
            $filename = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(6)) . $ext;
            $targetFull = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($avatarTmp, $targetFull)) throw new Exception('Failed to move uploaded file.');
            @chmod($targetFull, 0644);
            $newAvatarRelative = rtrim($publicUploadPath, '/') . '/' . $filename;
            $movedFilePath = $targetFull;
        }

        if ($changingPassword) {
            $upd = $accDB->prepare("UPDATE {$ACCOUNTS_TABLE} SET {$ACCOUNTS_PW_COL} = ? WHERE {$ACCOUNTS_ID_COL} = ? LIMIT 1");
            if (!$upd) throw new Exception('DB prepare failed (accounts update).');
            $upd->bind_param('ss', $newHash, $acc_id);
            if (!$upd->execute()) throw new Exception('Failed to update password.');
            $upd->close();
        }

        if ($newAvatarRelative !== null) {
            if ($srow) {
                $upd2 = $airDB->prepare("UPDATE {$STUDENTS_TABLE} SET {$STUDENTS_AVATAR_COL} = ? WHERE {$STUDENTS_ID_COL} = ? LIMIT 1");
                if (!$upd2) throw new Exception('DB prepare failed (students update).');
                $upd2->bind_param('ss', $newAvatarRelative, $acc_id);
                if (!$upd2->execute()) throw new Exception('Failed to update avatar.');
                $upd2->close();
            } else {
                $ins = $airDB->prepare("INSERT INTO {$STUDENTS_TABLE} ({$STUDENTS_ID_COL}, {$STUDENTS_AVATAR_COL}) VALUES (?, ?)");
                if (!$ins) throw new Exception('DB prepare failed (students insert).');
                $ins->bind_param('ss', $acc_id, $newAvatarRelative);
                if (!$ins->execute()) throw new Exception('Failed to insert student row.');
                $ins->close();
            }

            if ($oldAvatar) {
                $realOld = realpath(__DIR__ . '/' . ltrim($oldAvatar, '/\\'));
                $allowed = realpath($uploadDir);
                if ($realOld && $allowed && strpos($realOld, $allowed) === 0 && is_file($realOld)) {
                    @unlink($realOld);
                }
            }
        }

        flash('flash_success', 'Profile updated successfully.');
        $accDB->close();
        $airDB->close();
        redirect('students_edit.php');

    } catch (Exception $e) {
        if (!empty($movedFilePath) && file_exists($movedFilePath)) @unlink($movedFilePath);
        error_log('[students_edit] ' . $e->getMessage());
        flash('flash_error', 'Failed to update profile: ' . $e->getMessage());
        if ($accDB) $accDB->close();
        if ($airDB) $airDB->close();
        redirect('students_edit.php');
    }
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$accName = 'Student';
$avatarPath = $default_avatar;

try {
    $accDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_ACCOUNTS);
    $accDB->set_charset('utf8mb4');
    $stmt = $accDB->prepare("SELECT {$ACCOUNTS_NAME_COL} FROM {$ACCOUNTS_TABLE} WHERE {$ACCOUNTS_ID_COL} = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $acc_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($row = $r->fetch_assoc()) $accName = $row[$ACCOUNTS_NAME_COL] ?? $accName;
        $stmt->close();
    }
    $accDB->close();
} catch (Exception $e) {}

try {
    $airDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_AIRLINES);
    $airDB->set_charset('utf8mb4');
    $stmt2 = $airDB->prepare("SELECT {$STUDENTS_AVATAR_COL} FROM {$STUDENTS_TABLE} WHERE {$STUDENTS_ID_COL} = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('s', $acc_id);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        if ($row2 = $r2->fetch_assoc()) $avatarPath = $row2[$STUDENTS_AVATAR_COL] ?: $avatarPath;
        $stmt2->close();
    }
    $airDB->close();
} catch (Exception $e) {}

$flash_success = get_flash('flash_success');
$flash_error = get_flash('flash_error');

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Profile — TOURS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<?php include('templates/header.php'); ?>

<!-- preserve island image using an actual <img> element so it reliably displays -->
<img src="assets/island.jpg" alt="" class="page-bg-img" aria-hidden="true">

<main class="page-main container">
  <div class="overlay-box">

    <?php if ($flash_success): ?>
      <div class="notice notice-success"><?php echo htmlspecialchars($flash_success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="notice notice-error"><?php echo htmlspecialchars($flash_error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="grid" role="region" aria-label="Profile edit area">
      <section class="panel profile-panel" aria-labelledby="profileHeading">
        <div class="profile-top">
          <img id="currentAvatar" src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES); ?>" alt="Avatar" class="avatar-large">
          <div class="profile-info">
            <h2 id="profileHeading" class="profile-name"><?php echo htmlspecialchars($accName, ENT_QUOTES); ?></h2>
            <div class="muted">Student ID: <strong><?php echo htmlspecialchars($acc_id, ENT_QUOTES); ?></strong></div>
            <p class="muted small">Change your profile picture and password here.</p>

            <div class="profile-actions" role="group" aria-label="profile actions">
              <a href="index.php" class="btn gradient-btn" style="display:inline-flex;align-items:center;">Back to Home</a>
              <a href="logout.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;">Logout</a>
            </div>
          </div>
        </div>
      </section>

      <section class="panel edit-panel" aria-labelledby="editHeading">
        <h3 id="editHeading">Edit Profile</h3>

        <form id="editForm" action="students_edit.php" method="POST" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">

          <div class="form-row avatar-row">
            <div class="avatar-preview-wrap">
              <img id="preview" src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES); ?>" alt="preview" class="avatar-preview">
            </div>

            <div class="avatar-controls">
              <label for="avatarInput" class="file-label" tabindex="0" aria-label="Choose avatar image">
                <span class="file-btn">Choose image</span>
              </label>
              <input id="avatarInput" type="file" name="avatar" accept="image/*" style="display:none;">
              <div class="muted" style="margin-top:8px;">Allowed: JPG, PNG, WEBP — max 2 MB</div>
              <div class="hint" style="margin-top:10px;">
                <small class="muted">Recommended: square image, at least 300×300px.</small>
              </div>
            </div>
          </div>

          <div class="form-row passwords" style="margin-top:12px;">
            <div class="input-field">
              <input id="current_password" name="current_password" type="password" autocomplete="current-password" />
              <label for="current_password">Current password</label>
            </div>

            <div class="input-field">
              <input id="new_password" name="new_password" type="password" autocomplete="new-password" />
              <label for="new_password">New password</label>
            </div>

            <div class="input-field">
              <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" />
              <label for="confirm_password">Confirm new password</label>
            </div>
          </div>

          <div class="form-row actions-row">
            <button id="saveBtn" type="submit" class="btn gradient-btn">Save changes</button>
            <button type="reset" id="formResetBtn" class="btn btn-ghost">Reset</button>
          </div>
        </form>
      </section>
    </div>

  </div>
</main>

<?php include('templates/footer.php'); ?>

<style>
:root{
  --accent-1: #0d47a1;
  --accent-2: #1976d2;
  --muted: #bfc9d9;
  --card-bg: rgba(255,255,255,0.04);
  --max-width: 1100px;
}

/* use an image element so the island displays reliably */
.page-bg-img{
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  object-position: center center;
  filter: brightness(0.72) saturate(0.95);
  z-index: -1000;
  pointer-events: none;
  user-select: none;
}

/* main layout */
.page-main { padding: 36px 18px; display: flex; justify-content: center; min-height: calc(100vh - 84px); }
.overlay-box { width:100%; max-width: var(--max-width); background: linear-gradient(180deg, rgba(12,18,36,0.48), rgba(8,12,24,0.56)); border-radius:14px; padding:24px; box-shadow:0 18px 48px rgba(2,8,23,0.6); color:#fff; box-sizing:border-box; }

.notice { padding:12px 14px; border-radius:8px; margin-bottom:14px; font-weight:600; }
.notice-success { background: rgba(40,167,69,0.08); color: #c8f6d0; border: 1px solid rgba(40,167,69,0.12); }
.notice-error { background: rgba(198,40,40,0.06); color: #ffd6d6; border: 1px solid rgba(198,40,40,0.12); }

.grid { display:grid; grid-template-columns:360px 1fr; gap:18px; align-items:start; }
@media (max-width: 880px) { .grid { grid-template-columns: 1fr; } }

.panel { background: var(--card-bg); border-radius:12px; padding:16px; box-shadow: 0 8px 30px rgba(2,8,23,0.45); }

.profile-top { display:flex; gap:16px; align-items:center; }
.avatar-large { width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid rgba(255,255,255,0.14); box-shadow:0 6px 20px rgba(2,8,23,0.6); }
.profile-name { margin:0 0 6px; font-size:1.25rem; font-weight:700; color:#fff; }
.profile-info .muted { color: var(--muted); margin-top:6px; }

/* PROFILE ACTIONS: ensure no offset */
.profile-actions { margin-top:12px; display:flex; gap:10px; align-items:center; justify-content:flex-start; flex-wrap:wrap; }
.profile-actions .btn { display:inline-flex !important; align-items:center !important; justify-content:center !important; padding: 0 14px !important; height:44px !important; line-height:1 !important; vertical-align:middle !important; border-radius:10px !important; }

/* edit panel */
.edit-panel h3 { margin-top:0; margin-bottom:12px; font-size:1.05rem; color:#fff; }
.avatar-row { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.avatar-preview { width:110px; height:110px; border-radius:12px; object-fit:cover; border:3px solid rgba(255,255,255,0.08); box-shadow:0 6px 18px rgba(0,0,0,0.45); }
.file-label { cursor:pointer; display:inline-block; }
.file-btn { display:inline-block; padding:8px 12px; border-radius:8px; font-weight:700; color:#fff; background: linear-gradient(90deg, var(--accent-1), var(--accent-2)); box-shadow:0 8px 18px rgba(13,71,161,0.12); }

.input-field { margin-bottom:12px; }
.input-field input { color:#fff !important; }
.input-field label { color: var(--muted) !important; }

/* ACTIONS: make Save & Reset identical height/align */
.actions-row { margin-top:12px; display:flex; gap:12px; align-items:center; }
.btn { border-radius:10px; padding: 10px 16px; font-weight:700; height:44px; display:inline-flex; align-items:center; justify-content:center; }
.gradient-btn { color:#fff !important; background: linear-gradient(90deg, var(--accent-1), var(--accent-2)); border:none; box-shadow: 0 12px 28px rgba(13,71,161,0.12); }
.btn-ghost { background: transparent; border: 1px solid rgba(255,255,255,0.06); color:#fff; height:44px; padding: 0 14px; }

.muted { color: var(--muted); }
.hint { color: var(--muted); font-size:.9rem; }

@media (max-width:520px) {
  .avatar-large { width:96px; height:96px; }
  .avatar-preview { width:88px; height:88px; }
  .profile-actions { gap:8px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Preview avatar when user selects a file
  const avatarInput = document.getElementById('avatarInput');
  const preview = document.getElementById('preview');
  const currentAvatar = document.getElementById('currentAvatar');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      const f = this.files && this.files[0];
      if (!f) return;
      if (!f.type || !f.type.startsWith('image/')) {
        if (window.M && M.toast) M.toast({ html: 'Please select an image file.'});
        this.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = function (ev) { preview.src = ev.target.result; };
      reader.readAsDataURL(f);
    });
  }

  const fileLabel = document.querySelector('.file-label');
  if (fileLabel && avatarInput) {
    fileLabel.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); avatarInput.click(); }
    });
  }

  const resetBtn = document.getElementById('formResetBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      setTimeout(function () {
        preview.src = currentAvatar.src || '<?php echo addslashes($default_avatar); ?>';
        if (avatarInput) avatarInput.value = '';
      }, 10);
    });
  }

  // extra: detect if background image failed to load and warn in console
  const bg = document.querySelector('.page-bg-img');
  if (bg) {
    bg.addEventListener('error', function () {
      console.warn('Background image failed to load:', bg.src);
    });
  }
});
</script>

</body>
</html>

