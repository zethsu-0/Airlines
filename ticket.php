<?php
// ticket.php
session_start();

if (empty($_SESSION['acc_id'])) {
    header('Location: index.php');
    exit;
}

$require_login = true;
include('config/db_connect.php');

$studentId = (int) ($_SESSION['student_id'] ?? $_SESSION['acc_id']);

// =======================
// IATA DATA
// =======================

$iataData = [];

$sql = "SELECT IATACode, AirportName, City, CountryRegion FROM airports ORDER BY IATACode ASC";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $code = isset($row['IATACode']) ? trim($row['IATACode']) : '';
        if ($code === '') continue;
        $iataData[] = [
            'code'    => $code,
            'name'    => $row['AirportName'] ?? '',
            'city'    => $row['City'] ?? '',
            'country' => $row['CountryRegion'] ?? ''
        ];
    }
    $result->free();
} else {
    echo "<!-- IATA load error: " . htmlspecialchars($conn->error) . " -->";
}

$iataList = [];
$iataMap  = [];
foreach ($iataData as $it) {
    if (!empty($it['code'])) {
        $iataList[$it['code']] = $it['name'];
        $iataMap[$it['code']]  = [
            'name'    => $it['name'],
            'city'    => $it['city'],
            'country' => $it['country']
        ];
    }
}

function format_airport_display($code, $iataMap) {
    $code = trim(strtoupper((string)$code));
    if ($code === '') return '';
    $parts = [];
    if (!empty($iataMap[$code]['city']))    $parts[] = trim($iataMap[$code]['city']);
    if (!empty($iataMap[$code]['country'])) $parts[] = trim($iataMap[$code]['country']);
    if (!empty($iataMap[$code]['name']))    $parts[] = trim($iataMap[$code]['name']);
    if (count($parts) > 0) return implode(', ', $parts);
    return $code;
}

function airport_prompt_text($code, $iataMap) {
    $code = strtoupper(trim((string)$code));
    if ($code === '' || !isset($iataMap[$code])) {
        return $code ?: '---';
    }

    $info = $iataMap[$code];
    $parts = [];

    // Match quizmaker: NAME then CITY, no country, all caps, separated by " - "
    if (!empty($info['name'])) {
        $parts[] = strtoupper($info['name']);
    }
    if (!empty($info['city'])) {
        $parts[] = strtoupper($info['city']);
    }

    if (count($parts) > 0) {
        return implode(' - ', $parts);
    }

    return $code;
}

// >>> NEW: friendly label for assistance codes
function human_assist_label($code) {
    $code = strtoupper(trim((string)$code));
    switch ($code) {
        case 'WHEELCHAIR':
            return 'Wheelchair Assistance';
        case 'VISION_HEARING':
            return 'Vision/Hearing Impairment';
        case 'MOBILITY':
            return 'Reduced Mobility';
        case 'MEDICAL':
            return 'Medical Assistance';
        case 'OTHER':
            return 'Other / Special Handling';
        default:
            return $code;
    }
}

// =======================
// QUIZ LOADER (public_id or numeric id)
// =======================

$descObj       = null;
$quiz          = null;
$quizId        = null;        // internal numeric id
$quizPublicId  = null;        // public_id (string)
$quizInputType = 'airport-code'; // default; overridden by DB input_type

// Read ?id= from URL (can be public_id or numeric id)
$idRaw = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($idRaw !== '') {
    // sanitize for public_id candidate (letters & digits only)
    $publicIdCandidate = preg_replace('/[^A-Za-z0-9]/', '', $idRaw);
    // numeric fallback if all digits
    $numericId = ctype_digit($publicIdCandidate) ? (int)$publicIdCandidate : 0;

    // 1) Try lookup by public_id
    $sqlPub = "
        SELECT 
            id,
            public_id,
            title,
            `from` AS section,
            `to`   AS audience,
            duration,
            quiz_code AS code,
            input_type
        FROM quizzes
        WHERE public_id = ?
        LIMIT 1
    ";
    $qStmt = $conn->prepare($sqlPub);
    if ($qStmt) {
        $qStmt->bind_param('s', $publicIdCandidate);
        $qStmt->execute();
        $qres = $qStmt->get_result();
        $quiz = $qres->fetch_assoc();
        $qStmt->close();
    }

    // 2) If not found by public_id and looks numeric, try by id
    if (!$quiz && $numericId > 0) {
        $sqlId = "
            SELECT 
                id,
                public_id,
                title,
                `from` AS section,
                `to`   AS audience,
                duration,
                quiz_code AS code,
                input_type
            FROM quizzes
            WHERE id = ?
            LIMIT 1
        ";
        $qStmt = $conn->prepare($sqlId);
        if ($qStmt) {
            $qStmt->bind_param('i', $numericId);
            $qStmt->execute();
            $qres = $qStmt->get_result();
            $quiz = $qres->fetch_assoc();
            $qStmt->close();
        }
    }
}

// If still no quiz → 404
if (!$quiz) {
    http_response_code(404);
    echo "Quiz not found or you do not have access to it.";
    exit;
}

// Normalize quiz IDs / type
$quizId        = (int)$quiz['id'];                 // internal numeric id
$quizPublicId  = $quiz['public_id'] ?? null;       // may be null for old rows
$quizInputType = !empty($quiz['input_type']) ? $quiz['input_type'] : 'airport-code';

// =======================
// LOAD QUIZ ITEMS (legs_json + assistance_json + legacy ROUND-TRIP)
// =======================

$items = [];

// Detect if quiz_items has legs_json column
// Detect if quiz_items has legs_json / assistance_json columns
$hasLegsJson   = false;
$hasAssistJson = false;

$colCheck = $conn->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasLegsJson = true;
}

$colCheck2 = $conn->query("SHOW COLUMNS FROM `quiz_items` LIKE 'assistance_json'");
if ($colCheck2 && $colCheck2->num_rows > 0) {
    $hasAssistJson = true;
}

// Build SELECT query including legs_json / assistance_json if available
if ($hasLegsJson && $hasAssistJson) {
    $itemSql = "
      SELECT
        id,
        adults, children, infants,
        flight_type,
        origin_iata AS origin,
        destination_iata AS destination,
        departure_date AS departure,
        return_date,
        flight_number,
        seats,
        travel_class,
        legs_json,
        assistance_json
      FROM quiz_items
      WHERE quiz_id = ?
      ORDER BY item_index ASC, id ASC
    ";
} elseif ($hasLegsJson && !$hasAssistJson) {
    $itemSql = "
      SELECT
        id,
        adults, children, infants,
        flight_type,
        origin_iata AS origin,
        destination_iata AS destination,
        departure_date AS departure,
        return_date,
        flight_number,
        seats,
        travel_class,
        legs_json,
        NULL AS assistance_json
      FROM quiz_items
      WHERE quiz_id = ?
      ORDER BY item_index ASC, id ASC
    ";
} elseif (!$hasLegsJson && $hasAssistJson) {
    $itemSql = "
      SELECT
        id,
        adults, children, infants,
        flight_type,
        origin_iata AS origin,
        destination_iata AS destination,
        departure_date AS departure,
        return_date,
        flight_number,
        seats,
        travel_class,
        NULL AS legs_json,
        assistance_json
      FROM quiz_items
      WHERE quiz_id = ?
      ORDER BY item_index ASC, id ASC
    ";
} else {
    $itemSql = "
      SELECT
        id,
        adults, children, infants,
        flight_type,
        origin_iata AS origin,
        destination_iata AS destination,
        departure_date AS departure,
        return_date,
        flight_number,
        seats,
        travel_class,
        NULL AS legs_json,
        NULL AS assistance_json
      FROM quiz_items
      WHERE quiz_id = ?
      ORDER BY item_index ASC, id ASC
    ";
}

