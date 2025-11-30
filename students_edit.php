<?php
// students_edit.php - full page (GET shows form; POST processes update)
// Save as UTF-8 without BOM

session_start();

// ---------- CONFIG ----------
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';

// DB names / table/column mapping (adjust if different)
$DB_ACCOUNTS = 'account';
$ACCOUNTS_TABLE = 'accounts';
$ACCOUNTS_ID_COL = 'acc_id';
$ACCOUNTS_PW_COL = 'password';
$ACCOUNTS_NAME_COL = 'acc_name';

$DB_AIRLINES = 'airlines';
$STUDENTS_TABLE = 'students';
$STUDENTS_ID_COL = 'student_id'; // your students primary column
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
    // redirect to login page (preserve where to come back if you like)
    redirect('login.php');
}

$acc_id = (string) $_SESSION['acc_id'];

// ---------- Process POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optional CSRF check
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

    // normalize inputs
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

    // Validate upload
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

    // ensure upload dir exists if needed
    if ($avatarUploaded && !is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            flash('flash_error','Failed to create upload directory.');
            redirect('students_edit.php');
        }
    }

    // connect to DBs
    $accDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_ACCOUNTS);
    if ($accDB->connect_errno) { flash('flash_error','Cannot connect to accounts DB.'); redirect('students_edit.php'); }
    $accDB->set_charset('utf8mb4');

    $airDB = new mysqli($dbHost,$dbUser,$dbPass,$DB_AIRLINES);
    if ($airDB->connect_errno) { $accDB->close(); flash('flash_error','Cannot connect to airlines DB.'); redirect('students_edit.php'); }
    $airDB->set_charset('utf8mb4');

    try {
        // fetch current password hash
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

        // fetch existing student avatar (if any)
        $stmt2 = $airDB->prepare("SELECT {$STUDENTS_AVATAR_COL} FROM {$STUDENTS_TABLE} WHERE {$STUDENTS_ID_COL} = ? LIMIT 1");
        if (!$stmt2) throw new Exception('DB prepare failed (students select).');
        $stmt2->bind_param('s', $acc_id); // mapping acc_id -> student_id
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $srow = $res2->fetch_assoc();
        $stmt2->close();
        $oldAvatar = $srow[$STUDENTS_AVATAR_COL] ?? null;

        // process avatar upload
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

        // perform updates inside try/catch (no distributed transactions; operations done sequentially)
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
                // insert minimal row - adjust if students table requires other fields NOT NULL
                $ins = $airDB->prepare("INSERT INTO {$STUDENTS_TABLE} ({$STUDENTS_ID_COL}, {$STUDENTS_AVATAR_COL}) VALUES (?, ?)");
                if (!$ins) throw new Exception('DB prepare failed (students insert).');
                $ins->bind_param('ss', $acc_id, $newAvatarRelative);
                if (!$ins->execute()) throw new Exception('Failed to insert student row.');
                $ins->close();
            }

            // delete old avatar file safely if it's inside uploads dir
            if ($oldAvatar) {
                $realOld = realpath(__DIR__ . '/' . ltrim($oldAvatar, '/\\'));
                $allowed = realpath($uploadDir);
                if ($realOld && $allowed && strpos($realOld, $allowed) === 0 && is_file($realOld)) {
                    @unlink($realOld);
                }
            }
        }

        // success
        flash('flash_success', 'Profile updated successfully.');
        $accDB->close();
        $airDB->close();
        redirect('students_edit.php');

    } catch (Exception $e) {
        // cleanup moved file if any
        if (!empty($movedFilePath) && file_exists($movedFilePath)) @unlink($movedFilePath);
        error_log('[students_edit] ' . $e->getMessage());
        flash('flash_error', 'Failed to update profile: ' . $e->getMessage());
        if ($accDB) $accDB->close();
        if ($airDB) $airDB->close();
        redirect('students_edit.php');
    }
}

// Ensure CSRF token exists for form
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

// ---------- On GET: fetch current profile data ----------
$accName = 'Student';
$avatarPath = $default_avatar;

// connect to DBs to get display info (best-effort; not fatal if fails)
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
} catch (Exception $e) {
    // ignore
}

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
} catch (Exception $e) {
    // ignore
}

