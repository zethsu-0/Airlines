<?php
// admin.php — dashboard + profile modal

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// include header (shows nav + admin modal).
include('templates/header_admin.php');

// Quick signed-in check
$isLoggedIn = !empty($_SESSION['acc_id']);
if (!$isLoggedIn) {
    echo '<div class="page-wrap" style="padding:28px;max-width:900px;margin:40px auto;">';
    echo '<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.04);text-align:center;">';
    echo '<h2 style="margin:0 0 8px;font-size:20px;">You are not signed in</h2>';
    echo '<p style="color:#666;margin:0 0 16px;">Please log in to access quizzes and admin features.</p>';
    echo '</div></div>';
    include('templates/footer.php');
    exit;
}

$DB = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'airlines'
];

$conn = new mysqli($DB['host'], $DB['user'], $DB['pass'], $DB['name']);
if ($conn->connect_errno) {
    $dbError = 'DB connection error: ' . $conn->connect_error;
    $quizzes = []; $quizStats = []; $totalStudents = $totalCreated = $teacherStudents = 0;
} else {
    $dbError = '';
    $quizzes = [];
    $quizStats = [];
    $totalStudents = $totalCreated = $teacherStudents = 0;

    // Helpers
    $detect_columns = function(string $table) use ($conn) : array {
        $cols = [];
        $res = @$conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($table)}`");
        if ($res) {
            while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
            $res->free();
        }
        return $cols;
    };
    $safe_backtick = function(string $col) {
        return "`" . str_replace("`", "``", $col) . "`";
    };

    $isAdminSignedIn = !empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin';
    $myAcc = (string)($_SESSION['acc_id'] ?? '');

    // Load basic lists & counts used in all cases
    $r = $conn->query("SELECT id, title, COALESCE(quiz_code,'') AS quiz_code, '' AS deadline FROM quizzes ORDER BY id DESC");
    if ($r) { while ($row = $r->fetch_assoc()) $quizzes[] = $row; $r->free(); }
    $r = $conn->query("SELECT COUNT(*) AS c FROM students");
    if ($r) { $totalStudents = (int)$r->fetch_assoc()['c']; $r->free(); }
    $r = $conn->query("SELECT COUNT(*) AS c FROM quizzes");
    if ($r) { $totalCreated = (int)$r->fetch_assoc()['c']; $r->free(); }

    // If admin, attempt to filter quizzes to ones they created + build stats
    if ($isAdminSignedIn) {
        // Detect creator column in quizzes
        $quizCols = $detect_columns('quizzes');
        $creatorCandidates = ['created_by','creator','author','acc_id','account_id','admin_id','created_by_id','owner_id','user_id'];
        $creatorCol = null;
        foreach ($creatorCandidates as $c) if (in_array($c, $quizCols, true)) { $creatorCol = $c; break; }
        if (!$creatorCol) {
            foreach ($quizCols as $col) {
                if (preg_match('/\b(created|creator|author|owner|admin|user|acc)\b/i', $col)) { $creatorCol = $col; break; }
            }
        }

        if ($creatorCol) {
            $creatorQuoted = $safe_backtick($creatorCol);
            $sql = "SELECT id, title, COALESCE(quiz_code,'') AS quiz_code, '' AS deadline
                    FROM quizzes WHERE {$creatorQuoted} = ? ORDER BY id DESC";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $myAcc);
                $stmt->execute();
                $res = $stmt->get_result();
                $quizzes = [];
                while ($r = $res->fetch_assoc()) $quizzes[] = $r;
                $res->free();
                $stmt->close();
            } else {
                $dbError .= ($dbError ? ' ' : '') . 'Failed to prepare creator query; showing all quizzes.';
            }

            if ($stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM quizzes WHERE {$creatorQuoted} = ?")) {
                $stmt2->bind_param('s', $myAcc);
                $stmt2->execute();
                $r2 = $stmt2->get_result();
                if ($r2 && $row2 = $r2->fetch_assoc()) $totalCreated = (int)$row2['c'];
                if ($r2) $r2->free();
                $stmt2->close();
            }
        } else {
            $dbError .= ($dbError ? ' ' : '') . 'Creator column not detected in quizzes table; showing all quizzes.';
        }

        // Detect students table columns for teacher-student mapping
        $studentCols = $detect_columns('students');
        $studentIdCol = null;
        foreach (['student_id','acc_id','user_id','account_id','sid','id'] as $c) if (in_array($c,$studentCols,true)) { $studentIdCol = $c; break; }
        $teacherIdCol = null;
        foreach (['teacher_id','assigned_teacher','admin_id','owner_id'] as $c) if (in_array($c,$studentCols,true)) { $teacherIdCol = $c; break; }

        if ($teacherIdCol && $studentIdCol) {
            $q = "SELECT COUNT(*) AS c FROM students WHERE " . $safe_backtick($teacherIdCol) . " = ?";
            if ($stmt = $conn->prepare($q)) {
                $stmt->bind_param('s', $myAcc);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) $teacherStudents = (int)$row['c'];
                if ($res) $res->free();
                $stmt->close();
            } else {
                $esc = $conn->real_escape_string($myAcc);
                $r = $conn->query("SELECT COUNT(*) AS c FROM students WHERE `{$teacherIdCol}` = '{$esc}'");
                if ($r) { $teacherStudents = (int)$r->fetch_assoc()['c']; $r->free(); }
            }
        }

        // Build per-quiz submission stats using submitted_flights if available
        $sfCols = $detect_columns('submitted_flights');
        $quizCol = null;
        foreach (['quiz_id','quizid','quiz','exam_id','test_id'] as $c) if (in_array($c,$sfCols,true)) { $quizCol = $c; break; }
        $accCol = null;
        foreach (['acc_id','student_id','user_id','account_id','submitted_by','submitted_acc'] as $c) if (in_array($c,$sfCols,true)) { $accCol = $c; break; }
        if (!$accCol) foreach (['sid','id'] as $c) if (in_array($c,$sfCols,true)) { $accCol = $c; break; }

        foreach ($quizzes as $q) {
            $qid = (int)$q['id'];
            $submitted = 0;

            if ($quizCol && $accCol && $studentIdCol && $teacherIdCol) {
                $qq = "SELECT COUNT(DISTINCT sf." . $safe_backtick($accCol) . ") AS c
                       FROM submitted_flights AS sf
                       JOIN students AS s ON sf." . $safe_backtick($accCol) . " = s." . $safe_backtick($studentIdCol) . "
                       WHERE sf." . $safe_backtick($quizCol) . " = ? AND s." . $safe_backtick($teacherIdCol) . " = ?";
                if ($stmtS = $conn->prepare($qq)) {
                    $stmtS->bind_param('is', $qid, $myAcc);
                    $stmtS->execute();
                    $rS = $stmtS->get_result();
                    if ($rS && $rowS = $rS->fetch_assoc()) $submitted = (int)$rowS['c'];
                    if ($rS) $rS->free();
                    $stmtS->close();
                } else {
                    $escqid = $conn->real_escape_string($qid);
                    $rsc = $conn->query("SELECT COUNT(DISTINCT `" . $conn->real_escape_string($accCol ?: 'acc_id') . "`) AS c FROM submitted_flights WHERE `" . $conn->real_escape_string($quizCol ?: 'quiz_id') . "` = {$escqid}");
                    if ($rsc) { $submitted = (int)$rsc->fetch_assoc()['c']; $rsc->free(); }
                }
            } else if ($quizCol && $accCol) {
                $escqid = $conn->real_escape_string($qid);
                $rsc = $conn->query("SELECT COUNT(DISTINCT `" . $conn->real_escape_string($accCol) . "`) AS c FROM submitted_flights WHERE `" . $conn->real_escape_string($quizCol) . "` = {$escqid}");
                if ($rsc) { $submitted = (int)$rsc->fetch_assoc()['c']; $rsc->free(); }
            }

            $notSubmitted = max(0, $teacherStudents - $submitted);
            $quizStats[] = [
                'id' => $qid,
                'title' => $q['title'],
                'code' => $q['quiz_code'] ?? '',
                'submitted' => $submitted,
                'not_submitted' => $notSubmitted
            ];
        }
    }
}

