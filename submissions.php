<?php
session_start();

// 1) DB connection
$conn = new mysqli("localhost", "root", "", "airlines");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ---------- CONFIG: enable exact legs-sequence matching ----------
$requireLegsSequenceMatch = true; // set to false to disable the exact-sequence requirement

// ---------- Helper normalizers ----------
function norm_type_quiz($type) {
    $t = strtoupper(trim((string)$type));
    if ($t === 'ONEWAY' || $t === 'ONE-WAY') return 'ONEWAY';
    if ($t === 'ROUNDTRIP' || $t === 'ROUND-TRIP' || $t === 'TWOWAY') return 'ROUNDTRIP';
    return $t;
}

function norm_type_sub($type) {
    $t = strtoupper(trim((string)$type));
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

/**
 * Normalize a date string to YYYY-MM-DD if possible.
 * Returns normalized date string or original trimmed string if not valid.
 */
function norm_date($d) {
    $d = trim((string)$d);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if ($dt && $dt->format('Y-m-d') === $d) return $d;
    }
    return $d;
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
    $l = strtoupper(trim((string)$label));
    return $map[$l] ?? $l;
}

/**
 * Normalize a legs array to a canonical list of legs with origin/destination as IATA codes (when possible)
 * Input: array of ['origin'=>..., 'destination'=>..., 'date'=>...]
 * Returns: array of ['origin'=>'MNL','destination'=>'CEB','date'=>'2025-12-01'] (codes uppercase, date normalized)
 */
function normalize_legs_to_codes(array $legs, array $labelToCode, array $codeToLabel) {
    $out = [];
    foreach ($legs as $lg) {
        $rawO = isset($lg['origin']) ? trim((string)$lg['origin']) : '';
        $rawD = isset($lg['destination']) ? trim((string)$lg['destination']) : '';
        $rawDate = isset($lg['date']) ? trim((string)$lg['date']) : '';

        $o = strtoupper($rawO);
        $d = strtoupper($rawD);

        // If looks like a 3-letter code, keep it
        if (!preg_match('/^[A-Z]{3}$/', $o)) {
            // try map label->code
            $mapped = $labelToCode[$o] ?? null;
            if ($mapped) $o = $mapped;
            // else keep uppercase label fallback (won't match codes)
        }
        if (!preg_match('/^[A-Z]{3}$/', $d)) {
            $mapped = $labelToCode[$d] ?? null;
            if ($mapped) $d = $mapped;
        }

        $dateNorm = norm_date($rawDate);

        $out[] = ['origin' => $o, 'destination' => $d, 'date' => $dateNorm];
    }
    return $out;
}

/**
 * Compare two legs sequences for exact match (order, origin,destination,date).
 * Both $a and $b should be arrays of normalized legs (origin/destination codes uppercased, date normalized).
 * Returns true if exact equal.
 */
function compare_legs_sequence(array $a, array $b) {
    if (count($a) !== count($b)) return false;
    for ($i = 0; $i < count($a); $i++) {
        $la = $a[$i];
        $lb = $b[$i];
        if ((($la['origin'] ?? '') !== ($lb['origin'] ?? '')) ||
            ((($la['destination'] ?? '') !== ($lb['destination'] ?? '')) ||
            ((($la['date'] ?? '') !== ($lb['date'] ?? ''))))) {
            return false;
        }
    }
    return true;
}

// 2) Get ONE quiz_items row per quiz_id (first item), plus quizzes.input_type and quiz legs_json if present
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
        qi.travel_class,
        -- try to fetch legs_json if present in quiz_items (may be NULL)
        qi.legs_json
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

