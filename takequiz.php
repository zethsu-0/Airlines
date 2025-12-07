<?php
// takequiz.php
// Student-facing list of available quizzes and submission status.
// This version assumes templates/header.php and templates/footer.php already output the page <head> and top nav/footer.
// So we only output content + scoped CSS/JS to avoid duplication.

include('templates/header.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Ensure CSRF token exists (needed by generate_ticket.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// --- GLOBAL escape helper (available everywhere in this file) ---
if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Helper to check login quickly
function is_logged_in() {
    if (!empty($_SESSION['acc_id'])) return true;
    if (!empty($_SESSION['student_id'])) return true;
    if (!empty($_SESSION['user_id'])) return true;
    if (!empty($_SESSION['user']['id'])) return true;
    return false;
}

// If not logged in — show login prompt and stop further processing.
if (!is_logged_in()) {
    ?>
    <style>
      :root{
        --bg-900:#071428; --bg-800:#071b2a; --panel: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
        --muted:#98a0b3; --accent-1:#1976d2; --accent-2:#0b84ff; --text:#e6eef8; --card-border:rgba(255,255,255,0.03);
      }
      body { background: linear-gradient(180deg,var(--bg-900),var(--bg-800)); color:var(--text); font-family: "Roboto", sans-serif; }
      .sa-container{ max-width:1200px; margin:32px auto; padding:0 18px 64px; }
      .sa-card{ background:var(--panel); border-radius:14px; padding:18px; border:1px solid var(--card-border); box-shadow:0 10px 30px rgba(2,6,23,0.7); color:var(--text); }
      .sa-top{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; padding-left:8px; }
      .sa-top .badge{ width:46px;height:46px;border-radius:10px;background:linear-gradient(135deg,var(--accent-1),var(--accent-2));display:flex;align-items:center;justify-content:center;color:white;font-weight:800;}
      .sa-grid{ display:grid; grid-template-columns:1fr 360px; gap:18px; align-items:start; }
      @media (max-width:1024px){ .sa-grid{ grid-template-columns:1fr; } }
      .center{ text-align:center; }
      .small-note{ color:var(--muted); font-size:13px; }
      .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:8px 12px; border-radius:10px; font-weight:700; cursor:pointer; text-transform:none; border:none; }
      .btn--primary{ background: linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff; box-shadow:0 10px 28px rgba(11,132,255,0.10); }
      .btn--ghost{ background:transparent; color:var(--text); border:1px solid rgba(255,255,255,0.06); }
      .modal { z-index: 9999 !important; }
      .modal-overlay { z-index: 9998 !important; background: rgba(0,0,0,0.6) !important; }
      #loginModal .modal-content { background: linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff; border-radius:8px; }
      #loginModal .input-field label, #loginModal .input-field .prefix, #loginModal h5 { color:#fff !important; }
    </style>

    <div class="sa-container">
      <div class="sa-top">
        <div style="display:flex;gap:12px;align-items:center;">
          <div class="badge">SA</div>
          <div>
            <div style="font-weight:700;font-size:18px;color:var(--text)">Available Quizzes</div>
            <div class="small-note">Welcome, Guest</div>
          </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;">
          <div class="small-note" style="color:#cbd5e1">Not logged in</div>
          <a class="btn btn--ghost modal-trigger" href="index.php#loginModal">Log In</a>
        </div>
      </div>

      <div class="sa-grid">
        <div>
          <div class="sa-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-weight:700; font-size:16px;color:var(--text)">Quizzes</div>
                <div class="small-note">Click <strong>Open Login</strong> to sign in and view available quizzes.</div>
              </div>
              <div class="small-note" style="color:#cbd5e1">Total: —</div>
            </div>

            <div style="padding:48px 12px;" class="center">
              <h5 style="margin-bottom:8px;color:var(--text)">Please log in to view quizzes</h5>
              <p class="small-note" style="max-width:640px;margin:0 auto 18px;">
                If you already have an account, click <strong>Log In</strong> in the header (or use the form that opened). If you don't yet have an account, please register / contact your instructor.
              </p>

              <div style="display:flex;gap:12px;justify-content:center;margin-top:14px;">
                <a class="btn btn--ghost" href="index.php">Home</a>
                <button class="btn btn--primary modal-trigger" id="openLoginBtn" type="button">Open Login</button>
              </div>
            </div>
          </div>
        </div>

        <aside>
          <div class="sa-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-weight:700;color:var(--text)">Overview</div>
                <div class="small-note">Quick stats for your quizzes</div>
              </div>
              <div style="font-size:42px;color:var(--accent-2);font-weight:800">—</div>
            </div>

            <div style="margin-top:12px" class="sa-stats">
              <div style="display:flex;gap:8px">
                <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;margin-right:8px;text-align:center">
                  <div style="font-weight:800;font-size:18px">—</div>
                  <div class="small-note">Total</div>
                </div>
                <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;margin-right:8px;text-align:center">
                  <div style="font-weight:800;color:#7ade7a;font-size:18px">—</div>
                  <div class="small-note">Submitted</div>
                </div>
                <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;text-align:center">
                  <div style="font-weight:800;color:#ff8a80;font-size:18px">—</div>
                  <div class="small-note">Not Submitted</div>
                </div>
              </div>
            </div>

            <div style="margin-top:12px; display:flex;flex-direction:column;gap:8px;">
              <a class="btn btn--ghost" href="students_edit.php"><i class="material-icons" style="vertical-align:middle;margin-right:8px">person</i> My Student Profile</a>
              <a class="btn btn--ghost" href="logout.php"><i class="material-icons" style="vertical-align:middle;margin-right:8px">logout</i> Logout</a>
            </div>
          </div>
        </aside>
      </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function(){
      try {
        if (typeof M !== 'undefined' && M.Modal) {
          var elems = document.querySelectorAll('.modal');
          M.Modal.init(elems, { dismissible: false, opacity: 0.65, inDuration: 180, outDuration: 120 });

          var loginEl = document.getElementById('loginModal');
          if (loginEl) {
            var inst = M.Modal.getInstance(loginEl) || M.Modal.init(loginEl, { dismissible:false, opacity:0.65 });
            try { inst.open(); } catch(e) {}
          }

          var openBtn = document.getElementById('openLoginBtn');
          if (openBtn) openBtn.addEventListener('click', function(){ try { inst.open(); } catch(e){} });

          var startBtn = document.getElementById('startQuizBtn');
          if (startBtn) startBtn.addEventListener('click', function(){ try { inst.open(); } catch(e){} });
        } else {
          try {
            $('.modal').modal({ dismissible:false, opacity:0.65 });
            $('#loginModal').modal('open');
          } catch(e) { console.warn('Modal fallback failed', e); }
        }
      } catch (err) {
        console.error('Error initializing login modal', err);
      }
    });
    </script>

    <?php
    include('templates/footer.php');
    exit;
}