// ------------------ helper functions ------------------
$detect_columns = function(string $table) use (&$conn) : array {
    $cols = [];
    if (!$conn) return $cols;
    $res = @ $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        $res->free();
    }
    return $cols;
};

$normalize_avatar_value = function(string $val) {
    $val = trim($val);
    if ($val === '') return '';
    if (preg_match('#^https?://#i', $val)) return $val;
    if (strpos($val, '/') !== false) return ltrim($val, '/');
    return 'uploads/avatars/' . ltrim($val, '/');
};

$resolve_avatar_url = function(?string $rawAvatar) {
    $default = 'assets/avatar.png';
    if (empty($rawAvatar)) return $default;
    if (preg_match('#^https?://#i', $rawAvatar)) return $rawAvatar;
    $candidate = __DIR__ . '/' . ltrim($rawAvatar, '/');
    if (file_exists($candidate)) return $rawAvatar . '?v=' . filemtime($candidate);
    return $default;
};

// ------------------ Ensure session has account fields (avatar/name/email/role) ------------------
if (!empty($_SESSION['acc_id']) && (empty($_SESSION['acc_avatar']) || (!preg_match('#^https?://#i', $_SESSION['acc_avatar']) && !file_exists(__DIR__ . '/' . ltrim($_SESSION['acc_avatar'], '/'))))) {
    $accId = (string) $_SESSION['acc_id'];
    $accName = (string) ($_SESSION['acc_name'] ?? '');

    if ($conn) {
        // candidate tables to check (order matters: adjust if your actual table is known)
        $candidateTables = ['users','accounts','admins','admin_users','teachers','staff'];

        foreach ($candidateTables as $tbl) {
            // does table exist?
            $ok = @ $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tbl) . "'");
            if (!$ok || $ok->num_rows === 0) { if ($ok) $ok->free(); continue; }
            if ($ok) $ok->free();

            $cols = $detect_columns($tbl);
            if (empty($cols)) continue;

            // possible id columns and avatar-like columns
            $idCandidates = array_filter(['id','acc_id','user_id','admin_id','username'], fn($c) => in_array($c, $cols, true));
            $avatarCandidates = array_filter(['avatar','photo','image','profile_pic','picture','avatar_path'], fn($c) => in_array($c, $cols, true));
            $nameCandidates = array_filter(['name','display_name','username'], fn($c) => in_array($c, $cols, true));

            // build SELECT list (include avatar + name/email/role when available)
            $selectCols = [];
            if (!empty($avatarCandidates)) $selectCols[] = "`" . array_values($avatarCandidates)[0] . "` AS avatar_col";
            foreach (['name','display_name','username','email','role'] as $c) {
                if (in_array($c, $cols, true)) $selectCols[] = "`$c`";
            }
            if (empty($selectCols)) $selectCols[] = '*';

            // Try to find a matching row by id-like columns first
            $found = false;
            foreach ($idCandidates as $idCol) {
                $sql = "SELECT " . implode(', ', $selectCols) . " FROM `".$conn->real_escape_string($tbl)."` WHERE `".$conn->real_escape_string($idCol)."` = ? LIMIT 1";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param('s', $accId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $row = $res->fetch_assoc()) {
                        // populate session fields
                        if (isset($row['avatar_col']) && $row['avatar_col'] !== null && $row['avatar_col'] !== '') {
                            $_SESSION['acc_avatar'] = $normalize_avatar_value($row['avatar_col']);
                        }
                        if (isset($row['name']) && $row['name'] !== '') $_SESSION['acc_name'] = $row['name'];
                        if (isset($row['display_name']) && $row['display_name'] !== '') $_SESSION['acc_name'] = $row['display_name'];
                        if (isset($row['username']) && $row['username'] !== '') $_SESSION['acc_username'] = $row['username'];
                        if (isset($row['email'])) $_SESSION['acc_email'] = $row['email'];
                        if (isset($row['role'])) $_SESSION['acc_role'] = $row['role'];
                        $found = true;
                    }
                    if ($res) $res->free();
                    $stmt->close();
                }
                if ($found) break;
            }

            // If not found via id columns, try matching by accName (username/name)
            if (!$found && !empty($accName) && !empty($nameCandidates)) {
                foreach ($nameCandidates as $ncol) {
                    $sql = "SELECT " . implode(', ', $selectCols) . " FROM `".$conn->real_escape_string($tbl)."` WHERE `".$conn->real_escape_string($ncol)."` = ? LIMIT 1";
                    if ($stmt = $conn->prepare($sql)) {
                        $stmt->bind_param('s', $accName);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res && $row = $res->fetch_assoc()) {
                            if (isset($row['avatar_col']) && $row['avatar_col'] !== null && $row['avatar_col'] !== '') {
                                $_SESSION['acc_avatar'] = $normalize_avatar_value($row['avatar_col']);
                            }
                            if (isset($row['name']) && $row['name'] !== '') $_SESSION['acc_name'] = $row['name'];
                            if (isset($row['display_name']) && $row['display_name'] !== '') $_SESSION['acc_name'] = $row['display_name'];
                            if (isset($row['username']) && $row['username'] !== '') $_SESSION['acc_username'] = $row['username'];
                            if (isset($row['email'])) $_SESSION['acc_email'] = $row['email'];
                            if (isset($row['role'])) $_SESSION['acc_role'] = $row['role'];
                            $found = true;
                        }
                        if ($res) $res->free();
                        $stmt->close();
                    }
                    if ($found) break;
                }
            }

            if ($found) break; // stop searching tables after first match
        } // foreach candidateTables
    } // if $conn
}


