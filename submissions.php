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
$conn->set_charset('utf8mb4');

// 3) Get ONE quiz_items row for this quiz (first item)
$quizSql = "
    SELECT
        qi.quiz_id,
        qi.adults,
        qi.children,
        qi.infants,
        qi.flight_type,
        qi.origin_iata      AS origin,
        qi.destination_iata AS destination,
        qi.departure_date   AS departure,
        qi.return_date,
        qi.flight_number,
        qi.seats,
        qi.travel_class
    FROM quiz_items qi
    WHERE qi.quiz_id = ?
    ORDER BY qi.id ASC
    LIMIT 1
";

$stmt = $conn->prepare($quizSql);
if (!$stmt) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quizResult = $stmt->get_result();

if ($quizResult->num_rows === 0) {
    die("Quiz items not found for this quiz.");
}
$quiz = $quizResult->fetch_assoc();
$stmt->close();

// 4) Display quiz_items (requirements)
echo "<h2>Quiz Requirements (Reference Booking)</h2>";
echo "Quiz ID: " . htmlspecialchars($quiz['quiz_id']) . "<br>";
echo "Adults: " . (int)$quiz['adults'] . "<br>";
echo "Children: " . (int)$quiz['children'] . "<br>";
echo "Infants: " . (int)$quiz['infants'] . "<br>";
echo "Type: " . htmlspecialchars($quiz['flight_type']) . "<br>";
echo "From: " . htmlspecialchars($quiz['origin']) . "<br>";
echo "To: " . htmlspecialchars($quiz['destination']) . "<br>";
echo "Class: " . htmlspecialchars($quiz['travel_class']) . "<br>";
echo "<hr>";

// helper normalizers
function norm_type_quiz($type) {
    $t = strtoupper(trim((string)$type));
    // quiz_items uses 'oneway' / 'roundtrip'
    if ($t === 'ONE-WAY' || $t === 'ONE-WAY') return 'oneway';
    if ($t === 'roundtrip' || $t === 'TWO-WAY' || $t === 'twoway') return 'roundtrip';
    return $t;
}
function norm_type_sub($type) {
    $t = strtoupper(trim((string)$type));
    // submissions uses 'ONE-WAY' / 'TWO-WAY'
    if ($t === 'ONE-WAY' || $t === 'ONE-WAY') return 'oneway';
    if ($t === 'two-way' || $t === 'TWO-WAY' || $t === 'roundtrip') return 'roundtrip';
    return $t;
}
function norm_class($c) {
    return strtoupper(trim((string)$c));
}

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
        sf.travel_class,
        sf.seat_number,
        sf.departure,
        sf.return_date,
        sf.submitted_at
    FROM submitted_flights sf
    WHERE sf.quiz_id = ?
    ORDER BY sf.id
";

$stmt = $conn->prepare($subSql);
if (!$stmt) {
    die("Prepare failed (submissions): " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Submitted Answers</h2>";

if ($result->num_rows === 0) {
    echo "No submissions found.";
} else {

    // Pre-normalize quiz reference values
    $quizAdults   = (int)$quiz['adults'];
    $quizChildren = (int)$quiz['children'];
    $quizInfants  = (int)$quiz['infants'];
    $quizTypeNorm = norm_type_quiz($quiz['flight_type']);
    $quizOrigin   = strtoupper(trim($quiz['origin']));
    $quizDest     = strtoupper(trim($quiz['destination']));
    $quizClassNorm= norm_class($quiz['travel_class']);

    while ($row = $result->fetch_assoc()) {

        $subAdults   = (int)$row['adults'];
        $subChildren = (int)$row['children'];
        $subInfants  = (int)$row['infants'];
        $subTypeNorm = norm_type_sub($row['flight_type']);
        $subOrigin   = strtoupper(trim($row['origin']));
        $subDest     = strtoupper(trim($row['destination']));
        $subClassNorm= norm_class($row['travel_class']);

        // 6) Compare submission with reference quiz item
        $is_match = (
            $subAdults   === $quizAdults &&
            $subChildren === $quizChildren &&
            $subInfants  === $quizInfants &&
            $subTypeNorm === $quizTypeNorm &&
            $subOrigin   === $quizOrigin &&
            $subDest     === $quizDest &&
            $subClassNorm=== $quizClassNorm
        );

        echo "<div style='margin-bottom:14px;'>";
        echo "Submission ID: " . (int)$row['submission_id'] . "<br>";
        echo "Account ID: " . htmlspecialchars($row['acc_id']) . "<br>";

        echo "User: "
           . $subAdults   . " Adults, "
           . $subChildren . " Children, "
           . $subInfants  . " Infants, "
           . htmlspecialchars($row['flight_type']) . ", "
           . htmlspecialchars($row['origin']) . " → " . htmlspecialchars($row['destination']) . ", "
           . htmlspecialchars($row['travel_class']) . "<br>";

        if (!empty($row['seat_number'])) {
            echo "Seats: " . htmlspecialchars($row['seat_number']) . "<br>";
        }
        if (!empty($row['departure'])) {
            echo "Departure: " . htmlspecialchars($row['departure']);
            if (!empty($row['return_date'])) {
                echo " • Return: " . htmlspecialchars($row['return_date']);
            }
            echo "<br>";
        }

        echo "Correct (reference): "
           . $quizAdults   . " Adults, "
           . $quizChildren . " Children, "
           . $quizInfants  . " Infants, "
           . htmlspecialchars($quiz['flight_type']) . ", "
           . htmlspecialchars($quiz['origin']) . " → " . htmlspecialchars($quiz['destination']) . ", "
           . htmlspecialchars($quiz['travel_class']) . "<br>";

        echo "<strong>Result: " . ($is_match ? "MATCH ✅" : "NOT MATCH ❌") . "</strong>";
        echo "</div><hr>";
    }
}

$stmt->close();
$conn->close();
?>