// ---------------------------------------------
// User is logged in: detect which session key holds the account ID (VARCHAR)
// ---------------------------------------------

$currentAccId   = null;
$currentAccKey  = null;

$sessionCandidates = [
    'acc_id'     => $_SESSION['acc_id']         ?? null,
    'student_id' => $_SESSION['student_id']     ?? null,
    'user_id'    => $_SESSION['user_id']        ?? null,
    'user.id'    => $_SESSION['user']['id']     ?? null,
];

foreach ($sessionCandidates as $key => $val) {
    if ($val !== null && $val !== '') {
        $currentAccId  = (string)$val;
        $currentAccKey = $key;
        break;
    }
}

echo "<!-- DEBUG session IDs: ";
foreach ($sessionCandidates as $k => $v) {
    echo $k . '=' . htmlspecialchars((string)$v) . ' ';
}
echo "| chosen_key=" . htmlspecialchars((string)$currentAccKey) . " chosen_id=" . htmlspecialchars((string)$currentAccId) . " -->\n";

// DB connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('<div style="padding:20px; color:darkred">DB connection failed: ' . htmlspecialchars($conn->connect_error) . '</div>');
}
$conn->set_charset('utf8mb4');

// ---------------------------------------------
// Build main query (robust student section lookup + strict section-only filtering)
// ---------------------------------------------
$quizzes = [];
$student_section = null;

