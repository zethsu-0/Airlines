<?php
// delete_quiz.php
header('Content-Type: application/json');

// ------------------
// Read JSON body
// ------------------
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Client sends this as "public id" now, but it might still be a numeric old ID
$idRaw = isset($data['id']) ? (string)$data['id'] : '';
$idRaw = trim($idRaw);

if ($idRaw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz id']);
    exit;
}

// Clean up characters: keep only letters and digits
$publicIdCandidate = preg_replace('/[^a-zA-Z0-9]/', '', $idRaw);
// If it's all digits, we can also treat it as a numeric ID fallback
$numericId = ctype_digit($publicIdCandidate) ? (int)$publicIdCandidate : 0;

// ------------------
// DB config
// ------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

try {
    // ------------------
    // 1) Resolve to internal numeric quiz id
    //    Try public_id first, then numeric id
    // ------------------
    $quizId   = null;
    $publicId = null;

    // Try lookup by public_id
    if ($publicIdCandidate !== '') {
        $stmt = $mysqli->prepare("SELECT id, public_id FROM quizzes WHERE public_id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Prepare failed (public_id): ' . $mysqli->error);
        }
        $stmt->bind_param('s', $publicIdCandidate);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed (public_id): ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $quizId   = (int)$row['id'];
            $publicId = $row['public_id'];
        }
    }

    // If not found by public_id and we have numeric, try by id
    if ($quizId === null && $numericId > 0) {
        $stmt = $mysqli->prepare("SELECT id, public_id FROM quizzes WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Prepare failed (id): ' . $mysqli->error);
        }
        $stmt->bind_param('i', $numericId);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed (id): ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $quizId   = (int)$row['id'];
            $publicId = $row['public_id']; // might be null for old rows
        }
    }

    if ($quizId === null) {
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }

    // ------------------
    // 2) Delete quiz + quiz_items in a transaction
    // ------------------
    $mysqli->begin_transaction();

    // Delete child items first (if they exist)
    $stmtItems = $mysqli->prepare("DELETE FROM quiz_items WHERE quiz_id = ?");
    if (!$stmtItems) {
        throw new Exception('Prepare quiz_items delete failed: ' . $mysqli->error);
    }
    $stmtItems->bind_param('i', $quizId);
    if (!$stmtItems->execute()) {
        throw new Exception('Delete quiz_items failed: ' . $stmtItems->error);
    }
    $stmtItems->close();

    // Delete the quiz row
    $stmtQuiz = $mysqli->prepare("DELETE FROM quizzes WHERE id = ? LIMIT 1");
    if (!$stmtQuiz) {
        throw new Exception('Prepare quiz delete failed: ' . $mysqli->error);
    }
    $stmtQuiz->bind_param('i', $quizId);
    if (!$stmtQuiz->execute()) {
        throw new Exception('Delete quiz failed: ' . $stmtQuiz->error);
    }

    if ($stmtQuiz->affected_rows <= 0) {
        $stmtQuiz->close();
        $mysqli->rollback();
        echo json_encode(['success' => false, 'error' => 'Quiz not found or already deleted']);
        exit;
    }

    $stmtQuiz->close();
    $mysqli->commit();

    echo json_encode([
        'success'   => true,
        'id'        => $quizId,       // internal numeric id
        'public_id' => $publicId,     // null if old quiz had no public_id
    ]);
    exit;
    header('Location: admin.php');
} catch (Exception $e) {
    // try rollback if we were in a transaction
    @$mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
