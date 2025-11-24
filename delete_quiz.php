<?php
// delete_quiz.php
header('Content-Type: application/json; charset=utf-8');

// DB config - adjust if needed
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
$data = $raw ? json_decode($raw, true) : null;
if ($data === null || !isset($data['quiz_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz_id']);
    $mysqli->close();
    exit;
}

$quiz_id = intval($data['quiz_id']);
if ($quiz_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid quiz_id']);
    $mysqli->close();
    exit;
}

try {
    $mysqli->begin_transaction();

    // delete items (if any)
    $delItems = $mysqli->prepare("DELETE FROM quiz_items WHERE quiz_id = ?");
    if (!$delItems) throw new Exception('Prepare delete items failed: ' . $mysqli->error);
    if (!$delItems->bind_param('i', $quiz_id)) throw new Exception('Bind delete items failed: ' . $delItems->error);
    if (!$delItems->execute()) throw new Exception('Execute delete items failed: ' . $delItems->error);
    $delItems->close();

    // delete quiz row
    $delQuiz = $mysqli->prepare("DELETE FROM quizzes WHERE id = ?");
    if (!$delQuiz) throw new Exception('Prepare delete quiz failed: ' . $mysqli->error);
    if (!$delQuiz->bind_param('i', $quiz_id)) throw new Exception('Bind delete quiz failed: ' . $delQuiz->error);
    if (!$delQuiz->execute()) throw new Exception('Execute delete quiz failed: ' . $delQuiz->error);
    $affected = $delQuiz->affected_rows;
    $delQuiz->close();

    if ($affected === 0) {
        // nothing deleted - maybe id doesn't exist
        throw new Exception('No quiz found with id ' . $quiz_id);
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'id' => $quiz_id]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log('delete_quiz error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $mysqli->close();
}