if ($currentAccId !== null && $currentAccId !== '') {
    // --- (1) Discover students table columns and try to locate the student's section ---
    $colsRes = $conn->query("SHOW COLUMNS FROM students");
    $studentsCols = [];
    if ($colsRes) {
        while ($c = $colsRes->fetch_assoc()) {
            $studentsCols[] = $c['Field'];
        }
        $colsRes->free();
    }

    // Candidate id columns we might match against session id
    $idCandidates = ['acc_id','student_id','id','user_id','account_id'];
    $foundIdCols = array_values(array_intersect($idCandidates, $studentsCols));

    if (!empty($foundIdCols)) {
        // Build prepared statement like: WHERE (col1 = ? OR col2 = ?) LIMIT 1
        $whereParts = [];
        foreach ($foundIdCols as $col) $whereParts[] = "{$col} = ?";
        $whereSql = '(' . implode(' OR ', $whereParts) . ') LIMIT 1';

        $secSql = "SELECT section FROM students WHERE $whereSql";
        $secStmt = $conn->prepare($secSql);
        if ($secStmt) {
            $types = str_repeat('s', count($foundIdCols));
            $params = array_fill(0, count($foundIdCols), $currentAccId);
            $bindNames = array_merge([$types], $params);
            $tmp = [];
            foreach ($bindNames as $k => $v) $tmp[$k] = &$bindNames[$k];
            call_user_func_array([$secStmt, 'bind_param'], $tmp);
            $secStmt->execute();
            $secRes = $secStmt->get_result();
            if ($r = $secRes->fetch_assoc()) {
                $student_section = isset($r['section']) ? trim((string)$r['section']) : null;
            }
            $secStmt->close();
        } else {
            error_log("Failed to prepare students section lookup: " . $conn->error);
            echo "<!-- DEBUG: failed to prepare students section lookup: " . htmlspecialchars($conn->error) . " -->\n";
        }
    } else {
        // fallback: try matching by name-like column if we have acc_name in session
        $nameCandidates = ['name','fullname','student_name','acc_name','full_name'];
        $foundNameCols = array_values(array_intersect($nameCandidates, $studentsCols));
        if (!empty($foundNameCols) && !empty($_SESSION['acc_name'])) {
            $nameCol = $foundNameCols[0];
            $secStmt = $conn->prepare("SELECT section FROM students WHERE {$nameCol} = ? LIMIT 1");
            if ($secStmt) {
                $secStmt->bind_param('s', $_SESSION['acc_name']);
                $secStmt->execute();
                $secRes = $secStmt->get_result();
                if ($r = $secRes->fetch_assoc()) {
                    $student_section = isset($r['section']) ? trim((string)$r['section']) : null;
                }
                $secStmt->close();
            }
        } else {
            echo "<!-- DEBUG: students table columns: " . htmlspecialchars(json_encode($studentsCols)) . " -->\n";
        }
    }

    // --- (2) Collect distinct quiz.section values for debugging so you can inspect data format ---
    $sectionsForDebug = [];
    $secQ = $conn->query("SELECT DISTINCT TRIM(section) AS sec FROM quizzes");
    if ($secQ) {
        while ($s = $secQ->fetch_assoc()) {
            $sectionsForDebug[] = $s['sec'];
        }
        $secQ->free();
    }

    echo "<!-- DEBUG currentAccId=" . htmlspecialchars($currentAccId) . " student_section=" . htmlspecialchars((string)$student_section) . " quiz_sections=" . htmlspecialchars(json_encode($sectionsForDebug)) . " -->\n";

    // --- (3) Strict: only quizzes explicitly assigned to the student's section (no global quizzes) ---
    if (!empty($student_section)) {
        // Normalize student section values we will pass to SQL:
        $lowerSection = mb_strtolower(trim($student_section), 'UTF-8'); // used for case-insensitive equality
        // normalized for FIND_IN_SET: remove spaces and convert semicolons to commas
        $forFindInSet = str_replace(' ', '', str_replace(';', ',', trim($student_section)));

        $sql = "
          SELECT
            q.id AS quiz_id,
            q.public_id,
            q.title,
            q.quiz_code AS code,
            '' AS deadline,
            COALESCE(q.duration, 0) AS duration,

            COUNT(sf.id) AS submission_count,
            MIN(sf.submitted_at) AS submitted_at,

            COUNT(DISTINCT qi.id) AS num_items,
            q.section AS raw_section
          FROM quizzes q
          LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
          LEFT JOIN submitted_flights sf ON sf.quiz_id = q.id AND sf.acc_id = ?
          WHERE (
            LOWER(TRIM(q.section)) = ?
            OR FIND_IN_SET(?, REPLACE(REPLACE(q.section, ' ', ''), ';', ','))
          )
          GROUP BY q.id
          ORDER BY q.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die('<div style="padding:20px; color:darkred">Query prepare failed: ' . htmlspecialchars($conn->error) . '</div>');
        }

        // Bind parameters: acc_id (for left-joined submitted_flights), lowerSection (equality), forFindInSet (FIND_IN_SET)
        $stmt->bind_param('sss', $currentAccId, $lowerSection, $forFindInSet);
        $stmt->execute();
        $res = $stmt->get_result();
        $quizzes = []; // reset to only strictly-matching quizzes
        while ($row = $res->fetch_assoc()) {
            $row['raw_section'] = isset($row['raw_section']) ? trim($row['raw_section']) : '';
            $quizzes[] = $row;
        }
        $stmt->close();

        // Debug matched quizzes
        $dbgList = [];
        foreach ($quizzes as $qq) {
            $dbgList[] = ['id' => $qq['quiz_id'], 'title' => $qq['title'], 'section' => $qq['raw_section']];
        }
        echo "<!-- DEBUG strict_matched_quizzes=" . htmlspecialchars(json_encode($dbgList)) . " -->\n";

        // Also keep existing submitted_flights debug (unchanged)
        $dbg = $conn->prepare("SELECT DISTINCT quiz_id FROM submitted_flights WHERE acc_id = ?");
        if ($dbg) {
            $dbg->bind_param('s', $currentAccId);
            $dbg->execute();
            $dbgRes = $dbg->get_result();
            $ids = [];
            while ($r = $dbgRes->fetch_assoc()) $ids[] = $r['quiz_id'];
            $dbg->close();
            echo "<!-- DEBUG submitted_quiz_ids_for_acc_id=" . htmlspecialchars($currentAccId) . ": " . htmlspecialchars(implode(',', $ids)) . " -->\n";
        }

    } else {
        // Student section not found — return no quizzes (strict behavior)
        $quizzes = [];
        echo "<!-- DEBUG: No student_section found for acc_id=" . htmlspecialchars($currentAccId) . " — returning NO quizzes (strict mode) -->\n";
    }

} else {
    // Not logged in: original behavior (show all quizzes)
    $sql = "
      SELECT
        q.id AS quiz_id,
        q.public_id,
        q.title,
        q.quiz_code AS code,
        '' AS deadline,
        COALESCE(q.duration, 0) AS duration,
        0 AS submission_count,
        NULL AS submitted_at,
        COUNT(DISTINCT qi.id) AS num_items,
        q.section AS raw_section
      FROM quizzes q
      LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
      GROUP BY q.id
      ORDER BY q.created_at DESC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['raw_section'] = isset($row['raw_section']) ? trim($row['raw_section']) : '';
            $quizzes[] = $row;
        }
        $res->free();
    }
}




