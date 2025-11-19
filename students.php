<?php
// students.php - standalone (header embedded), synchronous, double-confirm edit
// Added: single toggle Collapse/Expand button (label switches) + button animations.
// IMPORTANT: save this file as UTF-8 WITHOUT BOM and place in your project root.

session_start();

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'airlines';   // change if needed
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

// ---------- CONNECT ----------
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('DB Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ---------- HELPERS ----------
function handle_avatar_upload($input_name, $existing = null) {
    global $uploads_dir;
    if (empty($_FILES[$input_name]) || empty($_FILES[$input_name]['tmp_name'])) {
        return $existing;
    }
    $f = $_FILES[$input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) return $existing;
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    $filename = uniqid('avatar_', true) . '.' . ($safe_ext ?: 'jpg');
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        return 'uploads/' . $filename;
    }
    return $existing;
}

function render_student_row_html($st) {
    $id = (int)$st['id'];
    $student_id = htmlspecialchars($st['student_id'] ?? '', ENT_QUOTES);
    $name = htmlspecialchars($st['name'] ?? '', ENT_QUOTES);
    $section = htmlspecialchars($st['section'] ?? '', ENT_QUOTES);
    $avatar = htmlspecialchars($st['avatar'] ?: 'assets/avatar.png', ENT_QUOTES);
    ob_start();
    ?>
<tr data-db-id="<?php echo $id; ?>">
  <td>
    <label>
      <input type="checkbox" class="filled-in chk" data-id="<?php echo $id; ?>" />
      <span></span>
    </label>
  </td>
  <td>
    <div class="table-avatar" style="background-image:url('<?php echo $avatar; ?>'); width:64px; height:64px; border-radius:50%; background-size:cover; background-position:center;"></div>
  </td>
  <td class="cell-name"><?php echo $name; ?></td>
  <td class="cell-studentid"><?php echo $student_id; ?></td>
  <td class="cell-section"><?php echo $section; ?></td>
  <td>
    <a class="btn-flat edit-btn tooltipped modal-trigger"
       href="#editStudentModal"
       data-db-id="<?php echo $id; ?>"
       data-name="<?php echo $name; ?>"
       data-studentid="<?php echo $student_id; ?>"
       data-section="<?php echo $section; ?>"
       data-avatar="<?php echo $avatar; ?>"
       data-position="left"
       data-tooltip="Edit">
      <i class="material-icons">edit</i>
    </a>
  </td>
</tr>
    <?php
    return ob_get_clean();
}

// ---------- POST HANDLERS (synchronous) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD STUDENT
    if (!empty($_POST['action']) && $_POST['action'] === 'add_student') {
        $name = trim($_POST['student_name'] ?? '');
        $student_id = trim($_POST['student_id_val'] ?? '');
        $section = trim($_POST['section'] ?? '');

        $avatar = handle_avatar_upload('student_photo', null);

        $stmt = $conn->prepare("INSERT INTO students (student_id, name, section, avatar, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('ssss', $student_id, $name, $section, $avatar);
        $stmt->execute();
        $stmt->close();

        header('Location: students.php');
        exit;
    }

    // EDIT STUDENT (synchronous)
    if (!empty($_POST['action']) && $_POST['action'] === 'edit_student') {
        $db_id = intval($_POST['db_id'] ?? 0);
        $name = trim($_POST['edit_student_name'] ?? '');
        $student_id = trim($_POST['edit_student_id'] ?? '');
        $section = trim($_POST['edit_section'] ?? '');

        // get current avatar
        $cur_avatar = null;
        $q = $conn->prepare("SELECT avatar FROM students WHERE id = ?");
        $q->bind_param('i', $db_id);
        $q->execute();
        $q->bind_result($cur_avatar);
        $q->fetch();
        $q->close();

        $new_avatar = handle_avatar_upload('edit_student_photo', $cur_avatar);

        $upd = $conn->prepare("UPDATE students SET student_id = ?, name = ?, section = ?, avatar = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param('ssssi', $student_id, $name, $section, $new_avatar, $db_id);
        $upd->execute();
        $upd->close();

        header('Location: students.php');
        exit;
    }

    // DELETE SELECTED (bulk)
    if (!empty($_POST['action']) && $_POST['action'] === 'delete_selected') {
        $ids = $_POST['delete_ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $clean = array_map('intval', $ids);
            $in = implode(',', $clean);

            // unlink avatars
            $res = $conn->query("SELECT avatar FROM students WHERE id IN ($in)");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    if (!empty($r['avatar']) && strpos($r['avatar'], 'uploads/') === 0) {
                        $f = __DIR__ . '/' . $r['avatar'];
                        if (is_file($f)) @unlink($f);
                    }
                }
                $res->free();
            }

            $conn->query("DELETE FROM students WHERE id IN ($in)");
        }
        header('Location: students.php');
        exit;
    }
}