// 3) Prepare submissions query (reused for each quiz) - include legs_json
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
        sf.legs_json,
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

    // Quiz legs_json (if present)
    $quizLegsJsonRaw = $quiz['legs_json'] ?? '';
    $quizLegsNormalized = null;
    if (!empty($quizLegsJsonRaw)) {
        $decodedQuizLegs = json_decode($quizLegsJsonRaw, true);
        if (is_array($decodedQuizLegs) && count($decodedQuizLegs) > 0) {
            // normalize quiz legs to codes (attempt mapping)
            $quizLegsNormalized = normalize_legs_to_codes($decodedQuizLegs, $labelToCode, $codeToLabel);
        }
    }

    // Origin / destination comparison values depend on input_type:
    //  - airport-code:   student answers CODE, so we compare by CODE
    //  - code-airport:   student answers LABEL, so we compare by LABEL
    // We'll still derive a quizOriginCmp/quizDestCmp for fallback comparisons
    $quizOriginRaw = $quiz['origin'];
    $quizDestRaw   = $quiz['destination'];

    if ($quizInputType === 'airport-code') {
        $quizOriginCmp = label_to_code($quizOriginRaw, $labelToCode);
        $quizDestCmp   = label_to_code($quizDestRaw,   $labelToCode);
    } else {
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

        // Default: submission raw origin/destination (legacy)
        $subOriginRaw = $row['origin'];
        $subDestRaw   = $row['destination'];

        // If submission has legs_json, decode and derive first/last legs and normalized legs
        $legsJson = $row['legs_json'] ?? '';
        $submissionLegs = null;
        $submissionLegsNormalized = null;
        if (!empty($legsJson)) {
            $decoded = json_decode($legsJson, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $submissionLegs = [];
                foreach ($decoded as $lg) {
                    $o = isset($lg['origin']) ? trim((string)$lg['origin']) : '';
                    $d = isset($lg['destination']) ? trim((string)$lg['destination']) : '';
                    $dt = isset($lg['date']) ? trim((string)$lg['date']) : '';
                    $submissionLegs[] = ['origin' => $o, 'destination' => $d, 'date' => $dt];
                }
                // derive default raw origin/destination from the legs
                $firstLeg = $submissionLegs[0];
                $lastLeg  = $submissionLegs[count($submissionLegs)-1];
                $subOriginRaw = $firstLeg['origin'];
                $subDestRaw   = $lastLeg['destination'];

                // normalize submission legs into codes (attempt mapping)
                $submissionLegsNormalized = normalize_legs_to_codes($submissionLegs, $labelToCode, $codeToLabel);
            }
        }

        // If quiz has legs sequence and the matching option is enabled, compare sequences
        $legsExactMatch = null; // null = not applicable, true/false = result
        if ($requireLegsSequenceMatch && $quizLegsNormalized !== null) {
            // If submission provided legs -> compare normalized sequences
            if ($submissionLegsNormalized !== null) {
                $legsExactMatch = compare_legs_sequence($quizLegsNormalized, $submissionLegsNormalized);
            } else {
                // submission has no legs -> cannot match an exact legs-sequence
                $legsExactMatch = false;
            }
        }

        // Transform submission values depending on quiz input_type for fallback origin/destination comparison
        if ($quizInputType === 'airport-code') {
            $subOriginCmp = norm_code($subOriginRaw);
            $subDestCmp   = norm_code($subDestRaw);
        } else {
            // code-airport: if submission has codes, convert to label; otherwise normalize label
            $maybeCodeOrigin = norm_code($subOriginRaw);
            $maybeCodeDest   = norm_code($subDestRaw);

            $convertedOriginLabel = isset($codeToLabel[$maybeCodeOrigin]) ? $codeToLabel[$maybeCodeOrigin] : $subOriginRaw;
            $convertedDestLabel   = isset($codeToLabel[$maybeCodeDest]) ? $codeToLabel[$maybeCodeDest] : $subDestRaw;

            $subOriginCmp = norm_label($convertedOriginLabel);
            $subDestCmp   = norm_label($convertedDestLabel);
        }

        // Quiz comparison values also normalized
        if ($quizInputType === 'airport-code') {
            $quizOriginCmpNorm = norm_code($quizOriginCmp);
            $quizDestCmpNorm   = norm_code($quizDestCmp);
        } else {
            $quizOriginCmpNorm = norm_label($quizOriginCmp);
            $quizDestCmpNorm   = norm_label($quizDestCmp);
        }

        // Final comparison: if legs-exact-match is required and applicable, include it in match criteria
        $baseMatch = (
            $subAdults    === $quizAdults &&
            $subChildren  === $quizChildren &&
            $subInfants   === $quizInfants &&
            $subTypeNorm  === $quizTypeNorm &&
            $subOriginCmp === $quizOriginCmpNorm &&
            $subDestCmp   === $quizDestCmpNorm &&
            $subClassNorm === $quizClassNorm
        );

        if ($requireLegsSequenceMatch && $quizLegsNormalized !== null) {
            // require legsExactMatch to be true in addition to baseMatch
            $is_match = ($baseMatch && $legsExactMatch === true);
        } else {
            $is_match = $baseMatch;
        }

        // Output
        echo "<div style='margin-bottom:14px;'>";
        echo "<strong>Submission ID:</strong> " . (int)$row['submission_id'] . "<br>";
        echo "Account ID: " . htmlspecialchars($row['acc_id']) . "<br>";

        echo "User: "
           . $subAdults   . " Adults, "
           . $subChildren . " Children, "
           . $subInfants  . " Infants, "
           . htmlspecialchars($row['flight_type']) . ", ";

        if ($submissionLegs) {
            $legsParts = [];
            foreach ($submissionLegs as $l) {
                $legsParts[] = htmlspecialchars($l['origin']) . "→" . htmlspecialchars($l['destination']) . " (" . htmlspecialchars($l['date']) . ")";
            }
            echo "Legs: " . implode(" • ", $legsParts) . ", ";
        } else {
            echo htmlspecialchars($row['origin']) . " → " . htmlspecialchars($row['destination']) . ", ";
        }

        echo htmlspecialchars($row['travel_class']) . "<br>";

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

        // Show reference comparison basis
        echo "Correct (reference comparison basis): ";
        if ($quizInputType === 'airport-code') {
            echo "Expect CODE • Origin: " . htmlspecialchars($quizOriginCmpNorm)
               . " → Destination: " . htmlspecialchars($quizDestCmpNorm);
        } else {
            echo "Expect LABEL • Origin: " . htmlspecialchars($quizOriginCmpNorm)
               . " → Destination: " . htmlspecialchars($quizDestCmpNorm);
        }
        echo "<br>";

        // If quiz has a legs reference, show it (friendly)
        if ($quizLegsNormalized !== null) {
            $parts = [];
            foreach ($quizLegsNormalized as $ql) {
                $parts[] = htmlspecialchars($ql['origin']) . "→" . htmlspecialchars($ql['destination']) . " (" . htmlspecialchars($ql['date']) . ")";
            }
            echo "Quiz reference legs: " . implode(" • ", $parts) . "<br>";
        }

        if ($legsExactMatch !== null) {
            echo "Exact legs-sequence match required: " . ($requireLegsSequenceMatch ? "YES" : "NO (config)") . "<br>";
            echo "Exact legs-sequence match result: " . ($legsExactMatch ? "YES ✅" : "NO ❌") . "<br>";
        }

        echo "<strong>Result: " . ($is_match ? "MATCH ✅" : "NOT MATCH ❌") . "</strong>";
        echo "</div><hr>";
    }

    echo "<br>";
}

$subStmt->close();
$conn->close();
?>