// ---------------------------------------------
// Load submitted_flights per quiz for this student
// ---------------------------------------------
$submissionsByQuiz = [];

if ($currentAccId !== null && $currentAccId !== '') {
    $subStmt = $conn->prepare("SELECT * FROM submitted_flights WHERE acc_id = ?");
    if ($subStmt) {
        $subStmt->bind_param('s', $currentAccId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($row = $subRes->fetch_assoc()) {
            $qid = (string)$row['quiz_id'];
            if (!isset($submissionsByQuiz[$qid])) {
                $submissionsByQuiz[$qid] = [];
            }
            $submissionsByQuiz[$qid][] = $row;
        }
        $subStmt->close();
    }
}

// Stats
$total = count($quizzes);
$submittedCount = 0;
foreach ($quizzes as $q) {
    if (!empty($q['submission_count']) && (int)$q['submission_count'] > 0) {
        $submittedCount++;
    }
}
$notSubmitted = $total - $submittedCount;
?>

<style>
:root{
  --sa-bg: #071428;
  --sa-bg2: #071826;
  --sa-text: #e6eef8;
  --sa-muted: #98a0b3;
  --sa-accent: #1976d2;
  --sa-accent-2: #0b84ff;
}

body {
  background: linear-gradient(180deg, var(--sa-bg) 0%, var(--sa-bg2) 100%);
  color: var(--sa-text);
  font-family: "Roboto", "Helvetica", Arial, sans-serif;
  min-height: 100vh;
}

.sa-container {
  max-width: 1200px;
  margin: 32px auto;
  padding: 0 18px;
}

.sa-card {
  background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255,255,255,0.05);
  box-shadow: 0 8px 30px rgba(2,6,23,0.6);
  color: var(--sa-text);
}

