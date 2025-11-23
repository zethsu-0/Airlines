<?php
// student_profile.php
// Student profile page: lists quizzes and whether the current student submitted them.
// Assumes DB schema from previous SQL (quizzes, submissions, students).
// Adjust DB creds if needed.

session_start();

// Include site header/footer (uses uploaded files)
include('/mnt/data/header.php');

// --- Determine current student ID from session (try common keys, fallback to demo) ---
$studentId = null;
if (!empty($_SESSION['student_id'])) {
    $studentId = intval($_SESSION['student_id']);
} elseif (!empty($_SESSION['user_id'])) {
    $studentId = intval($_SESSION['user_id']);
} elseif (!empty($_SESSION['user']['id'])) {
    $studentId = intval($_SESSION['user']['id']);
} elseif (!empty($_SESSION['acc_id'])) {
    $studentId = intval($_SESSION['acc_id']);
}

// fallback for demo (change or require login in production)
if (!$studentId) {
    // In production, you should redirect to login. For convenience we use demo id = 1
    $studentId = 1;
}

// DB connection (change creds as needed)
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('<div style="padding:20px; color:darkred">DB connection failed: ' . htmlspecialchars($conn->connect_error) . '</div>');
}

// --- Fetch quizzes and left-join submissions for this student ---
$sql = "
  SELECT q.id AS quiz_id, q.title, q.iata, q.deadline, q.duration, q.num_questions, q.code,
         s.submitted_at, s.student_id AS submitted_by
  FROM quizzes q
  LEFT JOIN submissions s
    ON s.quiz_id = q.id AND s.student_id = ?
  ORDER BY q.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();

$quizzes = [];
while ($row = $res->fetch_assoc()) {
    $quizzes[] = $row;
}
$stmt->close();

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
  <title>Student Profile — Quizzes</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
  <style>
    body { background: #f6fbff; font-family: Roboto, Arial, sans-serif; padding-bottom:40px; }
    .profile-wrap{ max-width:1100px; margin:28px auto; }
    .card { border-radius:12px; }
    .stat { text-align:center; padding:12px; border-radius:8px; background:#fff; }
    .quiz-row { display:flex; gap:12px; align-items:center; padding:12px; border-radius:8px; background:#fff; margin-bottom:10px; }
    .quiz-iata { font-weight:800; font-size:18px; width:78px; text-align:center; }
    .quiz-title { font-weight:700; }
    .small-note { color:#6b7280; font-size:13px }
    @media(max-width:800px){ .quiz-row{ flex-direction:column; align-items:flex-start } .quiz-iata{ width:100%; text-align:left } }
  </style>
</head>
<body>

<div class="profile-wrap">
  <div class="row">
    <div class="col s12 m8">
      <div class="card">
        <div class="card-content">
          <span class="card-title">Hello, Student</span>
          <p class="small-note">Below are the quizzes available. Click <strong>Take Quiz</strong> to start (if you haven't submitted).</p>

          <div style="margin-top:14px;">
            <?php if (empty($quizzes)): ?>
              <div class="center" style="padding:28px;color:#555">No quizzes available yet.</div>
            <?php else: ?>
              <?php foreach ($quizzes as $q): 
                  $isSubmitted = !empty($q['submitted_at']);
                  $deadlineText = $q['deadline'] ? date('M j, Y \@ H:i', strtotime($q['deadline'])) : 'No deadline';
              ?>
              <div class="quiz-row">
                <div class="quiz-iata">
                  <div><?php echo h(strtoupper($q['iata'] ?: '---')); ?></div>
                  <div class="small-note"><?php echo h($q['code']); ?></div>
                </div>

                <div style="flex:1">
                  <div class="quiz-title"><?php echo h($q['title']); ?></div>
                  <div class="small-note">
                    <?php echo h($q['num_questions']); ?> question<?php echo $q['num_questions'] == 1 ? '' : 's'; ?> • <?php echo h($q['duration']); ?> min • Deadline: <?php echo h($deadlineText); ?>
                  </div>
                </div>

                <div style="min-width:180px; text-align:right">
                  <?php if ($isSubmitted): ?>
                    <div style="color:green; font-weight:700">Submitted</div>
                    <div class="small-note">on <?php echo h(date('M j, Y H:i', strtotime($q['submitted_at']))); ?></div>
                    <div style="margin-top:8px">
                      <a class="btn-flat" href="Exam.php?id=<?php echo urlencode($q['quiz_id']); ?>">View</a>
                    </div>
                  <?php else: ?>
                    <div style="color:#d32f2f; font-weight:700">Not submitted</div>
                    <div class="small-note">&nbsp;</div>
                    <div style="margin-top:8px">
                      <a class="btn" href="Exam.php?id=<?php echo urlencode($q['quiz_id']); ?>">Take Quiz</a>
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
<?php include('/mnt/data/footer.php'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>

<?php
// close DB connection
$conn->close();
?>
