<?php
// take_quiz.php
session_start();
require_once 'db.php';

$quiz_id = intval($_GET['id'] ?? 0);
if (!$quiz_id) {
    die('Quiz ID required.');
}

// check quiz & deadline
$stmt = $mysqli->prepare("SELECT id,title,description,deadline FROM quizzes WHERE id=?");
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quiz) die('Quiz not found.');

$deadline = $quiz['deadline'];
$now = date('Y-m-d H:i:s');
$expired = ($deadline && $deadline < $now);

$questions = [];
$stmt = $mysqli->prepare("SELECT id,prompt,question_type,choices,answer,points FROM questions WHERE quiz_id=? ORDER BY id");
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $questions[] = $r;
$stmt->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($quiz['title']); ?></title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper container">
    <a href="index.php" class="brand-logo">Quiz Maker</a>
  </div>
</nav>

<div class="container" style="margin-top:24px;">
  <h5><?php echo htmlspecialchars($quiz['title']); ?></h5>
  <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
  <?php if ($expired): ?>
    <div class="card-panel red lighten-4">This quiz has expired (deadline passed).</div>
  <?php endif; ?>

  <form method="post" action="submit_quiz.php">
    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
    <div class="input-field">
      <input id="student_name" name="student_name" required>
      <label for="student_name">Your name (optional)</label>
    </div>

    <?php foreach ($questions as $i => $q): ?>
      <div class="card" style="margin-bottom:12px;">
        <div class="card-content">
          <span class="card-title">Q<?php echo $i+1; ?> (<?php echo intval($q['points']); ?>pt)</span>
          <p><?php echo nl2br(htmlspecialchars($q['prompt'])); ?></p>
          <div style="margin-top:8px;">
            <?php if ($q['question_type'] === 'text'): ?>
              <div class="input-field">
                <input type="text" name="answers[<?php echo $q['id']; ?>]" autocomplete="off">
              </div>
            <?php else: 
              $choices = json_decode($q['choices'], true) ?: []; ?>
              <?php foreach ($choices as $c): ?>
                <p>
                  <label>
                    <input name="answers[<?php echo $q['id']; ?>]" type="radio" value="<?php echo htmlspecialchars($c); ?>"/>
                    <span><?php echo htmlspecialchars($c); ?></span>
                  </label>
                </p>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <button class="btn" <?php if ($expired) echo 'disabled'; ?>>Submit Quiz</button>
  </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
