<?php
// delete_quiz.php
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid quiz ID']);
    exit;
}

// DB config (adjust if needed)
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines'; // change if your quizzes are in another DB

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// If you have a quiz_items table, delete those first
// $mysqli->query("DELETE FROM quiz_items WHERE quiz_id = $id");

// Adjust table/column names to your actual schema
$sql = "DELETE FROM quizzes WHERE id = $id LIMIT 1";

if (!$mysqli->query($sql)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Delete failed: '.$mysqli->error]);
    exit;
}

if ($mysqli->affected_rows <= 0) {
    echo json_encode(['success' => false, 'error' => 'Quiz not found or already deleted']);
    exit;
}

echo json_encode(['success' => true, 'id' => $id]);
