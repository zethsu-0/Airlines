<?php
// save_quiz.php
// Expects JSON body like the payload built in admin_quiz_maker.js
// Returns JSON: { success: true, id: <quiz_id> } or { success:false, error: '...' }

header('Content-Type: application/json; charset=utf-8');

// start session only if not active
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Require admin login
if (empty($_SESSION['acc_id']) || empty($_SESSION['acc_role']) || $_SESSION['acc_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated as admin']);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request body']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// basic fields
$title = trim((string)($data['title'] ?? 'Untitled Quiz'));
$code = trim((string)($data['code'] ?? ''));
$duration = isset($data['duration']) ? (int)$data['duration'] : null;
$num_questions = isset($data['num_questions']) ? (int)$data['num_questions'] : 0;
$items = $data['items'] ?? [];
$questions = $data['questions'] ?? [];
$section = trim((string)($data['from'] ?? ''));
$audience = trim((string)($data['to'] ?? ''));

// normalize code - if empty, generate one
if ($code === '') {
    $code = 'QZ-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6));
}

// encode items/questions as JSON for storage
$items_json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
$questions_json = json_encode(array_values($questions), JSON_UNESCAPED_UNICODE);

// DB config (adjust if needed)
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

// Ensure required columns exist (safe minimal attempt). If ALTER fails, we still try to insert.
$checkCols = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
$haveCreatedBy = false;
$haveItems = false;
$haveQuestions = false;
$haveDuration = false;
$cols = [];
if ($checkCols) {
    while ($c = $checkCols->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
    $haveCreatedBy = in_array('created_by', $cols, true);
    $haveItems = in_array('items_json', $cols, true) || in_array('items', $cols, true);
    $haveQuestions = in_array('questions_json', $cols, true) || in_array('questions', $cols, true);
    $haveDuration = in_array('duration_minutes', $cols, true) || in_array('duration', $cols, true);
}

// Try add columns if missing (best-effort)
if (!$haveCreatedBy) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `created_by` VARCHAR(191) DEFAULT NULL");
}
if (!$haveItems) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `items_json` TEXT DEFAULT NULL");
}
if (!$haveQuestions) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `questions_json` TEXT DEFAULT NULL");
}
if (!$haveDuration) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `duration_minutes` INT DEFAULT NULL");
}

// Insert quiz
// Choose column names that are most likely to exist; fallbacks handled below
$colsToInsert = ['title','code','created_by','items_json','questions_json','num_questions','duration_minutes','deadline','section','audience','created_at'];

// Build dynamic SQL that uses only columns present in DB (we'll detect).
$existingColsRes = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
$existingCols = [];
if ($existingColsRes) {
    while ($c = $existingColsRes->fetch_assoc()) $existingCols[] = $c['Field'];
    $existingColsRes->free();
}

// Map desired fields to actual column names present
$map = [];
if (in_array('title', $existingCols, true)) $map['title'] = 'title';
if (in_array('code', $existingCols, true)) $map['code'] = 'code';
if (in_array('created_by', $existingCols, true)) $map['created_by'] = 'created_by';
elseif (in_array('creator', $existingCols, true)) $map['created_by'] = 'creator';
elseif (in_array('author', $existingCols, true)) $map['created_by'] = 'author';

if (in_array('items_json', $existingCols, true)) $map['items_json'] = 'items_json';
elseif (in_array('items', $existingCols, true)) $map['items_json'] = 'items';

if (in_array('questions_json', $existingCols, true)) $map['questions_json'] = 'questions_json';
elseif (in_array('questions', $existingCols, true)) $map['questions_json'] = 'questions';

if (in_array('num_questions', $existingCols, true)) $map['num_questions'] = 'num_questions';
elseif (in_array('num_questions_count', $existingCols, true)) $map['num_questions'] = 'num_questions_count';

if (in_array('duration_minutes', $existingCols, true)) $map['duration_minutes'] = 'duration_minutes';
elseif (in_array('duration', $existingCols, true)) $map['duration_minutes'] = 'duration';

if (in_array('deadline', $existingCols, true)) $map['deadline'] = 'deadline';
if (in_array('section', $existingCols, true)) $map['section'] = 'section';
if (in_array('audience', $existingCols, true)) $map['audience'] = 'audience';
if (in_array('created_at', $existingCols, true)) $map['created_at'] = 'created_at';

// Build final insert list (only mapped columns)
$insertCols = [];
$placeholders = [];
$values = [];

if (isset($map['title'])) { $insertCols[] = $map['title']; $placeholders[] = '?'; $values[] = $title; }
if (isset($map['code'])) { $insertCols[] = $map['code']; $placeholders[] = '?'; $values[] = $code; }
if (isset($map['created_by'])) { $insertCols[] = $map['created_by']; $placeholders[] = '?'; $values[] = $_SESSION['acc_id']; }
if (isset($map['items_json'])) { $insertCols[] = $map['items_json']; $placeholders[] = '?'; $values[] = $items_json; }
if (isset($map['questions_json'])) { $insertCols[] = $map['questions_json']; $placeholders[] = '?'; $values[] = $questions_json; }
if (isset($map['num_questions'])) { $insertCols[] = $map['num_questions']; $placeholders[] = '?'; $values[] = $num_questions; }
if (isset($map['duration_minutes'])) { $insertCols[] = $map['duration_minutes']; $placeholders[] = '?'; $values[] = $duration; }
if (isset($map['deadline'])) { $insertCols[] = $map['deadline']; $placeholders[] = '?'; $values[] = null; } // individual item deadlines stored in items_json
if (isset($map['section'])) { $insertCols[] = $map['section']; $placeholders[] = '?'; $values[] = $section; }
if (isset($map['audience'])) { $insertCols[] = $map['audience']; $placeholders[] = '?'; $values[] = $audience; }

if (empty($insertCols)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No suitable columns to insert into quizzes table.']);
    exit;
}

$sql = 'INSERT INTO `quizzes` (' . implode(',', array_map(function($c){ return "`$c`"; }, $insertCols)) . ') VALUES (' . implode(',', $placeholders) . ')';

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $mysqli->error, 'sql' => $sql]);
    exit;
}

// bind params dynamically
$types = '';
foreach ($values as $v) {
    if (is_int($v) || (is_string($v) && ctype_digit($v))) $types .= 'i';
    else $types .= 's';
}
$stmt->bind_param($types, ...$values);

$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$insertId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'id' => $insertId]);
exit;
