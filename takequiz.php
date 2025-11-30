<?php
// takequiz.php
// Student-facing list of available quizzes and submission status.

include('templates/header.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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
    <div style="max-width:900px;margin:48px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);font-family:Roboto, Arial, sans-serif">
      <h4>Please log in to view quizzes</h4>
      <p>If you already have an account, click <strong>Log In</strong> in the header (or use the form that opened). If you don't yet have an account, please register / contact your instructor.</p>
      <p style="margin-top:18px">
        <a class="btn" href="index.php">Home</a>
        <a class="btn blue modal-trigger" href="#loginModal" id="openLoginBtn">Open Login</a>
      </p>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function(){
        if (typeof M !== 'undefined' && M.Modal) {
          var elems = document.querySelectorAll('.modal');
          M.Modal.init(elems);
          var loginEl = document.getElementById('loginModal');
          if (loginEl) {
            var inst = M.Modal.getInstance(loginEl) || M.Modal.init(loginEl);
            try { inst.open(); } catch(e) {}
          }
        } else {
          try { $('.modal').modal(); $('#loginModal').modal('open'); } catch(e) {}
        }
      });
    </script>

    <?php
    include('templates/footer.php');
    exit;
}

// ---------------------------------------------
// User is logged in: proceed to load quizzes
// ---------------------------------------------

// Determine current student ID from session (try common keys)
$studentId = null;
if (!empty($_SESSION['student_id'])) {
    $studentId = (int)$_SESSION['student_id'];
} elseif (!empty($_SESSION['user_id'])) {
    $studentId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $studentId = (int)$_SESSION['user']['id'];
} elseif (!empty($_SESSION['acc_id'])) {
    $studentId = (int)$_SESSION['acc_id'];
}

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
// Detect columns in submitted_flights (quiz + student)
// ---------------------------------------------
$sfQuizCol = null;
$sfStuCol  = null;

$sfCols = [];
$sfRes = $conn->query("SHOW COLUMNS FROM `submitted_flights`");
if ($sfRes) {
    while ($sc = $sfRes->fetch_assoc()) {
        $sfCols[] = $sc['Field'];
    }
    $sfRes->free();
}

// candidate quiz id columns
foreach (['quiz_id','quizid','quiz','exam_id','test_id'] as $cand) {
    if (in_array($cand, $sfCols, true)) { $sfQuizCol = $cand; break; }
}
// candidate student/account columns
foreach (['acc_id','student_id','user_id','account_id','submitted_by','submitted_acc','sid'] as $cand) {
    if (in_array($cand, $sfCols, true)) { $sfStuCol = $cand; break; }
}

// ---------------------------------------------
// Build main query
// ---------------------------------------------
$quizzes = [];

if ($sfQuizCol && $sfStuCol && $studentId) {
    // Normal path: submitted_flights exists and we know quiz + student columns.
    $quizColSafe = '`' . str_replace('`','``',$sfQuizCol) . '`';
    $stuColSafe  = '`' . str_replace('`','``',$sfStuCol) . '`';

    $sql = "
      SELECT
        q.id AS quiz_id,
        q.title,
        q.quiz_code AS code,
        '' AS deadline,
        COALESCE(q.duration, 0) AS duration,
        CASE WHEN COUNT(sf.$stuColSafe) > 0 THEN 1 ELSE NULL END AS submitted_at,
        CASE WHEN COUNT(sf.$stuColSafe) > 0 THEN ? ELSE NULL END AS submitted_by,
        COUNT(DISTINCT qi.id) AS num_items
      FROM quizzes q
      LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
      LEFT JOIN submitted_flights sf
        ON sf.$quizColSafe = q.id
       AND sf.$stuColSafe = ?
      GROUP BY q.id
      ORDER BY q.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('<div style="padding:20px; color:darkred">Query prepare failed: ' . htmlspecialchars($conn->error) . '</div>');
    }
    // bind same studentId twice (for submitted_by and JOIN condition)
    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $quizzes[] = $row;
    }
    $stmt->close();

} else {
    // Fallback: no submitted_flights or we couldn't detect columns.
    // Show quizzes without submission status (all as not submitted).
    $sql = "
      SELECT
        q.id AS quiz_id,
        q.title,
        q.quiz_code AS code,
        '' AS deadline,
        COALESCE(q.duration, 0) AS duration,
        NULL AS submitted_at,
        NULL AS submitted_by,
        COUNT(DISTINCT qi.id) AS num_items
      FROM quizzes q
      LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
      GROUP BY q.id
      ORDER BY q.created_at DESC
    ";
    $res = $conn->query($sql);
    if (!$res) {
        die('<div style="padding:20px; color:darkred">Query failed: ' . htmlspecialchars($conn->error) . '</div>');
    }
    while ($row = $res->fetch_assoc()) {
        $quizzes[] = $row;
    }
    $res->free();
}

