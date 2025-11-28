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
    echo json_encode(['success' => false, 'error' => 'DB connect failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No request body']);
    $mysqli->close();
    exit;
}

$data = json_decode($raw, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    $mysqli->close();
    exit;
}

// Helper to normalize date-like fields (empty -> null)
function norm_date($v) {
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '') return null;
    return $v;
}

try {
    // Begin transaction
    $mysqli->begin_transaction();

    // Extract quiz fields from payload
    $quiz_id = (isset($data['quiz_id']) && intval($data['quiz_id']) > 0) ? intval($data['quiz_id']) : null;
    $title = isset($data['title']) ? $data['title'] : 'Untitled Quiz';
    $section = isset($data['from']) ? $data['from'] : '';    // mapping: payload.from -> quizzes.section
    $audience = isset($data['to']) ? $data['to'] : '';       // payload.to -> quizzes.audience
    $duration = isset($data['duration']) ? intval($data['duration']) : 0;
    $code = isset($data['code']) ? $data['code'] : ( 'QZ-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6)) );

    if ($quiz_id) {
        // Update existing quiz
        $sqlUpdate = "UPDATE quizzes SET title = ?, section = ?, audience = ?, duration = ?, quiz_code = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sqlUpdate);
        if (!$stmt) throw new Exception('Prepare update quiz failed: ' . $mysqli->error);

        if (!$stmt->bind_param('sssisi', $title, $section, $audience, $duration, $code, $quiz_id)) {
            throw new Exception('Bind update params failed: ' . $stmt->error);
        }
        if (!$stmt->execute()) throw new Exception('Execute update quiz failed: ' . $stmt->error);
        $stmt->close();

        // Remove previous items for this quiz (simple sync strategy)
        $del = $mysqli->prepare("DELETE FROM quiz_items WHERE quiz_id = ?");
        if (!$del) throw new Exception('Prepare delete items failed: ' . $mysqli->error);
        if (!$del->bind_param('i', $quiz_id)) throw new Exception('Bind delete items failed: ' . $del->error);
        if (!$del->execute()) throw new Exception('Execute delete items failed: ' . $del->error);
        $del->close();

    } else {
        // Insert new quiz
        $sqlQuiz = "INSERT INTO quizzes (title, section, audience, duration, quiz_code, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmtQuiz = $mysqli->prepare($sqlQuiz);
        if (!$stmtQuiz) throw new Exception('Prepare quiz failed: ' . $mysqli->error);

        if (!$stmtQuiz->bind_param('sssis', $title, $section, $audience, $duration, $code)) {
            throw new Exception('Bind quiz params failed: ' . $stmtQuiz->error);
        }
        if (!$stmtQuiz->execute()) throw new Exception('Execute quiz failed: ' . $stmtQuiz->error);

        $quiz_id = $stmtQuiz->insert_id;
        $stmtQuiz->close();
    }

    // If there are items, insert them
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    if (count($items) > 0) {
        $sqlItem = "INSERT INTO quiz_items
            (quiz_id, deadline, adults, children, infants, flight_type, origin, destination, departure, return_date, flight_number, seats, travel_class)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtItem = $mysqli->prepare($sqlItem);
        if (!$stmtItem) throw new Exception('Prepare item failed: ' . $mysqli->error);

        // types: i (quiz_id), s (deadline), i,i,i, s,s,s,s,s,s,s,s  => 13 params
        $types = 'isiiissssssss';

        foreach ($items as $it) {
            $deadline = norm_date($it['deadline'] ?? null);
            $b = $it['booking'] ?? [];

            $adults = isset($b['adults']) ? intval($b['adults']) : 0;
            $children = isset($b['children']) ? intval($b['children']) : 0;
            $infants = isset($b['infants']) ? intval($b['infants']) : 0;
            $flight_type = isset($b['flight_type']) ? strtoupper($b['flight_type']) : '';
            $origin = isset($b['origin']) ? $b['origin'] : '';
            $destination = isset($b['destination']) ? $b['destination'] : '';
            $departure = norm_date($b['departure'] ?? null);
            $return_date = norm_date($b['return'] ?? ($b['return_date'] ?? null));
            $flight_number = isset($b['flight_number']) ? $b['flight_number'] : '';
            $seats = isset($b['seats']) ? $b['seats'] : '';
            $travel_class = isset($b['travel_class']) ? strtoupper($b['travel_class']) : '';

            // bind params and execute
            if (!$stmtItem->bind_param(
                $types,
                $quiz_id,
                $deadline,
                $adults,
                $children,
                $infants,
                $flight_type,
                $origin,
                $destination,
                $departure,
                $return_date,
                $flight_number,
                $seats,
                $travel_class
            )) {
                throw new Exception('Bind params failed: ' . $stmtItem->error);
            }

            if (!$stmtItem->execute()) {
                throw new Exception('Execute item failed: ' . $stmtItem->error);
            }
        }
        $stmtItem->close();
    }

    // Optional: handle questions array if you store them (not covered here). You may insert/update questions similarly.

    $mysqli->commit();

    echo json_encode(['success' => true, 'id' => $quiz_id]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log('save_quiz error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $mysqli->close();
}
