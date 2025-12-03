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

        $stmtQuiz = $mysqli->prepare("
            UPDATE quizzes
            SET title = ?, `from` = ?, `to` = ?, quiz_code = ?,
                duration = ?, num_questions = ?, input_type = ?
            WHERE id = ?
        ");
        if (!$stmtQuiz) {
            throw new Exception('Prepare failed for quizzes UPDATE: ' . $mysqli->error);
        }

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

        $stmtQuiz->bind_param(
            'ssssii' . 'ss',
            $title,
            $from,
            $to,
            $code,
            $duration,
            $num_questions,
            $input_type,
            $createdBy
        );

        if (!$stmtQuiz->execute()) {
            throw new Exception('Insert quiz failed: ' . $stmtQuiz->error);
        }

        $quiz_id = $stmtQuiz->insert_id;
        $stmtQuiz->close();
    }

    // ------------------
    // Ensure quiz_items has legs_json column (TEXT) to store multi-city legs
    // ------------------
    $resCol = $mysqli->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
    if ($resCol === false) {
        throw new Exception('Failed to check quiz_items columns: ' . $mysqli->error);
    }
    if ($resCol->num_rows === 0) {
        // Try to add the column (safe if user has permission)
        if (!$mysqli->query("ALTER TABLE `quiz_items` ADD COLUMN `legs_json` TEXT NULL")) {
            // If ALTER fails, do not abort — we'll continue but legs won't be persisted in the column.
            // Still throw an Exception? For now, log but continue.
            //throw new Exception('Failed to add legs_json column: ' . $mysqli->error);
            error_log('save_quiz.php: unable to add legs_json column: ' . $mysqli->error);
        }
    }

    // ------------------
    // INSERT QUIZ ITEMS (for both create + update)
    // ------------------
    if (!empty($items)) {

        // Prepare insert - include legs_json (some DBs may have column if ALTER succeeded)
        // We'll include legs_json in the column list; if the column doesn't exist this will fail,
        // but we attempted to create it above. If it still fails, fallback to inserting without legs_json.
        $insertWithLegsSql = "
            INSERT INTO quiz_items
            (quiz_id, item_index, origin_iata, destination_iata,
             adults, children, infants, flight_type,
             departure_date, return_date,
             flight_number, seats, travel_class, legs_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmtItem = $mysqli->prepare($insertWithLegsSql);
        if (!$stmtItem) {
            // Fallback: try insert without legs_json
            $insertFallbackSql = "
                INSERT INTO quiz_items
                (quiz_id, item_index, origin_iata, destination_iata,
                 adults, children, infants, flight_type,
                 departure_date, return_date,
                 flight_number, seats, travel_class)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmtItem = $mysqli->prepare($insertFallbackSql);
            if (!$stmtItem) {
                throw new Exception('Prepare failed for quiz_items (both with-legs and fallback): ' . $mysqli->error);
            }
            $useLegsColumn = false;
        } else {
            $useLegsColumn = true;
        }

        $index = 0;
        foreach ($items as $i => $item) {
            $index++;

            $booking = $item['booking'] ?? [];

            // Normalize legs: if booking contains legs array, use it. Otherwise create single-leg for compatibility.
            $legs = [];
            if (isset($booking['legs']) && is_array($booking['legs']) && count($booking['legs']) > 0) {
                foreach ($booking['legs'] as $lg) {
                    // Ensure each leg has origin/destination/date keys (normalize)
                    $legs[] = [
                        'origin' => isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '',
                        'destination' => isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '',
                        'date' => isset($lg['date']) ? $lg['date'] : null
                    ];
                }
            } else {
                // fallback to origin/destination at top-level / single leg
                $lgOrigin = strtoupper(trim($booking['origin'] ?? $item['iata'] ?? ''));
                $lgDest   = strtoupper(trim($booking['destination'] ?? $item['city'] ?? ''));
                $lgDate   = $booking['departure'] ?? null;
                $legs[] = ['origin'=>$lgOrigin, 'destination'=>$lgDest, 'date'=>$lgDate];
            }

            // Top-level origin/destination/dates for compatibility (first/last leg)
            $origin = $legs[0]['origin'] ?? '';
            $dest   = $legs[count($legs)-1]['destination'] ?? '';

            // Fallback from root object if missing
            if ($origin === '') $origin = strtoupper(trim($item['iata'] ?? ''));
            if ($dest === '')   $dest   = strtoupper(trim($item['city'] ?? ''));

            $adults   = (int)($booking['adults'] ?? 0);
            $children = (int)($booking['children'] ?? 0);
            $infants  = (int)($booking['infants'] ?? 0);
            $type     = strtoupper(trim($booking['flight_type'] ?? 'ONE-WAY'));

            // derive departure and return
            $depart   = $legs[0]['date'] ?? ($booking['departure'] ?? null);
            $return   = $booking['return'] ?? null;

            $flightNo = trim($booking['flight_number'] ?? '');
            $seats    = strtoupper(trim($booking['seats'] ?? ''));
            $class    = strtoupper(trim($booking['travel_class'] ?? ''));

            // legs_json string (or null)
            $legs_json = null;
            if ($useLegsColumn) {
                $legs_json = json_encode($legs, JSON_UNESCAPED_UNICODE);
                if ($legs_json === false) {
                    $legs_json = null; // encoding failed; store null
                }
            }

            if ($useLegsColumn) {
                // bind with legs_json
                // types: quiz_id(i), item_index(i), origin(s), destination(s),
                // adults(i), children(i), infants(i), flight_type(s),
                // departure(s), return(s), flight_number(s), seats(s), travel_class(s), legs_json(s)
                $types = 'iissiiisssssss';
                // Note: bind_param requires variables, not expressions
                $quiz_id_var = $quiz_id;
                $item_index_var = $index;
                $origin_var = $origin;
                $dest_var = $dest;
                $adults_var = $adults;
                $children_var = $children;
                $infants_var = $infants;
                $type_var = $type;
                $depart_var = $depart;
                $return_var = $return;
                $flightNo_var = $flightNo;
                $seats_var = $seats;
                $class_var = $class;
                $legs_json_var = $legs_json;

                if (!$stmtItem->bind_param(
                    $types,
                    $quiz_id_var,
                    $item_index_var,
                    $origin_var,
                    $dest_var,
                    $adults_var,
                    $children_var,
                    $infants_var,
                    $type_var,
                    $depart_var,
                    $return_var,
                    $flightNo_var,
                    $seats_var,
                    $class_var,
                    $legs_json_var
                )) {
                    throw new Exception('Bind failed for quiz_items (with legs): ' . $stmtItem->error);
                }
            } else {
                // fallback bind (no legs_json)
                $types = 'iissiiissssss';
                $quiz_id_var = $quiz_id;
                $item_index_var = $index;
                $origin_var = $origin;
                $dest_var = $dest;
                $adults_var = $adults;
                $children_var = $children;
                $infants_var = $infants;
                $type_var = $type;
                $depart_var = $depart;
                $return_var = $return;
                $flightNo_var = $flightNo;
                $seats_var = $seats;
                $class_var = $class;

                if (!$stmtItem->bind_param(
                    $types,
                    $quiz_id_var,
                    $item_index_var,
                    $origin_var,
                    $dest_var,
                    $adults_var,
                    $children_var,
                    $infants_var,
                    $type_var,
                    $depart_var,
                    $return_var,
                    $flightNo_var,
                    $seats_var,
                    $class_var
                )) {
                    throw new Exception('Bind failed for quiz_items (fallback): ' . $stmtItem->error);
                }
            }

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
