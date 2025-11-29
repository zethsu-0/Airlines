<?php
session_start();

// 1) Get quiz_id from URL: submissions.php?id=27
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die('Invalid quiz id.');
}
$quiz_id = (int) $_GET['id'];

// 2) DB connection
$conn = new mysqli("localhost", "root", "", "airlines");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3) Get quiz_items row using quiz_id
$quizSql = "
    SELECT quiz_id, deadline, adults, children, infants,
           flight_type, origin, destination,
           departure, return_date, flight_number,
           seats, travel_class, created_at
    FROM quiz_items
    WHERE quiz_id = ?
";

$stmt = $conn->prepare($quizSql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quizResult = $stmt->get_result();

if ($quizResult->num_rows === 0) {
    die("Quiz not found.");
}
$quiz = $quizResult->fetch_assoc();
$stmt->close();

// 4) Display quiz_items
echo "<h2>Quiz Requirements</h2>";
echo "Quiz ID: " . $quiz['quiz_id'] . "<br>";
echo "Adults: " . $quiz['adults'] . "<br>";
echo "Children: " . $quiz['children'] . "<br>";
echo "Infants: " . $quiz['infants'] . "<br>";
echo "Type: " . $quiz['flight_type'] . "<br>";
echo "From: " . $quiz['origin'] . "<br>";
echo "To: " . $quiz['destination'] . "<br>";
echo "Class: " . $quiz['travel_class'] . "<br>";
echo "<hr>";

// 5) Get submissions for that quiz_id
$subSql = "
    SELECT 
        sf.id AS submission_id,
        sf.acc_id,
        sf.adults,
        sf.children,
        sf.infants,
        sf.flight_type,
        sf.origin,
        sf.destination,
        sf.travel_class
    FROM submitted_flights sf
    WHERE sf.quiz_id = ?
    ORDER BY sf.id
";

$stmt = $conn->prepare($subSql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Submitted Answers</h2>";

if ($result->num_rows === 0) {
    echo "No submissions found.";
} else {

    while ($row = $result->fetch_assoc()) {

        // 6) Compare submission with quiz_items
        $is_match = (
            (int)$row['adults'] === (int)$quiz['adults'] &&
            (int)$row['children'] === (int)$quiz['children'] &&
            (int)$row['infants'] === (int)$quiz['infants'] &&
            $row['flight_type'] === $quiz['flight_type'] &&
            $row['origin'] === $quiz['origin'] &&
            $row['destination'] === $quiz['destination'] &&
            $row['travel_class'] === $quiz['travel_class']
        );

        echo "Submission ID: " . $row['submission_id'] . "<br>";
        echo "Account ID: " . $row['acc_id'] . "<br>";

        echo "User: 
              {$row['adults']} Adults,
              {$row['children']} Children,
              {$row['infants']} Infants,
              {$row['flight_type']},
              {$row['origin']} → {$row['destination']},
              {$row['travel_class']}<br>";

        echo "Correct:
              {$quiz['adults']} Adults,
              {$quiz['children']} Children,
              {$quiz['infants']} Infants,
              {$quiz['flight_type']},
              {$quiz['origin']} → {$quiz['destination']},
              {$quiz['travel_class']}<br>";

        echo "<strong>Result: " . ($is_match ? "MATCH ✅" : "NOT MATCH ❌") . "</strong>";
        echo "<hr>";
    }
}

$stmt->close();
$conn->close();
?>