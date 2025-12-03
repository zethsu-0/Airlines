<?php
// load_quiz.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ------------------
// DB CONFIG (adjust if needed)
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
// READ ID (GET or POST)
// ------------------
$id = null;
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
} else {
    // allow JSON body with { id: ... } for POST-based calls
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (is_array($body) && isset($body['id'])) {
        $id = (int) $body['id'];
    }
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz id']);
    exit;
}

try {
    // ------------------
    // Load quiz row
    // ------------------
    $stmt = $mysqli->prepare("SELECT id, title, `from`, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at FROM quizzes WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
    $res = $stmt->get_result();
    $quiz = $res->fetch_assoc();
    $stmt->close();

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }

    // ------------------
    // Load quiz items (ordered by item_index)
    // Include legs_json column if present
    // ------------------
    // We'll attempt to select legs_json; if column does not exist, the query will still succeed if the DB has it.
    $sqlItems = "
        SELECT id, quiz_id, item_index, origin_iata, destination_iata,
               adults, children, infants, flight_type,
               departure_date, return_date,
               flight_number, seats, travel_class,
               /* legs_json may not exist on older schemas; selecting it if present */
               COALESCE( (SELECT GROUP_CONCAT(NULL) FROM information_schema.COLUMNS WHERE 0), NULL) as legs_json_placeholder
        FROM quiz_items
        WHERE quiz_id = ?
        ORDER BY item_index ASC
    ";

    // The above is a placeholder to detect column presence robustly without failing the query.
    // Instead we will check schema for legs_json and run appropriate SELECT.

    $hasLegsJson = false;
    $colCheck = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasLegsJson = true;
    }

    if ($hasLegsJson) {
        $sqlItems = "
            SELECT id, quiz_id, item_index, origin_iata, destination_iata,
                   adults, children, infants, flight_type,
                   departure_date, return_date,
                   flight_number, seats, travel_class,
                   legs_json
            FROM quiz_items
            WHERE quiz_id = ?
            ORDER BY item_index ASC
        ";
    } else {
        $sqlItems = "
            SELECT id, quiz_id, item_index, origin_iata, destination_iata,
                   adults, children, infants, flight_type,
                   departure_date, return_date,
                   flight_number, seats, travel_class,
                   NULL AS legs_json
            FROM quiz_items
            WHERE quiz_id = ?
            ORDER BY item_index ASC
        ";
    }

    $stmt2 = $mysqli->prepare($sqlItems);
    if (!$stmt2) throw new Exception('Prepare items failed: ' . $mysqli->error);
    $stmt2->bind_param('i', $id);
    if (!$stmt2->execute()) throw new Exception('Execute items failed: ' . $stmt2->error);
    $res2 = $stmt2->get_result();

    $items = [];
    while ($row = $res2->fetch_assoc()) {
        // normalize fields
        $item = [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'item_index' => isset($row['item_index']) ? (int)$row['item_index'] : null,
            'iata' => isset($row['origin_iata']) ? strtoupper(trim($row['origin_iata'])) : '',
            'city' => isset($row['destination_iata']) ? strtoupper(trim($row['destination_iata'])) : '',
            'booking' => [
                'adults' => isset($row['adults']) ? (int)$row['adults'] : 0,
                'children' => isset($row['children']) ? (int)$row['children'] : 0,
                'infants' => isset($row['infants']) ? (int)$row['infants'] : 0,
                'flight_type' => isset($row['flight_type']) ? strtoupper(trim($row['flight_type'])) : 'ONE-WAY',
                'origin' => isset($row['origin_iata']) ? strtoupper(trim($row['origin_iata'])) : '',
                'destination' => isset($row['destination_iata']) ? strtoupper(trim($row['destination_iata'])) : '',
                'departure' => isset($row['departure_date']) ? $row['departure_date'] : null,
                'return' => isset($row['return_date']) ? $row['return_date'] : null,
                'flight_number' => isset($row['flight_number']) ? $row['flight_number'] : '',
                'seats' => isset($row['seats']) ? strtoupper(trim($row['seats'])) : '',
                'travel_class' => isset($row['travel_class']) ? strtolower(trim($row['travel_class'])) : ''
            ]
        ];

        // parse legs_json if present
        $legs_json_raw = $row['legs_json'] ?? null;
        $legs_parsed = null;

        if ($legs_json_raw !== null && $legs_json_raw !== '') {
            // try decode
            $decoded = json_decode($legs_json_raw, true);
            if (is_array($decoded)) {
                $legs_parsed = [];
                foreach ($decoded as $lg) {
                    $legs_parsed[] = [
                        'origin' => isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '',
                        'destination' => isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '',
                        'date' => isset($lg['date']) ? $lg['date'] : null
                    ];
                }
            } else {
                // invalid JSON: ignore and fallback
                $legs_parsed = null;
            }
        }

        if ($legs_parsed === null) {
            // Build single-leg fallback using stored columns
            $legs_parsed = [];
            $legs_parsed[] = [
                'origin' => $item['booking']['origin'] ?? '',
                'destination' => $item['booking']['destination'] ?? '',
                'date' => $item['booking']['departure'] ?? null
            ];
        }

        // attach legs into booking
        $item['booking']['legs'] = $legs_parsed;

        $items[] = $item;
    }

    $stmt2->close();

    // build quiz payload
    $quizPayload = [
        'success' => true,
        'quiz' => [
            'id' => (int)$quiz['id'],
            'title' => $quiz['title'],
            'from' => $quiz['from'],
            'to' => $quiz['to'],
            'code' => $quiz['quiz_code'],
            'duration' => (int)$quiz['duration'],
            'num_questions' => (int)$quiz['num_questions'],
            'input_type' => $quiz['input_type'] ?? 'code-airport',
            'created_by' => (int)$quiz['created_by'],
            'created_at' => $quiz['created_at'],
            'items' => $items
        ]
    ];

    echo json_encode($quizPayload, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
    