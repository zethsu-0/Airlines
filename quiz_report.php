<?php
// quiz_report.php (fixed, robust)
// Shows submissions / not-submitted chart + edit / return for a given quiz id

include('templates/header_admin.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// require admin
if (empty($_SESSION['acc_id']) || empty($_SESSION['acc_role']) || $_SESSION['acc_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("DB connection failed: " . $mysqli->connect_error);
}

// get quiz id from query
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) {
    die("Missing or invalid quiz id.");
}

// fetch quiz and detect creator column
$quiz = null;
$creatorCol = null;
$cols = [];
$colRes = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) $cols[] = $c['Field'];
    $colRes->free();
}
$candidates = ['created_by','creator','author','created_by_id','admin_id','owner_id','user_id','acc_id'];
foreach ($candidates as $cand) {
    if (in_array($cand, $cols, true)) { $creatorCol = $cand; break; }
}

// fetch quiz row
$quizSql = "SELECT * FROM `quizzes` WHERE id = ? LIMIT 1";
$stmtQ = $mysqli->prepare($quizSql);
if (!$stmtQ) {
    die("Failed to prepare quiz query: " . $mysqli->error);
}
$stmtQ->bind_param('i', $quizId);
$stmtQ->execute();
$resQ = $stmtQ->get_result();
$quiz = $resQ->fetch_assoc() ?: null;
if ($resQ) $resQ->free();
$stmtQ->close();

if (!$quiz) {
    die("Quiz not found.");
}

// if creator column exists, verify ownership (if value set)
if ($creatorCol) {
    $creatorVal = (string)($quiz[$creatorCol] ?? '');
    if ($creatorVal !== '' && $creatorVal !== (string)$_SESSION['acc_id']) {
        die("You are not the owner of this quiz.");
    }
}

// detect submissions columns: find student identifier and timestamp
$subCols = [];
$rc = $mysqli->query("SHOW COLUMNS FROM `submissions`");
if ($rc) {
    while ($c = $rc->fetch_assoc()) $subCols[] = $c['Field'];
    $rc->free();
}

$studentCandidates = ['student_id','student','stud_id','sid','user_id','account_id','acc_id'];
$studentCol = null;
foreach ($studentCandidates as $cand) {
    if (in_array($cand, $subCols, true)) { $studentCol = $cand; break; }
}
$timestampCol = null;
if (in_array('created_at', $subCols, true)) $timestampCol = 'created_at';
elseif (in_array('submitted_at', $subCols, true)) $timestampCol = 'submitted_at';
elseif (in_array('timestamp', $subCols, true)) $timestampCol = 'timestamp';

// total students
$totalStudents = null;
$r = $mysqli->query("SELECT COUNT(*) AS c FROM students");
if ($r) { $row = $r->fetch_assoc(); $totalStudents = (int)$row['c']; $r->free(); }

// compute submitted_count
$submittedCount = 0;
$notes = [];

if ($studentCol) {
    $sql = "SELECT COUNT(DISTINCT `$studentCol`) AS c FROM `submissions` WHERE quiz_id = ?";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) $submittedCount = (int)$row['c'];
        if ($res) $res->free();
        $stmt->close();
        $notes[] = "Using `$studentCol` column to count distinct submitting students.";
    } else {
        // fallback to counting rows if prepare fails
        $notes[] = "Could not prepare submissions DISTINCT query, falling back to row count. (" . $mysqli->error . ")";
        $sql2 = "SELECT COUNT(*) AS c FROM `submissions` WHERE quiz_id = ?";
        $stmt2 = $mysqli->prepare($sql2);
        if ($stmt2) {
            $stmt2->bind_param('i', $quizId);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2 && ($row2 = $res2->fetch_assoc())) $submittedCount = (int)$row2['c'];
            if ($res2) $res2->free();
            $stmt2->close();
        }
    }
} else {
    // no student column — count rows
    $sql = "SELECT COUNT(*) AS c FROM `submissions` WHERE quiz_id = ?";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) $submittedCount = (int)$row['c'];
        if ($res) $res->free();
        $stmt->close();
    } else {
        $notes[] = "No student identifier detected; and submissions count query failed: " . $mysqli->error;
    }
    $notes[] = "No student identifier detected in submissions; counting submission rows.";
}

// compute not-submitted if totalStudents known
$notSubmitted = null;
if (is_int($totalStudents)) {
    $notSubmitted = max(0, $totalStudents - $submittedCount);
} else {
    $notes[] = "Total students count unavailable; cannot compute 'not submitted' exact number.";
}

// Build recent submissions safe query: only select columns that exist
$recentSubmissions = [];
$hasAnswersCol = in_array('answers', $subCols, true); // optional
$fetchCols = ['id','quiz_id'];
if ($studentCol) $fetchCols[] = $studentCol;
if ($timestampCol) $fetchCols[] = $timestampCol;
if ($hasAnswersCol) $fetchCols[] = 'answers';

