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
$title = trim($data['title'] ?? 'Untitled Quiz');
$from  = trim($data['from'] ?? '');
$to    = trim($data['to'] ?? '');
$code  = trim($data['code'] ?? '');
$duration = (int)($data['duration'] ?? 0);
$num_questions = (int)($data['num_questions'] ?? 0);
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

if ($code === '') {
    $code = 'QZ-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

// ------------------
// DB TRANSACTION
// ------------------
$mysqli->begin_transaction();

try {

    // ------------------
    // INSERT QUIZ
    // ------------------
    $stmtQuiz = $mysqli->prepare("
        INSERT INTO quizzes
        (title, `from`, `to`, quiz_code, duration, num_questions, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmtQuiz) {
        throw new Exception('Prepare failed for quizzes: ' . $mysqli->error);
    }

    $stmtQuiz->bind_param(
        'ssssiis',
        $title,
        $from,
        $to,
        $code,
        $duration,
        $num_questions,
        $createdBy
    );

    if (!$stmtQuiz->execute()) {
        throw new Exception('Insert quiz failed: ' . $stmtQuiz->error);
    }

    $quiz_id = $stmtQuiz->insert_id;
    $stmtQuiz->close();


    // ------------------
    // INSERT QUIZ ITEMS
    // ------------------
    if (!empty($items)) {

        $stmtItem = $mysqli->prepare("
            INSERT INTO quiz_items
            (quiz_id, item_index, origin_iata, destination_iata,
             adults, children, infants, flight_type,
             departure_date, return_date,
             flight_number, seats, travel_class)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmtItem) {
            throw new Exception('Prepare failed for quiz_items: ' . $mysqli->error);
        }

        $index = 0;
        foreach ($items as $i => $item) {
            $index++;

            $booking = $item['booking'] ?? [];

            $origin = strtoupper(trim($booking['origin'] ?? ''));
            $dest   = strtoupper(trim($booking['destination'] ?? ''));

            // Fallback from root object if missing
            if ($origin === '') $origin = strtoupper(trim($item['iata'] ?? ''));
            if ($dest === '')   $dest   = strtoupper(trim($item['city'] ?? ''));

            $adults   = (int)($booking['adults'] ?? 0);
            $children = (int)($booking['children'] ?? 0);
            $infants  = (int)($booking['infants'] ?? 0);
            $type     = trim($booking['flight_type'] ?? 'oneway');
            $depart   = $booking['departure'] ?? null;
            $return   = $booking['return'] ?? null;
            $flightNo = trim($booking['flight_number'] ?? '');
            $seats    = trim($booking['seats'] ?? '');
            $class    = trim($booking['travel_class'] ?? '');

            $stmtItem->bind_param(
                'iissiiissssss',
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
                $class
            );

            if (!$stmtItem->execute()) {
                throw new Exception('Insert quiz_items failed: ' . $stmtItem->error);
            }
        }

        $stmtItem->close();
    }

    // ------------------
    // COMMIT
    // ------------------
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'id' => $quiz_id
    ]);
    exit;

} catch (Exception $e) {

    // ------------------
    // ROLLBACK ON ERROR
    // ------------------
    $mysqli->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
