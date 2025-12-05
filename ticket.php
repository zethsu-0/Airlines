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
// LOAD QUIZ ITEMS (legs_json + legacy ROUND-TRIP)
// =======================

$items = [];

// Detect if quiz_items has legs_json column
$hasLegsJson = false;
$colCheck = $conn->query("SHOW COLUMNS FROM `quiz_items` LIKE 'legs_json'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasLegsJson = true;
}

// Build SELECT query including legs_json if available
if ($hasLegsJson) {
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
        legs_json
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
        NULL AS legs_json
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
            $segmentStrings[] = "segment {$i} from {$org} to {$dst} on {$date}";
        } else {
            $segmentStrings[] = "segment {$i} from {$org} to {$dst}";
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
    "{$classSentence} " .
    $instructionSentence;

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
    'expected_answer' => $firstDestination ?: null
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $quizInputType = isset($_POST['quiz_type']) && $_POST['quiz_type'] !== ''
        ? $_POST['quiz_type']
        : 'airport-code';

    if (!isset($_SESSION['acc_id'])) {
        $errors['login'] = 'You must be logged in to submit a flight.';
    }

    // Normalize selected flight type from POST (defensive)
    $flight_type = isset($_POST['flight_type']) ? strtoupper(trim($_POST['flight_type'])) : 'ONE-WAY';

    // If MULTI-CITY we expect leg_* arrays; otherwise use single origin/destination inputs
    if ($flight_type === 'MULTI-CITY') {
        // Collect legs from POST arrays (names used in your JS: leg_origin_iata[], leg_destination_iata[], leg_date[])
        $leg_origins = isset($_POST['leg_origin_iata']) && is_array($_POST['leg_origin_iata']) ? $_POST['leg_origin_iata'] : [];
        $leg_dests   = isset($_POST['leg_destination_iata']) && is_array($_POST['leg_destination_iata']) ? $_POST['leg_destination_iata'] : [];
        $leg_dates   = isset($_POST['leg_date']) && is_array($_POST['leg_date']) ? $_POST['leg_date'] : [];

        // Trim and uppercase origins/dests
        $legs = [];
        $numLegs = max(count($leg_origins), count($leg_dests), count($leg_dates));
        for ($i = 0; $i < $numLegs; $i++) {
            $o  = strtoupper(trim((string)($leg_origins[$i] ?? '')));
            $d  = strtoupper(trim((string)($leg_dests[$i] ?? '')));
            $dt = trim((string)($leg_dates[$i] ?? ''));

            // skip completely empty rows
            if ($o === '' && $d === '' && $dt === '') continue;

            $legs[] = ['origin' => $o, 'destination' => $d, 'date' => $dt];
        }

        if (count($legs) === 0) {
            $errors['legs'] = 'At least one leg is required for multi-city bookings.';
        } else {
            // Validate each leg
            foreach ($legs as $idx => $lg) {
                $o  = $lg['origin'];
                $d  = $lg['destination'];
                $dt = $lg['date'];

                if ($o === '') {
                    $errors['origin_' . $idx] = "Origin is required for leg " . ($idx + 1) . ".";
                } else {
                    if ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $o)) {
                        $errors['origin_' . $idx] = "Origin must be a 3-letter IATA code for leg " . ($idx + 1) . ".";
                    }
                }

                if ($d === '') {
                    $errors['destination_' . $idx] = "Destination is required for leg " . ($idx + 1) . ".";
                } else {
                    if ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $d)) {
                        $errors['destination_' . $idx] = "Destination must be a 3-letter IATA code for leg " . ($idx + 1) . ".";
                    }
                }

                if ($o !== '' && $d !== '' && $o === $d) {
                    $errors['destination_' . $idx] = "Origin and destination cannot be the same for leg " . ($idx + 1) . ".";
                }

                // date validation
                if ($dt === '') {
                    $errors['flight_date_' . $idx] = "Date is required for leg " . ($idx + 1) . ".";
                } else {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                        $errors['flight_date_' . $idx] = "Invalid date format for leg " . ($idx + 1) . ". Use YYYY-MM-DD.";
                    } else {
                        $dobj = DateTime::createFromFormat('Y-m-d', $dt);
                        if (!$dobj || $dobj->format('Y-m-d') !== $dt) {
                            $errors['flight_date_' . $idx] = "Invalid date for leg " . ($idx + 1) . ".";
                        }
                    }
                }
            }
        }

        // If there were no errors for multi-city, set the "first" flight metadata for compatibility
        if (empty($errors)) {
            $firstLeg   = $legs[0];
            $lastLeg    = end($legs);
            $origin      = $firstLeg['origin'] ?? '';
            $destination = $lastLeg['destination'] ?? '';
            $flight_date = $firstLeg['date'] ?? '';
            $return_date = $lastLeg['date'] ?? '';
        }

    } else {
        // ONE-WAY or ROUND-TRIP (existing behavior)

        $origin       = strtoupper(trim($_POST['origin'] ?? ''));
        $destination  = strtoupper(trim($_POST['destination'] ?? ''));
        $flight_date  = trim($_POST['flight_date'] ?? '');
        $return_date  = trim($_POST['return_date'] ?? '');

        if (empty($origin)) {
            $errors['origin'] = 'Origin is required.';
        } elseif ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $origin)) {
            $errors['origin'] = 'Origin must be a 3-letter IATA code.';
        }

        if (empty($destination)) {
            $errors['destination'] = 'Destination is required.';
        } elseif ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $destination)) {
            $errors['destination'] = 'Destination must be a 3-letter IATA code.';
        }

        if ($origin === $destination && !empty($origin)) {
            $errors['destination'] = 'Destination code cannot be the same as origin.';
        }

        if (empty($flight_date)) {
            $errors['flight_date'] = 'Departure date is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
            $errors['flight_date'] = 'Invalid date format.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $flight_date);
            if (!$d || $d->format('Y-m-d') !== $flight_date) {
                $errors['flight_date'] = 'Invalid date.';
            }
        }

        if ($flight_type !== 'ROUND-TRIP') {
            // ensure return_date cleared for NON-RT
            $return_date = '';
        } else {
            if (empty($return_date)) {
                $errors['return_date'] = 'Return date is required for round-trip flights.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
                $errors['return_date'] = 'Invalid return date format.';
            } else {
                $r = DateTime::createFromFormat('Y-m-d', $return_date);
                if (!$r || $r->format('Y-m-d') !== $return_date) {
                    $errors['return_date'] = 'Invalid return date.';
                } else {
                    $d = DateTime::createFromFormat('Y-m-d', $flight_date);
                    if ($d && $r < $d) {
                        $errors['return_date'] = 'Return date cannot be before departure date.';
                    }
                }
            }
        }
    }

    // If AJAX validation requested, return JSON including legs when MULTI-CITY
    if (!empty($_POST['ajax_validate'])) {
        header('Content-Type: application/json; charset=utf-8');

        $responseFlight = [
            'origin'      => $origin,
            'destination' => $destination,
            'flight_date' => $flight_date,
            'return_date' => $return_date,
            'flight_type' => $flight_type
        ];

        // include legs if we parsed them
        if (isset($legs) && is_array($legs) && count($legs) > 0) {
            // ensure legs are in normalized format (origin,destination,date)
            $responseFlight['legs'] = array_values($legs);
        } else {
            $responseFlight['legs'] = [];
        }

        echo json_encode([
            'ok'       => empty($errors),
            'errors'   => $errors,
            'quiz_type'=> $quizInputType,
            'flight'   => $responseFlight
        ]);
        exit;
    }
}

