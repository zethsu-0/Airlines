<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---------- CONFIG ----------
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "airlines";

$requireLegsSequenceMatch = true;
$allowSingleLegFallback = true;
$enableDebugLogs = false;

// 1) DB connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ---------- 2) Load airports for mapping (code <-> friendly label) ----------
$codeToLabel = [];
$labelToCode = [];

$airSql = "
    SELECT IATACode,
           COALESCE(AirportName,'') AS AirportName,
           COALESCE(City,'')        AS City,
           COALESCE(CountryRegion,'') AS CountryRegion
    FROM airports
";
$airRes = $conn->query($airSql);
if ($airRes) {
    while ($r = $airRes->fetch_assoc()) {
        $code = strtoupper(trim($r['IATACode'] ?? ''));
        if ($code === '') continue;
        $name = trim($r['AirportName'] ?? '');
        $city = trim($r['City'] ?? '');
        $country = trim($r['CountryRegion'] ?? '');
        $parts = [];
        if ($name !== '') $parts[] = strtoupper($name);
        if ($city !== '') $parts[] = strtoupper($city);
        if ($country !== '') $parts[] = strtoupper($country);
        $label = $parts ? implode(' - ', $parts) : $code;
        $codeToLabel[$code] = $label;
        $labelToCode[$label] = $code;
    }
    $airRes->free();
}

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
function code_to_label($code, $map) {
    $c = norm_code($code);
    return $map[$c] ?? $c;
}
function label_to_code($label, $map) {
    $l = strtoupper(trim((string)$label));
    return $map[$l] ?? $l;
}
function norm_date($d) {
    $d = trim((string)$d);
    if ($d === '') return '';
    $invalidPlaceholders = ['0000-00-00','0000-00-00 00:00:00'];
    if (in_array($d, $invalidPlaceholders, true)) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if ($dt && $dt->format('Y-m-d') === $d) return $d;
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $d);
    if ($dt) return $dt->format('Y-m-d');
    $ts = strtotime($d);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
    return '';
}
function normalize_legs_to_codes(array $legs, array $labelToCode, array $codeToLabel) {
    $out = [];
    foreach ($legs as $lg) {
        $rawO = isset($lg['origin']) ? trim((string)$lg['origin']) : '';
        $rawD = isset($lg['destination']) ? trim((string)$lg['destination']) : '';
        $rawDate = isset($lg['date']) ? trim((string)$lg['date']) : '';
        $o = strtoupper($rawO);
        $d = strtoupper($rawD);
        if (!preg_match('/^[A-Z]{3}$/', $o)) {
            $mapped = $labelToCode[$o] ?? null;
            if ($mapped) $o = $mapped;
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
function compare_legs_sequence(array $a, array $b) {
    if (count($a) !== count($b)) return false;
    for ($i = 0; $i < count($a); $i++) {
        $la = $a[$i];
        $lb = $b[$i];
        $originA = ($la['origin'] ?? '');
        $originB = ($lb['origin'] ?? '');
        $destA   = ($la['destination'] ?? '');
        $destB   = ($lb['destination'] ?? '');
        if ($originA !== $originB) return false;
        if ($destA   !== $destB)   return false;
    }
    return true;
}

// ---------- Queries ----------
// Quiz items (one row per quiz_id - first item)
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

// Submissions prepared stmt (with acc_name)
$subSql = "
    SELECT 
        sf.id AS submission_id,
        sf.acc_id,
        ac.acc_name,
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
    LEFT JOIN accounts ac ON ac.acc_id = sf.acc_id
    WHERE sf.quiz_id = ?
    ORDER BY sf.id
";
$subStmt = $conn->prepare($subSql);
if (!$subStmt) {
    die("Prepare failed (submissions): " . htmlspecialchars($conn->error));
}

// Fetch all quizzes into array
$quizzes = [];
$quizResult->data_seek(0);
while ($q = $quizResult->fetch_assoc()) {
    // Derive IATA codes for reference origin/dest
    $rawQuizOrigin = trim((string)$q['origin']);
    $rawQuizDest   = trim((string)$q['destination']);

    if (preg_match('/^[A-Za-z]{3}$/', $rawQuizOrigin)) {
        $quizOriginIATA = norm_code($rawQuizOrigin);
    } else {
        $quizOriginIATA = $labelToCode[strtoupper($rawQuizOrigin)] ?? norm_code($rawQuizOrigin);
    }
    if (preg_match('/^[A-Za-z]{3}$/', $rawQuizDest)) {
        $quizDestIATA = norm_code($rawQuizDest);
    } else {
        $quizDestIATA = $labelToCode[strtoupper($rawQuizDest)] ?? norm_code($rawQuizDest);
    }
    $q['quizOriginIATA'] = $quizOriginIATA;
    $q['quizDestIATA']   = $quizDestIATA;

    // pre-decode legs_json
    $quizLegsJsonRaw = $q['legs_json'] ?? '';
    $quizLegsNormalized = null;
    if (!empty($quizLegsJsonRaw)) {
        $decodedQuizLegs = json_decode($quizLegsJsonRaw, true);
        if (is_array($decodedQuizLegs) && count($decodedQuizLegs) > 0) {
            $quizLegsNormalized = normalize_legs_to_codes($decodedQuizLegs, $labelToCode, $codeToLabel);
        }
    }
    $q['quizLegsNormalized'] = $quizLegsNormalized;

    $quizzes[] = $q;
}

// ---------- Output HTML (single table with submission inner tables) ----------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Reference Bookings — Submissions Below</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  :root{
    --bg:#0b0f14;
    --panel:#071015;
    --accent:#00ff9c;
    --muted:#9aa3ad;
    --card-shadow: 0 8px 30px rgba(0,0,0,0.7);
    --glass: rgba(255,255,255,0.03);
    --red: #ff5c5c;
    --green:#3be07a;
  }
  body{
    margin:0;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(180deg, var(--navy-900), var(--navy-800));
    color: #e6eef3;
    padding:18px;
  }
  h1{ font-size:20px; margin:0 0 14px 0; letter-spacing:1px; color:var(--muted) }
  .wrap{ max-width:1200px; margin:0 auto; }

  /* Controls */
  .controls { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
  .btn-global {
      appearance:none; border: none; padding:8px 12px; border-radius:6px;
      background: rgba(255,255,255,0.04); color: #e6eef3; cursor:pointer;
      font-weight:700; letter-spacing:0.4px;
  }

  .ref-table {
    width:100%;
    border-collapse:collapse;
    background: linear-gradient(90deg,#071018,#061016);
    border:1px solid rgba(255,255,255,0.03);
    border-radius:8px;
    overflow:hidden;
    box-shadow: var(--card-shadow);
    margin-top:6px;
  }
  .ref-table thead th {
    text-align:left; padding:12px; font-size:13px; color:var(--muted); text-transform:uppercase; letter-spacing:1px;
    border-bottom:1px solid rgba(255,255,255,0.03);
    background: rgba(255,255,255,0.01);
    font-weight:700;
  }
  .ref-row {
    background: linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.005));
    font-weight:700;
    text-transform:uppercase;
  }
  .ref-row td { padding:12px; border-bottom:1px dashed rgba(255,255,255,0.02); vertical-align:middle; }
  .sub-container { padding:0 12px 12px 12px; background: rgba(255,255,255,0.01); }
  .sub-table {
    width:100%; border-collapse:collapse; margin-top:8px; font-family: "Courier New", monospace;
  }
  .sub-table thead th {
    text-align:left; padding:8px 10px; font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:0.8px;
    border-bottom:1px dotted rgba(255,255,255,0.03);
    font-weight:700;
  }
  .sub-row td { padding:8px 10px; font-size:13px; border-bottom:1px dashed rgba(255,255,255,0.02); color:#dfeaf0; vertical-align:middle; }
  .sub-empty { padding:10px; color:var(--muted); font-size:13px; }
  .iatas { font-weight:800; letter-spacing:0.8px; }
  .badge { display:inline-block; padding:4px 8px; border-radius:6px; background:var(--glass); font-size:12px; color:var(--muted); border:1px solid rgba(255,255,255,0.02); }

  /* Seats compact pill layout */
  .seat-pills { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .seat-pill {
    display:inline-block;
    padding:4px 6px;
    border-radius:6px;
    font-size:12px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.03);
    min-width: 28px;
    text-align:center;
  }

  .match-good { color:var(--green); font-weight:800; }
  .match-bad { color:var(--red); font-weight:800; }
  .btn-toggle {
      appearance:none; border: none; padding:6px 10px; border-radius:6px; cursor:pointer;
      background: rgba(255,255,255,0.03); color:var(--muted); font-weight:700;
  }
  .btn-toggle.hidden { background: rgba(255,255,255,0.01); color:var(--muted); opacity:0.8; }

  @media (max-width:880px){
    .ref-table thead { display:none; }
    .ref-table, .ref-table tbody, .ref-table tr, .ref-table td { display:block; width:100%; }
    .ref-row td { display:block; padding:10px; }
    .sub-table thead { display:none; }
    .sub-table, .sub-table tbody, .sub-table tr, .sub-table td { display:block; width:100%; }
    .sub-row td { display:block; padding:10px; }
    .seat-pills { gap:8px; }
  }
  .header{
    display: flex;
  }

</style>
  <link rel="stylesheet" href="materialize/css/materialize.min.css">

</head>
<body>
<div class="wrap">
        <h1>FLIGHTS</h1>
        <a href="admin.php" style="float:right; font-size:30px; color:#9aa3ad; text-decoration:none; font-weight:600px;">⟵ Go Back</a>
  <div class="controls">
    <button id="globalToggleBtn" class="btn-global">Hide all submissions</button>
    <div style="flex:1"></div>
  </div>

  <table class="ref-table" role="table" aria-label="Reference bookings with submissions">
    <thead>
      <tr>
        <th>Quiz ID</th>
        <th>Pax (A/C/I)</th>
        <th>Type</th>
        <th>Class</th>
        <th>From → To (IATA)</th>
        <th>Departure</th>
        <th>Flight# / Actions</th>
      </tr>
    </thead>
    <tbody>
<?php
foreach ($quizzes as $quiz) {
    $qid = (int)$quiz['quiz_id'];
    $pax = ((int)$quiz['adults']) . '/' . ((int)$quiz['children']) . '/' . ((int)$quiz['infants']);
    $type = htmlspecialchars(norm_type_quiz($quiz['flight_type'] ?? ''));
    $class = htmlspecialchars(norm_class($quiz['travel_class'] ?? ''));
    $fromto = htmlspecialchars($quiz['quizOriginIATA'] ?? '') . ' → ' . htmlspecialchars($quiz['quizDestIATA'] ?? '');
    $dep = !empty($quiz['departure']) ? htmlspecialchars($quiz['departure']) : '-';
    $fno = !empty($quiz['flight_number']) ? htmlspecialchars($quiz['flight_number']) : '-';

    // Reference row with an action button to toggle its submissions
    echo '<tr class="ref-row" data-quiz="'.$qid.'">';
    echo '<td>' . $qid . '</td>';
    echo '<td>' . $pax . '</td>';
    echo '<td>' . $type . '</td>';
    echo '<td>' . $class . '</td>';
    echo '<td class="iatas">' . $fromto . '</td>';
    echo '<td>' . $dep . '</td>';
    // Flight# and toggle button
    echo '<td>';
    echo $fno . ' &nbsp; ';
    echo '<button class="btn-toggle" data-toggle-quiz="'.$qid.'">Hide submissions</button>';
    echo '</td>';
    echo '</tr>';

    // Now render submissions inside a single table cell (spanning all columns) for this quiz
    echo '<tr class="sub-container-row" id="subcontainer-'.$qid.'">';
    echo '<td colspan="7" class="sub-container">';

    // Submissions header + table
    echo '<table class="sub-table" role="table" aria-label="Submitted flights for quiz '.$qid.'">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Account</th>';
    echo '<th>Pax</th>';
    echo '<th>Route</th>';
    echo '<th>Seats</th>';
    echo '<th>Flight Type</th>';
    echo '<th>Travel Class</th>';
    echo '<th>Departure</th>';
    echo '<th>Result</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Fetch submissions
    $subStmt->bind_param("i", $qid);
    $subStmt->execute();
    $result = $subStmt->get_result();

    if ($result->num_rows === 0) {
        echo '<tr><td colspan="8" class="sub-empty">No submissions for this quiz.</td></tr>';
    } else {
        while ($row = $result->fetch_assoc()) {
            $subAdults    = (int)$row['adults'];
            $subChildren  = (int)$row['children'];
            $subInfants   = (int)$row['infants'];
            $subTypeNorm  = norm_type_sub($row['flight_type']);
            $subClassNorm = norm_class($row['travel_class']);

            $subOriginRaw = $row['origin'];
            $subDestRaw   = $row['destination'];

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
                    $firstLeg = $submissionLegs[0];
                    $lastLeg  = $submissionLegs[count($submissionLegs)-1];
                    $subOriginRaw = $firstLeg['origin'];
                    $subDestRaw   = $lastLeg['destination'];
                    $submissionLegsNormalized = normalize_legs_to_codes($submissionLegs, $labelToCode, $codeToLabel);
                }
            }

            // fallback single-leg derive when quiz has exactly 1 leg
            $quizLegsNormalized = $quiz['quizLegsNormalized'] ?? null;
            if ($allowSingleLegFallback && $quizLegsNormalized !== null && $submissionLegsNormalized === null) {
                if (count($quizLegsNormalized) === 1) {
                    $derived = [['origin' => $subOriginRaw, 'destination' => $subDestRaw, 'date' => '']];
                    $submissionLegsNormalized = normalize_legs_to_codes($derived, $labelToCode, $codeToLabel);
                    $submissionLegs = [['origin' => $subOriginRaw, 'destination' => $subDestRaw, 'date' => '']];
                }
            }

            // transform for comparison depending on input type
            $quizInputType = trim($quiz['input_type'] ?? 'code-airport');
            if ($quizInputType === 'airport-code') {
                $subOriginCmp = norm_code($subOriginRaw);
                $subDestCmp   = norm_code($subDestRaw);
                $quizOriginCmpNorm = norm_code($quiz['quizOriginIATA'] ?? $quiz['origin']);
                $quizDestCmpNorm   = norm_code($quiz['quizDestIATA'] ?? $quiz['destination']);
            } else {
                $maybeCodeOrigin = norm_code($subOriginRaw);
                $maybeCodeDest   = norm_code($subDestRaw);
                $convertedOriginLabel = isset($codeToLabel[$maybeCodeOrigin]) ? $codeToLabel[$maybeCodeOrigin] : $subOriginRaw;
                $convertedDestLabel   = isset($codeToLabel[$maybeCodeDest]) ? $codeToLabel[$maybeCodeDest] : $subDestRaw;
                $subOriginCmp = norm_label($convertedOriginLabel);
                $subDestCmp   = norm_label($convertedDestLabel);
                $quizOriginCmpNorm = norm_label($quiz['quizOriginIATA'] ?? $quiz['origin']);
                $quizDestCmpNorm   = norm_label($quiz['quizDestIATA'] ?? $quiz['destination']);
            }

            // core counts & class/type check
            $coreCountsAndTypesMatch = (
                $subAdults   === (int)$quiz['adults'] &&
                $subChildren === (int)$quiz['children'] &&
                $subInfants  === (int)$quiz['infants'] &&
                $subTypeNorm === norm_type_quiz($quiz['flight_type']) &&
                $subClassNorm === norm_class($quiz['travel_class'])
            );

            if ($quizLegsNormalized !== null && $requireLegsSequenceMatch) {
                $legsMatchResult = ($submissionLegsNormalized !== null) ? compare_legs_sequence($quizLegsNormalized, $submissionLegsNormalized) : false;
                $is_match = ($coreCountsAndTypesMatch && $legsMatchResult === true);
            } else {
                $originDestMatch = ($subOriginCmp === $quizOriginCmpNorm) && ($subDestCmp === $quizDestCmpNorm);
                $is_match = ($coreCountsAndTypesMatch && $originDestMatch);
            }

            // Build values for display
            $accName = !empty($row['acc_name']) ? $row['acc_name'] : $row['acc_id'];
            $passengerSummary = $subAdults.'A '.$subChildren.'C '.$subInfants.'I';
            if ($submissionLegs) {
                $parts = [];
                foreach ($submissionLegs as $l) {
                    $parts[] = htmlspecialchars($l['origin']).'→'.htmlspecialchars($l['destination']);
                }
                $routeDisplay = implode(' • ', $parts);
            } else {
                $routeDisplay = htmlspecialchars($row['origin']).'→'.htmlspecialchars($row['destination']);
            }

            // Seats: create compact pills from seat_number (split by commas, spaces)
            $seatRaw = trim((string)$row['seat_number']);
            $seatParts = [];
            if ($seatRaw !== '') {
                // normalize separators (commas or spaces)
                $seatParts = preg_split('/[\s,;]+/', $seatRaw);
            }

            $depField = !empty($row['departure']) ? htmlspecialchars($row['departure']) : '-';
            $classType = htmlspecialchars($subClassNorm);
            $flightTypeStr = htmlspecialchars($subTypeNorm);
            $resultLabel = $is_match ? '<span class="match-good">MATCH ✅</span>' : '<span class="match-bad">NOT MATCH ❌</span>';

            echo '<tr class="sub-row">';
            echo '<td>' . htmlspecialchars($accName) . '</td>';
            echo '<td>' . htmlspecialchars($passengerSummary) . '</td>';
            echo '<td>' . $routeDisplay . '</td>';
            // seats column: compact pills
            echo '<td>';
            echo '<div class="seat-pills">';
            if (count($seatParts) === 0) {
                echo '<div class="seat-pill">—</div>';
            } else {
                foreach ($seatParts as $s) {
                    if ($s === '') continue;
                    echo '<div class="seat-pill">' . htmlspecialchars($s) . '</div>';
                }
            }
            echo '</div>';
            echo '</td>';
            echo '<td>' . $flightTypeStr . '</td>';
            echo '<td>' . $classType . '</td>';
            echo '<td>' . $depField . '</td>';
            echo '<td>' . $resultLabel . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>'; // close sub-table
    echo '</td></tr>'; // close sub container row
}
$subStmt->close();
$conn->close();
?>
    </tbody>
  </table>

</div> <!-- wrap -->

<script>
// GLOBAL toggle: Hide/show all submissions
(function(){
    const globalBtn = document.getElementById('globalToggleBtn');
    let allHidden = false;

    globalBtn.addEventListener('click', function(){
        allHidden = !allHidden;
        document.querySelectorAll('.sub-container-row').forEach(row => {
            row.style.display = allHidden ? 'none' : '';
        });
        // update per-quiz buttons text & classes accordingly
        document.querySelectorAll('[data-toggle-quiz]').forEach(btn => {
            btn.textContent = allHidden ? 'Show submissions' : 'Hide submissions';
            if (allHidden) btn.classList.add('hidden'); else btn.classList.remove('hidden');
        });
        globalBtn.textContent = allHidden ? 'Show all submissions' : 'Hide all submissions';
    });

    // per-quiz toggle handlers
    document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-toggle-quiz]');
        if (!btn) return;
        const quizId = btn.getAttribute('data-toggle-quiz');
        const containerRow = document.getElementById('subcontainer-' + quizId);
        if (!containerRow) return;
        // if global is currently in hidden state and user toggles one, we need to keep global state consistent:
        // toggling one will make that one visible/hidden independent of global state. We'll set global button to "Show all" if any are hidden.
        const isHidden = containerRow.style.display === 'none';
        containerRow.style.display = isHidden ? '' : 'none';
        btn.textContent = isHidden ? 'Hide submissions' : 'Show submissions';
        btn.classList.toggle('hidden', !isHidden);

        // update global button state: if any sub-container is visible and any is hidden, set to "Show all submissions"
        const anyHidden = Array.from(document.querySelectorAll('.sub-container-row')).some(r => r.style.display === 'none');
        allHidden = anyHidden && Array.from(document.querySelectorAll('.sub-container-row')).every(r => r.style.display === 'none');
        globalBtn.textContent = allHidden ? 'Show all submissions' : 'Hide all submissions';
    });
})();
</script>

</body>
</html>