$colsSql = implode(',', array_map(function($c){ return "`$c`"; }, $fetchCols));
$orderBy = $timestampCol ? "`$timestampCol` DESC" : "`id` DESC";

$sql = "SELECT $colsSql FROM `submissions` WHERE quiz_id = ? ORDER BY $orderBy LIMIT 100";
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $recentSubmissions[] = $r;
    if ($res) $res->free();
    $stmt->close();
} else {
    // if prepare failed, set notes and skip recent submissions
    $notes[] = "Could not prepare recent submissions query: " . $mysqli->error;
}

$submittedCountJs = $submittedCount;
$notSubmittedJs = is_int($notSubmitted) ? $notSubmitted : 0;
$quizTitle = htmlspecialchars($quiz['title'] ?? 'Untitled Quiz', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$quizCode = htmlspecialchars($quiz['code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$quizIdEsc = (int)$quizId;
$notesHtml = '';
if (!empty($notes)) {
    $notesHtml = '<div style="margin-bottom:10px; color:#6b7280; font-size:13px;"><strong>Notes:</strong><br>' . implode('<br>', array_map('htmlspecialchars', $notes)) . '</div>';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quiz report — <?php echo $quizTitle; ?></title>
  <link rel="stylesheet" href="css/admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .container{max-width:1100px;margin:26px auto;padding:0 16px}
    .card{background:#fff;padding:16px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.04);margin-bottom:16px}
    .btn { display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;background:#0d6efd;color:#fff }
    .muted { color:#6b7280; font-size:13px }
    table { width:100%; border-collapse:collapse; margin-top:12px }
    th,td { text-align:left; padding:8px; border-bottom:1px solid #eee }
    .small { font-size:13px; color:#666 }
  </style>
</head>
<body>
<div class="container">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px">
    <div>
      <h3 style="margin:0"><?php echo $quizTitle; ?></h3>
      <div class="muted">Code: <?php echo $quizCode; ?> • Quiz ID: <?php echo $quizIdEsc; ?></div>
    </div>
    <div style="display:flex; gap:8px; align-items:center">
      <a class="btn" href="quizmaker.php?id=<?php echo $quizIdEsc; ?>">Edit Quiz</a>
      <a class="btn" href="admin.php" style="background:#6c757d">Return</a>
    </div>
  </div>

  <div class="card">
    <?php echo $notesHtml; ?>
    <div style="display:flex; gap:18px; align-items:center; flex-wrap:wrap">
      <div style="width:300px">
        <canvas id="subPie" width="300" height="300"></canvas>
      </div>
      <div>
        <div style="font-weight:700; font-size:18px"><?php echo number_format($submittedCountJs) ?></div>
        <div class="small">Submitted</div>

        <div style="height:12px"></div>

        <?php if (is_int($notSubmitted)): ?>
          <div style="font-weight:700; font-size:18px"><?php echo number_format($notSubmittedJs) ?></div>
          <div class="small">Not submitted</div>
        <?php else: ?>
          <div class="small">Not-submitted: unavailable (students table missing)</div>
        <?php endif; ?>

        <?php if (is_int($totalStudents)): ?>
          <div style="margin-top:10px" class="small">Total registered students: <?php echo (int)$totalStudents; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <div style="font-weight:700">Recent Submissions (latest first)</div>
      <div class="small">Showing up to 100</div>
    </div>

    <?php if (count($recentSubmissions) === 0): ?>
      <div style="margin-top:10px" class="small">No submissions yet for this quiz.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <?php if ($studentCol): ?><th>Student</th><?php endif; ?>
            <?php if ($timestampCol): ?><th>Submitted at</th><?php endif; ?>
            <th class="small">Submission id</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($recentSubmissions as $row): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <?php if ($studentCol): ?><td><?php echo htmlspecialchars($row[$studentCol] ?? ''); ?></td><?php endif; ?>
              <?php if ($timestampCol): ?>
                <td><?php
                  $ts = $row[$timestampCol] ?? null;
                  echo $ts ? htmlspecialchars($ts) : '<span class="small">n/a</span>';
                ?></td>
              <?php endif; ?>
              <td class="small"><?php echo (int)$row['id']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<script>
  const submitted = <?php echo json_encode($submittedCountJs, JSON_NUMERIC_CHECK); ?>;
  const notSubmitted = <?php echo json_encode(is_int($notSubmitted) ? $notSubmittedJs : 0, JSON_NUMERIC_CHECK); ?>;
  const ctx = document.getElementById('subPie').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Submitted', 'Not submitted'],
      datasets: [{ data: [submitted, notSubmitted] }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });
</script>

<?php include('templates/footer.php'); ?>
</body>
</html>