// Stats
$total = count($quizzes);
$submittedCount = 0;
foreach ($quizzes as $q) {
    if (!empty($q['submitted_at'])) $submittedCount++;
}
$notSubmitted = $total - $submittedCount;

// Helper
function h($s){ return htmlspecialchars((string)$s); }
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Available Quizzes</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
  <style>
    body { background: #f6fbff; font-family: Roboto, Arial, sans-serif; padding-bottom:40px; }
    .profile-wrap{ max-width:1100px; margin:28px auto; }
    .card { border-radius:12px; }
    .stat { text-align:center; padding:12px; border-radius:8px; background:#fff; }
    .quiz-row { display:flex; gap:12px; align-items:center; padding:12px; border-radius:8px; background:#fff; margin-bottom:10px; }
    .quiz-code { font-weight:800; font-size:18px; width:78px; text-align:center; }
    .quiz-title { font-weight:700; }
    .small-note { color:#6b7280; font-size:13px }
    @media(max-width:800px){ .quiz-row{ flex-direction:column; align-items:flex-start } .quiz-code{ width:100%; text-align:left } }
  </style>
</head>
<body>

<div class="profile-wrap">
  <div class="row">
    <div class="col s12 m8">
      <div class="card">
        <div class="card-content">
          <span class="card-title">Hello, <?php echo h(!empty($_SESSION['acc_name']) ? $_SESSION['acc_name'] : 'Student'); ?></span>
          <p class="small-note">Below are the quizzes available. Click <strong>Take Quiz</strong> to start (if you haven't submitted).</p>

          <div style="margin-top:14px;">
            <?php if (empty($quizzes)): ?>
              <div class="center" style="padding:28px;color:#555">No quizzes available yet.</div>
            <?php else: ?>
              <?php foreach ($quizzes as $q):
                  $isSubmitted = !empty($q['submitted_at']);
                  // num_questions might not exist in schema — default to 0
                  $numQuestions = isset($q['num_questions']) ? (int)$q['num_questions'] : 0;
                  $duration = isset($q['duration']) ? (int)$q['duration'] : 0;
                  $numItems = isset($q['num_items']) ? (int)$q['num_items'] : 0;
              ?>
              <div class="quiz-row">
                <div class="quiz-code">
                  <div><?php echo h(strtoupper($q['code'] ?: 'REF')); ?></div>
                  <div class="small-note"><?php echo h($numItems); ?> item<?php echo ($numItems == 1) ? '' : 's'; ?></div>
                </div>

                <div style="flex:1">
                  <div class="quiz-title"><?php echo h($q['title']); ?></div>
                  <div class="small-note">
                    <?php echo h($numQuestions); ?> question<?php echo $numQuestions == 1 ? '' : 's'; ?>  ?>
                  </div>
                </div>

                <div style="min-width:180px; text-align:right">
                  <?php if ($isSubmitted): ?>
                    <div style="color:green; font-weight:700">Submitted</div>
                    <div class="small-note">on <?php echo h(date('M j, Y H:i', strtotime($q['submitted_at']))); ?></div>
                    <div style="margin-top:8px">
                      <a class="btn-flat" href="ticket.php?id=<?php echo urlencode($q['quiz_id']); ?>">View</a>
                    </div>
                  <?php else: ?>
                    <div style="color:#d32f2f; font-weight:700">Not submitted</div>
                    <div class="small-note">&nbsp;</div>
                    <div style="margin-top:8px">
                      <a class="btn" href="ticket.php?id=<?php echo urlencode($q['quiz_id']); ?>">Take Quiz</a>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col s12 m4">
      <div class="card" style="padding:12px">
        <div style="display:flex; gap:10px;">
          <div style="flex:1">
            <div class="stat">
              <div style="font-size:18px; font-weight:800"><?php echo h($total); ?></div>
              <div class="small-note">Total Quizzes</div>
            </div>
          </div>
          <div style="flex:1">
            <div class="stat">
              <div style="font-size:18px; font-weight:800; color:green"><?php echo h($submittedCount); ?></div>
              <div class="small-note">Submitted</div>
            </div>
          </div>
          <div style="flex:1">
            <div class="stat">
              <div style="font-size:18px; font-weight:800; color:#d32f2f"><?php echo h($notSubmitted); ?></div>
              <div class="small-note">Not Submitted</div>
            </div>
          </div>
        </div>

        <div style="margin-top:12px">
          <a class="btn-flat" href="Students.php">My Student Profile</a>
        </div>
      </div>

      <div class="card" style="margin-top:12px; padding:12px">
        <div style="font-weight:700; margin-bottom:8px">Quick actions</div>
        <div style="display:flex; flex-direction:column; gap:8px">
          <a class="btn-small btn" href="Quizzes.php">View All Quizzes</a>
          <a class="btn-small btn" href="logout.php">Logout</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- footer include -->
<?php include('templates/footer.php'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>

<?php
// close DB connection
$conn->close();
?>
