<?php
session_start();
header('Content-Type: application/json');

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
    echo json_encode([
        'success' => false,
        'error' => 'DB connection failed: ' . $mysqli->connect_error
    ]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// ------------------
// CREATOR (SESSION)
// ------------------
$createdBy = $_SESSION['acc_id'] ?? null;
if (!$createdBy) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in (acc_id missing)'
    ]);
    exit;
}

// ------------------
// READ INPUT JSON
// ------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON received'
    ]);
    exit;
}

// ------------------
// EXTRACT QUIZ DATA
// ------------------
$quiz_id_in = isset($data['id']) ? (int)$data['id'] : 0;

$title       = trim($data['title'] ?? 'Untitled Quiz');
$from        = trim($data['from'] ?? '');
$to          = trim($data['to'] ?? '');
$code        = trim($data['code'] ?? '');
$duration    = (int)($data['duration'] ?? 0);
$num_questions = (int)($data['num_questions'] ?? 0);
$items       = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$input_type  = trim($data['input_type'] ?? 'code-airport');

if ($code === '') {
    $code = 'QZ-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

// ------------------
// DB TRANSACTION
// ------------------
$mysqli->begin_transaction();

try {

    $isUpdate = $quiz_id_in > 0;

    if ($isUpdate) {
        // UPDATE QUIZ
        $stmtCheck = $mysqli->prepare("SELECT id FROM quizzes WHERE id = ?");
        $stmtCheck->bind_param('i', $quiz_id_in);
        $stmtCheck->execute();
        $res = $stmtCheck->get_result();
        $row = $res->fetch_assoc();
        $stmtCheck->close();

        if (!$row) {
            throw new Exception('Quiz not found for update');
        }

        $stmtQuiz = $mysqli->prepare("
            UPDATE quizzes
            SET title = ?, `from` = ?, `to` = ?, quiz_code = ?,
                duration = ?, num_questions = ?, input_type = ?
            WHERE id = ?
        ");

        $stmtQuiz->bind_param(
            'ssssiisi',
            $title,
            $from,
            $to,
            $code,
            $duration,
            $num_questions,
            $input_type,
            $quiz_id_in
        );

        $stmtQuiz->execute();
        $stmtQuiz->close();

        $quiz_id = $quiz_id_in;

        // Delete old items
        $stmtDel = $mysqli->prepare("DELETE FROM quiz_items WHERE quiz_id = ?");
        $stmtDel->bind_param('i', $quiz_id_in);
        $stmtDel->execute();
        $stmtDel->close();

    } else {

        // INSERT NEW QUIZ
        $public_id = bin2hex(random_bytes(8));

        $stmtQuiz = $mysqli->prepare("
            INSERT INTO quizzes
            (public_id, title, `from`, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmtQuiz = $mysqli->prepare("
            INSERT INTO quizzes
            (public_id, title, `from`, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmtQuiz->bind_param(
            'sssssiisi',
            $public_id,
            $title,
            $from,
            $to,
            $code,
            $duration,
            $num_questions,
            $input_type,
            $createdBy
        );

        $stmtQuiz->execute();
        $quiz_id = $stmtQuiz->insert_id;
        $stmtQuiz->close();
    }

    // Ensure legs_json exists
    $resCol = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
    if ($resCol->num_rows === 0) {
        $mysqli->query("ALTER TABLE quiz_items ADD COLUMN legs_json TEXT NULL");
    }

    // INSERT ITEMS
    if (!empty($items)) {

        $insertSql = "
            INSERT INTO quiz_items
            (public_id, quiz_id, item_index, origin_iata, destination_iata,
             adults, children, infants, flight_type,
             departure_date, return_date,
             flight_number, seats, travel_class, legs_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmtItem = $mysqli->prepare($insertSql);

        $index = 0;
        foreach ($items as $item) {
            $index++;

            // Generate unique public ID per item
            $item_public_id = bin2hex(random_bytes(8));

            $booking = $item['booking'] ?? [];

            // Normalize legs
            $legs = [];
            if (!empty($booking['legs'])) {
                foreach ($booking['legs'] as $lg) {
                    $legs[] = [
                        'origin' => strtoupper(trim($lg['origin'] ?? '')),
                        'destination' => strtoupper(trim($lg['destination'] ?? '')),
                        'date' => $lg['date'] ?? null
                    ];
                }
            } else {
                $legs[] = [
                    'origin' => strtoupper(trim($booking['origin'] ?? $item['iata'] ?? '')),
                    'destination' => strtoupper(trim($booking['destination'] ?? $item['city'] ?? '')),
                    'date' => $booking['departure'] ?? null
                ];
            }

            // Compatible top-level fields
            $origin = $legs[0]['origin'] ?? '';
            $dest   = $legs[count($legs)-1]['destination'] ?? '';

            $adults   = (int)($booking['adults'] ?? 0);
            $children = (int)($booking['children'] ?? 0);
            $infants  = (int)($booking['infants'] ?? 0);
            $type     = strtoupper(trim($booking['flight_type'] ?? 'ONE-WAY'));
            $depart   = $legs[0]['date'] ?? null;
            $return   = $booking['return'] ?? null;

            $flightNo = trim($booking['flight_number'] ?? '');
            $seats    = strtoupper(trim($booking['seats'] ?? ''));
            $class    = strtoupper(trim($booking['travel_class'] ?? ''));

            $legs_json = json_encode($legs, JSON_UNESCAPED_UNICODE);

            // Bind
            $stmtItem->bind_param(
                'siissiiisssssss',
                $item_public_id,
                $quiz_id,
                $index,
                $origin,
                $dest,
                $adults,
                $children,
                $infants,
                $type,
                $depart,
                $return,
                $flightNo,
                $seats,
                $class,
                $legs_json
            );

            if (!$stmtItem->execute()) {
                throw new Exception("Insert quiz_items failed: " . $stmtItem->error);
            }
        }

        $stmtItem->close();
    }

    // Commit
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'public_id' => $public_id ?? null,
        'id' => $quiz_id,
        'mode' => $isUpdate ? 'update' : 'create'
    ]);
    exit;

} catch (Exception $e) {

    $mysqli->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