// Pull flash messages to show
$flash_success = get_flash('flash_success');
$flash_error = get_flash('flash_error');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Materialize (local includes are ok if you have them in templates/header.php) -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"/>
<style>
  :root {
    --accent: #516BFF;
    --muted: #ccc;
  }

  /* Make sure page background is transparent so the background image shows */
  html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    background: transparent !important;
    font-family: "Roboto", "Helvetica", Arial, sans-serif;
  }

  /* FULL-SCREEN BACKGROUND IMAGE */
  .background-image {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    object-fit: cover;
    object-position: center;
    z-index: -9999;
    pointer-events: none;
    filter: brightness(1.03) saturate(1.05) contrast(1.05);
  }

  /* OUTER TRANSLUCENT BOX (the wrapper container) */
  .overlay-box {
    background: rgba(30, 30, 30, 0.46);  /* dark gray transparent */
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.35);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    color: #fff;
    z-index: 10;
    position: relative;
  }

  /* Override Materialize card styles (many are solid white) */
  .overlay-box .card,
  .overlay-box .card-panel {
    background: rgba(255,255,255,0.05) !important;
    color: #fff !important;
    border-radius: 12px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
  }

  /* PROFILE CARD */
  .profile-card {
    background: rgba(255,255,255,0.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    color: #fff;
  }

  /* EDIT CARD */
  .edit-card {
    background: rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    color: #fff;
  }

  /* AVATAR */
  .avatar-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid rgba(255,255,255,0.3);
  }

  /* small preview image */
  #preview {
    width: 110px;
    height: 110px;
    border-radius: 10px;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.4);
    display: block;
    margin-bottom: 8px;
  }

  /* FILE SELECT BUTTON */
  .file-label-btn {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 6px;
    background: rgba(50, 120, 255, 0.8);
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    transition: 0.15s;
  }
  .file-label-btn:hover {
    background: rgba(50, 120, 255, 1);
  }

  /* INPUT LABELS & TEXT COLORS */
  label {
    color: #eee !important;
  }
  .input-field input {
    color: #fff !important;
  }
  .input-field input:focus + label {
    color: #fff !important;
  }
  .input-field input:focus {
    border-bottom: 1px solid #fff !important;
    box-shadow: 0 1px 0 0 #fff !important;
  }

  /* Buttons on dark translucent background */
  .overlay-box .btn {
    color: #fff !important;
  }
  .overlay-box .btn-flat {
    color: #fff !important;
  }

  /* TEXT MUTED */
  .muted {
    color: #ddd;
  }

  /* Responsive layout */
  @media (max-width: 600px) {
    .overlay-box {
      margin: 12px;
      padding: 16px;
      border-radius: 12px;
    }
    .avatar-large {
      width: 110px;
      height: 110px;
    }
    .profile-card, .edit-card {
      padding: 14px;
    }
  }
</style>

</head>
<body>
  <?php include('templates/header.php'); ?>

  <img src="assets/island.jpg" class="background-image">


    <div class="container container-compact overlay-box">

    <?php if ($flash_success): ?>
      <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo htmlspecialchars($flash_success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo htmlspecialchars($flash_error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="profile-row">
      <div class="profile-left">
        <div class="profile-card">
          <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <img id="currentAvatar" src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES); ?>" alt="Avatar" class="avatar-large">
            <div style="flex:1; min-width:160px;">
              <h5 style="margin:0;"><?php echo htmlspecialchars($accName, ENT_QUOTES); ?></h5>
              <p style="margin:6px 0 0;"><strong>Student ID:</strong> <?php echo htmlspecialchars($acc_id, ENT_QUOTES); ?></p>
              <p class="small-note">You can change your profile picture and password here.</p>

              <div class="hdr-actions" style="margin-top:12px;">
                <a href="index.php" class="btn blue lighten-1 black-text">Back</a>
                <a href="logout.php" class="btn red">Logout</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="profile-right" style="margin-top:12px;">
        <div class="edit-card">
          <h6 style="margin-top:0;">Edit Profile</h6>

          <form id="editForm" action="students_edit.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">

            <div class="row" style="margin-bottom:0;">
              <div class="col s12">
                <label for="avatarInput" class="muted"></label><br>
                <img id="preview" src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES); ?>" alt="preview">
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                  <label class="file-label-btn" for="avatarInput">Choose image</label>
                  <input id="avatarInput" type="file" name="avatar" accept="image/*" style="display:none;">
                  <div class="muted" style="margin-left:6px;">Allowed: JPG, PNG, WEBP — max 2 MB</div>
                </div>
              </div>

              <div class="col s12" style="margin-top:16px;">
                <div class="input-field">
                  <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                  <label for="current_password">Current password</label>
                </div>

                <div class="input-field">
                  <input id="new_password" name="new_password" type="password" autocomplete="new-password">
                  <label for="new_password">New password</label>
                </div>

                <div class="input-field">
                  <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
                  <label for="confirm_password">Confirm new password</label>
                </div>
              </div>

              <div class="col s12" style="margin-top:6px;">
                <div class="controls-row">
                  <button id="saveBtn" type="submit" class="btn blue">Save changes</button>
                  <button type="reset" class="btn-flat">Reset</button>
                </div>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>

  </div>

  <!-- Materialize + scripts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      // preview image
      const avatarInput = document.getElementById('avatarInput');
      const preview = document.getElementById('preview');
      avatarInput && avatarInput.addEventListener('change', function(){
        const f = this.files && this.files[0];
        if (!f) return;
        if (!f.type.startsWith('image/')) { M.toast({ html: 'Please select an image file.'}); this.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => preview.src = e.target.result;
        reader.readAsDataURL(f);
      });

      // optional: improve labels for the custom file label
      const fileLabel = document.querySelector('.file-label-btn');
      if (fileLabel && avatarInput) {
        fileLabel.addEventListener('keydown', function(e){
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); avatarInput.click(); }
        });
        fileLabel.addEventListener('click', function(){ avatarInput.click(); });
      }
    });
  </script>
</body>
</html>
