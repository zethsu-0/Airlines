<?php
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

/**
 * Render one table row for a student (uses <img> for avatar)
 */
function render_student_row_html($st) {
    $id = (int)$st['id'];
    $student_id = htmlspecialchars($st['student_id'] ?? '', ENT_QUOTES);
    $last = htmlspecialchars($st['last_name'] ?? '', ENT_QUOTES);
    $first = htmlspecialchars($st['first_name'] ?? '', ENT_QUOTES);
    $middle = htmlspecialchars($st['middle_name'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($st['suffix'] ?? '', ENT_QUOTES);
    $section = htmlspecialchars($st['section'] ?? '', ENT_QUOTES);

    // Normalize avatar path for output
    $avatar = trim((string)($st['avatar'] ?? ''));
    if ($avatar === '' || strtolower($avatar) === 'null') {
        $avatar = 'assets/avatar.png';
    } else {
        if (strpos($avatar, '/') === 0) $avatar = ltrim($avatar, '/');
    }
    // If file missing on disk, fallback to placeholder
    if (!is_file(__DIR__ . '/' . $avatar)) {
        $avatar = 'assets/avatar.png';
    }
    $avatar_html = htmlspecialchars($avatar, ENT_QUOTES);

    ob_start();
    ?>
<tr data-db-id="<?php echo $id; ?>">
  <td>
    <label><input type="checkbox" class="filled-in chk" data-id="<?php echo $id; ?>"><span></span></label>
  </td>

  <td>
    <img src="<?php echo $avatar_html; ?>" alt="avatar" class="table-avatar-img"
         onerror="this.onerror=null;this.src='assets/avatar.png';" />
  </td>

  <td class="cell-last"><?php echo $last; ?></td>
  <td class="cell-first"><?php echo $first; ?></td>
  <td class="cell-middle"><?php echo $middle; ?></td>
  <td class="cell-suffix"><?php echo $suffix; ?></td>
  <td class="cell-studentid"><?php echo $student_id; ?></td>
  <td class="cell-section"><?php echo $section; ?></td>
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
       data-position="left"
       data-tooltip="Edit">
      <i class="material-icons">edit</i>
    </a>
  </td>
</tr>
    <?php
    return ob_get_clean();
}

// ---------- PROCESS POSTS (must run BEFORE sending any output) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD student
    if (!empty($_POST['action']) && $_POST['action'] === 'add_student') {
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $student_id_val = trim($_POST['student_id_val'] ?? '');
        $section = trim($_POST['section'] ?? '');

        $avatar = handle_avatar_upload('student_photo', null);

        $stmt = $conn->prepare("INSERT INTO students (student_id, last_name, first_name, middle_name, suffix, section, avatar, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sssssss', $student_id_val, $last, $first, $middle, $suffix, $section, $avatar);
        $stmt->execute();
        $stmt->close();

        header('Location: students.php');
        exit;
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

        // fetch current avatar
        $cur_avatar = null;
        $q = $conn->prepare("SELECT avatar FROM students WHERE id = ?");
        $q->bind_param('i', $db_id);
        $q->execute();
        $q->bind_result($cur_avatar);
        $q->fetch();
        $q->close();

        $new_avatar = handle_avatar_upload('edit_student_photo', $cur_avatar);

        $upd = $conn->prepare("UPDATE students SET student_id = ?, last_name = ?, first_name = ?, middle_name = ?, suffix = ?, section = ?, avatar = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param('sssssssi', $student_id_val, $last, $first, $middle, $suffix, $section, $new_avatar, $db_id);
        $upd->execute();
        $upd->close();

        header('Location: students.php');
        exit;
    }

    // DELETE selected (bulk)
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
$sql = "SELECT id, student_id, last_name, first_name, middle_name, suffix, section, avatar
        FROM students
        ORDER BY COALESCE(NULLIF(section,''),'~'), last_name, first_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($sid_pk, $sid_val, $nlast, $nfirst, $nmiddle, $nsuffix, $ssection, $savatar);
while ($stmt->fetch()) {
    $students[] = [
        'id' => $sid_pk,
        'student_id' => $sid_val,
        'last_name' => $nlast ?? '',
        'first_name' => $nfirst ?? '',
        'middle_name' => $nmiddle ?? '',
        'suffix' => $nsuffix ?? '',
        'section' => $ssection ?? '',
        'avatar' => $savatar ?? ''
    ];
}
$stmt->close();

// ---------- NORMALISE avatars (server-side) ----------
foreach ($students as &$s) {
    $a = trim((string)($s['avatar'] ?? ''));
    if ($a === '' || strtolower($a) === 'null') {
        $a = 'assets/avatar.png';
    } else {
        // remove leading slash to make relative paths consistent
        if (strpos($a, '/') === 0) $a = ltrim($a, '/');
    }
    // if file doesn't exist on disk, fallback
    if (!is_file(__DIR__ . '/' . $a)) {
        $a = 'assets/avatar.png';
    }
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Students</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <!-- Materialize CSS -->
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
    nav{background-image:url(assets/Banner.png);background-size:cover;background-position:center;height:80px}
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
<nav>
  <div class="nav-wrapper">
    <a href="admin.php" class="brand-logo center" style="line-height:80px;color:white;font-weight:800;">
      <i class="material-icons hide-on-med-and-down" style="vertical-align:middle">flight_takeoff</i>&nbsp;TOURS
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

      <div class="confirm-row"><label><input type="checkbox" id="editConfirmCheckbox"><span>I confirm I want to save these changes</span></label></div>

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

<!-- Materialize JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  M.AutoInit();
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
    M.updateTextFields();
    document.getElementById('editConfirmCheckbox').checked = false;
    document.getElementById('saveEditBtn').disabled = true;
    M.Modal.getInstance(document.getElementById('editStudentModal')).open();
    setTimeout(function(){ try{ document.getElementById('editFirstName').focus(); }catch(e){} }, 200);
  }
  document.querySelectorAll('.edit-btn').forEach(function(b){ b.addEventListener('click', function(){ openEditModal(this); }); });

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
      // get src from <img>
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

  // collapse/expand per section + toggle
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
