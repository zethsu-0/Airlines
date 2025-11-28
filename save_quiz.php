<?php
// save_quiz.php (robust version)
// Expects JSON body like admin_quiz_maker.js
// Returns JSON: { success: true, id: <quiz_id> } or { success:false, error: '...' }

header('Content-Type: application/json; charset=utf-8');
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
$duration = isset($data['duration']) && $data['duration'] !== '' ? (int)$data['duration'] : null;
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

// DB config
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

// small helper: quote identifier
function qi($s){ return '`' . str_replace('`','``',$s) . '`'; }

// fetch existing columns for quizzes
$existingCols = [];
$res = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
if ($res) {
    while ($r = $res->fetch_assoc()) $existingCols[] = $r['Field'];
    $res->free();
} else {
    // table missing?
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>'Unable to read `quizzes` table structure: '.$mysqli->error]);
    exit;
}

// decide column names (map logical -> actual)
$map = [];

// title / code
if (in_array('title', $existingCols, true)) $map['title'] = 'title';
if (in_array('code', $existingCols, true)) $map['code'] = 'code';

// created_by / creator / author mapping (we will try to add created_by if none found)
if (in_array('created_by', $existingCols, true)) $map['created_by'] = 'created_by';
elseif (in_array('creator', $existingCols, true)) $map['created_by'] = 'creator';
elseif (in_array('author', $existingCols, true)) $map['created_by'] = 'author';

// items json
if (in_array('items_json', $existingCols, true)) $map['items_json'] = 'items_json';
elseif (in_array('items', $existingCols, true)) $map['items_json'] = 'items';

// questions json
if (in_array('questions_json', $existingCols, true)) $map['questions_json'] = 'questions_json';
elseif (in_array('questions', $existingCols, true)) $map['questions_json'] = 'questions';

// num_questions
if (in_array('num_questions', $existingCols, true)) $map['num_questions'] = 'num_questions';
elseif (in_array('num_questions_count', $existingCols, true)) $map['num_questions'] = 'num_questions_count';

// duration
if (in_array('duration_minutes', $existingCols, true)) $map['duration_minutes'] = 'duration_minutes';
elseif (in_array('duration', $existingCols, true)) $map['duration_minutes'] = 'duration';

// deadline / section / audience / created_at
if (in_array('deadline', $existingCols, true)) $map['deadline'] = 'deadline';
if (in_array('section', $existingCols, true)) $map['section'] = 'section';
if (in_array('audience', $existingCols, true)) $map['audience'] = 'audience';
if (in_array('created_at', $existingCols, true)) $map['created_at'] = 'created_at';

// Best-effort: create useful columns if missing (non-fatal)
/* NOTE: ALTER requires privileges; if your DB user lacks permission these queries will fail.
   We don't abort on ALTER errors; we proceed using whatever columns are present.
*/
if (!isset($map['created_by'])) {
    // try to add created_by
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `created_by` VARCHAR(191) DEFAULT NULL");
    // refresh existingCols & map
    $existingCols = [];
    $r2 = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
    if ($r2) { while($rr=$r2->fetch_assoc()) $existingCols[]=$rr['Field']; $r2->free(); }
    if (in_array('created_by', $existingCols, true)) $map['created_by'] = 'created_by';
}
if (!isset($map['items_json'])) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `items_json` TEXT DEFAULT NULL");
    $existingCols = [];
    $r2 = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
    if ($r2) { while($rr=$r2->fetch_assoc()) $existingCols[]=$rr['Field']; $r2->free(); }
    if (in_array('items_json', $existingCols, true)) $map['items_json'] = 'items_json';
}
if (!isset($map['questions_json'])) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `questions_json` TEXT DEFAULT NULL");
    $existingCols = [];
    $r2 = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
    if ($r2) { while($rr=$r2->fetch_assoc()) $existingCols[]=$rr['Field']; $r2->free(); }
    if (in_array('questions_json', $existingCols, true)) $map['questions_json'] = 'questions_json';
}
if (!isset($map['duration_minutes'])) {
    $mysqli->query("ALTER TABLE `quizzes` ADD COLUMN `duration_minutes` INT DEFAULT NULL");
    $existingCols = [];
    $r2 = $mysqli->query("SHOW COLUMNS FROM `quizzes`");
    if ($r2) { while($rr=$r2->fetch_assoc()) $existingCols[]=$rr['Field']; $r2->free(); }
    if (in_array('duration_minutes', $existingCols, true)) $map['duration_minutes'] = 'duration_minutes';
}

// rebuild insertCols with available map entries (only include when present)
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
if (isset($map['deadline'])) { $insertCols[] = $map['deadline']; $placeholders[] = '?'; $values[] = null; }
if (isset($map['section'])) { $insertCols[] = $map['section']; $placeholders[] = '?'; $values[] = $section; }
if (isset($map['audience'])) { $insertCols[] = $map['audience']; $placeholders[] = '?'; $values[] = $audience; }
if (isset($map['created_at'])) { $insertCols[] = $map['created_at']; $placeholders[] = 'NOW()'; /* use DB NOW() directly */ }

// if created_at used as NOW(), remove it from values/placeholder pairing (we used direct SQL expression)
$useDirectNow = false;
if (in_array('created_at', $insertCols, true)) {
    $useDirectNow = true;
    // remove the last placeholder and value if we appended them as '?' previously (we didn't; we set 'NOW()' placeholder above)
}

// Validate we have at least one insert column
if (empty($insertCols)) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>'No suitable columns to insert into quizzes table.']);
    exit;
}

// Build final SQL - when a column has placeholder 'NOW()' we keep it as-is
$sqlCols = [];
$sqlPlaceholders = [];
foreach ($insertCols as $idx => $c) {
    $sqlCols[] = qi($c);
    $ph = $placeholders[$idx] ?? '?';
    $sqlPlaceholders[] = $ph;
}
$sql = 'INSERT INTO `quizzes` (' . implode(', ', $sqlCols) . ') VALUES (' . implode(', ', $sqlPlaceholders) . ')';

// Prepare statement
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $mysqli->error, 'sql' => $sql]);
    exit;
}

// bind_param: build types string and argument array for only the placeholders that are '?'
$bindTypes = '';
$bindValues = [];
for ($i = 0; $i < count($placeholders); $i++) {
    if ($placeholders[$i] === '?') {
        $v = $values[$i] ?? null;
        // choose type: 'i' for integer, otherwise 's'
        if (is_int($v)) $bindTypes .= 'i';
        elseif (is_null($v)) $bindTypes .= 's';
        elseif (is_string($v) && ctype_digit($v)) $bindTypes .= 'i';
        else $bindTypes .= 's';
        $bindValues[] = $v;
    }
}

// dynamic bind_param requires references
if (count($bindValues) > 0) {
    $refs = [];
    foreach ($bindValues as $k => $v) {
        // ensure variables are references
        $refs[$k] = &$bindValues[$k];
    }
    // prepend types
    array_unshift($refs, $bindTypes);
    // call bind_param with dynamic args
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// execute
$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $stmt->error, 'sql' => $sql]);
    $stmt->close();
    exit;
}

$insertId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'id' => $insertId]);
exit;
