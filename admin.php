<?php
// Admin.php (Quizzes list — reads from DB)
// uses header/footer you uploaded at /mnt/data/header.php and /mnt/data/footer.php
include('templates/header.php');

// DB connection (adjust credentials if needed)
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    // We'll still render the page; JS will show fallback content.
    $dbError = $conn->connect_error;
    $quizzes = [];
    $totalStudents = 0;
    $totalSubmitted = 0;
} else {
    $dbError = null;
    // set proper charset to avoid surprises with utf8 data
    $conn->set_charset('utf8mb4');

    // Fetch quizzes along with item count and earliest deadline (if any)
    // Assumes quizzes table has `id`, `title`, `quiz_code`, `created_at`
    // and quiz_items has `quiz_id` and `deadline`.
    $quizzes = [];
    $sql = "
      SELECT
        q.id,
        q.title,
        q.quiz_code AS code,
        q.created_at,
        COALESCE(MIN(qi.deadline), '') AS deadline,
        COUNT(qi.id) AS num_items
      FROM quizzes q
      LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
      GROUP BY q.id
      ORDER BY q.id DESC
    ";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) $quizzes[] = $r;
        $res->free();
    } else {
        error_log('Quizzes query failed: ' . $conn->error);
    }

    // fetch submission summary: total students & total submitted (global)
    $totalStudents = 0;
    $totalSubmitted = 0;

    // try students table
    $r = $conn->query("SELECT COUNT(*) AS c FROM students");
    if ($r) {
        $row = $r->fetch_assoc();
        $totalStudents = intval($row['c']);
        $r->free();
    } else {
        error_log('Students count query failed: ' . $conn->error);
    }

    // submissions count (distinct student+quiz submissions)
    $r = $conn->query("SELECT COUNT(*) AS c FROM submissions");
    if ($r) {
        $row = $r->fetch_assoc();
        $totalSubmitted = intval($row['c']);
        $r->free();
    } else {
        error_log('Submissions count query failed: ' . $conn->error);
    }

    // close connection (we're done with DB reads on this page)
    $conn->close();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Quizzes</title>
  <link rel="stylesheet" href="css/admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* small helpers so the frame-cards look reasonable if your css isn't loaded */
    .frame-card{border-radius:8px; padding:12px; margin-bottom:10px; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,0.04); display:flex; align-items:center; justify-content:space-between}
    .quiz-title{font-weight:700}
    .quiz-dead{color:#666; font-size:13px}
    .left-create{display:inline-block; padding:8px 12px; background:#0d6efd; color:#fff; border-radius:8px; text-decoration:none}
    .edit-circle{cursor:pointer; padding:8px}
    .meta-small { color:#777; font-size:12px; margin-top:6px; }
  </style>
</head>
<body>
<div class="page-wrap">
  <div class="layout container" style="display:grid; grid-template-columns:200px 1fr 320px; gap:18px; align-items:start">

    <!-- LEFT: Create Button -->
    <div class="left-col">
      <a class="left-create" href="quizmaker.php">CREATE QUIZ</a>
    </div>

    <!-- MIDDLE: Quizzes list -->
    <div>
      <div class="quiz-list">
        <div id="quizzesContainer">
          <?php if ($dbError): ?>
            <div class="frame-card">DB error: <?php echo htmlspecialchars($dbError); ?> — falling back to demo list</div>
            <div class="frame-card"><div class="quiz-title">EXAM/QUIZ NAME:</div><div class="quiz-dead">DEADLINE: —</div></div>
            <div class="frame-card"><div class="quiz-title">EXAM/QUIZ NAME:</div><div class="quiz-dead">DEADLINE: —</div></div>
          <?php else: ?>
            <?php if (count($quizzes) === 0): ?>
              <div class="frame-card">No quizzes found. Click CREATE QUIZ to add one.</div>
            <?php else: ?>
              <?php foreach ($quizzes as $q): ?>
                <div class="frame-card" data-id="<?php echo (int)$q['id']; ?>">
                  <div class="inner" style="flex:1; display:flex; gap:12px; align-items:center;">
                    <div style="width:48px; height:48px; border-radius:6px; background:#eef6ff; display:flex; align-items:center; justify-content:center; font-weight:800; color:#0b5ed7;"><?php echo htmlspecialchars($q['code'] ?: 'Q'); ?></div>
                    <div style="flex:1">
                      <div class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></div>
                      <div class="quiz-dead">DEADLINE: <?php echo $q['deadline'] ? htmlspecialchars($q['deadline']) : '—'; ?></div>
                      <div class="meta-small">Items: <?php echo (int)$q['num_items']; ?> • Created: <?php echo htmlspecialchars($q['created_at']); ?></div>
                    </div>
                  </div>

                  <div style="display:flex; gap:8px; align-items:center; margin-left:12px;">
                    <a class="btn-flat" href="Exam.php?id=<?php echo (int)$q['id']; ?>">Open</a>
                    <a class="edit-circle" href="quizmaker.php?id=<?php echo (int)$q['id']; ?>" title="Edit">
                        <i class="material-icons">edit</i>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Stats + Edit Students -->
    <div>
      <div style="display:flex; justify-content:flex-end; margin-bottom:18px;">
        <a href="Students.php" class="edit-students-btn">EDIT STUDENTS</a>
      </div>

      <div class="stats-box" style="padding:12px; background:#fff; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.04)">
        <div style="width:100%; height:140px; display:flex; justify-content:center; align-items:center;">
          <canvas id="quizPieChart" style="max-width:140px; max-height:140px;"></canvas>
        </div>

        <div style="margin-top: 18px;">
          <h5 style="font-weight:700;">STUDENTS WHO<br>SUBMITTED</h5>
        </div>

        <div style="writing-mode: vertical-rl; transform: rotate(180deg); margin-left: auto; margin-top: 12px; font-weight:800;">
          OTHER STATS
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Materialize & app JS (keep minimal) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
(function(){
  // Pull server-provided submission counts (inlined for speed). PHP will echo numbers.
  const totalStudents = <?php echo isset($totalStudents) ? (int)$totalStudents : 30; ?>;
  const totalSubmitted = <?php echo isset($totalSubmitted) ? (int)$totalSubmitted : 12; ?>;

  // Build pie
  const ctx = document.getElementById('quizPieChart').getContext('2d');
  const notSubmitted = Math.max(0, totalStudents - totalSubmitted);
  const pie = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Submitted','Not submitted'],
      datasets: [{ data: [totalSubmitted, notSubmitted] }]
    },
    options: { plugins:{ legend:{ display:false } } }
  });

  // enhance cards: allow clicking card to open exam page
  document.querySelectorAll('#quizzesContainer .frame-card').forEach(card => {
    const openBtn = card.querySelector('a[href^="Exam.php"]');
    if(openBtn) {
      // keep link as-is
      return;
    }
    // If card has data-id, make the whole card clickable
    const id = card.dataset.id;
    if(id){
      card.style.cursor = 'pointer';
      card.addEventListener('click', (e) => {
        // avoid clicks on the edit button
        if(e.target.closest('.edit-circle')) return;
        window.location.href = 'Exam.php?id=' + id;
      });
    }
  });

})();
</script>

<?php include('templates/footer.php'); ?>
</body>
</html>