$stmt = $conn->prepare($itemSql);
if ($stmt) {
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {

        // Normalize numeric fields
        $r['adults']   = (int)($r['adults'] ?? 0);
        $r['children'] = (int)($r['children'] ?? 0);
        $r['infants']  = (int)($r['infants'] ?? 0);

        // Normalize travel class
        $r['travel_class'] = strtoupper(trim($r['travel_class'] ?? 'ECONOMY'));

        // ---------------------------
        // TRY PARSING legs_json FIRST
        // ---------------------------
        $legs = [];
        if (!empty($r['legs_json'])) {
            $decoded = json_decode($r['legs_json'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                foreach ($decoded as $lg) {
                    $legs[] = [
                        'origin'      => strtoupper(trim($lg['origin'] ?? '')),
                        'destination' => strtoupper(trim($lg['destination'] ?? '')),
                        'date'        => trim($lg['date'] ?? '')
                    ];
                }
            }
        }

        // ---------------------------------------------
        // LEGACY FALLBACK — build legs from old columns
        // ---------------------------------------------
        if (count($legs) === 0) {

            $legacy_origin = strtoupper(trim($r['origin'] ?? ''));
            $legacy_dest   = strtoupper(trim($r['destination'] ?? ''));
            $legacy_depart = trim($r['departure'] ?? '');
            $legacy_return = trim($r['return_date'] ?? '');
            $rowFlightType = strtoupper(trim($r['flight_type'] ?? 'ONE-WAY'));

            // Always add outbound leg
            $legs[] = [
                'origin'      => $legacy_origin,
                'destination' => $legacy_dest,
                'date'        => $legacy_depart
            ];

            // If ROUND-TRIP and return_date exists, add return leg
            if ($rowFlightType === 'ROUND-TRIP' && $legacy_return !== '') {
                $legs[] = [
                    'origin'      => $legacy_dest,
                    'destination' => $legacy_origin,
                    'date'        => $legacy_return
                ];
            }
        }

        // Attach legs back to row
        $r['legs'] = $legs;

        // ---------- NEW: decode assistance_json (robust) ----------
          $assistList = [];

          if (!empty($r['assistance_json'])) {
              $decodedA = json_decode($r['assistance_json'], true);

              if (json_last_error() !== JSON_ERROR_NONE) {
                  // Optional: log error for debugging
                  error_log('assistance_json decode error for quiz_item '.$r['id'].': '.json_last_error_msg().' RAW: '.$r['assistance_json']);
              } elseif (is_array($decodedA)) {

                  // Case 1: wrapped like { "assistances": [ ... ] }
                  if (isset($decodedA['assistances']) && is_array($decodedA['assistances'])) {
                      $decodedA = $decodedA['assistances'];
                  }

                  // Case 2: a single object { "passenger":1, "type":"..." }
                  $isAssoc = array_keys($decodedA) !== range(0, count($decodedA) - 1);
                  if ($isAssoc && isset($decodedA['passenger'])) {
                      $decodedA = [$decodedA];
                  }

                  // Now $decodedA should be an array of assistance objects
                  foreach ($decodedA as $a) {
                      if (!is_array($a)) continue;

                      $paxNum = isset($a['passenger']) ? (int)$a['passenger'] : null;
                      $atype  = isset($a['type']) ? trim((string)$a['type']) : '';

                      if ($paxNum !== null && $paxNum > 0 && $atype !== '') {
                          $assistList[] = [
                              'passenger' => $paxNum,
                              'type'      => $atype, // or human_assist_label($atype)
                          ];
                      }
                  }
              }
          }

          $r['assist'] = $assistList;

        $items[] = $r;
    }
    $stmt->close();
}

// =====================================================
// BUILD REFORMATTED STUDENT PROMPT (MULTI-CITY FORMAT)
// =====================================================

// helper: check if exactly 2 legs and true roundtrip (A→B, B→A)
function is_true_roundtrip_for_legs($legs) {
    if (!is_array($legs) || count($legs) !== 2) return false;
    $l0 = $legs[0]; 
    $l1 = $legs[1];
    if (empty($l0['origin']) || empty($l0['destination']) || empty($l1['origin']) || empty($l1['destination'])) {
        return false;
    }
    return ($l0['origin'] === $l1['destination'] && $l0['destination'] === $l1['origin']);
}

// aggregate totals & segments
$totalAdults      = 0;
$totalChildren    = 0;
$totalInfants     = 0;
$allSegments      = [];
$classes          = [];
$anyTrueRoundTrip = false;
$totalLegs        = 0;

// >>> NEW: collect all assistance definitions
$allAssistances   = [];

// use quizInputType we normalized above
$inputType = $quizInputType ?? 'airport-code';

foreach ($items as $it) {
    $totalAdults   += max(0, (int)($it['adults'] ?? 0));
    $totalChildren += max(0, (int)($it['children'] ?? 0));
    $totalInfants  += max(0, (int)($it['infants'] ?? 0));

    $tc = strtoupper(trim($it['travel_class'] ?? 'ECONOMY'));
    if ($tc !== '') $classes[$tc] = true;

    $legs = $it['legs'] ?? [];
    if (!is_array($legs)) $legs = [];

    if (is_true_roundtrip_for_legs($legs)) {
        $anyTrueRoundTrip = true;
    }

    foreach ($legs as $lg) {
        $org  = strtoupper(trim($lg['origin'] ?? ''));
        $dst  = strtoupper(trim($lg['destination'] ?? ''));
        $date = trim($lg['date'] ?? '');
        $allSegments[] = ['origin' => $org, 'destination' => $dst, 'date' => $date];
        $totalLegs++;
    }

    // >>> NEW: aggregate assistance info
    if (!empty($it['assist']) && is_array($it['assist'])) {
        foreach ($it['assist'] as $a) {
            $p = isset($a['passenger']) ? (int)$a['passenger'] : null;
            $t = isset($a['type']) ? trim($a['type']) : '';
            if ($p !== null && $p > 0 && $t !== '') {
                $allAssistances[] = [
                    'passenger' => $p,
                    'type'      => $t
                ];
            }
        }
    }
}

// Determine overall flight type
$overallFlightType = 'ONE-WAY';
if ($anyTrueRoundTrip && $totalLegs === 2 && count($items) === 1) {
    $overallFlightType = 'ROUND-TRIP';
} elseif ($totalLegs > 1) {
    $overallFlightType = 'MULTI-CITY';
}

// Build "Class of Service" display:
$classList = array_keys($classes);
$classOfService = '';
if (count($classList) === 1) {
    $classOfService = strtoupper($classList[0]);
    if (stripos($classOfService, 'CLASS') === false) {
        $classOfService .= ' CLASS';
    }
} elseif (count($classList) > 1) {
    $classOfService = implode(', ', array_map('strtoupper', $classList));
} else {
    $classOfService = 'ECONOMY CLASS';
}

// Build the description text as a situation-style sentence
$adultCount   = max(0, $totalAdults);
$childCount   = max(0, $totalChildren);
$infantCount  = max(0, $totalInfants);
$totalPax     = $adultCount + $childCount + $infantCount;

// Passenger phrase
$passengerParts = [];
if ($adultCount > 0) {
    $passengerParts[] = $adultCount . ' adult' . ($adultCount > 1 ? 's' : '');
}
if ($childCount > 0) {
    $passengerParts[] = $childCount . ' child' . ($childCount > 1 ? 'ren' : '');
}
if ($infantCount > 0) {
    $passengerParts[] = $infantCount . ' infant' . ($infantCount > 1 ? 's' : '');
}
$passengerPhrase = 'a single passenger';
if ($totalPax > 1) {
    $passengerPhrase = $totalPax . ' passengers';
}
if (!empty($passengerParts)) {
    $passengerPhrase .= ' (' . implode(', ', $passengerParts) . ')';
}

// Flight type phrase
$ftLower = strtolower($overallFlightType);
if ($ftLower === 'round-trip') {
    $flightTypePhrase = 'a round-trip journey';
} elseif ($ftLower === 'multi-city') {
    $flightTypePhrase = 'a multi-city itinerary';
} else {
    $flightTypePhrase = 'a one-way trip';
}

// Segment phrase
$routePhrase = 'no route specified';
if (count($allSegments) > 0) {
    $segmentStrings = [];
    $i = 1;
    foreach ($allSegments as $seg) {
        $org = $seg['origin'] ?: '---';
        $dst = $seg['destination'] ?: '---';
        $date = trim($seg['date'] ?? '');
        if ($date !== '') {
            $segmentStrings[] = "Flight {$i} from {$org} to {$dst} on {$date}";
        } else {
            $segmentStrings[] = "Flight {$i} from {$org} to {$dst}";
        }
        $i++;
    }

    if (count($segmentStrings) === 1) {
        $routePhrase = $segmentStrings[0];
    } else {
        // join with commas and "and" for the last segment
        $last = array_pop($segmentStrings);
        $routePhrase = implode(', ', $segmentStrings) . ' and ' . $last;
    }
}

// Class of service sentence
$classSentence = "All flights are booked in {$classOfService}.";

// Instruction depends on quiz input type (code vs airport name)
if ($inputType === 'airport-code') {
    $instructionSentence = "Based on this information, enter the correct three-letter IATA code for each city pair in the correct order.";
} else {
    $instructionSentence = "Based on this information, enter the correct airport / city name for each city pair in the correct order.";
}

// Final narrative description
$descriptionText =
    "You are assisting {$passengerPhrase} on {$flightTypePhrase}. " .
    "The itinerary consists of {$routePhrase}. " .
    "{$classSentence} ";

// firstDeadline & expected_answer
$firstDeadline = '';
foreach ($items as $it) {
    foreach (($it['legs'] ?? []) as $lg) {
        if (!empty($lg['date'])) {
            if ($firstDeadline === '' || strtotime($lg['date']) < strtotime($firstDeadline)) {
                $firstDeadline = $lg['date'];
            }
        }
    }
}

$firstDestination = null;
if (!empty($items) && !empty($items[0]['legs']) && !empty($items[0]['legs'][0])) {
    $dst = strtoupper(trim($items[0]['legs'][0]['destination'] ?? ''));
    if ($dst !== '') {
        if ($inputType === 'airport-code') {
            // student will enter IATA code
            $firstDestination = $dst;
        } else {
            // student will enter airport name/city
            $firstDestination = $dst ? airport_prompt_text($dst, $iataMap) : null;
        }
    }
}

// Final descObj used in your HTML
$descObj = [
    'description'     => $descriptionText,
    'flight_type'     => $overallFlightType,
    'passengers'      => [
        'adults'   => $adultCount,
        'children' => $childCount,
        'infants'  => $infantCount,
    ],
    'segments'        => $allSegments, // array of ['origin','destination','date']
    'class_of_service'=> $classOfService,
    'instruction'     => $instructionSentence,
    'itemsCount'      => count($items),
    'firstDeadline'   => $firstDeadline ?: '',
    'expected_answer' => $firstDestination ?: null,
    // >>> NEW: pass assistance list to the prompt
    'assistances'     => $allAssistances
];


// =======================
// POST / VALIDATION
// =======================

$origin       = strtoupper(trim($_POST['origin'] ?? ''));
$destination  = strtoupper(trim($_POST['destination'] ?? ''));
$flight_date  = trim($_POST['flight_date'] ?? '');
$flight_type  = $_POST['flight_type'] ?? 'ONE-WAY';
$return_date  = trim($_POST['return_date'] ?? '');
$errors       = [];

// (rest of your file below is unchanged – JS + HTML, except for the new
//  “Special assistance” section in the PROMPT markup)

// -------------- existing POST handler, JS, HTML etc. --------------

// If this is the AJAX validation call from "Confirm Booking"
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['ajax_validate'])
    && $_POST['ajax_validate'] == '1'
) {
    // Make sure nothing else is printed before JSON
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $errors = [];

    // Use the quiz input type we already computed above
    $inputType = $quizInputType ?? 'airport-code';

    // ---- Origin validation ----
    if ($origin === '') {
        $errors['origin'] = 'Origin is required.';
    } elseif ($inputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $origin)) {
        $errors['origin'] = 'Origin must be 3 uppercase letters.';
    }

    // ---- Destination validation ----
    if ($destination === '') {
        $errors['destination'] = 'Destination is required.';
    } elseif ($inputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $destination)) {
        $errors['destination'] = 'Destination must be 3 uppercase letters.';
    }

    if ($origin !== '' && $destination !== '' && $origin === $destination) {
        $errors['destination'] = 'Origin and destination cannot be the same.';
    }

    // ---- Dates ----
    $flight_type = strtoupper(trim((string)$flight_type));
    if (!in_array($flight_type, ['ONE-WAY', 'ROUND-TRIP', 'MULTI-CITY'], true)) {
        $flight_type = 'ONE-WAY';
    }

    // departure
    if ($flight_date === '') {
        $errors['flight_date'] = 'Departure date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
        $errors['flight_date'] = 'Invalid departure date (YYYY-MM-DD).';
    } else {
        $dObj = DateTime::createFromFormat('Y-m-d', $flight_date);
        if (!$dObj || $dObj->format('Y-m-d') !== $flight_date) {
            $errors['flight_date'] = 'Departure date is invalid.';
        }
    }

    // return date only if ROUND-TRIP
    if ($flight_type === 'ROUND-TRIP') {
        if ($return_date === '') {
            $errors['return_date'] = 'Return date is required for round-trip flights.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
            $errors['return_date'] = 'Invalid return date (YYYY-MM-DD).';
        } else {
            $rObj = DateTime::createFromFormat('Y-m-d', $return_date);
            if (!$rObj || $rObj->format('Y-m-d') !== $return_date) {
                $errors['return_date'] = 'Return date is invalid.';
            } elseif (isset($dObj) && $rObj < $dObj) {
                $errors['return_date'] = 'Return date cannot be before departure date.';
            }
        }
    }

    if (!empty($errors)) {
        echo json_encode([
            'ok'     => false,
            'errors' => $errors,
        ]);
        exit;
    }

    // Success: send back the flight object used by buildSummaryAndOpen()
    echo json_encode([
        'ok'     => true,
        'errors' => new stdClass(),
        'flight' => [
            'origin'       => $origin,
            'destination'  => $destination,
            'flight_date'  => $flight_date,
            'return_date'  => $return_date,
            'flight_type'  => $flight_type,
        ],
    ]);
    exit;
}