$displayName  = $_SESSION['acc_name']  ?? ($_SESSION['acc_username'] ?? 'Admin');
$displayId    = $_SESSION['acc_id']    ?? '';
$displayRole  = $_SESSION['acc_role']  ?? '';
$displayEmail = $_SESSION['acc_email'] ?? '';

$rawAvatar = $_SESSION['acc_avatar'] ?? '';
$avatarUrl = $resolve_avatar_url($rawAvatar);


?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Your Quizzes</title>
<link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== GLOBAL DARK THEME ===== */
body{
  background:#050816;
  font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  color:#e6f1ff;
}
.page-wrap{
  padding:24px 28px 40px;
  max-width:1300px;
  margin:0 auto;
}

/* small profile header bar */
.profile-strip{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:18px;
  padding:10px 14px;
  border-radius:999px;
  background:#0b1020;
  box-shadow:0 10px 30px rgba(0,0,0,0.7);
}
.profile-strip-left{
  display:flex;
  align-items:center;
  gap:10px;
}
.profile-strip-avatar{
  width:40px;
  height:40px;
  border-radius:50%;
  object-fit:cover;
}
.profile-strip-name{
  font-weight:600;
  font-size:14px;
  color:#ffffff;
}
.profile-strip-meta{
  font-size:11px;
  color:#7c8fb6;
}
.profile-strip-meta span+span::before{
  content:"•";
  margin:0 4px;
}
.profile-strip-btn{
  border:none;
  border-radius:999px;
  padding:8px 16px;
  font-size:12px;
  font-weight:600;
  text-transform:uppercase;
  background:linear-gradient(135deg,#1e88ff,#1565c0);
  color:#fff;
  cursor:pointer;
  box-shadow:0 10px 28px rgba(30,136,255,0.7);
  display:inline-flex;
  align-items:center;
  gap:6px;
}

/* main layout */
.admin-layout{
  display:grid;
  grid-template-columns:220px minmax(0,1.1fr) 360px;
  gap:18px;
  align-items:flex-start;
}

/* left column pills */
.pill-btn{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  margin-bottom:14px;
  padding:12px 22px;
  border-radius:999px;
  background:linear-gradient(135deg,#1e88ff,#1565c0);
  color:#fff;
  font-weight:600;
  text-decoration:none;
  text-transform:uppercase;
  letter-spacing:.03em;
  font-size:13px;
  box-shadow:0 12px 26px rgba(30,136,255,0.8);
  transition:transform .14s ease, box-shadow .14s ease, background .14s ease;
}
.pill-btn:hover{
  transform:translateY(-2px);
  box-shadow:0 16px 32px rgba(30,136,255,0.9);
}

/* quiz list center */
.quiz-list{
  display:flex;
  flex-direction:column;
  gap:10px;
}
.frame-card{
  border-radius:18px;
  padding:16px 18px;
  background:#0b1020;
  box-shadow:0 18px 55px rgba(0,0,0,0.95);
  display:flex;
  align-items:center;
  justify-content:space-between;
  transition:transform .18s ease, box-shadow .18s ease, background .18s ease;
}
.frame-card:hover{
  transform:translateY(-3px);
  background:#0f1528;
  box-shadow:0 24px 70px rgba(0,0,0,1);
}
.quiz-main{
  flex:1;
  display:flex;
  gap:14px;
  align-items:center;
}
.quiz-title{
  font-weight:700;
  font-size:15px;
  color:#ffffff;
}
.quiz-dead{
  color:#7c8fb6;
  font-size:12px;
  margin-top:2px;
}
.quiz-code-chip{
  width:52px;
  height:52px;
  border-radius:16px;
  background:#111b3a;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  font-weight:800;
  font-size:11px;
  color:#4ba4ff;
  text-align:center;
  line-height:1.1;
}
.quiz-code-chip span.code-main{
  font-size:14px;
}

.quiz-actions{
  display:flex;
  align-items:center;
  gap:8px;
  margin-left:12px;
}
.open-btn{
  font-size:12px;
  font-weight:600;
  padding:10px 18px;
  border-radius:999px;
  text-decoration:none;
  background:linear-gradient(135deg,#1e88ff,#1565c0);
  color:#fff;
  box-shadow:0 10px 26px rgba(30,136,255,0.7);
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.edit-circle{
  width:36px;
  height:36px;
  border-radius:50%;
  background:#111b3a;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#9bb6ff;
  box-shadow:0 10px 26px rgba(0,0,0,0.8);
}

/* right column */
.right-top-row{
  display:flex;
  justify-content:flex-end;
  margin-bottom:12px;
}
.edit-students-btn{
  text-decoration:none;
  padding:10px 20px;
  border-radius:999px;
  text-transform:uppercase;
  font-size:12px;
  font-weight:600;
  background:linear-gradient(135deg,#1e88ff,#1565c0);
  color:#fff;
  box-shadow:0 12px 28px rgba(30,136,255,0.8);
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.summary-card{
  background:#0b1020;
  border-radius:18px;
  padding:14px 16px 18px;
  box-shadow:0 16px 45px rgba(0,0,0,0.95);
}
.summary-card-header{
  font-weight:700;
  margin-bottom:8px;
  color:#ffffff;
}
.summary-metrics{
  display:flex;
  gap:16px;
  align-items:center;
  margin-bottom:10px;
}
.summary-metrics small{
  font-size:11px;
  color:#7c8fb6;
}
.summary-metrics span.value{
  font-size:18px;
  font-weight:700;
  color:#ffffff;
}

/* pie chart */
.pie-canvas{
  width:100% !important;
  height:160px !important;
  max-width:260px;
  margin:0 auto;
}
.glider-contain{
  position:relative;
  margin-top:6px;
}
.glider-prev,.glider-next{
  position:absolute;
  top:45%;
  transform:translateY(-50%);
  border:none;
  background:transparent;
  font-size:18px;
  cursor:pointer;
  padding:0 6px;
  color:#7c8fb6;
}
.glider-prev{left:-6px;}
.glider-next{right:-6px;}
#dots{text-align:center;margin-top:8px;}
.empty-card-text{
  font-size:14px;
  color:#7c8fb6;
}

@media(max-width:1024px){
  .admin-layout{grid-template-columns:1fr;}
}

/* ===== DARK PROFILE MODAL (with smooth transition) ===== */
.profile-modal-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.65);
  display:flex;                  /* always flex; visibility via opacity */
  align-items:center;
  justify-content:center;
  z-index:9999;
  opacity:0;
  pointer-events:none;
  transition:opacity .25s ease;
}
.profile-modal-overlay.is-open{
  opacity:1;
  pointer-events:auto;
}
.profile-modal{
  width:100%;
  max-width:760px;
  background:linear-gradient(135deg,#050816,#07152a);
  border-radius:18px;
  padding:22px 24px 20px;
  color:#e6f1ff;
  box-shadow:0 22px 70px rgba(0,0,0,0.8);
  position:relative;
  transform:translateY(12px) scale(.97);
  opacity:0;
  transition:transform .25s ease, opacity .25s ease;
}
.profile-modal-overlay.is-open .profile-modal{
  transform:translateY(0) scale(1);
  opacity:1;
}
.profile-modal h2{
  margin:0 0 4px;
  font-size:22px;
  font-weight:700;
}
.profile-modal-header-line{
  height:3px;
  width:100%;
  margin:4px 0 18px;
  background:#1e88ff;
  border-radius:999px;
}
.profile-modal-grid{
  display:grid;
  grid-template-columns:160px 1fr;
  gap:18px;
}
.profile-modal-avatar-wrap{
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:10px;
}
.profile-modal-avatar{
  width:96px;
  height:96px;
  border-radius:50%;
  object-fit:cover;
  border:3px solid #1e88ff;
}
.profile-modal-upload-btn{
  border:none;
  border-radius:999px;
  padding:8px 18px;
  font-size:13px;
  font-weight:600;
  cursor:pointer;
  background:#1e88ff;
  color:#fff;
  box-shadow:0 14px 30px rgba(30,136,255,0.5);
}
.profile-modal-fields label{
  font-size:12px;
  color:#8eb4ff;
  margin-bottom:2px;
  display:block;
}
.profile-modal-fields input[type="text"],
.profile-modal-fields input[type="password"]{
  width:100%;
  border:none;
  border-bottom:1px solid #273a5c;
  background:transparent;
  color:#e6f1ff;
  padding:4px 2px 6px;
  font-size:14px;
  outline:none;
}
.profile-modal-fields input::placeholder{
  color:#4b5f86;
}
.profile-modal-fields input:focus{
  border-bottom-color:#1e88ff;
}
.profile-modal-row{
  margin-bottom:14px;
}
.profile-modal-confirm{
  margin-top:6px;
  display:flex;
  align-items:center;
  gap:8px;
  font-size:13px;
  color:#a6c2ff;
  cursor:pointer;
}
.profile-modal-confirm input[type="checkbox"]{
  accent-color:#1e88ff;
  transform:scale(1.1);
}
.profile-modal-footer{
  margin-top:16px;
  display:flex;
  justify-content:flex-end;
  gap:10px;
}
.profile-modal-close-btn{
  border:none;
  background:transparent;
  color:#9eb4ff;
  font-size:13px;
  cursor:pointer;
}
.profile-modal-save-btn{
  border:none;
  border-radius:999px;
  padding:10px 22px;
  font-size:14px;
  font-weight:600;
  cursor:not-allowed;
  background:#0b2a53;
  color:#6f8bbf;
  box-shadow:none;
  transition:background .15s ease, box-shadow .15s ease, color .15s ease;
}
.profile-modal-save-enabled{
  cursor:pointer !important;
  background:#1e88ff !important;
  color:#fff !important;
  box-shadow:0 18px 40px rgba(30,136,255,0.6) !important;
}
.profile-modal-close-x{
  position:absolute;
  top:10px;
  right:12px;
  cursor:pointer;
  font-size:18px;
  color:#7f9bd0;
}

/* ===== QUIZ CARD COLOR FIX ===== */
.frame-card{
  background:#0b1020 !important;
  color:#e6f1ff !important;
  box-shadow:0 18px 55px rgba(0,0,0,0.9) !important;
}

.quiz-title{
  color:#ffffff !important;
}

.quiz-dead{
  color:#8fa8d7 !important;
}

.quiz-code-chip{
  background:#101b3d !important;
  color:#4ba4ff !important;
}

/* button matches UI */
.open-btn{
  background:linear-gradient(135deg,#1e88ff,#1565c0) !important;
  box-shadow:0 12px 26px rgba(30,136,255,0.8) !important;
}

/* remove leftover edit circle if any cached */
.edit-circle{
  display:none !important;
}
/* remove teal active/focus highlight on glider arrows */
.glider-prev:focus,
.glider-prev:active,
.glider-next:focus,
.glider-next:active {
  outline: none;
  background: transparent;
  box-shadow: none;
  color: #7c8fb6; /* same as normal arrow color */
}

</style>


</head>
<body>
<div class="page-wrap">

  <!-- PROFILE STRIP WITH EDIT BUTTON -->
  <div class="profile-strip">
    <div class="profile-strip-left">
      <img src="<?php echo htmlspecialchars($avatarUrl); ?>" class="profile-strip-avatar" alt="Avatar">
      <div>
        <div class="profile-strip-name"><?php echo htmlspecialchars($displayName); ?></div>
        <div class="profile-strip-meta">
          <?php if ($displayEmail): ?><span><?php echo htmlspecialchars($displayEmail); ?></span><?php endif; ?>
          <?php if ($displayId): ?><span><?php echo htmlspecialchars($displayId); ?></span><?php endif; ?>
          <?php if ($displayRole): ?><span><?php echo htmlspecialchars(ucfirst($displayRole)); ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <button class="profile-strip-btn" id="openProfileModal">
      <i class="material-icons" style="font-size:16px;">edit</i>
      Edit Profile
    </button>
  </div>

  <div class="admin-layout">
    <!-- LEFT COLUMN -->
    <div class="left-col">
      <a class="pill-btn" href="quizmaker.php">
        <i class="material-icons" style="font-size:18px;">add_circle_outline</i>
        CREATE QUIZ
      </a>
      <a class="pill-btn" href="submissions.php">
        <i class="material-icons" style="font-size:18px;">assignment</i>
        VIEW SUBMISSIONS
      </a>
    </div>

    <!-- CENTER COLUMN -->
    <div>
      <div class="quiz-list" id="quizzesContainer">
        <?php if(!empty($dbError)): ?>
          <div class="frame-card"><div class="empty-card-text">Notice: <?php echo htmlspecialchars($dbError); ?></div></div>
        <?php endif; ?>

        <?php if(empty($quizzes)): ?>
          <div class="frame-card">
            <div class="empty-card-text">
              <?php echo $isAdminSignedIn ? 'You haven\'t created any quizzes yet.' : 'No quizzes found. Click CREATE QUIZ to add one.'; ?>
            </div>
          </div>
        <?php else: foreach($quizzes as $q): ?>
          <div class="frame-card" data-id="<?php echo (int)$q['id']; ?>">
            <div class="quiz-main">
              <div>
                <div class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></div>
                <div class="quiz-dead">
                  DEADLINE: <?php echo $q['deadline'] ? htmlspecialchars($q['deadline']) : '—'; ?>
                </div>
              </div>
            </div>
            <div class="quiz-actions">
              <a class="open-btn" href="quiz_report.php?id=<?php echo (int)$q['id']; ?>">
                <i class="material-icons" style="font-size:16px;">open_in_new</i>
                Open
              </a>
              </a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
      <div class="right-top-row">
        <a href="Students.php" class="edit-students-btn">
          <i class="material-icons" style="font-size:18px;">group</i>
          EDIT STUDENTS
        </a>
      </div>

      <div class="summary-card">
        <div class="summary-card-header">Summary</div>
        <div class="summary-metrics">
          <div>
            <small>Total students</small>
            <span class="value"><?php echo (int)$totalStudents; ?></span>
          </div>
          <div>
            <small>Quizzes you created</small>
            <span class="value"><?php echo (int)$totalCreated; ?></span>
          </div>
          <div>
            <small>Your assigned students</small>
            <span class="value"><?php echo (int)$teacherStudents; ?></span>
          </div>
        </div>

        <div class="glider-contain">
          <div class="glider" id="quizPieCarousel">
            <?php if (empty($quizStats)): ?>
              <div class="slide">
                <div style="padding:14px;text-align:center;color:#666">
                  No per-quiz stats available yet.
                </div>
              </div>
            <?php else: foreach ($quizStats as $qs): ?>
              <div class="slide" style="padding:10px;text-align:center;">
                <h6 style="margin:6px 0 8px;"><?php echo htmlspecialchars($qs['title']); ?></h6>
                <canvas id="pie_<?php echo $qs['id']; ?>" class="pie-canvas"></canvas>
                <div style="font-size:12px; margin-top:8px; color:#e6f1ff;">
                  Submitted: <b><?php echo $qs['submitted']; ?></b> &nbsp;|&nbsp;
                  Not Submitted: <b><?php echo $qs['not_submitted']; ?></b>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <button class="glider-prev" aria-label="Previous">«</button>
          <button class="glider-next" aria-label="Next">»</button>
          <div id="dots"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== PROFILE MODAL ===== -->
<div class="profile-modal-overlay" id="profileModalOverlay">
  <div class="profile-modal">
    <div class="profile-modal-close-x" id="profileModalCloseX">&times;</div>
    <h2>Edit Your Profile</h2>
    <div class="profile-modal-header-line"></div>

    <form id="profileForm" action="update_profile.php" method="post" enctype="multipart/form-data">
      <div class="profile-modal-grid">
        <div class="profile-modal-avatar-wrap">
          <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="profile-modal-avatar" id="profileModalAvatar">
          <label class="profile-modal-upload-btn">
            Upload
            <input type="file" name="avatar" id="avatarFile" accept="image/*" style="display:none;">
          </label>
        </div>

        <div class="profile-modal-fields">
          <div class="profile-modal-row">
            <label for="displayName">Display Name</label>
            <input type="text" id="displayName" name="display_name" class="browser-default"
                   value="<?php echo htmlspecialchars($displayName); ?>">
          </div>
<!--           <div class="profile-modal-row">
            <label for="currentPwd">Current Password (required to change)</label>
            <input type="password" id="currentPwd" name="current_password" class="browser-default">
          </div>
          <div class="profile-modal-row">
            <label for="newPwd">New Password</label>
            <input type="password" id="newPwd" name="new_password" class="browser-default">
          </div>
          <div class="profile-modal-row">
            <label for="confirmPwd">Retype New Password</label>
            <input type="password" id="confirmPwd" name="confirm_password" class="browser-default">
          </div> -->

          <label class="profile-modal-confirm">
            <input type="checkbox" id="confirmSave">
            <span>I confirm I want to save these changes</span>
          </label>
        </div>
      </div>

      <div class="profile-modal-footer">
        <button type="button" class="profile-modal-close-btn" id="profileModalCloseBtn">Cancel</button>
        <button type="submit" class="profile-modal-save-btn" id="profileModalSaveBtn" disabled>Save Profile</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
(function(){
  const BLUE  = '#4ba4ff';
  const WHITE = '#ffffff';

  // ----- PROFILE MODAL -----
  const openBtn     = document.getElementById('openProfileModal');
  const overlay     = document.getElementById('profileModalOverlay');
  const closeBtn    = document.getElementById('profileModalCloseBtn');
  const closeX      = document.getElementById('profileModalCloseX');
  const confirmCb   = document.getElementById('confirmSave');
  const saveBtn     = document.getElementById('profileModalSaveBtn');
  const avatarInput = document.getElementById('avatarFile');
  const avatarImg   = document.getElementById('profileModalAvatar');

  function updateSaveState(on){
    if (!saveBtn) return;
    saveBtn.disabled = !on;
    if (on) saveBtn.classList.add('profile-modal-save-enabled');
    else    saveBtn.classList.remove('profile-modal-save-enabled');
  }

  function openModal(){
    if (!overlay) return;
    overlay.classList.add('is-open');
  }
  function closeModal(){
    if (!overlay) return;
    overlay.classList.remove('is-open');
    if (confirmCb){
      confirmCb.checked = false;
      updateSaveState(false);
    }
  }

  if(openBtn)  openBtn.addEventListener('click', openModal);
  if(closeBtn) closeBtn.addEventListener('click', closeModal);
  if(closeX)   closeX.addEventListener('click', closeModal);
  if(overlay){
    overlay.addEventListener('click', function(e){
      if(e.target === overlay) closeModal();
    });
  }

  if(confirmCb){
    confirmCb.addEventListener('change', function(){
      updateSaveState(this.checked);
    });
  }

  // preview avatar
  if(avatarInput && avatarImg){
    avatarInput.addEventListener('change', function(){
      const file = this.files && this.files[0];
      if(!file) return;
      const reader = new FileReader();
      reader.onload = function(e){
        avatarImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  // ----- GLIDER -----
  const gliderEl = document.querySelector('#quizPieCarousel');
  var gliderInstance = null;
  if (gliderEl) {
    gliderInstance = new Glider(gliderEl, {
      slidesToShow: 1,
      slidesToScroll: 1,
      draggable: true,
      dots: '#dots',
      arrows: { prev: '.glider-prev', next: '.glider-next' },
      rewind: true
    });
    let autoplayTimer = null;
    function startAutoplay(){ stopAutoplay(); autoplayTimer = setInterval(()=>{ try{ gliderInstance.scrollItem('next'); }catch(e){} }, 4200); }
    function stopAutoplay(){ if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; } }
    gliderEl.addEventListener('mouseenter', stopAutoplay);
    gliderEl.addEventListener('mouseleave', startAutoplay);
    startAutoplay();
  }

  // ----- PIE CHARTS (blue + white) -----
  <?php foreach ($quizStats as $qs): ?>
  (function(){
    const submitted    = <?php echo (int)$qs['submitted']; ?>;
    const notSubmitted = <?php echo (int)$qs['not_submitted']; ?>;
    const canvas       = document.getElementById('pie_<?php echo $qs['id']; ?>');
    if (!canvas) return;
    const total = submitted + notSubmitted;

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Submitted','Not Submitted'],
        datasets: [{
          data: [submitted, notSubmitted],
          backgroundColor: [BLUE, WHITE],
          borderColor: '#050816',
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          animateRotate:true,
          animateScale:true,
          duration:800,
          easing:'easeOutQuart'
        },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#e6f1ff',
              font: { size: 11 }
            }
          },
          tooltip: {
            enabled: true,
            bodyColor:'#050816',
            backgroundColor:'#e6f1ff',
            titleColor:'#050816',
            callbacks: {
              label: function(context) {
                const label   = context.label || '';
                const value   = context.parsed || 0;
                const percent = total > 0 ? ((value/total)*100).toFixed(1) : '0.0';
                return label + ': ' + value + ' (' + percent + '%)';
              }
            }
          }
        }
      }
    });
  })();
  <?php endforeach; ?>

  // clickable quiz card (except action area)
  document.querySelectorAll('#quizzesContainer .frame-card').forEach(card=>{
    const id = card.dataset.id;
    if(id){
      card.style.cursor='pointer';
      card.addEventListener('click',(e)=>{
        if(e.target.closest('.quiz-actions')) return;
        window.location.href='quiz_report.php?id='+id;
      });
    }
  });

})();
</script>

</body>
</html>