$flight = [
    'origin_code'      => htmlspecialchars($origin),
    'destination_code' => htmlspecialchars($destination),
    'flight_date'      => htmlspecialchars($flight_date),
    'return_date'      => htmlspecialchars($return_date),
    'flight_type'      => htmlspecialchars($flight_type)
];
?>


<!DOCTYPE html>
  <html lang="en">
  <?php include('templates/header.php'); ?>
  <link rel="stylesheet" href="css/ticket.css">
  <body>
    <h4 class="center-align">🎫 Plane Ticket Booking</h4>
    <div class="container">
      <div class="card center">
        <h4>PROMPT</h4>

        <?php if ($descObj): ?>
          <div style="padding:14px; max-width:980px; margin:10px auto; text-align:left;">
            <div style="font-weight:700; margin-bottom:8px;">Student prompt (description)</div>
            <div style="margin-bottom:8px; font-size:15px;"><?php echo htmlspecialchars($descObj['description']); ?></div>

            <div style="color:#555;">
              <strong>Items:</strong> <?php echo intval($descObj['itemsCount']); ?> &nbsp;•&nbsp;
              <strong>First deadline:</strong> <?php echo htmlspecialchars($descObj['firstDeadline'] ?: '—'); ?>
            </div>

            <?php if (!empty($quiz['title'])): ?>
              <div style="margin-top:10px; font-size:0.95em;" class="muted">
                Quiz: <?php echo htmlspecialchars($quiz['title']); ?>
                (Code: <?php echo htmlspecialchars($quiz['code'] ?? '—'); ?>)
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="padding:12px; color:#666;">
            No quiz prompt available. Open this page with <code>?id=&lt;quiz_id&gt;</code> to see the prompt.
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
              <div style="font-weight:700">Multi-city legs</div>
              <div>
                <button type="button" id="addLegBtn" class="btn btn-small">ADD FLIGHT</button>
              </div>
            </div>

            <div id="legsList">
              <!-- JS will create .leg-row entries here -->
            </div>

            <div style="margin-top:10px; color:#666; font-size:0.95em;">
              Add multiple legs. Each leg has a visible label (IATA or airport name depending on quiz) and a hidden IATA value saved with the booking.
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
                <input type="text" name="impairment[]" class="impairment-field" placeholder="Specify" disabled style="display:none;">
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
          <button type="button" id="addTicketBtn" class="btn-floating blue">+</button>
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

        <div id="cabinContainer"></div>
        <div id="seatMap" class="seat-map" aria-label="Seat map" role="application"></div>

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
        <input type="text" class="leg-origin-display" id="leg_origin_display_${idx}_${legIndex}" placeholder="Origin (IATA or name)">
        <label for="leg_origin_display_${idx}_${legIndex}">Leg origin</label>
        <input type="hidden" class="leg-origin-iata" id="leg_origin_iata_${idx}_${legIndex}" name="leg_origin_iata[]">
      </div>
    </div>
    <div style="flex:1;">
      <div class="input-field">
        <input type="text" class="leg-destination-display" id="leg_dest_display_${idx}_${legIndex}" placeholder="Destination (IATA or name)">
        <label for="leg_dest_display_${idx}_${legIndex}">Leg destination</label>
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
      if (checkbox.checked) {
        field.style.display = 'inline-block';
        field.disabled = false;
      } else {
        field.style.display = 'none';
        field.disabled = true;
        field.value = '';
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
</script>


<?php include('templates/footer.php'); ?>
</body>
</html>

<style>
.datepicker-date-display{
  display: none !important;
}
select.datepicker-select{
  display: none !important;
}
input.select-dropdown{
  width: 100% !important ;
}

/* ===== Seat picker styles ===== */
.seat-map {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  max-width: 960px;
  margin: 0 auto;
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
  color: #444;
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
  border: 1px solid rgba(0,0,0,0.12);
  transition: transform .08s ease, box-shadow .12s ease;
  background: #fff;
  font-weight: 600;
}
.seat:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 14px rgba(0,0,0,0.08);
}