?>
<!DOCTYPE html>
  <html lang="en">
  <?php include('templates/header.php'); ?>
  <body>
    <h4 class="center-align ticket-title">🎫 Plane Ticket Booking</h4>
    <div class="container prompt-card">
      <div class="card center">
        <h4>PROMPT</h4>
        <?php if ($descObj): ?>
          <?php
            $pax = $descObj['passengers'] ?? ['adults' => 0, 'children' => 0, 'infants' => 0];
            $totalPax = ($pax['adults'] ?? 0) + ($pax['children'] ?? 0) + ($pax['infants'] ?? 0);
          ?>

            <div class="prompt-layout ">
              <!-- Narrative description -->
              <div class="prompt-description-text">
                <?php echo nl2br(htmlspecialchars($descObj['description'])); ?>
              </div>


              <!-- Chips: flight type, passengers, class of service, deadline -->
              <div class="container center" style="padding: 0;">
                <div class="prompt-meta-row center">
                  <span class="prompt-chip">
                    <span class="prompt-chip-label">Flight type</span>
                    <span class="prompt-chip-value">
                      <?php echo htmlspecialchars($descObj['flight_type'] ?? ''); ?>
                    </span>
                  </span>

                  <span class="prompt-chip">
                    <span class="prompt-chip-label">Passengers</span>
                    <span class="prompt-chip-value">
                      <?php echo (int)$totalPax; ?> pax
                      (<?php echo (int)$pax['adults']; ?>A,
                      <?php echo (int)$pax['children']; ?>C,
                      <?php echo (int)$pax['infants']; ?>I)
                    </span>
                  </span>

                  <span class="prompt-chip">
                    <span class="prompt-chip-label">Class</span>
                    <span class="prompt-chip-value">
                      <?php echo htmlspecialchars($descObj['class_of_service'] ?? ''); ?>
                    </span>
                  </span>
                <?php if (!empty($descObj['assistances']) && is_array($descObj['assistances'])): ?>
                  <div>
                    <ul class="prompt-assist-list">
                      <?php foreach ($descObj['assistances'] as $a): ?>
                        <li>
                        <span class="prompt-chip">
                          <span class="prompt-chip-label">Disability</span>
                          <span class="prompt-chip-value">
                                P <?php echo (int)$a['passenger']; ?> – 
                                <?php echo htmlspecialchars($a['type']); ?>
                          </span>
                        </span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                  </div>
              <?php endif; ?>
                </div>
              </div>
              


                        <!-- >>> NEW: show assistance requirements from quizmaker -->



              <!-- Segments + instruction -->
              <div class="prompt-sections">
                <?php if (!empty($descObj['segments']) && is_array($descObj['segments'])): ?>
                  <div>
                    <div class="prompt-section-title">Flights</div>
                    <ul class="prompt-segment-list">
                      <?php foreach ($descObj['segments'] as $idx => $seg): ?>
                        <?php
                          $org  = strtoupper(trim($seg['origin'] ?? '---'));
                          $dst  = strtoupper(trim($seg['destination'] ?? '---'));
                          $date = trim($seg['date'] ?? '');
                        ?>
                        <li class="prompt-segment-item">
                          <div class="prompt-segment-bullet"></div>
                          <div class="prompt-segment-body">
                            <div class="prompt-segment-route">
                              Flight <?php echo $idx + 1; ?>: <?php echo $org; ?> → <?php echo $dst; ?>
                            </div>
                            <?php if ($date !== ''): ?>
                              <div class="prompt-segment-date">
                                Date: <?php echo htmlspecialchars($date); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>

                
              <div>
                <div class="prompt-section-title">What you need to do</div>
                <div class="prompt-instruction">
                  <?php echo htmlspecialchars($descObj['instruction'] ?? ''); ?>
                </div>
              </div>
            </div>
          </div>

        <?php else: ?>
          <div class="student-prompt-empty">
            No quiz prompt available. Open this page with
            <code>?id=&lt;quiz_id&gt;</code> to see the prompt.
          </div>
        <?php endif; ?>
            
      </div>
    </div>

    <div class="bg-container container center">
      <form id="flightForm" action="ticket.php" method="POST" name="form_submit" autocomplete="off" class="card">
        <p>
          <label>
            <input name="flight_type" type="radio" value="ONE-WAY" <?php echo ($flight_type !== 'ROUND-TRIP') ? 'checked' : ''; ?> />
            <span>ONE-WAY</span>
          </label>
          <label>
            <input name="flight_type" type="radio" value="ROUND-TRIP" <?php echo ($flight_type === 'ROUND-TRIP') ? 'checked' : ''; ?> />
            <span>ROUND-TRIP</span>
          </label>
          <label>
            <input name="flight_type" type="radio" value="MULTI-CITY" <?php echo ($flight_type === 'MULTI-CITY') ? 'checked' : ''; ?> />
            <span>MULTI-CITY</span>
          </label>
        </p>
        <div class="row">
          <!-- SINGLE ROUTE (ONE-WAY / RT) -->
          <div id="singleRouteWrap" style="">
            <!-- ORIGIN -->
            <div class="col s4 md3">
              <div class="input-field" style="position:relative;">
                <i class="material-icons prefix">flight_takeoff</i>
                <input type="text" id="origin_autocomplete" class="center" autocomplete="off"
                  placeholder="e.g. MNL"
                  value="<?php echo !empty($origin) ? htmlspecialchars(format_airport_display($origin, $iataMap)) : ''; ?>">
                <label for="origin_autocomplete">ORIGIN</label>
                <div class="red-text"><?php echo $errors['origin'] ?? ''; ?></div>
                <input type="hidden" id="origin" name="origin" value="<?php echo htmlspecialchars($origin); ?>">
              </div>
            </div>

            <!-- DESTINATION -->
            <div class="col s4 md3">
              <div class="input-field" style="position:relative;">
                <i class="material-icons prefix">flight_land</i>
                <input type="text" id="destination_autocomplete" class="center" autocomplete="off"
                  placeholder="e.g. CEB"
                  value="<?php echo !empty($destination) ? htmlspecialchars(format_airport_display($destination, $iataMap)) : ''; ?>">
                <label for="destination_autocomplete">DESTINATION</label>
                <div class="red-text"><?php echo $errors['destination'] ?? ''; ?></div>
                <input type="hidden" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
              </div>
            </div>

            <!-- DATES (single) -->
            <div class="col s4 md3">
              <div class="center">
                <div class="row">
                  <div class="input-field col s6">
                    <i class="material-icons prefix">calendar_today</i>
                    <input type="text" id="flight-date" name="flight_date" class="datepicker" value="<?php echo htmlspecialchars($flight_date); ?>" readonly>
                    <label for="flight-date">DEPARTURE</label>
                    <div class="red-text"><?php echo $errors['flight_date'] ?? ''; ?></div>
                  </div>
                  <div class="input-field col s6" id="return-date-wrapper" style="<?php echo ($flight_type === 'ROUND-TRIP') ? '' : 'display:none;'; ?>">
                    <i class="material-icons prefix">calendar_today</i>
                    <input type="text" id="return-date" name="return_date" class="datepicker" value="<?php echo htmlspecialchars($return_date); ?>" readonly>
                    <label for="return-date">RETURN</label>
                    <div class="red-text"><?php echo $errors['return_date'] ?? ''; ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- MULTI-CITY: dynamic legs editor (hidden by default) -->
          <div id="multiLegsWrap" style="display:none; width:100%; margin-top:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <div style="font-weight:700">Multi-city Flight</div>
              <div>
                <button type="button" id="addLegBtn" class="btn btn-small">ADD FLIGHT</button>
              </div>
            </div>

            <div id="legsList">
              <!-- JS will create .leg-row entries here -->
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="container">
      <form id="bookingForm" method="POST" action="save_booking.php">
        <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quizId); ?>">
        <input type="hidden" name="origin" id="booking_origin" value="">
        <input type="hidden" name="destination" id="booking_destination" value="">
        <input type="hidden" name="flight_date" id="booking_flight_date" value="">
        <input type="hidden" name="return_date" id="booking_return_date" value="">
        <input type="hidden" name="flight_type" id="booking_flight_type" value="">
        <input type="hidden" name="origin_airline" id="booking_origin_airline" value="">
        <input type="hidden" name="destination_airline" id="booking_destination_airline" value="">
        <input type="hidden" name="booking_legs" id="booking_legs" value="">
        <div id="ticketContainer">
          <div class="ticket-card">
            <button type="button" class="remove-btn" onclick="removeTicket(this)" style="display:none;">✕</button>
            <div class="counter">Passenger 1</div>

            <div class="input-field">
              <input type="text" name="name[]" required autocomplete="false">
              <label>Full Name</label>
            </div>

            <div class="row">
              <div class="input-field col s6">
                <input type="number" name="age[]" min="0" max="130" required oninput="checkAge(this)">
                <label>Age</label>
              </div>
              <div class="input-field col s2">
                <input type="text" name="special[]" readonly placeholder="Adult/Child/Infant">
                <label>Passenger Type</label>
              </div>
            </div>

            <div class="row">
              <div class="col s6">
                <span class="field-title">Gender</span><br>
                <label class="custom-radio-inline">
                  <input type="radio" name="gender[0]" value="Male" required>
                  <span class="checkmark"></span> Male
                </label>
                <label class="custom-radio-inline">
                  <input type="radio" name="gender[0]" value="Female">
                  <span class="checkmark"></span> Female
                </label>
                <label class="custom-radio-inline">
                  <input type="radio" name="gender[0]" value="Prefer not to say">
                  <span class="checkmark"></span> Prefer not to say
                </label>
              </div>

              <div class="col s6 pwd-group">
                  <span class="field-title">Disability</span><br>
                  <label class="custom-checkbox-inline">
                    <input type="checkbox" name="pwd[]" onchange="toggleImpairment(this)">
                    <span class="checkmark"></span>
                  </label>

                  <!-- Dropdown instead of free-text -->
                  <select name="impairment[]" 
                          class="impairment-field browser-default" 
                          disabled 
                          style="display:none; max-width:260px;">
                    <option value="" disabled selected>Select assistance</option>
                    <option value="Wheelchair Assistance">Wheelchair Assistance</option>
                    <option value="Vision/Hearing Impairment">Vision/Hearing Impairment</option>
                    <option value="Reduced Mobility">Reduced Mobility</option>
                    <option value="Medical Assistance">Medical Assistance</option>
                    <option value="Other / Special Handling">Other / Special Handling</option>
                  </select>
                </div>
            </div>

            <div class="row">
              <div class="input-field col s6">
                <input type="text" name="seat[]" class="dropdown-trigger seat-input" data-target="dropdown_1" readonly required>
                <label>Seat Type</label>

                <ul id="dropdown_1" class="dropdown-content seat-options">
                  <li><a data-value="Economy">Economy</a></li>
                  <li><a data-value="Premium">Premium</a></li>
                  <li><a data-value="Business">Business</a></li>
                  <li><a data-value="First Class">First Class</a></li>
                </ul>
              </div>
              <div class="input-field col s6">
                <label for="">SEAT NUMBER</label>
                <input type="text" name="seat_number[]" class="seat-number-input" placeholder="Seat (e.g., 12A)" required>
              </div>
            </div>

          </div>
        </div>

        <div class="add-btn">
          <button type="button" id="addTicketBtn" class="btn-floating">+</button>
        </div>

        <div class="form-actions">
          <button type="button" id="openSummary" class="btn waves-effect waves-light">
            Confirm Booking
          </button>
        </div>
      </form>
    </div>

    <div id="summaryModal" class="modal">
      <div class="modal-content">
        <h4>Booking Summary</h4>
        <div id="summaryContent"></div>
        <div id="summaryError" style="color:#c62828; display:none; margin-top:10px;"></div>
      </div>
      <div class="modal-footer">
        <button id="modalConfirmBtn" type="button" class="btn green">Confirm Booking</button>
        <button id="modalCancelBtn" type="button" class="btn red">Cancel</button>
      </div>
    </div>

    <!-- Seat Picker Modal -->
    <div id="seatPickerModal" class="modal modal-fixed-footer">
      <div class="modal-content">
        <h5>Seat Picker</h5>
        <p class="grey-text text-darken-1" style="margin-top:-4px;">
          First: rows 1–6 (1–2–1), Business: 7–20 (1–2–1), Premium: 25–27 (2–4–2), Economy: 30–40 (3–4–3)
        </p>

        <div class="seat-map-wrapper">
          <div id="cabinContainer"></div>
          <div id="seatMap" class="seat-map" aria-label="Seat map" role="application"></div>
        </div>

        <div class="legend">
          <span><span class="box selected"></span> Selected</span>
          <span><span class="box disabled"></span> Taken / Unavailable</span>
        </div>
        <div class="legend">
          <span><span class="box" style="background:#1e88e5"></span> First Class</span>
          <span><span class="box" style="background:#fb8c00"></span> Business Class</span>
          <span><span class="box" style="background:#7e57c2"></span> Premium Economy</span>
          <span><span class="box" style="background:#43a047"></span> Economy</span>
        </div>

        <div class="selection-summary">
          <h6>Selected seat</h6>
          <div id="selectedChips" class="chips"></div>
          <p id="summaryText" class="grey-text text-darken-1"></p>
        </div>
      </div>

      <div class="modal-footer">
        <a id="clearSeatSelectionBtn" class="btn-flat">Clear</a>
        <a class="modal-close btn" id="seatModalDoneBtn">Done</a>
      </div>
    </div>

<script>const IATA_DATA = <?php echo json_encode($iataData, JSON_UNESCAPED_UNICODE); ?>;</script>

