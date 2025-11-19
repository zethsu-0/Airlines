<?php
// index.php
session_start();
require_once 'db.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quizzes</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper container">
    <a href="index.php" class="brand-logo">Quiz Maker</a>
    <ul id="nav-mobile" class="right">
      <li><a href="admin.php">Admin</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="margin-top:24px;">
  <h5>Available quizzes</h5>
  <div class="row">
    <?php
    $res = $mysqli->query("SELECT id, title, description, deadline FROM quizzes ORDER BY created_at DESC");
    while ($quiz = $res->fetch_assoc()):
      $deadlineLabel = $quiz['deadline'] ? date('M j, Y, g:i A', strtotime($quiz['deadline'])) : 'No deadline';
    ?>
    <div class="col s12 m6">
      <div class="card">
        <div class="card-content">
          <span class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></span>
          <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
        </div>
        <div class="card-action">
          <span><?php echo $deadlineLabel; ?></span>
          <a class="right" href="take_quiz.php?id=<?php echo $quiz['id']; ?>">Take Quiz</a>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