// ---------- FETCH students ----------
$students = [];
$stmt = $conn->prepare("SELECT id, student_id, name, section, avatar FROM students ORDER BY COALESCE(NULLIF(section,''),'~'), section, name");
$stmt->execute();
$stmt->bind_result($sid_pk, $sid_val, $sname, $ssection, $savatar);
while ($stmt->fetch()) {
    $students[] = [
        'id' => $sid_pk,
        'student_id' => $sid_val,
        'name' => $sname,
        'section' => $ssection,
        'avatar' => $savatar ?: 'assets/avatar.png'
    ];
}
$stmt->close();

// group by section and compute counts
$groups = [];
foreach ($students as $st) {
    $sec = trim((string)$st['section']);
    if ($sec === '') $sec = 'Unassigned';
    if (!isset($groups[$sec])) $groups[$sec] = ['count' => 0, 'students' => []];
    $groups[$sec]['students'][] = $st;
    $groups[$sec]['count']++;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Students</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <style>
    html, body { margin: 0; padding: 0; background-color: #f5f5f5; }
    .page-wrap { padding:18px 0 48px; }
    .small-btn {
      border-radius:40px; padding:10px 20px; background:#4a74ff; color:#fff; font-weight:700;
      text-transform:uppercase; display:inline-block; border: none; cursor: pointer;
      transition: transform .12s ease, box-shadow .18s ease, background .12s ease;
      box-shadow: 0 6px 18px rgba(74,116,255,0.12);
    }
    .small-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(74,116,255,0.16); }
    .small-btn:active { transform: translateY(0); box-shadow: 0 6px 14px rgba(74,116,255,0.12); }
    .small-btn.delete { background:#ff5252; box-shadow: 0 6px 18px rgba(255,82,82,0.12); }
    .small-btn.ghost {
      background: transparent; color: #4a74ff; border: 2px solid #4a74ff; padding:8px 14px; text-transform:none; border-radius:8px; font-weight:700;
      transition: transform .12s ease, box-shadow .12s ease, background .12s ease, color .12s ease;
    }
    .btn-pulse {
      animation: pulse .5s ease;
    }
    @keyframes pulse {
      0% { transform: scale(1); box-shadow: 0 6px 18px rgba(74,116,255,0.12); }
      50% { transform: scale(1.05); box-shadow: 0 14px 34px rgba(74,116,255,0.18); }
      100% { transform: scale(1); box-shadow: 0 6px 18px rgba(74,116,255,0.12); }
    }

    .table-avatar { width:64px; height:64px; border-radius:50%; background-size:cover; background-position:center; }
    .edit-btn { background:#00d1ff; border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center; color:#fff; cursor:pointer; transition: transform .12s ease; }
    .edit-btn:hover { transform: translateY(-3px); }
    nav{ background-image: url(assets/Banner.png); background-size: cover; background-repeat: no-repeat; background-position: center center; height: 80px;}

    /* section header styling */
    .section-wrap { margin-bottom: 18px; }
    .section-header {
      display:flex; align-items:center; justify-content:space-between; background: transparent;
      padding: 8px 10px 6px 10px; color: #6b6b6b; text-transform: uppercase; font-weight: 700;
      letter-spacing: 0.6px; font-size: 14px; cursor: pointer; user-select: none;
      transition: background .12s ease;
    }
    .section-header:hover { background: rgba(0,0,0,0.02); }
    .section-title { display:flex; gap:10px; align-items:baseline; }
    .section-count { color:#9a9a9a; font-weight:600; font-size:12px; text-transform:none; }
    .section-hr {
      border: 0; height: 1px; background: linear-gradient(to right, rgba(0,0,0,0.06), rgba(0,0,0,0.12), rgba(0,0,0,0.06));
      margin: 6px 0 12px 0;
    }
    .section-body { transition: max-height 0.28s cubic-bezier(.4,0,.2,1), opacity 0.22s ease; overflow: hidden; }
    .section-body.collapsed { max-height: 0 !important; opacity: 0; padding: 0; margin: 0; }
    .collapse-icon { transition: transform 0.25s ease; color:#6b6b6b; }
    .collapsed .collapse-icon { transform: rotate(-90deg); }
    .section-table tbody tr td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
    @media (max-width: 600px) { .table-avatar { width:48px; height:48px; } }
    .top-controls { display:flex; gap:12px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
  </style>
</head>
<body>
<nav>
  <div class="nav-wrapper">
    <a href="admin.php" class="brand-logo center" style="line-height:80px; color:white; font-weight:800;">
      <i class="material-icons hide-on-med-and-down" style="vertical-align: middle;">flight_takeoff</i> &nbsp;TOURS
    </a>
  </div>
</nav>

<div class="container page-wrap">
  <div class="row" style="margin-bottom:6px;">
    <div class="col s12 m12">
      <div class="top-controls">
        <button id="addBtn" class="small-btn modal-trigger">Add Student</button>
        <button id="deleteSelectedBtn" class="small-btn delete">Delete Selected</button>

        <!-- Single toggle button for collapse/expand -->
        <button id="toggleCollapseBtn" class="small-btn ghost" type="button">Collapse All</button>
      </div>
    </div>
  </div>

  <form id="deleteForm" method="post" style="display:none;">
    <input type="hidden" name="action" value="delete_selected">
  </form>

  <!-- Render groups -->
  <?php if (empty($groups)): ?>
    <div class="card-panel">No students found.</div>
  <?php else: ?>
    <?php foreach ($groups as $sectionName => $grp): ?>
      <?php
        $safeSection = htmlspecialchars($sectionName, ENT_QUOTES);
        $count = (int)$grp['count'];
        // create a safe id for DOM storage
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
                <th style="width:48px;"><label><input type="checkbox" class="check-section-all" data-section="<?php echo $domId; ?>" /><span></span></label></th>
                <th>Avatar</th>
                <th>Name</th>
                <th>Student ID</th>
                <th>Section</th>
                <th style="width:120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grp['students'] as $st): ?>
                <?php echo render_student_row_html($st); ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Add Student Modal -->
<div id="addStudentModal" class="modal">
  <div class="modal-content">
    <h5>Add Student</h5>
    <form method="post" enctype="multipart/form-data" id="addForm">
      <input type="hidden" name="action" value="add_student">

      <div class="file-field input-field">
        <div class="btn blue"><span>Upload Photo</span><input type="file" name="student_photo" accept="image/*"></div>
        <div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Optional photo"></div>
      </div>

      <div class="input-field">
        <input id="studentName" name="student_name" type="text" required>
        <label for="studentName">Name</label>
      </div>

      <div class="input-field">
        <input id="studentID" name="student_id_val" type="text" required>
        <label for="studentID">Student ID</label>
      </div>

      <div class="input-field">
        <input id="sectionInput" name="section" type="text">
        <label for="sectionInput">Section</label>
      </div>

      <div class="right-align">
        <button class="btn blue" type="submit">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentModal" class="modal">
  <div class="modal-content">
    <h5>Edit Student</h5>
    <form method="post" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" id="edit_db_id" name="db_id" value="">

      <div class="file-field input-field">
        <div class="btn blue"><span>Change Photo</span><input type="file" name="edit_student_photo" accept="image/*"></div>
        <div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Change photo (optional)"></div>
      </div>

      <div class="input-field">
        <input id="editStudentName" name="edit_student_name" type="text" required>
        <label for="editStudentName">Name</label>
      </div>

      <div class="input-field">
        <input id="editStudentID" name="edit_student_id" type="text" required>
        <label for="editStudentID">Student ID</label>
      </div>

      <div class="input-field">
        <input id="editSectionInput" name="edit_section" type="text">
        <label for="editSectionInput">Section</label>
      </div>

      <div class="right-align">
        <button class="btn blue" type="submit" id="saveEditBtn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Materialize + app JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  M.AutoInit();

  var modals = document.querySelectorAll('.modal');
  M.Modal.init(modals, {dismissible: true});

  var tips = document.querySelectorAll('.tooltipped');
  M.Tooltip.init(tips);

  // wire Add button to open modal
  document.getElementById('addBtn').addEventListener('click', function(){ 
    var modal = document.getElementById('addStudentModal');
    M.Modal.getInstance(modal).open();
  });

  // fill edit modal when edit button clicked
  document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var dbId = this.getAttribute('data-db-id');
      var name = this.getAttribute('data-name');
      var studentId = this.getAttribute('data-studentid');
      var section = this.getAttribute('data-section');

      document.getElementById('edit_db_id').value = dbId;
      document.getElementById('editStudentName').value = name;
      document.getElementById('editStudentID').value = studentId;
      document.getElementById('editSectionInput').value = section;
      M.updateTextFields();
      setTimeout(function(){ var input = document.getElementById('editStudentName'); if (input) input.focus(); }, 200);
    });
  });

  // intercept edit form submit and ask two confirmations before submitting
  document.getElementById('editForm').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to save changes to this student?')) {
      e.preventDefault();
      return;
    }
    if (!confirm('Please confirm again: proceed with editing this student?')) {
      e.preventDefault();
      return;
    }
  });

  // delete selected -> build and submit POST form
  document.getElementById('deleteSelectedBtn').addEventListener('click', function(e) {
    e.preventDefault();
    var checked = document.querySelectorAll('.chk:checked');
    if (!checked.length) {
      M.toast({html: 'Select at least one student to delete.'});
      return;
    }
    if (!confirm('Delete selected students? This cannot be undone.')) return;

    var form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';
    var actionInput = document.createElement('input');
    actionInput.name = 'action'; actionInput.value = 'delete_selected';
    form.appendChild(actionInput);

    checked.forEach(function(ch) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'delete_ids[]';
      inp.value = ch.getAttribute('data-id');
      form.appendChild(inp);
    });

    document.body.appendChild(form);
    form.submit();
  });

  // section header collapse/expand
  var headers = document.querySelectorAll('.section-header');
  var COLL_KEY = 'students_sections_collapsed_v1';
  var collapsedState = {};
  try { collapsedState = JSON.parse(localStorage.getItem(COLL_KEY)) || {}; } catch(e) { collapsedState = {}; }

  // DEFAULT behavior: collapse all sections by default on first load if there's no saved state
  var DEFAULT_COLLAPSED = true;
  var hasSavedState = Object.keys(collapsedState).length > 0;
  if (!hasSavedState && DEFAULT_COLLAPSED) {
    headers.forEach(function(h){ var sec = h.getAttribute('data-section'); collapsedState[sec] = true; });
  }

  headers.forEach(function(h){
    var sec = h.getAttribute('data-section');
    var body = document.getElementById(sec + '_body');
    var wrap = document.getElementById(sec + '_wrap');
    var icon = h.querySelector('.collapse-icon');

    if (collapsedState[sec]) {
      body.classList.add('collapsed');
      wrap.classList.add('collapsed');
      if (icon) icon.style.transform = 'rotate(-90deg)';
    }

    h.addEventListener('click', function(){
      var isCollapsed = body.classList.toggle('collapsed');
      wrap.classList.toggle('collapsed', isCollapsed);
      if (icon) icon.style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
      try { collapsedState[sec] = isCollapsed; localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); } catch(e){}
      updateToggleBtnLabel(); // reflect new aggregate state
    });

    // 'check all in section' toggle
    var sectionCheck = wrap.querySelector('.check-section-all');
    if (sectionCheck) {
      sectionCheck.addEventListener('change', function(){
        var checked = this.checked;
        wrap.querySelectorAll('.chk').forEach(function(cb){ cb.checked = checked; });
      });
    }
  });

  // helper: are all sections collapsed?
  function areAllCollapsed() {
    var all = true, any = false;
    headers.forEach(function(h){
      var sec = h.getAttribute('data-section');
      any = true;
      if (!collapsedState[sec]) { all = false; }
    });
    // if no sections, treat as expanded (so button shows "Collapse All")
    if (!any) return false;
    return all;
  }

  var toggleBtn = document.getElementById('toggleCollapseBtn');

  function updateToggleBtnLabel(pulse) {
    var allCollapsed = areAllCollapsed();
    toggleBtn.textContent = allCollapsed ? 'Expand All' : 'Collapse All';
    // animated pulse for feedback
    if (pulse) {
      toggleBtn.classList.remove('btn-pulse');
      // force reflow then add
      void toggleBtn.offsetWidth;
      toggleBtn.classList.add('btn-pulse');
    }
  }

  function setAllSectionsCollapsed(collapsed) {
    headers.forEach(function(h){
      var sec = h.getAttribute('data-section');
      var body = document.getElementById(sec + '_body');
      var wrap = document.getElementById(sec + '_wrap');
      var icon = h.querySelector('.collapse-icon');

      if (collapsed) {
        body.classList.add('collapsed');
        wrap.classList.add('collapsed');
        if (icon) icon.style.transform = 'rotate(-90deg)';
      } else {
        body.classList.remove('collapsed');
        wrap.classList.remove('collapsed');
        if (icon) icon.style.transform = 'rotate(0deg)';
      }
      collapsedState[sec] = collapsed;
    });
    try { localStorage.setItem(COLL_KEY, JSON.stringify(collapsedState)); } catch(e){}
    updateToggleBtnLabel(true);
  }

  // initialize toggle button state & attach handler
  updateToggleBtnLabel(false);

  toggleBtn.addEventListener('click', function(){
    var toCollapse = !areAllCollapsed(); // if not all collapsed -> collapse, else expand
    // We want button to toggle: if some expanded, pressing should collapse all (so toCollapse=true)
    setAllSectionsCollapsed(toCollapse);
    // small visual feedback on click: pulse
    toggleBtn.classList.remove('btn-pulse');
    void toggleBtn.offsetWidth;
    toggleBtn.classList.add('btn-pulse');
  });

  // global check/uncheck all optionally
  var globalCheck = document.getElementById('checkAll');
  if (globalCheck) {
    globalCheck.addEventListener('change', function() {
      var checked = this.checked;
      document.querySelectorAll('.chk').forEach(function(cb) { cb.checked = checked; });
    });
  }

});
</script>

</body>
</html>