<script>
  // 🔹 Make the PHP-generated student prompt available in JS
  const QUIZ_PROMPT = <?php echo json_encode($descObj['description'] ?? null); ?>;
  const QUIZ_TYPE   = <?php echo json_encode($quiz['input_type'] ?? null); ?>;
  window.IATA_DATA = <?php echo json_encode($iataData); ?>;

  document.addEventListener('DOMContentLoaded', function () {
    // ====== SEAT PICKER (BOEING 777) CORE VARS ======
    let seatPickerModalInstance = null;
    let seatNumberTargetInput = null;       // which passenger input we're editing
    let activeCabinKeyForSelection = null;  // 'economy' | 'business' | 'premium' | 'first'
    let selectedSeats = new Set();          // single seat at a time
    let lastClickedSeat = null;
    let currentFilterKey = 'all';


    const CABINS = [
      {
        key: 'first',
        name: 'First Class',
        className: 'first',
        startRow: 1,
        endRow: 6,
        letters: ['A', '', 'D', 'G', '', 'K'] // 1–2–1
      },
      {
        key: 'business',
        name: 'Business Class',
        className: 'business',
        startRow: 7,
        endRow: 20,
        letters: ['A', '', 'D', 'G', '', 'K']
      },
      {
        key: 'premium',
        name: 'Premium Economy',
        className: 'premium',
        startRow: 25,
        endRow: 27,
        letters: ['A','B','','D','E','F','G','','J','K']
      },
      {
        key: 'economy',
        name: 'Economy',
        className: 'economy',
        startRow: 30,
        endRow: 40,
        letters: ['A','B','C','','D','E','F','G','','H','J','K']
      }
    ];
    
    function createCabinHeader(name, rowsText, className, key) {
      const wrap = document.createElement('div');
      wrap.className = 'cabin-header ' + className;
      wrap.setAttribute('data-cabin-key', key);
      const title = document.createElement('h6');
      title.textContent = name + ' (' + rowsText + ')';
      const line = document.createElement('div');
      line.className = 'line';
      wrap.appendChild(title);
      wrap.appendChild(line);
      return wrap;
    }

    function applyCabinFilter(filterKey) {
      currentFilterKey = filterKey || 'all';

      const rows = document.querySelectorAll('.seat-row');
      const headers = document.querySelectorAll('.cabin-header');

      rows.forEach(row => {
        const key = row.getAttribute('data-cabin-key');
        row.style.display = (key === filterKey) ? 'flex' : 'none';
      });

      headers.forEach(header => {
        const key = header.getAttribute('data-cabin-key');
        header.style.display = (key === filterKey) ? 'flex' : 'none';
      });
    }

    function clearSeatSelections() {
      document.querySelectorAll('.seat.selected').forEach(s => {
        s.classList.remove('selected');
        s.setAttribute('aria-pressed', 'false');
      });
      selectedSeats.clear();
      updateSeatSummary();
    }

    function updateSeatSummary() {
      const selectedChipsEl = document.getElementById('selectedChips');
      const summaryText = document.getElementById('summaryText');
      if (!selectedChipsEl || !summaryText) return;

      selectedChipsEl.innerHTML = '';

      const seats = Array.from(selectedSeats);
      if (seats.length) {
        const s = seats[0];
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.textContent = s;
        selectedChipsEl.appendChild(chip);
        summaryText.textContent = `Selected seat: ${s}`;
      } else {
        summaryText.textContent = 'No seat selected.';
      }
    }

    function getTakenSeatsExcludingCurrent() {
      const set = new Set();
      const inputs = document.querySelectorAll('input[name="seat_number[]"]');
      inputs.forEach(inp => {
        if (inp === seatNumberTargetInput) return;
        const v = (inp.value || '').trim().toUpperCase();
        if (v) set.add(v);
      });
      return set;
    }

    function markTakenSeatsDisabled() {
      const taken = getTakenSeatsExcludingCurrent();
      const allSeats = document.querySelectorAll('.seat');
      allSeats.forEach(seatEl => {
        const id = seatEl.getAttribute('data-seat');
        if (taken.has(id)) {
          seatEl.classList.add('disabled');
          seatEl.setAttribute('aria-disabled', 'true');
          seatEl.setAttribute('title', id + ' (taken)');
        } else {
          seatEl.classList.remove('disabled');
          seatEl.removeAttribute('aria-disabled');
        }
      });
    }

    function onSeatClick(ev, seatBtn) {
      if (seatBtn.classList.contains('disabled')) return;

      const seatCabinKey = seatBtn.getAttribute('data-cabin-key');

      // must be in the active cabin
      if (activeCabinKeyForSelection && seatCabinKey !== activeCabinKeyForSelection) {
        if (typeof M !== 'undefined' && M.toast) {
          M.toast({html: 'Please pick a seat in the selected class only.'});
        }
        return;
      }

      const seatId = seatBtn.getAttribute('data-seat');

      // single selection
      if (!seatBtn.classList.contains('selected') && selectedSeats.size >= 1) {
        document.querySelectorAll('.seat.selected').forEach(el => {
          el.classList.remove('selected');
          el.setAttribute('aria-pressed', 'false');
        });
        selectedSeats.clear();
      }

      const isSelected = seatBtn.classList.contains('selected');
      if (isSelected) {
        seatBtn.classList.remove('selected');
        seatBtn.setAttribute('aria-pressed', 'false');
        selectedSeats.delete(seatId);
      } else {
        seatBtn.classList.add('selected');
        seatBtn.setAttribute('aria-pressed', 'true');
        selectedSeats.add(seatId);
      }

      lastClickedSeat = seatId;
      updateSeatSummary();

      if (selectedSeats.size === 1 && seatNumberTargetInput) {
        const chosen = Array.from(selectedSeats)[0];
        seatNumberTargetInput.value = chosen;
        if (typeof M !== 'undefined' && M.updateTextFields) {
          M.updateTextFields();
        }
        if (seatPickerModalInstance && seatPickerModalInstance.close) {
          seatPickerModalInstance.close();
        }
      }
    }

    function generateSeatLayout() {
      const seatMapEl = document.getElementById('seatMap');
      const cabinContainerEl = document.getElementById('cabinContainer');
      if (!seatMapEl || !cabinContainerEl) return;

      seatMapEl.innerHTML = '';
      cabinContainerEl.innerHTML = '';
      selectedSeats.clear();
      updateSeatSummary();

      CABINS.forEach(cabin => {
        const rowsText = cabin.startRow + '–' + cabin.endRow;
        const headerEl = createCabinHeader(cabin.name, rowsText, cabin.className, cabin.key);
        cabinContainerEl.appendChild(headerEl);

        for (let r = cabin.startRow; r <= cabin.endRow; r++) {
          const rowEl = document.createElement('div');
          rowEl.className = 'seat-row';
          rowEl.setAttribute('data-row', r);
          rowEl.setAttribute('data-cabin', cabin.name);
          rowEl.setAttribute('data-cabin-key', cabin.key);

          const rowLabel = document.createElement('div');
          rowLabel.className = 'row-label';
          rowLabel.textContent = r;
          rowEl.appendChild(rowLabel);

          cabin.letters.forEach(part => {
            if (part === '') {
              const aisle = document.createElement('div');
              aisle.className = 'aisle';
              rowEl.appendChild(aisle);
              return;
            }

            const seatId = `${r}${part}`;
            const seatBtn = document.createElement('button');
            seatBtn.type = 'button';
            seatBtn.className = 'seat ' + cabin.className;
            seatBtn.textContent = part;
            seatBtn.setAttribute('data-seat', seatId);
            seatBtn.setAttribute('data-cabin', cabin.name);
            seatBtn.setAttribute('data-cabin-key', cabin.key);
            seatBtn.setAttribute('aria-pressed', 'false');
            seatBtn.setAttribute('title', `${seatId} – ${cabin.name}`);
            seatBtn.setAttribute('aria-label', `Seat ${seatId} in ${cabin.name}`);

            seatBtn.addEventListener('click', (ev) => onSeatClick(ev, seatBtn));
            seatBtn.addEventListener('keydown', (ev) => {
              if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                seatBtn.click();
              }
            });

            rowEl.appendChild(seatBtn);
          });

          seatMapEl.appendChild(rowEl);
        }
      });

      applyCabinFilter('economy'); // default
    }
    
    // ===== General helpers =====
    /* ---------- MULTI-CITY: legs UI & helpers ---------- */
  function createLegRow(idx, legIndex, prefill) {
    // prefill: { origin_display, origin_iata, destination_display, destination_iata, date }
    const wrap = document.createElement('div');
    wrap.className = 'leg-row';
    wrap.dataset.legIndex = legIndex;
    wrap.style = 'display:flex; gap:8px; align-items:center; margin-bottom:6px;';

    wrap.innerHTML = `
      <div style="flex:1;">
        <div class="input-field">
          <input type="text" class="leg-origin-display" id="leg_origin_display_${idx}_${legIndex}">
          <label for="leg_origin_display_${idx}_${legIndex}">Origin</label>
          <input type="hidden" class="leg-origin-iata" id="leg_origin_iata_${idx}_${legIndex}" name="leg_origin_iata[]">
        </div>
      </div>
      <div style="flex:1;">
        <div class="input-field">
          <input type="text" class="leg-destination-display" id="leg_dest_display_${idx}_${legIndex}" >
          <label for="leg_dest_display_${idx}_${legIndex}">Destination</label>
          <input type="hidden" class="leg-destination-iata" id="leg_dest_iata_${idx}_${legIndex}" name="leg_destination_iata[]">
        </div>
      </div>
      <div style="width:180px;">
        <div class="input-field">
          <input type="text" class="leg-date datepicker" id="leg_date_${idx}_${legIndex}" name="leg_date[]">
          <label for="leg_date_${idx}_${legIndex}">Date</label>
        </div>
      </div>
      <div style="width:36px;">
        <button type="button" class="btn-flat remove-leg" title="Remove leg">&minus;</button>
      </div>
    `;

    // prefill values if available
    if (prefill) {
      const od = wrap.querySelector('.leg-origin-display');
      const oi = wrap.querySelector('.leg-origin-iata');
      const dd = wrap.querySelector('.leg-destination-display');
      const di = wrap.querySelector('.leg-destination-iata');
      const ld = wrap.querySelector('.leg-date');

      if (od && prefill.origin_display) od.value = prefill.origin_display;
      if (oi && prefill.origin_iata) oi.value = prefill.origin_iata;
      if (dd && prefill.destination_display) dd.value = prefill.destination_display;
      if (di && prefill.destination_iata) di.value = prefill.destination_iata;
      if (ld && prefill.date) ld.value = prefill.date;
    }

    return wrap;
  }

  const legsListEl = document.getElementById('legsList');
  const singleRouteWrap = document.getElementById('singleRouteWrap');
  const multiLegsWrap = document.getElementById('multiLegsWrap');
  const addLegBtn = document.getElementById('addLegBtn');

  function clearAllLegs() {
    if (!legsListEl) return;
    legsListEl.innerHTML = '';
  }

  function addLeg(prefill = null) {
    if (!legsListEl) return;
    const idx = 0; // only one ticket context — keep idx 0 to match IDs
    const legIndex = legsListEl.querySelectorAll('.leg-row').length;
    const row = createLegRow(idx, legIndex, prefill);
    legsListEl.appendChild(row);

    // init datepicker for this row
    const dateInput = row.querySelector('.datepicker');
    if (dateInput) {
      M.Datepicker.init(dateInput, { format:'yyyy-mm-dd', minDate: new Date(), autoClose:true });
    }

    // attach autocomplete/behaviour for display <-> iata mapping
    const originDisplay = row.querySelector('.leg-origin-display');
    const originIata = row.querySelector('.leg-origin-iata');
    const destDisplay = row.querySelector('.leg-destination-display');
    const destIata = row.querySelector('.leg-destination-iata');

    if (QUIZ_TYPE === 'code-airport') {
      // users will type airport name (student answers with name) — store name in display and also attempt to lookup code
      if (originDisplay) initAirportNameDropdown(originDisplay.id, originIata.id);
      if (destDisplay) initAirportNameDropdown(destDisplay.id, destIata.id);
    } else {
      // users will input IATA code directly
      if (originDisplay) initPlainIataInput(originDisplay.id, originIata.id);
      if (destDisplay) initPlainIataInput(destDisplay.id, destIata.id);
    }

    // hide remove on first leg
    const removeBtn = row.querySelector('.remove-leg');
    if (removeBtn) {
      removeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        row.remove();
        // re-index ids if you want (not required)
      });
    }
  }

  if (addLegBtn) {
    addLegBtn.addEventListener('click', function (e) {
      e.preventDefault();
      addLeg();
    });
  }

  // Flight type radio toggles (show multi legs or single route)
  document.querySelectorAll('input[name="flight_type"]').forEach(radio => {
    radio.addEventListener('change', function () {
      if (this.value === 'MULTI-CITY') {
        // show legs editor
        if (singleRouteWrap) singleRouteWrap.style.display = 'none';
        if (multiLegsWrap) multiLegsWrap.style.display = '';
        // create at least one leg if none
        if (legsListEl && legsListEl.children.length === 0) addLeg();
      } else {
        // hide legs editor
        if (multiLegsWrap) multiLegsWrap.style.display = 'none';
        if (singleRouteWrap) singleRouteWrap.style.display = '';
      }

      // hide return-date for non RT
      if (this.value === 'ROUND-TRIP') {
        if (returnWrapper) returnWrapper.style.display = '';
      } else {
        if (returnWrapper) returnWrapper.style.display = 'none';
        if (returnInput) returnInput.value = '';
      }
    });
  });

  // When building booking hidden fields, include legs if present
  window.fillBookingHiddenFlightFields = function(flight) {
    // hidden fields
    const bOrigin = document.getElementById('booking_origin');
    const bDestination = document.getElementById('booking_destination');
    const bDate = document.getElementById('booking_flight_date');
    const bReturn = document.getElementById('booking_return_date');
    const bType = document.getElementById('booking_flight_type');
    const bOriginAir = document.getElementById('booking_origin_airline');
    const bDestAir = document.getElementById('booking_destination_airline');
    const bLegs = document.getElementById('booking_legs');

    // allow caller to pass a flight object; otherwise use DOM as source of truth
    flight = flight || {};

    // Detect multi-city legs in DOM and the selected flight type
    const legRows = document.querySelectorAll('.leg-row');
    const selectedType = (document.querySelector('input[name="flight_type"]:checked') || {}).value || 'ONE-WAY';

    if (legRows && legRows.length > 0 && selectedType === 'MULTI-CITY') {
      const legs = [];
      legRows.forEach(lr => {
        const oiEl = lr.querySelector('.leg-origin-iata');
        const diEl = lr.querySelector('.leg-destination-iata');
        const dateEl = lr.querySelector('.leg-date');
        const originIata = (oiEl && oiEl.value || '').toUpperCase();
        const destIata = (diEl && diEl.value || '').toUpperCase();
        const dateVal = dateEl && dateEl.value ? dateEl.value : '';
        legs.push({ origin: originIata, destination: destIata, date: dateVal });
      });

      const first = legs[0] || null;
      const last = legs[legs.length - 1] || null;

      if (bOrigin) bOrigin.value = first ? (first.origin || '') : '';
      if (bDestination) bDestination.value = last ? (last.destination || '') : '';
      if (bDate) bDate.value = first ? (first.date || '') : '';
      if (bReturn) bReturn.value = last ? (last.date || '') : '';
      if (bType) bType.value = 'MULTI-CITY';
      if (bOriginAir) bOriginAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[bOrigin.value]) || '';
      if (bDestAir) bDestAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[bDestination.value]) || '';
      if (bLegs) bLegs.value = JSON.stringify(legs); // <<< use standard JS stringify
      return;
    }

    // fallback = single-route behavior (ONE-WAY / ROUND-TRIP)
    const originCode = flight.origin || (document.getElementById('origin') && document.getElementById('origin').value) || '';
    const destCode   = flight.destination || (document.getElementById('destination') && document.getElementById('destination').value) || '';
    const depDate    = flight.flight_date || (document.getElementById('flight-date') && document.getElementById('flight-date').value) || '';
    const retDate    = flight.return_date || (document.getElementById('return-date') && document.getElementById('return-date').value) || '';
    const typeRadio  = document.querySelector('input[name="flight_type"]:checked');
    const typeVal    = flight.flight_type || (typeRadio ? typeRadio.value : 'ONE-WAY');

    if (bOrigin) bOrigin.value = originCode;
    if (bDestination) bDestination.value = destCode;
    if (bDate) bDate.value = depDate;
    if (bReturn) bReturn.value = retDate;
    if (bType) bType.value = typeVal;
    if (bOriginAir) bOriginAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[originCode]) || '';
    if (bDestAir) bDestAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[destCode]) || '';
    if (bLegs) bLegs.value = '';
  };

      function initIataDropdown(inputId) {
        const input = document.getElementById(inputId);
        if (!input || typeof M === 'undefined' || !M.Autocomplete || !window.IATA_DATA) return;

        const data = {};
        // Build autocomplete entries: "MNL — MANILA / PHILIPPINES / NINOY AQUINO INTL"
        window.IATA_DATA.forEach(it => {
          const parts = [];
          if (it.city) parts.push(it.city);
          if (it.country) parts.push(it.country);
          if (it.name) parts.push(it.name);
          const label = `${it.code} — ${parts.join(' / ')}`;
          data[label] = null; // Materialize wants { text: null } if no icon
        });

        M.Autocomplete.init(input, {
          data,
          minLength: 1,
          onAutocomplete: (val) => {
            // Extract first 3 letters as IATA code
            const m = val.match(/^([A-Za-z]{3})/);
            if (m) {
              input.value = m[1].toUpperCase();
              // Trigger input so initPlainIataInput syncs hidden field
              input.dispatchEvent(new Event('input'));
            }
          }
        });
      }

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
          return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
        });
      }
      

      window.IATA_LOOKUP = {};
      IATA_DATA.forEach(it => window.IATA_LOOKUP[it.code] = it.name);

      const elemsDate = document.querySelectorAll('.datepicker');
      M.Datepicker.init(elemsDate, {
        format: 'yyyy-mm-dd',
        minDate: new Date(),
        autoClose: true,
      });

      const dropdowns = document.querySelectorAll('.dropdown-trigger');
      M.Dropdown.init(dropdowns);

      const modalElem = document.getElementById('summaryModal');
      const summaryModal = M.Modal.init(modalElem, {dismissible: true});
      const openBtn = document.getElementById('openSummary');
      const confirmBtn = document.getElementById('modalConfirmBtn');
      const cancelBtn = document.getElementById('modalCancelBtn');
      const summaryContent = document.getElementById('summaryContent');
      const summaryError = document.getElementById('summaryError');
      const bookingForm = document.getElementById('bookingForm');
      const flightForm = document.getElementById('flightForm');

      // ===== init seat picker modal =====
      const seatPickerElem = document.getElementById('seatPickerModal');
      if (seatPickerElem && M && M.Modal) {
        seatPickerModalInstance = M.Modal.init(seatPickerElem, {dismissible:true});
        generateSeatLayout();
      }
      document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('modal-overlay')) {
          if (seatPickerModalInstance && seatPickerModalInstance.close) {
            seatPickerModalInstance.close();
          }
        }
      });
      const clearSeatSelectionBtn = document.getElementById('clearSeatSelectionBtn');
      const seatModalDoneBtn = document.getElementById('seatModalDoneBtn');

      if (clearSeatSelectionBtn) {
        clearSeatSelectionBtn.addEventListener('click', function(e){
          e.preventDefault();
          clearSeatSelections();
        });
      }

      if (seatModalDoneBtn) {
        seatModalDoneBtn.addEventListener('click', function (e) {
          e.preventDefault();

          if (seatNumberTargetInput) {
            const chosen = Array.from(selectedSeats)[0] || '';
            if (chosen) {
              seatNumberTargetInput.value = chosen;
              if (typeof M !== 'undefined' && M.updateTextFields) {
                M.updateTextFields();
              }
            }
          }

          if (seatPickerModalInstance && seatPickerModalInstance.close) {
            seatPickerModalInstance.close();
          }
        });
      }

      function initAirportNameInput(displayId, hiddenId) {
        const display = document.getElementById(displayId);
        const hidden  = document.getElementById(hiddenId);
        if (!display || !hidden) return;

        display.addEventListener('input', function () {
          hidden.value = (this.value || '').toUpperCase();
        });

        display.addEventListener('blur', function () {
          hidden.value = (this.value || '').toUpperCase();
        });

        if (display.value) {
          hidden.value = display.value.toUpperCase();
        }
      }
      // For quiz_type = 'code-airport': dropdown shows ONLY airport name/city, no code
      function initAirportNameDropdown(inputId, hiddenId) {
        const input  = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        if (!input || !hidden || typeof M === 'undefined' || !M.Autocomplete || !window.IATA_DATA) return;

        const data = {};

        // Build dropdown entries: NAME - CITY - COUNTRY (NO IATA CODE)
        window.IATA_DATA.forEach(it => {
          const parts = [];
          if (it.name)    parts.push(it.name.toUpperCase());
          if (it.city)    parts.push(it.city.toUpperCase());
          if (it.country) parts.push(it.country.toUpperCase());
          if (!parts.length) return;

          const label = parts.join(' - ');  // 👈 NO it.code here
          data[label] = null;               // Materialize autocomplete format
        });

        M.Autocomplete.init(input, {
          data,
          minLength: 1,
          onAutocomplete: (val) => {
            // Show the full label in the visible input (name / city / country)
            input.value = val.toUpperCase();

            // Store the student's "answer" in the hidden field (you want name, not code)
            hidden.value = val.toUpperCase();

            // If you EVER want to store the IATA code internally instead, you can change this:
            // const match = window.IATA_DATA.find(it => {
            //   const parts = [];
            //   if (it.name)    parts.push(it.name.toUpperCase());
            //   if (it.city)    parts.push(it.city.toUpperCase());
            //   if (it.country) parts.push(it.country.toUpperCase());
            //   return parts.join(' - ') === val.toUpperCase();
            // });
            // hidden.value = match ? match.code.toUpperCase() : val.toUpperCase();

            input.dispatchEvent(new Event('input'));
          }
        });
      }


      function initPlainIataInput(displayId, hiddenId) {
        const display = document.getElementById(displayId);
        const hidden  = document.getElementById(hiddenId);
        if (!display || !hidden) return;

        display.addEventListener('input', function () {
          let v = (this.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
          this.value = v;
          hidden.value = v;
        });

        display.addEventListener('blur', function () {
          hidden.value = (hidden.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0,3);
        });

        if (display.value) {
          const m = display.value.match(/^([A-Za-z]{3})/);
          if (m) hidden.value = m[1].toUpperCase();
        }
      }

      if (QUIZ_TYPE === 'code-airport') {
        // Student answers AIRPORT NAME -> allow any text + name-only dropdown
        initAirportNameInput('origin_autocomplete', 'origin');
        initAirportNameInput('destination_autocomplete', 'destination');
        initAirportNameDropdown('origin_autocomplete', 'origin');
        initAirportNameDropdown('destination_autocomplete', 'destination');

        } else {
          // Default / airport-code: student answers IATA code -> 3 letters only
          initPlainIataInput('origin_autocomplete', 'origin');
          initPlainIataInput('destination_autocomplete', 'destination');
        }

      // seat class dropdown click handler (for first ticket; clones are wired later)
      document.querySelectorAll('.seat-options a').forEach(item => {
        item.addEventListener('click', function (e) {
          e.preventDefault();
          const value = this.getAttribute('data-value');
          const dropdown = this.closest('.dropdown-content');
          const targetId = dropdown && dropdown.getAttribute('id');
          const input = document.querySelector(`[data-target="${targetId}"]`);

          if (input) {
            input.value = value;
            M.updateTextFields();

            // 🔁 Reset seat number when seat type changes
            const card = input.closest('.ticket-card');
            if (card) {
              const seatNumInput = card.querySelector('.seat-number-input');
              if (seatNumInput) {
                seatNumInput.value = '';
              }
            }
          }
        });
      });

      let ticketCount = 1;
      const maxTickets = 9;

      function attachSeatNumberPickerHandlers(root) {
        const cards = root ? [root] : Array.from(document.querySelectorAll('.ticket-card'));
        cards.forEach(card => {
          const seatNumberInput = card.querySelector('.seat-number-input');
          const seatTypeInput = card.querySelector('input[name="seat[]"]');

          if (!seatNumberInput) return;

          function openSeatPicker() {
            if (!seatPickerModalInstance) return;

            seatNumberTargetInput = seatNumberInput;

            // decide cabin from seat type
            let cabinKey = 'economy';
            const rawSeatType = (seatTypeInput && seatTypeInput.value || '').toLowerCase();
            if (rawSeatType.includes('first')) cabinKey = 'first';
            else if (rawSeatType.includes('business')) cabinKey = 'business';
            else if (rawSeatType.includes('premium')) cabinKey = 'premium';
            else cabinKey = 'economy';

            activeCabinKeyForSelection = cabinKey;
            applyCabinFilter(cabinKey);

            clearSeatSelections();
            markTakenSeatsDisabled();

            // preselect existing seat if valid for cabin
            const currentVal = (seatNumberInput.value || '').trim().toUpperCase();
            if (currentVal) {
              const seatEl = document.querySelector(`.seat[data-seat="${currentVal}"]`);
              if (seatEl && !seatEl.classList.contains('disabled')) {
                const seatCabinKey = seatEl.getAttribute('data-cabin-key');
                if (seatCabinKey === cabinKey) {
                  seatEl.classList.add('selected');
                  seatEl.setAttribute('aria-pressed','true');
                  selectedSeats.add(currentVal);
                  updateSeatSummary();
                }
              }
            }

            seatPickerModalInstance.open();
          }

          seatNumberInput.addEventListener('click', openSeatPicker);
          seatNumberInput.addEventListener('focus', openSeatPicker);
        });
      }

      // attach to initial card
      attachSeatNumberPickerHandlers();

      document.getElementById('addTicketBtn').addEventListener('click', () => {
        if (ticketCount >= maxTickets) {
          M.toast({ html: 'Maximum of 9 passengers per booking!' });
          return;
        }
        const container = document.getElementById('ticketContainer');
        const firstTicket = container.querySelector('.ticket-card');
        const newTicket = firstTicket.cloneNode(true);
        ticketCount++;
        const newDropdownId = 'dropdown_' + ticketCount;

        const seatInput = newTicket.querySelector('input[name="seat[]"]');
        const dropdownContent = newTicket.querySelector('.dropdown-content');
        if (seatInput) seatInput.setAttribute('data-target', newDropdownId);
        if (dropdownContent) dropdownContent.setAttribute('id', newDropdownId);

        newTicket.querySelectorAll('input').forEach(input => {
          if (['checkbox', 'radio'].includes(input.type)) input.checked = false;
          else {
            input.value = '';
            if (input.name === 'special[]') input.readOnly = true;
          }
        });

        const index = ticketCount - 1;
        newTicket.querySelectorAll('input[type="radio"]').forEach(r => r.name = `gender[${index}]`);
        const impairmentField = newTicket.querySelector('.impairment-field');
          if (impairmentField) {
            impairmentField.name = `impairment[${index}]`;
            impairmentField.style.display = 'none';
            impairmentField.disabled = true;
            impairmentField.value = '';
            if (impairmentField.tagName === 'SELECT') {
              impairmentField.selectedIndex = 0;
            }
          }
        const pwdCheckbox = newTicket.querySelector('input[type="checkbox"]');
        if (pwdCheckbox) pwdCheckbox.name = `pwd[${index}]`;

        newTicket.querySelector('.counter').textContent = `Passenger ${ticketCount}`;
        const rem = newTicket.querySelector('.remove-btn');
        if (rem) rem.style.display = 'block';

        container.appendChild(newTicket);
        M.updateTextFields();

        attachSeatNumberPickerHandlers(newTicket);

        const newDropdownTrigger = newTicket.querySelector('.dropdown-trigger');
        if (newDropdownTrigger) M.Dropdown.init(newDropdownTrigger);

        newTicket.querySelectorAll('.seat-options a').forEach(item => {
          item.addEventListener('click', function(e) {
            e.preventDefault();
            const value = this.getAttribute('data-value');
            const dropdown = this.closest('.dropdown-content');
            const targetId = dropdown && dropdown.getAttribute('id');
            const input = newTicket.querySelector(`[data-target="${targetId}"]`);
            if (input) { input.value = value; M.updateTextFields(); }
          });
        });
      });

      function removeTicket(btn) {
        const card = btn.closest('.ticket-card');
        if (!card || ticketCount <= 1) return;
        card.remove();
        ticketCount--;
        document.querySelectorAll('.ticket-card').forEach((card, index) => {
          const newIndex = index + 1;
          card.querySelector('.counter').textContent = `Passenger ${newIndex}`;
          const seatInput = card.querySelector('input[name="seat[]"]');
          const dropdownContent = card.querySelector('.dropdown-content');
          const newDropdownId = 'dropdown_' + newIndex;
          if (seatInput) seatInput.setAttribute('data-target', newDropdownId);
          if (dropdownContent) dropdownContent.setAttribute('id', newDropdownId);
          card.querySelectorAll('input[type="radio"]').forEach(r => r.name = `gender[${index}]`);
          const impairmentField = card.querySelector('.impairment-field');
          if (impairmentField) impairmentField.name = `impairment[${index}]`;
          const pwdCheckbox = card.querySelector('input[type="checkbox"]');
          if (pwdCheckbox) pwdCheckbox.name = `pwd[${index}]`;
          if (index === 0) {
            const rem = card.querySelector('.remove-btn');
            if (rem) rem.style.display = 'none';
          }
        });
      }
      window.removeTicket = removeTicket;

      function checkAge(input) {
        let age = parseInt(input.value);
        if (!isNaN(age) && age > 130) {
          age = 130;
          input.value = age;
        }
        const card = input.closest('.ticket-card');
        const typeField = card.querySelector('input[name="special[]"]');
        if (!isNaN(age)) {
          if (age <= 2) typeField.value = 'Infant';
          else if (age >= 3 && age <= 12) typeField.value = 'Child';
          else typeField.value = 'Adult';
        } else {
          typeField.value = '';
        }
      }
      window.checkAge = checkAge;

      function toggleImpairment(checkbox) {
        const field = checkbox.closest('.pwd-group').querySelector('.impairment-field');
        if (!field) return;

        if (checkbox.checked) {
          field.style.display = 'inline-block';
          field.disabled = false;
        } else {
          field.style.display = 'none';
          field.disabled = true;

          // Reset value
          field.value = '';
          if (field.tagName === 'SELECT') {
            field.selectedIndex = 0; // back to "Select assistance"
          }
        }
      }
      window.toggleImpairment = toggleImpairment;

      const returnWrapper = document.getElementById('return-date-wrapper');
      const returnInput = document.getElementById('return-date');
      document.querySelectorAll('input[name="flight_type"]').forEach(radio => {
        radio.addEventListener('change', function () {
          if (this.value === 'ROUND-TRIP') {
            if (returnWrapper) returnWrapper.style.display = '';
          } else {
            if (returnWrapper) returnWrapper.style.display = 'none';
            if (returnInput) returnInput.value = '';
          }
        });
      });

      function showServerErrors(errors) {
        document.querySelectorAll('.red-text').forEach(el => el.textContent = '');
        if (!errors) return;
        if (errors.origin) {
          const originErr = document.querySelector('#origin_autocomplete').closest('.input-field').querySelector('.red-text');
          if (originErr) originErr.textContent = errors.origin;
        }
        if (errors.destination) {
          const destErr = document.querySelector('#destination_autocomplete').closest('.input-field').querySelector('.red-text');
          if (destErr) destErr.textContent = errors.destination;
        }
        if (errors.flight_date) {
          const dateErr = document.querySelector('#flight-date').closest('.input-field').querySelector('.red-text');
          if (dateErr) dateErr.textContent = errors.flight_date;
        }
        if (errors.return_date) {
          const retErrWrapper = document.querySelector('#return-date');
          if (retErrWrapper) {
            const retErr = retErrWrapper.closest('.input-field').querySelector('.red-text');
            if (retErr) retErr.textContent = errors.return_date;
          }
        }
        if (errors.login) M.toast({ html: errors.login });
        if (errors.db) M.toast({ html: 'Server error: ' + errors.db });
      }


      function buildSummaryAndOpen(flight) {
        fillBookingHiddenFlightFields(flight);

        const tickets = document.querySelectorAll('.ticket-card');
        const passengerCount = tickets.length;
        const typeLabel = (flight.flight_type === 'ROUND-TRIP') ? 'ROUND-TRIP (round trip)' : 'One-way';

        // 🔹 Start summary HTML, include quiz student prompt if available
        let html = '';

        if (QUIZ_PROMPT) {
          html += `<p><strong>Student prompt:</strong> ${escapeHtml(QUIZ_PROMPT)}</p><hr>`;
        }

        html += `<p><strong>Origin:</strong> ${escapeHtml(flight.origin)}</p>
                <p><strong>Destination:</strong> ${escapeHtml(flight.destination)}</p>
                <p><strong>Departure:</strong> ${escapeHtml(flight.flight_date)}</p>`;

        if (flight.flight_type === 'ROUND-TRIP') {
          html += `<p><strong>Return:</strong> ${escapeHtml(flight.return_date)}</p>`;
        }

        html += `<p><strong>Type:</strong> ${escapeHtml(typeLabel)}</p>
                <p><strong>Passengers:</strong> ${passengerCount}</p><hr><h5>Passenger Details:</h5>`;

        tickets.forEach((card, idx) => {
          const name = (card.querySelector('input[name="name[]"]') || {}).value || '';
          const age = (card.querySelector('input[name="age[]"]') || {}).value || '';
          const type = (card.querySelector('input[name="special[]"]') || {}).value || '';
          const seat = (card.querySelector('input[name="seat[]"]') || {}).value || '';
          const seatNumber = (card.querySelector('input[name="seat_number[]"]') || {}).value || '';
          const genderRadio = card.querySelector('input[type="radio"]:checked');
          const gender = genderRadio ? genderRadio.value : 'Not set';
          const pwdCheckbox = card.querySelector('input[type="checkbox"]');
          const pwd = (pwdCheckbox && pwdCheckbox.checked) ? (card.querySelector('.impairment-field').value || 'PWD') : 'None';

          html += `<div style="margin-bottom:10px;">
                    <strong>Passenger ${idx + 1}</strong><br>
                    Name: ${escapeHtml(name)}<br>
                    Age: ${escapeHtml(age)} (${escapeHtml(type)})<br>
                    Gender: ${escapeHtml(gender)}<br>
                    Seat Class: ${escapeHtml(seat)}<br>
                    Seat Number: ${escapeHtml(seatNumber)}<br>
                    Disability: ${escapeHtml(pwd)}<br>
                  </div><hr>`;
        });

        summaryContent.innerHTML = html;
        summaryModal.open();
      }

      openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        showServerErrors(null);
        summaryError.style.display = 'none';

        fillBookingHiddenFlightFields();

        const fd = new FormData(flightForm || document.createElement('form'));
        fd.set('form_submit', '1');
        fd.set('ajax_validate', '1');
        fd.set('quiz_type', QUIZ_TYPE || '');
        // include numeric quiz_id if present in bookingForm hidden field (fallback)
        const qidField = document.querySelector('input[name="quiz_id"]');
        if (qidField && qidField.value) fd.set('quiz_id', qidField.value);

        // ensure we call ticket.php with the same querystring the page was loaded with
        const ajaxUrl = 'ticket.php' + (window.location.search || '');

        fetch(ajaxUrl, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(resp => resp.text())
        .then(text => {
          // try to parse JSON; if the server returned non-JSON, show helpful error
          let json = null;
          try {
            json = JSON.parse(text);
          } catch (err) {
            console.error('Validation response is not valid JSON:', text);
            M.toast({ html: 'Server returned unexpected response while validating. Check console.' });
            summaryError.style.display = 'block';
            summaryError.textContent = 'Server validation error — see console for details.';
            return;
          }

          if (!json) {
            M.toast({ html: 'Invalid server response' });
            return;
          }
          if (!json.ok) {
            showServerErrors(json.errors || {});
            if (json.errors && Object.keys(json.errors).length) {
              summaryError.style.display = 'block';
              summaryError.textContent = 'Please fix the highlighted errors before continuing.';
              summaryError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
          }
          buildSummaryAndOpen(json.flight);
        })
        .catch(err => {
          console.error('Validation error', err);
          M.toast({ html: 'Network or server error while validating. Try again.' });
        });
      });

      cancelBtn.addEventListener('click', function (e) {
        e.preventDefault();
        summaryModal.close();
      });

      confirmBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const typeRadio = document.querySelector('input[name="flight_type"]:checked');
        const flight = {
          origin: document.getElementById('origin').value.trim(),
          destination: document.getElementById('destination').value.trim(),
          flight_date: document.getElementById('flight-date').value.trim(),
          return_date: document.getElementById('return-date') ? document.getElementById('return-date').value.trim() : '',
          flight_type: typeRadio ? typeRadio.value : 'ONE-WAY'
        };

        fillBookingHiddenFlightFields(flight);

        // disable confirm to prevent double-click and submit once
        confirmBtn.disabled = true;
        summaryModal.close();
        bookingForm.submit();
      });


    });

