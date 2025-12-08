<?php
// load_quiz.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ------------------
// DB CONFIG
// ------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

// ------------------
// CONNECT DB
// ------------------
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connect failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// ------------------
// READ ID (GET or POST) – can be public_id OR numeric id
// ------------------
$idRaw = null;

if (isset($_GET['id'])) {
    $idRaw = (string)$_GET['id'];
} else {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (is_array($body) && isset($body['id'])) {
        $idRaw = (string)$body['id'];
    }
}

$idRaw = $idRaw !== null ? trim($idRaw) : '';
if ($idRaw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz id']);
    exit;
}

// sanitize for public_id style
$publicIdCandidate = preg_replace('/[^a-zA-Z0-9]/', '', $idRaw);
// numeric fallback if it's all digits
$numericId = ctype_digit($publicIdCandidate) ? (int)$publicIdCandidate : 0;

try {
    // ------------------
    // Detect if quizzes has 'section' column
    // ------------------
    $hasSectionCol = false;
    $checkSection = $mysqli->query("SHOW COLUMNS FROM `quizzes` LIKE 'section'");
    if ($checkSection && $checkSection->num_rows > 0) {
        $hasSectionCol = true;
    }
    if ($checkSection) $checkSection->free();

    // ------------------
    // Try 1: lookup by public_id
    // ------------------
    $quiz = null;

    if ($publicIdCandidate !== '') {
        if ($hasSectionCol) {
            $stmt = $mysqli->prepare("
                SELECT 
                    id,            -- internal numeric id
                    public_id,
                    title, section, `from`, `to`, quiz_code, duration, num_questions,
                    input_type, created_by, created_at
                FROM quizzes
                WHERE public_id = ?
                LIMIT 1
            ");
        } else {
            $stmt = $mysqli->prepare("
                SELECT 
                    id,
                    public_id,
                    title, `from`, `to`, quiz_code, duration, num_questions,
                    input_type, created_by, created_at
                FROM quizzes
                WHERE public_id = ?
                LIMIT 1
            ");
        }
        if (!$stmt) throw new Exception('Prepare failed (public_id): ' . $mysqli->error);
        $stmt->bind_param('s', $publicIdCandidate);
        if (!$stmt->execute()) throw new Exception('Execute failed (public_id): ' . $stmt->error);
        $res  = $stmt->get_result();
        $quiz = $res->fetch_assoc();
        $stmt->close();
    }

    // ------------------
    // Try 2: if not found by public_id and we have numeric, lookup by id
    // ------------------
    if (!$quiz && $numericId > 0) {
        if ($hasSectionCol) {
            $stmt = $mysqli->prepare("
                SELECT 
                    id,
                    public_id,
                    title, section, `from`, `to`, quiz_code, duration, num_questions,
                    input_type, created_by, created_at
                FROM quizzes
                WHERE id = ?
                LIMIT 1
            ");
        } else {
            $stmt = $mysqli->prepare("
                SELECT 
                    id,
                    public_id,
                    title, `from`, `to`, quiz_code, duration, num_questions,
                    input_type, created_by, created_at
                FROM quizzes
                WHERE id = ?
                LIMIT 1
            ");
        }
        if (!$stmt) throw new Exception('Prepare failed (id): ' . $mysqli->error);
        $stmt->bind_param('i', $numericId);
        if (!$stmt->execute()) throw new Exception('Execute failed (id): ' . $stmt->error);
        $res  = $stmt->get_result();
        $quiz = $res->fetch_assoc();
        $stmt->close();
    }

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }

    $quizId   = (int)$quiz['id'];             // internal numeric id
    $publicId = $quiz['public_id'] ?? null;   // may be null for old rows

    // ------------------
    // Check if quiz_items.legs_json and assistance_json exist
    // ------------------
    $hasLegsJson = false;
    $hasAssistJson = false;

    $colCheck = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasLegsJson = true;
    }
    if ($colCheck) $colCheck->free();

    $colCheckA = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'assistance_json'");
    if ($colCheckA && $colCheckA->num_rows > 0) {
        $hasAssistJson = true;
    }
    if ($colCheckA) $colCheckA->free();

    // build dynamic column expressions
    $legsExpr    = $hasLegsJson   ? 'legs_json'           : 'NULL AS legs_json';
    $assistExpr  = $hasAssistJson ? 'assistance_json'     : 'NULL AS assistance_json';

    $sqlItems = "
        SELECT id, quiz_id, item_index, origin_iata, destination_iata,
               adults, children, infants, flight_type,
               departure_date, return_date,
               flight_number, seats, travel_class,
               $legsExpr,
               $assistExpr
        FROM quiz_items
        WHERE quiz_id = ?
        ORDER BY item_index ASC
    ";

    $stmt2 = $mysqli->prepare($sqlItems);
    if (!$stmt2) throw new Exception('Prepare items failed: ' . $mysqli->error);
    $stmt2->bind_param('i', $quizId);
    if (!$stmt2->execute()) throw new Exception('Execute items failed: ' . $stmt2->error);
    $res2 = $stmt2->get_result();

    $items = [];
    while ($row = $res2->fetch_assoc()) {
        $item = [
            'id'         => isset($row['id']) ? (int)$row['id'] : null,
            'item_index' => isset($row['item_index']) ? (int)$row['item_index'] : null,
            'iata'       => isset($row['origin_iata']) ? strtoupper(trim($row['origin_iata'])) : '',
            'city'       => isset($row['destination_iata']) ? strtoupper(trim($row['destination_iata'])) : '',
            'booking'    => [
                'adults'        => isset($row['adults']) ? (int)$row['adults'] : 0,
                'children'      => isset($row['children']) ? (int)$row['children'] : 0,
                'infants'       => isset($row['infants']) ? (int)$row['infants'] : 0,
                'flight_type'   => isset($row['flight_type']) ? strtoupper(trim($row['flight_type'])) : 'ONE-WAY',
                'origin'        => isset($row['origin_iata']) ? strtoupper(trim($row['origin_iata'])) : '',
                'destination'   => isset($row['destination_iata']) ? strtoupper(trim($row['destination_iata'])) : '',
                'departure'     => isset($row['departure_date']) ? $row['departure_date'] : null,
                'return'        => isset($row['return_date']) ? $row['return_date'] : null,
                'flight_number' => isset($row['flight_number']) ? $row['flight_number'] : '',
                'seats'         => isset($row['seats']) ? strtoupper(trim($row['seats'])) : '',
                'travel_class'  => isset($row['travel_class']) ? strtolower(trim($row['travel_class'])) : ''
            ]
        ];

        // -------- legs_json decode --------
        $legs_json_raw = $row['legs_json'] ?? null;
        $legs_parsed   = null;

        if ($legs_json_raw !== null && $legs_json_raw !== '') {
            $decoded = json_decode($legs_json_raw, true);
            if (is_array($decoded)) {
                $legs_parsed = [];
                foreach ($decoded as $lg) {
                    $legs_parsed[] = [
                        'origin'      => isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '',
                        'destination' => isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '',
                        'date'        => isset($lg['date']) ? $lg['date'] : null
                    ];
                }
            }
        }

        if ($legs_parsed === null) {
            $legs_parsed = [[
                'origin'      => $item['booking']['origin'] ?? '',
                'destination' => $item['booking']['destination'] ?? '',
                'date'        => $item['booking']['departure'] ?? null
            ]];
        }

        $item['booking']['legs'] = $legs_parsed;

        // -------- assistance_json decode --------
        $assist_parsed = [];
        $assist_raw = $row['assistance_json'] ?? null;
        if ($assist_raw !== null && $assist_raw !== '') {
            $decodedA = json_decode($assist_raw, true);
            if (is_array($decodedA)) {
                foreach ($decodedA as $a) {
                    if (!is_array($a)) continue;
                    $pNum = isset($a['passenger']) ? (int)$a['passenger'] : 0;
                    $tVal = isset($a['type']) ? strtoupper(trim($a['type'])) : '';
                    if ($pNum > 0 && $tVal !== '') {
                        $assist_parsed[] = [
                            'passenger' => $pNum,
                            'type'      => $tVal
                        ];
                    }
                }
            }
        }
        $item['booking']['assistance'] = $assist_parsed;

        $items[] = $item;
    }

    $stmt2->close();

    // prefer 'section' if column exists, else legacy 'from'
    $sectionVal = null;
    if ($hasSectionCol && array_key_exists('section', $quiz)) {
        $sectionVal = $quiz['section'];
    }

    $quizPayload = [
        'success' => true,
        'quiz'    => [
            'id'            => $quizId,
            'public_id'     => $publicId, // may be null for old ones
            'title'         => $quiz['title'],
            'section'       => $sectionVal,
            'from'          => $quiz['from'] ?? null,
            'to'            => $quiz['to'],
            'code'          => $quiz['quiz_code'],
            'duration'      => (int)$quiz['duration'],
            'num_questions' => (int)$quiz['num_questions'],
            'input_type'    => $quiz['input_type'] ?? 'code-airport',
            'created_by'    => (int)$quiz['created_by'],
            'created_at'    => $quiz['created_at'],
            'items'         => $items
        ]
    ];

    echo json_encode($quizPayload, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
