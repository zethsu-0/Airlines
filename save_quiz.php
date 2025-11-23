<?php
// save_quiz.php
header('Content-Type: application/json; charset=utf-8');

// DB config - adjust if your environment differs
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}

// read JSON body
$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request body']);
    exit;
}
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload: ' . json_last_error_msg()]);
    exit;
}

// basic validation & sanitizing helpers
function get_val($arr, $k, $default = null) {
    return isset($arr[$k]) ? $arr[$k] : $default;
}
$title = trim((string)get_val($data, 'title', 'Untitled Quiz'));
$code  = trim((string)get_val($data, 'code', ''));
$from  = trim((string)get_val($data, 'from', ''));
$to    = trim((string)get_val($data, 'to', ''));
$duration = intval(get_val($data, 'duration', 0));
$num_questions = intval(get_val($data, 'num_questions', 0));
$items = is_array(get_val($data, 'items', [])) ? $data['items'] : [];
$questions = is_array(get_val($data, 'questions', [])) ? $data['questions'] : [];

// make code if empty
if ($code === '') {
    $code = 'QZ-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

// create minimal tables if they don't exist (safe to run repeatedly)
$createQuizzes = <<<SQL
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `code` VARCHAR(64) DEFAULT NULL,
  `from_field` VARCHAR(128) DEFAULT NULL,
  `to_field` VARCHAR(128) DEFAULT NULL,
  `duration` INT DEFAULT NULL,
  `num_questions` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$createItems = <<<SQL
CREATE TABLE IF NOT EXISTS `quiz_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT UNSIGNED NOT NULL,
  `iata` CHAR(3) DEFAULT NULL,
  `city` VARCHAR(128) DEFAULT NULL,
  `difficulty` VARCHAR(32) DEFAULT NULL,
  `deadline` DATETIME DEFAULT NULL,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$createQuestions = <<<SQL
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT UNSIGNED NOT NULL,
  `text` TEXT,
  `type` VARCHAR(32) DEFAULT 'text',
  `points` INT DEFAULT 1,
  `expected_answer` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

// run table creation
if (!$mysqli->query($createQuizzes) || !$mysqli->query($createItems) || !$mysqli->query($createQuestions)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed ensuring DB tables exist: ' . $mysqli->error]);
    exit;
}

// Begin transaction
$mysqli->begin_transaction();

try {
    // insert into quizzes
    $stmt = $mysqli->prepare("INSERT INTO quizzes (title, code, from_field, to_field, duration, num_questions) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare failed (quizzes): ' . $mysqli->error);
    $stmt->bind_param('ssssii', $title, $code, $from, $to, $duration, $num_questions);
    if (!$stmt->execute()) throw new Exception('Insert failed (quizzes): ' . $stmt->error);
    $quiz_id = $stmt->insert_id;
    $stmt->close();

    // insert items
    if (!empty($items) && is_array($items)) {
        $stmtItem = $mysqli->prepare("INSERT INTO quiz_items (quiz_id, iata, city, difficulty, deadline) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtItem) throw new Exception('Prepare failed (quiz_items): ' . $mysqli->error);
        foreach ($items as $it) {
            $iata = isset($it['iata']) ? substr(trim((string)$it['iata']), 0, 3) : null;
            $city = isset($it['city']) ? trim((string)$it['city']) : null;
            $difficulty = isset($it['difficulty']) ? trim((string)$it['difficulty']) : null;
            $deadlineRaw = isset($it['deadline']) && $it['deadline'] ? trim((string)$it['deadline']) : null;
            // normalize datetime or set null
            $deadline = null;
            if ($deadlineRaw) {
                // try to convert to MySQL DATETIME
                $d = new DateTime($deadlineRaw);
                if ($d) $deadline = $d->format('Y-m-d H:i:s');
            }
            $stmtItem->bind_param('issss', $quiz_id, $iata, $city, $difficulty, $deadline);
            if (!$stmtItem->execute()) throw new Exception('Insert failed (quiz_items): ' . $stmtItem->error);
        }
        $stmtItem->close();
    }

    // insert questions (if any)
    if (!empty($questions) && is_array($questions)) {
        $stmtQ = $mysqli->prepare("INSERT INTO questions (quiz_id, text, type, points, expected_answer) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtQ) throw new Exception('Prepare failed (questions): ' . $mysqli->error);
        foreach ($questions as $q) {
            $qtext = isset($q['text']) ? trim((string)$q['text']) : null;
            $qtype = isset($q['type']) ? trim((string)$q['type']) : 'text';
            $qpoints = isset($q['points']) ? intval($q['points']) : 1;
            $qexp = isset($q['expected_answer']) && $q['expected_answer'] !== '' ? trim((string)$q['expected_answer']) : null;
            $stmtQ->bind_param('issis', $quiz_id, $qtext, $qtype, $qpoints, $qexp);
            if (!$stmtQ->execute()) throw new Exception('Insert failed (questions): ' . $stmtQ->error);
        }
        $stmtQ->close();
    }

    // commit
    $mysqli->commit();

    echo json_encode(['success' => true, 'id' => $quiz_id]);
    exit;

} catch (Exception $ex) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit;
}