.seat.selected {
  color: white;
  border-color: rgba(0,0,0,0.15);
}

.seat.disabled {
  background: #efefef;
  color: #9e9e9e;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.aisle {
  width: 28px;
  min-width: 28px;
}

.legend {
  display:flex;
  gap:12px;
  align-items:center;
  margin: 8px 16px 18px;
  flex-wrap: wrap;
  justify-content: center;
}
.legend .box {
  width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,0.12);
  display:inline-block;vertical-align:middle;margin-right:6px;
}
.legend .box.selected { background:#26a69a; border:none; }
.legend .box.disabled { background:#efefef; color:#9e9e9e; border:none; }

.selection-summary {
  margin-top: 12px;
  max-width: 960px;
  margin-left: auto;
  margin-right: auto;
  padding: 0 16px 16px;
}

.cabin-header {
  margin-top: 10px;
  margin-bottom: 4px;
  text-align: left;
  max-width: 960px;
  margin-left: auto;
  margin-right: auto;
  padding: 0 18px;
  display:flex;
  align-items:center;
  gap:8px;
}
.cabin-header h6 {
  margin: 0;
  font-weight: 600;
}
.cabin-header .line {
  flex:1;
  height: 1px;
  background: rgba(0,0,0,0.12);
}

/* Cabin colors */
.seat.first    { background-color: #e3f2fd; }  /* light blue */
.seat.business { background-color: #fff3e0; }  /* light orange */
.seat.premium  { background-color: #ede7f6; }  /* light purple */
.seat.economy  { background-color: #e8f5e9; }  /* light green */

.seat.first.selected    { background-color: #1e88e5; }
.seat.business.selected { background-color: #fb8c00; }
.seat.premium.selected  { background-color: #7e57c2; }
.seat.economy.selected  { background-color: #43a047; }

.cabin-header.first h6    { color: #1e88e5; }
.cabin-header.business h6 { color: #fb8c00; }
.cabin-header.premium h6  { color: #7e57c2; }
.cabin-header.economy h6  { color: #43a047; }

@media(max-width:680px){
  .seat { width:36px; height:36px; border-radius:6px; }
  .row-label { width:36px; min-width:36px; font-size:0.9rem; }
}
.ticket-card:hover { 
  transform: scale(1.01); 
  z-index: 999 !important;
}
</style>