document.addEventListener('DOMContentLoaded', function () {
  const promptWrapper = document.querySelector('.prompt-card');
  if (!promptWrapper) return;

  let stickPoint = 0;
  let spacer = null;

  function recalc() {
    const rectTop = promptWrapper.getBoundingClientRect().top + window.scrollY;
    const height = promptWrapper.offsetHeight || 0;

    // stick when the MIDDLE of the prompt reaches the top of the screen
    stickPoint = rectTop + height / 2;

    if (!spacer) {
      spacer = document.createElement('div');
      promptWrapper.parentNode.insertBefore(spacer, promptWrapper);
    }
    spacer.style.height = height + 'px';
    spacer.style.display = promptWrapper.classList.contains('is-stuck') ? 'block' : 'none';
  }

  recalc();
  window.addEventListener('resize', recalc);

  function onScroll() {
    if (window.scrollY > stickPoint) {
      if (!promptWrapper.classList.contains('is-stuck')) {
        promptWrapper.classList.add('is-stuck');
        if (spacer) spacer.style.display = 'block';
      }
    } else {
      if (promptWrapper.classList.contains('is-stuck')) {
        promptWrapper.classList.remove('is-stuck');
        if (spacer) spacer.style.display = 'none';
      }
    }
  }

  window.addEventListener('scroll', onScroll);
});
</script>


