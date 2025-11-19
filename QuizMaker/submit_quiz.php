<?php
// submit_quiz.php (grades using airports table when appropriate)
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$quiz_id = intval($_POST['quiz_id'] ?? 0);
$student_name = trim($_POST['student_name'] ?? 'Guest');
$answers = $_POST['answers'] ?? [];

if (!$quiz_id) {
    die('Missing quiz ID.');
}

// fetch questions for this quiz
$stmt = $mysqli->prepare("SELECT id,answer,question_type,points,prompt,choices FROM questions WHERE quiz_id=?");
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$res = $stmt->get_result();

$score = 0;
$max_score = 0;
$save_responses = [];

function normalize($s) {
    return mb_strtoupper(trim((string)$s), 'UTF-8');
}

while ($q = $res->fetch_assoc()) {
    $qid = $q['id'];
    $correct_answer = $q['answer'];
    $qtype = $q['question_type'];
    $pts = intval($q['points']);
    $max_score += $pts;

    $given_raw = isset($answers[$qid]) ? $answers[$qid] : null;
    $given = $given_raw !== null ? trim($given_raw) : null;
    $save_responses[$qid] = $given;

    $ok = false;

    // 1) Multiple choice: direct match with choice text
    if ($qtype === 'mc') {
        if ($given !== null && $given === $correct_answer) $ok = true;
    } else {
        // For text questions, try to use airports table if the stored answer is an IATA code (3 letters)
        $stored = trim($correct_answer);

        // helper: find airport row by IATA or by name (returns assoc array or null)
        $findAirportByIata = function($code) use ($mysqli) {
            $codeu = mb_strtoupper(trim($code), 'UTF-8');
            $s = $mysqli->prepare("SELECT IATACode, AirportName, City, CountryRegion FROM airports WHERE UPPER(IATACode)=?");
            $s->bind_param('s', $codeu);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            $s->close();
            return $r ?: null;
        };
        $findAirportByName = function($name) use ($mysqli) {
            $like = '%' . $name . '%';
            $s = $mysqli->prepare("SELECT IATACode, AirportName, City, CountryRegion FROM airports WHERE UPPER(AirportName) LIKE UPPER(?) OR UPPER(City) LIKE UPPER(?) LIMIT 1");
            $s->bind_param('ss', $like, $like);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            $s->close();
            return $r ?: null;
        };

        // if stored answer looks like a 3-letter code, try code lookup
        if (preg_match('/^[A-Za-z]{3}$/', $stored)) {
            $airport = $findAirportByIata($stored);
            if ($airport) {
                // acceptable answers:
                // - IATA code
                // - airport name
                // - city
                $given_u = normalize($given ?? '');
                if ($given_u === normalize($airport['IATACode'])
                    || $given_u === normalize($airport['AirportName'])
                    || $given_u === normalize($airport['City'])) {
                    $ok = true;
                } else {
                    // also accept if user typed full airport name partially (contains)
                    if (stripos($airport['AirportName'], $given ?? '') !== false || stripos($airport['City'], $given ?? '') !== false) {
                        $ok = true;
                    }
                }
            } else {
                // no airport found for stored code — fallback to exact compare with stored answer
                if ($given !== null && strcasecmp($given, $stored) === 0) $ok = true;
            }
        } else {
            // stored answer is not obviously a 3-letter code. Try to find an airport by name (loose)
            $airport = $findAirportByName($stored);
            if ($airport) {
                $given_u = normalize($given ?? '');
                if ($given_u === normalize($airport['IATACode'])
                    || $given_u === normalize($airport['AirportName'])
                    || $given_u === normalize($airport['City'])) {
                    $ok = true;
                } else {
                    // allow partial matches to airport name / city
                    if (stripos($airport['AirportName'], $given ?? '') !== false || stripos($airport['City'], $given ?? '') !== false) {
                        $ok = true;
                    }
                }
            } else {
                // fallback to simple case-insensitive equality with stored answer (existing behavior)
                if ($given !== null && strcasecmp($given, $stored) === 0) $ok = true;
            }
        }
    }

    if ($ok) $score += $pts;
}
$res->free();
$stmt->close();

// store attempt (responses JSON)
$sr_json = json_encode($save_responses, JSON_UNESCAPED_UNICODE);

// insert attempt
$stmt = $mysqli->prepare("INSERT INTO attempts (quiz_id,student_name,score,max_score,responses) VALUES (?,?,?,?,?)");
$stmt->bind_param('issis', $quiz_id, $student_name, $score, $max_score, $sr_json);
$stmt->execute();
$stmt->close();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Results</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"/>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper container"><a href="index.php" class="brand-logo">Quiz Maker</a></div>
</nav>
<div class="container" style="margin-top:24px;">
  <h5>Results</h5>
  <p>Thank you, <?php echo htmlspecialchars($student_name ?: 'Guest'); ?>. Your score: <strong><?php echo intval($score); ?></strong> / <?php echo intval($max_score); ?></p>

  <a class="btn" href="index.php">Back to quizzes</a>
</div>
</body>
</html>
