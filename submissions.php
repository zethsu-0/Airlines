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

function norm_code($c) {
    return strtoupper(trim((string)$c));
}

function norm_label($s) {
    return strtoupper(trim((string)$s));
}

// ---------- 1. Load airports for code <-> label mapping ----------
$codeToLabel = [];   // 'MNL' => 'NINOY AQUINO INTERNATIONAL AIRPORT - MANILA - PHILIPPINES'
$labelToCode = [];   // 'NINOY AQUINO INTERNATIONAL AIRPORT - MANILA - PHILIPPINES' => 'MNL'

$airSql = "
    SELECT IATACode,
           COALESCE(AirportName,'') AS AirportName,
           COALESCE(City,'')        AS City,
           COALESCE(CountryRegion,'') AS CountryRegion
    FROM airports
";
$airRes = $conn->query($airSql);
if ($airRes) {
    while ($row = $airRes->fetch_assoc()) {
        $code = strtoupper(trim($row['IATACode'] ?? ''));
        if ($code === '') continue;

        $name    = trim($row['AirportName'] ?? '');
        $city    = trim($row['City'] ?? '');
        $country = trim($row['CountryRegion'] ?? '');

        $parts = [];
        if ($name !== '')    $parts[] = strtoupper($name);
        if ($city !== '')    $parts[] = strtoupper($city);
        if ($country !== '') $parts[] = strtoupper($country);

        $label = $parts ? implode(' - ', $parts) : $code;

        $codeToLabel[$code]  = $label;
        $labelToCode[$label] = $code;
    }
    $airRes->free();
}

// helpers
function code_to_label($code, $map) {
    $c = norm_code($code);
    return $map[$c] ?? $c;
}

function label_to_code($label, $map) {
    $l = norm_label($label);
    return $map[$l] ?? $l;
}

// 2) Get ONE quiz_items row per quiz_id (first item), plus quizzes.input_type
$quizSql = "
    SELECT
        qi.quiz_id,
        q.input_type,
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
    INNER JOIN quizzes q
        ON q.id = qi.quiz_id
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
    $quizInputType = trim($quiz['input_type'] ?? 'code-airport'); // 'airport-code' or 'code-airport'

    echo "<h2>Quiz Requirements (Reference Booking) - Quiz ID: " . $quiz_id . "</h2>";
    echo "Quiz ID: " . htmlspecialchars($quiz['quiz_id']) . "<br>";
    echo "Input type: " . htmlspecialchars($quizInputType) . "<br>";
    echo "Adults: " . (int)$quiz['adults'] . "<br>";
    echo "Children: " . (int)$quiz['children'] . "<br>";
    echo "Infants: " . (int)$quiz['infants'] . "<br>";
    echo "Type: " . htmlspecialchars($quiz['flight_type']) . "<br>";
    echo "From (stored): " . htmlspecialchars($quiz['origin']) . "<br>";
    echo "To (stored): " . htmlspecialchars($quiz['destination']) . "<br>";
    echo "Class: " . htmlspecialchars($quiz['travel_class']) . "<br>";
    echo "<hr>";

    // Pre-normalize quiz reference values (counts, type, class)
    $quizAdults    = (int)$quiz['adults'];
    $quizChildren  = (int)$quiz['children'];
    $quizInfants   = (int)$quiz['infants'];
    $quizTypeNorm  = norm_type_quiz($quiz['flight_type']);
    $quizClassNorm = norm_class($quiz['travel_class']);

    // Origin / destination comparison values depend on input_type:
    //  - airport-code:   student answers CODE, so we compare by CODE
    //                    -> quiz is stored as LABEL, convert LABEL -> CODE
    //  - code-airport:   student answers LABEL, so we compare by LABEL
    //                    -> quiz is stored as CODE, convert CODE -> LABEL

    $quizOriginRaw = $quiz['origin'];
    $quizDestRaw   = $quiz['destination'];

    if ($quizInputType === 'airport-code') {
        // Expect code answers -> convert quiz label back to code
        $quizOriginCmp = label_to_code($quizOriginRaw, $labelToCode);
        $quizDestCmp   = label_to_code($quizDestRaw,   $labelToCode);
    } else { // default: code-airport
        // Expect label answers -> convert quiz code to label
        $quizOriginCmp = code_to_label($quizOriginRaw, $codeToLabel);
        $quizDestCmp   = code_to_label($quizDestRaw,   $codeToLabel);
    }

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
        $subClassNorm = norm_class($row['travel_class']);

        // Submission raw origin/destination
        $subOriginRaw = $row['origin'];
        $subDestRaw   = $row['destination'];

        // Transform submission values depending on quiz input_type
        if ($quizInputType === 'airport-code') {
            // Student typed CODE directly -> compare as CODE
            $subOriginCmp = norm_code($subOriginRaw);
            $subDestCmp   = norm_code($subDestRaw);
        } else {
            // code-airport: student typed LABEL -> compare as LABEL
            $subOriginCmp = norm_label($subOriginRaw);
            $subDestCmp   = norm_label($subDestRaw);
        }

        // Quiz comparison values also normalized
        if ($quizInputType === 'airport-code') {
            $quizOriginCmpNorm = norm_code($quizOriginCmp);
            $quizDestCmpNorm   = norm_code($quizDestCmp);
        } else {
            $quizOriginCmpNorm = norm_label($quizOriginCmp);
            $quizDestCmpNorm   = norm_label($quizDestCmp);
        }

        // Compare submission with reference quiz item
        $is_match = (
            $subAdults    === $quizAdults &&
            $subChildren  === $quizChildren &&
            $subInfants   === $quizInfants &&
            $subTypeNorm  === $quizTypeNorm &&
            $subOriginCmp === $quizOriginCmpNorm &&
            $subDestCmp   === $quizDestCmpNorm &&
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

        // Show what the checker considered "correct"
        echo "Correct (reference comparison basis): ";
        if ($quizInputType === 'airport-code') {
            echo "Expect CODE • Origin: " . htmlspecialchars($quizOriginCmpNorm)
               . " → Destination: " . htmlspecialchars($quizDestCmpNorm);
        } else {
            echo "Expect LABEL • Origin: " . htmlspecialchars($quizOriginCmpNorm)
               . " → Destination: " . htmlspecialchars($quizDestCmpNorm);
        }
        echo "<br>";

        echo "<strong>Result: " . ($is_match ? "MATCH ✅" : "NOT MATCH ❌") . "</strong>";
        echo "</div><hr>";
    }

    echo "<br>";
}

$subStmt->close();
$conn->close();
?>