<?php include('templates/footer.php'); ?>
</body>
</html>

<style>
  /* ============= GLOBAL LAYOUT ============= */
  html, body {
    font-family: "Roboto", sans-serif;
    background: linear-gradient(180deg, #0b1830 0%, #07121a 100%);
    color: #e5e7eb;
  }

  body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .container {
    flex: 1 0 auto;
    padding: 30px 16px 24px;
  }

  /* ============= PAGE TITLE BAR ============= */

  .ticket-title {
    margin-top: 18px;
    margin-bottom: 8px;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #facc15;
    text-align: center;
    font-size: 1rem;
    position: relative;
  }

  .ticket-title::after {
    content: "";
    display: block;
    margin: 10px auto 0;
    width: 110px;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, #facc15, transparent);
  }

  /* ============= PROMPT CARD (TOP) ============= */
  /* ===== PROMPT CARD BASE (normal, inside layout) ===== */
  .prompt-card {
    max-width: 100% ;
    margin: 10px auto 24px;
    transition: all 0.25s ease;
  }

  /* the card itself (keep your existing styling if you already have) */
  .prompt-card .card {
    transition: all 0.25s ease;
  }

  /* ===== WHEN STUCK (full-width fixed header) ===== */
  .prompt-card.is-stuck {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    margin: 0;
    z-index: 1000;
  }

  /* make inner card fill the width when stuck */
  .prompt-card.is-stuck .card {
    max-width: 100%;
    border-radius: 0 0 18px 18px;
    margin: 0;
  }
  .prompt-card.is-stuck .prompt-description-text, .prompt-card.is-stuck .prompt-instruction, .prompt-card.is-stuck .prompt-section-title,.prompt-card.is-stuck h4{
    display: none;
    margin: 0 !important;
  }
  .prompt-card.is-stuck.container{
    padding-top: 0 !important;
  }
  .prompt-card .card {
    /* background: radial-gradient(circle at top left, #111827 0%, #020617 55%, #020617 100%); */
    background: radial-gradient(circle at top left, #0052cc 0%, #1e90ff 100%);
    border-radius: 18px;
    border: 1px solid rgba(250, 204, 21, 0.25);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6);
    padding: 10px 20px 10px ;
    text-align: left;
    position: relative;
  }

  .prompt-card .card::before {
    content: "STUDENT BRIEFING";
    position: absolute;
    top: 14px;
    right: 20px;
    font-size: 0.65rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(250, 204, 21, 0.7);
  }

  .prompt-card h4 {
    margin-top: 2px;
    margin-bottom: 10px;
    font-size: 0.9rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #facc15;
    text-align: left;
  }

  .prompt-description-text {
    color: #f9fafb;
    font-size: 0.95rem;
    line-height: 1.6;
  }

  /* Chips row */
  .prompt-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
    margin-bottom: 6px;
    font-size: 0.78rem;
    justify-content: center;
  }

  .prompt-chip {
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid rgba(250, 204, 21, 0.55);
    background: rgba(133, 93, 14, 0.25);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #fefce8;
  }

  .prompt-chip-label {
    color: #fde047;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.68rem;
  }

  .prompt-chip-value {
    font-weight: 600;
    color: #ffffff;
  }

  /* Sections */
  .prompt-sections {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
  }

  .prompt-section-title {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #fde047;
    margin-bottom: 4px;
  }

  /* Segments timeline */
  .prompt-segment-list {
    list-style: none;
    padding: 8px 10px;
    margin: 0;
    border-radius: 12px;
    background: rgba(5, 11, 18, 0.95);
    border: 1px solid rgba(250, 204, 21, 0.35);
    max-height: 160px;
    overflow-y: auto;
  }

  .prompt-segment-item {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 8px;
    padding: 6px 4px;
    font-size: 0.8rem;
    color: #e5e7eb;
    border-bottom: 1px dashed rgba(250, 204, 21, 0.2);
  }

  .prompt-segment-item:last-child {
    border-bottom: none;
  }

  .prompt-segment-bullet {
    width: 12px;
    display: flex;
    justify-content: center;
    padding-top: 3px;
  }

  .prompt-segment-bullet::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 999px;
    border: 1px solid #facc15;
    background: #facc15;
  }

  .prompt-segment-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .prompt-segment-route {
    color: #fef3c7;
    font-weight: 600;
  }

  .prompt-segment-date {
    color: #eab308;
    font-size: 0.75rem;
  }

  /* Instruction box */
  .prompt-instruction {
    border-radius: 10px;
    border: 1px dashed rgba(250, 204, 21, 0.7);
    background: rgba(66, 54, 10, 0.32);
    color: #fff7cc;
    font-size: 0.8rem;
    padding: 7px 10px;
  }

  .student-prompt-empty {
    padding: 12px 6px;
    color: #facc15 !important;
    font-size: 0.9rem;
  }
  .is-stuck .prompt-segment-list{
    display: flex;
  }
  /* ============= FLIGHT FORM CARD (ROUTE SELECTOR) ============= */

  .bg-container {
    max-width: 960px;
    margin: 0 auto 24px;
  }

  .bg-container .card {
    /* background: radial-gradient(circle at top right, #020617 0%, #020617 50%, #020617 100%); */
    background: radial-gradient(circle at top left, #0052cc 0%, #1e90ff 100%);
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, 0.4);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.7);
    padding: 18px 20px 16px;
    color: #e5e7eb;
  }

  /* Flight type tabs (radio group) */
  .bg-container p {
    display: flex;
    justify-content: center;
    gap: 18px;
    margin-bottom: 10px;
  }

  .bg-container p label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #e5e7eb;
    cursor: pointer;
  }

  .bg-container p input[type="radio"] + span::before {
    content: "";
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    border: 2px solid rgba(148, 163, 184, 0.8);
    margin-right: 2px;
  }

  .bg-container p input[type="radio"]:checked + span::before {
    border-color: #facc15;
    background: #facc15;
  }

  /* Inputs (origin/destination/dates) */
  .bg-container .input-field label {
    color: #9ca3af !important;
  }

  .bg-container .input-field input {
    color: #e5e7eb;
  }

  .bg-container .input-field input::placeholder {
    color: #6b7280;
  }

  .bg-container .material-icons.prefix {
    color: #facc15;
  }

  /* ================== MULTI-CITY PANEL ================== */
  #multiLegsWrap {
    position: relative;
    overflow: hidden;
  }

  /* subtle top glow line */
  #multiLegsWrap::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.8;
    pointer-events: none;
  }

  /* header row: title + ADD FLIGHT button */
  #multiLegsWrap > div:first-child {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px !important;
    font-size: 0.78rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #fefce8;
  }

  #multiLegsWrap > div:first-child > div:first-child {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  /* little pill in front of "Multi-city legs" */
  #multiLegsWrap > div:first-child > div:first-child::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #facc15;
    box-shadow: 0 0 10px rgba(250, 204, 21, 0.7);
  }

  /* ADD FLIGHT button inside panel */
  #multiLegsWrap #addLegBtn.btn {
    border-radius: 999px;
    background: #facc15;
    color: #111827;
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0 14px;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.7);
  }

  #multiLegsWrap #addLegBtn.btn:hover {
    background: #fde047;
    transform: translateY(-1px);
  }

  /* ========== LEG ROWS ========== */
  #multiLegsWrap .leg-row {
    background: rgba(15, 23, 42, 0.85);
    border-radius: 12px;
    padding: 8px 10px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    margin-bottom: 8px !important;
    display: flex;
    gap: 10px;
    align-items: flex-end;
    position: relative;
  }

  /* thin accent line on the left of each row */
  #multiLegsWrap .leg-row::before {
    content: "";
    position: absolute;
    left: 0;
    top: 8px;
    bottom: 8px;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(to bottom, #facc15, transparent);
  }

  /* inputs inside leg rows */
  #multiLegsWrap .leg-row .input-field label {
    color: #cbd5f5 !important;
  }

  #multiLegsWrap .leg-row .input-field input {
    color: #e5e7eb;
  }

  #multiLegsWrap .leg-row .input-field input::placeholder {
    color: #6b7280;
  }

  /* remove-leg button */
  #multiLegsWrap .leg-row .remove-leg {
    border-radius: 999px;
    border: 1px solid rgba(239, 68, 68, 0.7);
    color: #fecaca;
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: rgba(24, 24, 27, 0.9);
    transition: background 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
  }

  #multiLegsWrap .leg-row .remove-leg:hover {
    background: #ef4444;
    color: #111827;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.7);
    transform: translateY(-1px);
  }

  /* helper text under the legs */
  #multiLegsWrap > div:last-child {
    margin-top: 8px;
    font-size: 0.78rem;
    color: #e5e7eb;
    opacity: 0.9;
  }

  /* ===== RESPONSIVE ADJUST FOR LEG ROWS (keep what you had, but refine) ===== */
  @media (max-width: 992px) {
    #multiLegsWrap .leg-row {
      flex-wrap: wrap;
    }

    #multiLegsWrap .leg-row > div[style*="flex:1"],
    #multiLegsWrap .leg-row > div[style*="width:180px"],
    #multiLegsWrap .leg-row > div[style*="width:36px"] {
      width: 100% !important;
      flex: 1 1 100%;
    }

    #multiLegsWrap .leg-row > div[style*="width:36px"] {
      display: flex;
      justify-content: flex-end;
    }
    .is-stuck .prompt-segment-list{
    display: block;
  }
  }

  @media (max-width: 600px) {
    #multiLegsWrap {
      padding: 10px 10px 8px;
    }

    #multiLegsWrap .leg-row {
      padding: 8px;
    }
    
  }


  /* ============= PASSENGER TICKET CARDS ============= */

  #ticketContainer {
    max-width: 960px;
    margin: 0 auto;
  }

  .ticket-card {
    /* background: linear-gradient(135deg, #020617 0%, #020617 65%, #111827 100%); */
    background: radial-gradient(circle at top left, #0052cc 0%, #1e90ff 100%);
    border-radius: 18px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.7);
    padding: 18px 18px 16px;
    margin-bottom: 16px;
    position: relative;
    border-left: 4px solid #facc15;
    border-top: 1px solid rgba(148, 163, 184, 0.4);
    border-right: 1px solid rgba(15, 23, 42, 0.8);
    border-bottom: 1px solid rgba(15, 23, 42, 0.8);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
  }

  .ticket-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 22px 55px rgba(0, 0, 0, 0.9);
    z-index: 999 !important;
  }

  .counter {
    font-weight: 600;
    font-size: 0.95rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #facc15;
    margin-bottom: 8px;
  }

  .ticket-card label,
  .field-title {
    font-weight: 500;
    color: #cbd5f5;
  }

  /* Remove button */
  .remove-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    color: #f97373;
    cursor: pointer;
    font-weight: bold;
    font-size: 18px;
  }

  /* Inputs inside ticket cards */
  .ticket-card .input-field input {
    color: #e5e7eb;
  }

  .ticket-card .input-field input::placeholder {
    color: #6b7280;
  }

  /* Gender / PWD custom controls */
  .custom-radio-inline,
  .custom-checkbox-inline {
    display: inline-flex;
    align-items: center;
    margin-right: 15px;
    position: relative;
    cursor: pointer;
    color: #e5e7eb;
    user-select: none;
    font-weight: 400;
    font-size: 0.85rem;
  }

  .custom-radio-inline input,
  .custom-checkbox-inline input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
  }

  .checkmark {
    height: 18px;
    width: 18px;
    background-color: transparent;
    border: 2px solid #9ca3af;
    margin-right: 6px;
    display: inline-block;
    vertical-align: middle;
    border-radius: 50%;
  }

  .custom-checkbox-inline .checkmark {
    border-radius: 4px;
  }

  .custom-radio-inline input:checked ~ .checkmark,
  .custom-checkbox-inline input:checked ~ .checkmark {
    background-color: #facc15 !important;
    border-color: #facc15 !important;
  }

  .pwd-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .pwd-group .impairment-field {
    flex: 1;
    min-width: 120px;
    padding: 4px 6px;
    border: 1px solid #4b5563;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.8);
    color: #e5e7eb;
  }

  /* ============= ADD PASSENGER BUTTON + PRIMARY CTA ============= */

  .add-btn {
    display: flex;
    justify-content: center;
    margin-top: 20px;
  }

  .btn-floating {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    font-size: 2rem;
    background-color: #facc15;
    color: #111827;
    border: none;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.6);
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.2s ease;
  }

  .btn-floating:hover {
    transform: translateY(-2px) scale(1.05);
    background-color: #fde047;
    box-shadow: 0 12px 26px rgba(0, 0, 0, 0.8);
  }

  .form-actions {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
    max-width: 960px;
    margin-left: auto;
    margin-right: auto;
  }

  .form-actions .btn {
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.95rem;
    height: 44px;
    background-color: #facc15;
    color: #111827;
    padding: 0 26px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.7);
    transition: all 0.18s ease-in-out;
  }

  .form-actions .btn:hover {
    background-color: #fde047;
    transform: translateY(-1px);
  }

  /* ============= MODALS (SUMMARY + SEAT PICKER) ============= */

  .modal {
    background: transparent;
  }

  .modal .modal-content {
    background: radial-gradient(circle at top, #020617 0%, #020617 40%, #020617 100%);
    border-radius: 18px 18px 0 0;
    border-bottom: 1px solid rgba(30, 64, 175, 0.4);
    color: #e5e7eb;
  }

  .modal .modal-content h4,
  .modal .modal-content h5 {
    color: #facc15;
  }

  .modal .modal-footer {
    background: #020617;
    border-radius: 0 0 18px 18px;
    border-top: 1px solid rgba(30, 64, 175, 0.4);
  }

  .modal .modal-footer .btn {
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.8rem;
  }

  .modal .modal-footer .btn.green {
    background-color: #22c55e;
  }

  .modal .modal-footer .btn.red {
    background-color: #ef4444;
  }

  /* ============= SEAT PICKER ============= */

  .seat-map {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px;
    max-width: 960px;
    margin: 0 auto;
    background: radial-gradient(circle at top, #020617 0%, #020617 60%, #020617 100%);
    border-radius: 16px;
    border: 1px solid rgba(55, 65, 81, 0.8);
  }

  .seat-row {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
  }

  .row-label {
    width: 44px;
    min-width: 44px;
    text-align: center;
    font-weight: 600;
    color: #9ca3af;
  }

  .seat {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    user-select: none;
    border: 1px solid rgba(15, 23, 42, 0.9);
    transition: transform .08s ease, box-shadow .12s ease, background 0.08s ease;
    font-weight: 600;
    color: #0f172a;
  }

  /* Cabin background tints */
  .seat.first    { background-color: #e3f2fd; }
  .seat.business { background-color: #fff3e0; }
  .seat.premium  { background-color: #ede7f6; }
  .seat.economy  { background-color: #e8f5e9; }

  .seat:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.5);
  }

  .seat.selected {
    color: white;
  }

  /* Selected seat colors per cabin */
  .seat.first.selected    { background-color: #1e88e5; }
  .seat.business.selected { background-color: #fb8c00; }
  .seat.premium.selected  { background-color: #7e57c2; }
  .seat.economy.selected  { background-color: #22c55e; }

  .seat.disabled {
    background: #111827;
    color: #6b7280;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  .aisle {
    width: 28px;
    min-width: 28px;
  }

  /* Legenda / legend */
  .legend {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 8px 16px 12px;
    flex-wrap: wrap;
    justify-content: center;
    color: #d1d5db;
    font-size: 0.8rem;
  }

  .legend .box {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid rgba(15, 23, 42, 0.9);
    display: inline-block;
    vertical-align: middle;
    margin-right: 6px;
  }

  .legend .box.selected { background: #facc15; border-color: #facc15; }
  .legend .box.disabled { background: #111827; color: #9ca3af; border-color: #4b5563; }

  .selection-summary {
    margin-top: 8px;
    max-width: 960px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 16px 12px;
  }

  /* Cabin headers */
  .cabin-header {
    margin-top: 10px;
    margin-bottom: 4px;
    text-align: left;
    max-width: 960px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 18px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .cabin-header h6 {
    margin: 0;
    font-weight: 600;
  }

  .cabin-header .line {
    flex: 1;
    height: 1px;
    background: rgba(55, 65, 81, 0.9);
  }

  .cabin-header.first h6    { color: #1e88e5; }
  .cabin-header.business h6 { color: #fb8c00; }
  .cabin-header.premium h6  { color: #a855f7; }
  .cabin-header.economy h6  { color: #22c55e; }

  /* ============= DATEPICKER / DROPDOWN FIXES ============= */

  .datepicker-date-display {
    display: none !important;
  }
  select.datepicker-select {
    display: none !important;
  }
  input.select-dropdown {
    width: 100% !important;
  }
  .datepicker-modal{
    max-width: 350px !important;
  }
  /* ============= RESPONSIVE ============= */

  @media (max-width: 600px) {
    .container {
      padding-top: 18px;
    }

    .bg-container .row .col.s4,
    .bg-container .row .col.s6,
    .bg-container .row .col.md3 {
      width: 100%;
    }

    .bg-container p {
      flex-direction: column;
      gap: 6px;
    }

    .pwd-group {
      flex-direction: column;
      align-items: flex-start;
    }

    .form-actions {
      justify-content: center;
    }

    .form-actions .btn {
      width: 100%;
    }

    .seat {
      width: 36px;
      height: 36px;
      border-radius: 6px;
    }
    .row-label {
      width: 36px;
      min-width: 36px;
      font-size: 0.9rem;
    }

    .prompt-card .card::before {
      position: static;
      display: inline-block;
      margin-bottom: 4px;
    }
  }
  /* ================== LAYOUT GRID FOR PROMPT ================== */
  @media (max-width: 992px) {
    .prompt-layout {
      grid-template-columns: 1fr;
    }

    .prompt-card .card {
      padding: 12px 14px 10px;
    }

    .prompt-segment-list {
      max-height: 220px;
    }
  }

  /* ================== GENERIC CONTAINER WIDTHS ================== */
  /* .container {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
  } */
  [type="radio"]:checked+span:after, [type="radio"].with-gap:checked+span:after{
    background-color: transparent !important;
    border: none !important;
  }
  /* ================== MAKE FORMS STACK BETTER ================== */
  @media (max-width: 992px) {
    .bg-container .row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .bg-container .row > [class*="col"] {
      flex: 1 1 100%;
      max-width: 100%;
    }

    /* Multi-city legs: stack fields instead of one long row */
    .leg-row {
      flex-wrap: wrap;
    }

    .leg-row > div[style*="flex:1"],
    .leg-row > div[style*="width:180px"],
    .leg-row > div[style*="width:36px"] {
      width: 100% !important;
      flex: 1 1 100%;
    }

    .leg-row > div[style*="width:36px"] {
      text-align: right;
    }
  }

  /* ================== SEAT MAP: SCROLLABLE ON MOBILE ================== */
  .seat-map-wrapper {
    max-width: 100%;
    overflow-x: auto;
    padding-bottom: 8px;
  }

  .seat-map {
    min-width: 560px; /* keeps columns readable but allows horizontal scroll */
  }

  /* slightly smaller seats on tablets too */
  @media (max-width: 992px) {
    .seat {
      width: 38px;
      height: 38px;
    }

    .row-label {
      width: 38px;
      min-width: 38px;
    }
    .prompt-segment-list {
          max-height: 128px;
      }
  }

  /* keep your 600px overrides, but add a bit more for really small screens */
  @media (max-width: 600px) {
    .ticket-card {
      padding: 14px 12px 12px;
    }

    .ticket-card .row .col.s6 {
      width: 100%;
    }

    .pwd-group {
      gap: 6px;
    }

    .seat-map {
      min-width: 520px;
    }
  }


  /* ================== TICKET + ACTIONS ON SMALL DEVICES ================== */
  @media (max-width: 768px) {
    #ticketContainer {
      padding: 0 8px;
    }

    .ticket-card .row .col.s6,
    .ticket-card .row .col.s2 {
      width: 100%;
    }

    .add-btn {
      margin-top: 16px;
    }

    .form-actions {
      margin-top: 20px;
      padding: 0 8px;
      justify-content: center;
    }

    .form-actions .btn {
      width: 100%;
    }
  }
</style>