.sa-top {
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-bottom:12px;
}

.sa-grid { display:grid; grid-template-columns: 1fr 320px; gap:18px; align-items:start; }
@media (max-width:980px) { .sa-grid { grid-template-columns: 1fr; } }

.quiz-list { display:flex; flex-direction:column; gap:12px; margin-top:12px; }
.quiz-row {
  display:flex;
  gap:14px;
  align-items:center;
  padding:12px;
  border-radius:10px;
  background: linear-gradient(180deg, rgba(255,255,255,0.01), transparent);
  border:1px solid rgba(255,255,255,0.02);
}
.quiz-code {
  width:86px;
  text-align:center;
  font-weight:800;
  font-size:16px;
  padding:8px;
  border-radius:8px;
  background: rgba(255,255,255,0.02);
  color:#fff;
  box-shadow: inset 0 -4px 12px rgba(0,0,0,0.25);
}
.quiz-title { font-weight:700; font-size:15px; color:#fff; }
.quiz-meta { color:#98a0b3; font-size:13px; margin-top:6px; }

.quiz-actions {
  min-width:200px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:flex-end;
  gap:8px;
}

.btn {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:8px 12px;
  border-radius:10px;
  font-weight:700;
  text-transform:none;
  cursor:pointer;
}

.btn--primary {
  background: linear-gradient(180deg,#1976d2,#0b84ff);
  color:white;
  border:none;
}

.btn--ghost {
  background:transparent;
  color:#e6eef8;
  border:1px solid rgba(255,255,255,0.06);
}

.view-submissions-btn {
  background:transparent;
  color:#0b84ff;
  border:1px dashed rgba(11,132,255,0.12);
  padding:6px 10px;
  border-radius:8px;
  cursor:pointer;
  font-weight:700;
}

.small-note { color:#98a0b3; font-size:13px; }

.modal .modal-content { color: #e6eef8; background: transparent; }

.btn--ghost,
.btn-flat,
.btn-transparent {
  background: transparent !important;
  color: #e6eef8 !important;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  box-shadow: none !important;
}

.btn--ghost:hover,
.btn-flat:hover,
.btn-transparent:hover {
  background: linear-gradient(180deg,#1976d2,#0b84ff) !important;
  color: #fff !important;
  border-color: transparent !important;
}

.btn--ghost:focus,
.btn--ghost:active,
.btn-flat:focus,
.btn-flat:active,
.btn-transparent:focus,
.btn-transparent:active {
  background: linear-gradient(180deg,#1976d2,#0b84ff) !important;
  color: #fff !important;
}

.waves-effect .waves-ripple {
  background: rgba(11, 132, 255, 0.35) !important;
}
.modal.modal-fixed-footer .modal-content{
  height: 100% !important;
}
.tall-modal {
  max-height: 100% !important;
  height: 85% !important;
  top: 2% !important;
}

.tall-modal .modal-content {
  overflow: hidden;
}
.modalTicket{
  width: 80% !important;
  overflow: hidden !important;
  box-shadow: none !important;
}
.modal-content{
  overflow: hidden !important;
}
.modalTicket, .modal-footer{
  background-color: transparent !important;
}
.modal .modal-footer .btn-flat{
  margin-right: 20px;
}
</style>

<div class="sa-container">
  <div class="sa-top">
    <div style="display:flex;gap:12px;align-items:center;">
      <div style="width:46px;height:46px;border-radius:8px;background:linear-gradient(135deg,#1976d2,#0b84ff);display:flex;align-items:center;justify-content:center;font-weight:700;color:white;">SA</div>
      <div>
        <div style="font-weight:700;font-size:18px;color:#fff">Available Quizzes</div>
        <div class="small-note">Welcome, <?php echo h(!empty($_SESSION['acc_name']) ? $_SESSION['acc_name'] : 'Student'); ?></div>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
      <div class="small-note" style="color:#cbd5e1">Logged in as <strong style="color:#fff"><?php echo h(!empty($_SESSION['acc_name']) ? $_SESSION['acc_name'] : 'Student'); ?></strong></div>
      <a class="btn btn--ghost" href="students_edit.php"><i class="material-icons" style="font-size:18px;color:#fff;margin-right:6px;vertical-align:middle">person</i>Profile</a>
      <a class="btn btn--primary" href="logout.php"><i class="material-icons" style="font-size:18px;color:#fff;margin-right:6px;vertical-align:middle">logout</i>Logout</a>
    </div>
  </div>

  <div class="sa-grid">
    <div>
      <div class="sa-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-weight:700; font-size:16px;color:#fff">Quizzes</div>
            <div class="small-note">Click <strong>Take Quiz</strong> to start (if you haven't submitted).</div>
          </div>
          <div class="small-note" style="color:#cbd5e1">Total: <strong><?php echo h($total); ?></strong></div>
        </div>

        <div class="quiz-list">
          <?php if (empty($quizzes)): ?>
            <div class="center" style="padding:28px;color:#98a0b3">No quizzes available yet.</div>
          <?php else: ?>
            <?php foreach ($quizzes as $q):
                $submissionCount = isset($q['submission_count']) ? (int)$q['submission_count'] : 0;
                $isSubmitted     = $submissionCount > 0;
                $numQuestions    = isset($q['num_questions']) ? (int)$q['num_questions'] : 0;
                $duration        = isset($q['duration']) ? (int)$q['duration'] : 0;
                $numItems        = isset($q['num_items']) ? (int)$q['num_items'] : 0;
                $quizPublicId    = !empty($q['public_id']) ? $q['public_id'] : $q['quiz_id'];
            ?>
            <div class="quiz-row">
              <div class="quiz-code">
                <div><?php echo h(strtoupper($q['code'] ?: 'REF')); ?></div>
                <div class="small-note"><?php echo h($numItems); ?> item<?php echo ($numItems == 1) ? '' : 's'; ?></div>
              </div>

              <div style="flex:1">
                <div class="quiz-title"><?php echo h($q['title']); ?></div>
                <div class="quiz-meta">
                  <?php echo h($numQuestions); ?> question<?php echo $numQuestions == 1 ? '' : 's'; ?> &middot; <?php echo h($duration); ?> min
                </div>
              </div>

              <div class="quiz-actions">
                <?php if ($isSubmitted): ?>
                  <div style="color:#7ade7a; font-weight:700">Submitted</div>
                  <?php if (!empty($q['submitted_at'])): ?>
                    <div class="small-note">on <?php echo h(date('M j, Y H:i', strtotime($q['submitted_at']))); ?></div>
                  <?php else: ?>
                    <div class="small-note">&nbsp;</div>
                  <?php endif; ?>

                  <?php
                    $quizIdStr      = (string)$q['quiz_id'];
                    $flightsForQuiz = $submissionsByQuiz[$quizIdStr] ?? [];
                    $firstFlight    = $flightsForQuiz[0] ?? null;
                  ?>

                  <div style="margin-top:8px">
                    <?php if ($firstFlight): ?>
                      <button
                        type="button"
                        class="view-submissions-btn view-ticket-btn"
                        data-url="submitted_ticket.php?id=<?php echo (int)$firstFlight['id']; ?>"
                      >
                        View ticket
                      </button>
                    <?php else: ?>
                      <div class="small-note">No submission data available.</div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div style="color:#ff8a80; font-weight:700">Not submitted</div>
                  <div class="small-note">&nbsp;</div>
                  <div style="margin-top:8px">
                    <a class="btn btn--primary" href="ticket.php?id=<?php echo urlencode($quizPublicId); ?>">Take Quiz</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <aside>
      <div class="sa-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-weight:700;color:#fff">Overview</div>
            <div class="small-note">Quick stats for your quizzes</div>
          </div>
          <div style="font-size:42px;color:#0b84ff;font-weight:800"><?php echo h($total); ?></div>
        </div>

        <div style="margin-top:12px" class="sa-stats" >
          <div style="display:flex;gap:8px">
            <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;margin-right:8px;text-align:center">
              <div style="font-weight:800;font-size:18px"><?php echo h($total); ?></div>
              <div class="small-note">Total</div>
            </div>
            <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;margin-right:8px;text-align:center">
              <div style="font-weight:800;color:#7ade7a;font-size:18px"><?php echo h($submittedCount); ?></div>
              <div class="small-note">Submitted</div>
            </div>
            <div style="background:rgba(255,255,255,0.02);padding:12px;border-radius:8px;text-align:center">
              <div style="font-weight:800;color:#ff8a80;font-size:18px"><?php echo h($notSubmitted); ?></div>
              <div class="small-note">Not Submitted</div>
            </div>
          </div>
        </div>

        <div style="margin-top:12px; display:flex;flex-direction:column;gap:8px;">
          <a class="btn btn--ghost" href="students_edit.php"><i class="material-icons" style="vertical-align:middle;margin-right:8px">person</i> My Student Profile</a>
          <a class="btn btn--ghost" href="logout.php"><i class="material-icons" style="vertical-align:middle;margin-right:8px">logout</i> Logout</a>
        </div>
      </div>
    </aside>
  </div>
</div>
    <!-- Ticket modal -->
    <div id="ticketModal" class="modalTicket modal modal-fixed-footer tall-modal">
      <div class="modal-content" style="padding:0;">
        <!-- Simple responsive iframe wrapper -->
        <div style="position:relative;padding-top:90%;height: 80% !important; overflow:hidden;">
          <iframe
            id="ticketFrame"
            src=""
            frameborder="0"
            style="position:absolute;top:0;left:0;width:100%;height:80%;border:0;"
          ></iframe>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#!" class="modal-close btn-flat">Close</a>
      </div>
    </div>
<?php
$conn->close();
include('templates/footer.php');
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Init Materialize modals
  if (typeof M !== 'undefined' && M.Modal) {
    var elems = document.querySelectorAll('.modal');
    M.Modal.init(elems, { dismissible: true, opacity: 0.7 });
  } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
    // jQuery fallback if needed
    jQuery('.modal').modal({ dismissible: true, opacity: 0.7 });
  }

  var ticketModal = document.getElementById('ticketModal');
  var ticketFrame = document.getElementById('ticketFrame');

  // Attach click handlers to all "View ticket" buttons
  document.querySelectorAll('.view-ticket-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var url = this.getAttribute('data-url');
      if (!url) return;

      // Load the ticket page into iframe
      ticketFrame.src = url;

      // Open the modal
      if (typeof M !== 'undefined' && M.Modal) {
        var inst = M.Modal.getInstance(ticketModal) || M.Modal.init(ticketModal, { dismissible: true, opacity: 0.7 });
        inst.open();
      } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        jQuery('#ticketModal').modal('open');
      }
    });
  });
});
</script>
