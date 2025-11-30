<?php
session_start();

// 1) DB connection
$conn = new mysqli("localhost", "root", "", "airlines");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ---------- Helper normalizers ----------
function norm_type_quiz($type) {
    $t = strtoupper(trim((string)$type));
    // quiz_items uses 'oneway' / 'roundtrip'
    if ($t === 'ONEWAY' || $t === 'ONE-WAY') return 'ONEWAY';
    if ($t === 'ROUNDTRIP' || $t === 'ROUND-TRIP' || $t === 'TWOWAY') return 'ROUNDTRIP';
    return $t;
}

function norm_type_sub($type) {
    $t = strtoupper(trim((string)$type));
    // submissions uses 'ONE-WAY' / 'ROUND-TRIP'
    if ($t === 'ONEWAY' || $t === 'ONE-WAY') return 'ONEWAY';
    if ($t === 'ROUND-TRIP' || $t === 'TWOWAY' || $t === 'ROUNDTRIP') return 'ROUNDTRIP';
    return $t;
}

function norm_class($c) {
    return strtoupper(trim((string)$c));
}

// 2) Get ONE quiz_items row per quiz_id (first item)
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
    INNER JOIN (
        SELECT quiz_id, MIN(id) AS min_id
        FROM quiz_items
        GROUP BY quiz_id
    ) AS t
        ON t.quiz_id = qi.quiz_id
       AND t.min_id  = qi.id
    ORDER BY qi.quiz_id ASC
";

$quizResult = $conn->query($quizSql);
if (!$quizResult) {
    die("Quiz query failed: " . htmlspecialchars($conn->error));
}

if ($quizResult->num_rows === 0) {
    echo "<h2>No quiz items found.</h2>";
    $conn->close();
    exit;
}

// 3) Prepare submissions query (reused for each quiz)
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

$subStmt = $conn->prepare($subSql);
if (!$subStmt) {
    die("Prepare failed (submissions): " . htmlspecialchars($conn->error));
}

// 4) Loop through ALL quizzes
while ($quiz = $quizResult->fetch_assoc()) {

    $quiz_id = (int)$quiz['quiz_id'];

    echo "<h2>Quiz Requirements (Reference Booking) - Quiz ID: " . $quiz_id . "</h2>";
    echo "Quiz ID: " . htmlspecialchars($quiz['quiz_id']) . "<br>";
    echo "Adults: " . (int)$quiz['adults'] . "<br>";
    echo "Children: " . (int)$quiz['children'] . "<br>";
    echo "Infants: " . (int)$quiz['infants'] . "<br>";
    echo "Type: " . htmlspecialchars($quiz['flight_type']) . "<br>";
    echo "From: " . htmlspecialchars($quiz['origin']) . "<br>";
    echo "To: " . htmlspecialchars($quiz['destination']) . "<br>";
    echo "Class: " . htmlspecialchars($quiz['travel_class']) . "<br>";
    echo "<hr>";

    // Pre-normalize quiz reference values
    $quizAdults    = (int)$quiz['adults'];
    $quizChildren  = (int)$quiz['children'];
    $quizInfants   = (int)$quiz['infants'];
    $quizTypeNorm  = norm_type_quiz($quiz['flight_type']);
    $quizOrigin    = strtoupper(trim($quiz['origin']));
    $quizDest      = strtoupper(trim($quiz['destination']));
    $quizClassNorm = norm_class($quiz['travel_class']);

    // 5) Get submissions for THIS quiz_id
    $subStmt->bind_param("i", $quiz_id);
    $subStmt->execute();
    $result = $subStmt->get_result();

    echo "<h3>Submitted Answers for Quiz ID: " . $quiz_id . "</h3>";

    if ($result->num_rows === 0) {
        echo "No submissions found for this quiz.<br><br><hr>";
        continue;
    }

    while ($row = $result->fetch_assoc()) {

        $subAdults    = (int)$row['adults'];
        $subChildren  = (int)$row['children'];
        $subInfants   = (int)$row['infants'];
        $subTypeNorm  = norm_type_sub($row['flight_type']);
        $subOrigin    = strtoupper(trim($row['origin']));
        $subDest      = strtoupper(trim($row['destination']));
        $subClassNorm = norm_class($row['travel_class']);

        // Compare submission with reference quiz item
        $is_match = (
            $subAdults    === $quizAdults &&
            $subChildren  === $quizChildren &&
            $subInfants   === $quizInfants &&
            $subTypeNorm  === $quizTypeNorm &&
            $subOrigin    === $quizOrigin &&
            $subDest      === $quizDest &&
            $subClassNorm === $quizClassNorm
        );

        echo "<div style='margin-bottom:14px;'>";
        echo "<strong>Submission ID:</strong> " . (int)$row['submission_id'] . "<br>";
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

    echo "<br>";
}

$subStmt->close();
$conn->close();
?>