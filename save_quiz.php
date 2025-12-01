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
$createdByInt = (int)$createdBy;

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
$quiz_id_in = isset($data['id']) ? (int)$data['id'] : 0;   // 0 = create, >0 = update

$title       = trim($data['title'] ?? 'Untitled Quiz');
$from        = trim($data['from'] ?? '');
$to          = trim($data['to'] ?? '');
$code        = trim($data['code'] ?? '');
$duration    = (int)($data['duration'] ?? 0);
$num_questions = (int)($data['num_questions'] ?? 0);
$items       = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

// NEW: input_type from JS payload ('airport-code' or 'code-airport')
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
        // ------------------
        // UPDATE EXISTING QUIZ
        // ------------------
        // Optional: check quiz exists & belongs to this teacher
        $stmtCheck = $mysqli->prepare("SELECT id, created_by FROM quizzes WHERE id = ?");
        if (!$stmtCheck) {
            throw new Exception('Prepare failed for quiz check: ' . $mysqli->error);
        }
        $stmtCheck->bind_param('i', $quiz_id_in);
        $stmtCheck->execute();
        $res = $stmtCheck->get_result();
        $row = $res->fetch_assoc();
        $stmtCheck->close();

        if (!$row) {
            throw new Exception('Quiz not found for update');
        }

        // If you want to restrict edits to creator only, uncomment this:
        // if ((int)$row['created_by'] !== $createdByInt) {
        //     throw new Exception('You do not have permission to edit this quiz');
        // }

        $stmtQuiz = $mysqli->prepare("
            UPDATE quizzes
            SET title = ?, `from` = ?, `to` = ?, quiz_code = ?,
                duration = ?, num_questions = ?, input_type = ?
            WHERE id = ?
        ");
        if (!$stmtQuiz) {
            throw new Exception('Prepare failed for quizzes UPDATE: ' . $mysqli->error);
        }

        // types: title(s), from(s), to(s), code(s),
        //        duration(i), num_questions(i), input_type(s), id(i)
        $stmtQuiz->bind_param(
            'ssssii' . 'si',
            $title,
            $from,
            $to,
            $code,
            $duration,
            $num_questions,
            $input_type,
            $quiz_id_in
        );

        if (!$stmtQuiz->execute()) {
            throw new Exception('Update quiz failed: ' . $stmtQuiz->error);
        }
        $stmtQuiz->close();

        $quiz_id = $quiz_id_in;

        // Clear old items so we can reinsert fresh ones
        $stmtDel = $mysqli->prepare("DELETE FROM quiz_items WHERE quiz_id = ?");
        if (!$stmtDel) {
            throw new Exception('Prepare failed for quiz_items delete: ' . $mysqli->error);
        }
        $stmtDel->bind_param('i', $quiz_id);
        if (!$stmtDel->execute()) {
            throw new Exception('Delete quiz_items failed: ' . $stmtDel->error);
        }
        $stmtDel->close();

    } else {
        // ------------------
        // INSERT NEW QUIZ
        // ------------------
        $stmtQuiz = $mysqli->prepare("
            INSERT INTO quizzes
            (title, `from`, `to`, quiz_code, duration, num_questions, input_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmtQuiz) {
            throw new Exception('Prepare failed for quizzes INSERT: ' . $mysqli->error);
        }

        // types: title(s), from(s), to(s), code(s),
        //        duration(i), num_questions(i), input_type(s), created_by(i)
        $stmtQuiz->bind_param(
            'ssssii' . 'si',
            $title,
            $from,
            $to,
            $code,
            $duration,
            $num_questions,
            $input_type,
            $createdByInt
        );

        if (!$stmtQuiz->execute()) {
            throw new Exception('Insert quiz failed: ' . $stmtQuiz->error);
        }

        $quiz_id = $stmtQuiz->insert_id;
        $stmtQuiz->close();
    }

    // ------------------
    // INSERT QUIZ ITEMS (for both create + update)
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
            $type     = strtoupper(trim($booking['flight_type'] ?? 'ONE-WAY'));
            $depart   = $booking['departure'] ?? null;
            $return   = $booking['return'] ?? null;
            $flightNo = trim($booking['flight_number'] ?? '');
            $seats    = strtoupper(trim($booking['seats'] ?? ''));
            $class    = strtoupper(trim($booking['travel_class'] ?? ''));

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
        'id'      => $quiz_id,
        'mode'    => $isUpdate ? 'update' : 'create'
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
