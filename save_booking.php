<?php
// save_booking.php - ONLY inserts into submitted_flights (now supports multi-city legs)
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Database connection failed: " . htmlspecialchars($mysqli->connect_error));
}

/**
 * Utility: clean input value
 */
function clean($s) {
    return trim($s === null ? '' : (string)$s);
}

/* -------------------------
   Identify account (acc_id)
--------------------------*/
$acc_id = clean($_POST['acc_id'] ?? '');

if ($acc_id === '') {
    if (isset($_SESSION['student_id']) && $_SESSION['student_id'] !== '') {
        $acc_id = (string)$_SESSION['student_id'];
    } elseif (isset($_SESSION['acc_id']) && $_SESSION['acc_id'] !== '') {
        $acc_id = (string)$_SESSION['acc_id'];
    }
}

if ($acc_id === '') {
    die('Not logged in or acc_id not provided.');
}

// prefer integer acc_id for DB
$acc_id_int = is_numeric($acc_id) ? (int)$acc_id : 0;

/* -------------------------
   quiz_id from POST (ticket.php sends this)
--------------------------*/
$quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;

/* -------------------------
   Determine quiz input_type from DB
   ('airport-code' or 'code-airport')
--------------------------*/
$quizInputType = 'airport-code'; // safe default

if ($quiz_id > 0) {
    $qs = $mysqli->prepare("SELECT input_type FROM quizzes WHERE id = ?");
    if ($qs) {
        $qs->bind_param("i", $quiz_id);
        $qs->execute();
        $qres = $qs->get_result();
        if ($row = $qres->fetch_assoc()) {
            if (!empty($row['input_type'])) {
                $quizInputType = $row['input_type'];
            }
        }
        $qs->close();
    }
}

/* -------------------------
   Flight fields (from hidden inputs in bookingForm)
--------------------------*/
// These top-level fields may be overridden by legs if booking_legs is present
$origin      = strtoupper(clean($_POST['origin'] ?? ''));
$destination = strtoupper(clean($_POST['destination'] ?? ''));
$departure   = clean($_POST['flight_date'] ?? '');
$return_date = clean($_POST['return_date'] ?? '');
$flight_type = strtoupper(clean($_POST['flight_type'] ?? 'ONE-WAY'));
if (!in_array($flight_type, ['ONE-WAY','ROUND-TRIP','MULTI-CITY'], true)) $flight_type = 'ONE-WAY';
if ($flight_type !== 'ROUND-TRIP' && $flight_type !== 'MULTI-CITY') {
    // For non-RT / non-multi-city, clear return
    $return_date = '';
}

/* -------------------------
   legs_json POST (from ticket.php -> booking_legs)
--------------------------*/
$booking_legs_raw = $_POST['booking_legs'] ?? '';
$booking_legs = null;
$hasLegs = false;
if (is_string($booking_legs_raw) && trim($booking_legs_raw) !== '') {
    $decoded = json_decode($booking_legs_raw, true);
    if (is_array($decoded) && count($decoded) > 0) {
        // normalize legs to array of {origin,destination,date}
        $legs = [];
        foreach ($decoded as $lg) {
            $o = isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '';
            $d = isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '';
            $dt = isset($lg['date']) ? trim($lg['date']) : '';
            $legs[] = ['origin'=>$o, 'destination'=>$d, 'date'=>$dt];
        }
        $booking_legs = $legs;
        $hasLegs = true;
        // enforce multi-city type
        $flight_type = 'MULTI-CITY';
    }
}

/* -------------------------
   If multi-city legs present, derive top-level fields from legs
--------------------------*/
if ($hasLegs && is_array($booking_legs) && count($booking_legs) > 0) {
    $firstLeg = $booking_legs[0];
    $lastLeg  = $booking_legs[count($booking_legs)-1];

    // derive origin/destination/date
    $origin = strtoupper(trim($firstLeg['origin'] ?? '')) ?: $origin;
    $destination = strtoupper(trim($lastLeg['destination'] ?? '')) ?: $destination;
    $departure = trim($firstLeg['date'] ?? '') ?: $departure;
    $return_date = trim($lastLeg['date'] ?? '') ?: $return_date;
}

/* -------------------------
   Passengers (for counts/seats/travel_class)
--------------------------*/
$names        = $_POST['name'] ?? [];
$ages         = $_POST['age'] ?? [];
$specials     = $_POST['special'] ?? [];             // "Infant", "Child", "Regular"
$seats_class  = $_POST['seat_class'] ?? $_POST['seat'] ?? [];  // "Economy", etc. (try both)
$seats_number = $_POST['seat_number'] ?? [];

$passengerCount = is_array($names) ? count($names) : 0;

/* -------------------------
   Basic validation
--------------------------*/
/* -------------------------
   Basic validation
--------------------------*/
$errors = [];

// If we have booking legs, validate the legs (and skip top-level origin/destination requirement)
if ($hasLegs) {
    foreach ($booking_legs as $i => $lg) {
        $o = $lg['origin'] ?? '';
        $d = $lg['destination'] ?? '';
        $dt = $lg['date'] ?? '';

        // presence checks
        if ($o === '') $errors[] = "Leg #".($i+1)." origin is required.";
        if ($d === '') $errors[] = "Leg #".($i+1)." destination is required.";

        // IATA format only when quiz expects codes
        if ($quizInputType === 'airport-code') {
            if ($o !== '' && !preg_match('/^[A-Z]{3}$/', $o)) $errors[] = "Leg #".($i+1)." origin must be a 3-letter IATA code.";
            if ($d !== '' && !preg_match('/^[A-Z]{3}$/', $d)) $errors[] = "Leg #".($i+1)." destination must be a 3-letter IATA code.";
        }

        // same-origin/destination for the leg
        if ($o !== '' && $d !== '' && $o === $d) {
            $errors[] = "Leg #".($i+1)." origin and destination cannot be the same.";
        }

        // date validation for each leg (if provided)
        if ($dt === '') {
            $errors[] = "Leg #".($i+1)." date is required.";
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                $errors[] = "Leg #".($i+1)." date must be YYYY-MM-DD.";
            } else {
                $dtObj = DateTime::createFromFormat('Y-m-d', $dt);
                if (!$dtObj || $dtObj->format('Y-m-d') !== $dt) {
                    $errors[] = "Leg #".($i+1)." date is invalid.";
                }
            }
        }
    }

    // Optional: ensure legs chain together (leg[n].destination == leg[n+1].origin)
    // If you want this, uncomment the block below:
    /*
    for ($i = 0; $i < count($booking_legs) - 1; $i++) {
        $cur = $booking_legs[$i];
        $next = $booking_legs[$i+1];
        if (!empty($cur['destination']) && !empty($next['origin']) && $cur['destination'] !== $next['origin']) {
            $errors[] = "Leg #".($i+1)." destination must match Leg #".($i+2)." origin.";
        }
    }
    */

} else {
    // No legs => legacy top-level validation
    if ($origin === '') {
        $errors[] = 'Origin is required.';
    } elseif ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $origin)) {
        $errors[] = 'Origin must be 3 uppercase letters.';
    }

    if ($destination === '') {
        $errors[] = 'Destination is required.';
    } elseif ($quizInputType === 'airport-code' && !preg_match('/^[A-Z]{3}$/', $destination)) {
        $errors[] = 'Destination must be 3 uppercase letters.';
    }

    if ($origin !== '' && $destination !== '' && $origin === $destination) {
        $errors[] = 'Origin and destination cannot be the same.';
    }

    // Validate departure & return dates
    if ($departure === '') {
        $errors[] = 'Departure date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure)) {
        $errors[] = 'Invalid departure date format (expected YYYY-MM-DD).';
    } else {
        $dObj = DateTime::createFromFormat('Y-m-d', $departure);
        if (!$dObj || $dObj->format('Y-m-d') !== $departure) {
            $errors[] = 'Departure date is invalid.';
        }
    }

    if ($flight_type === 'ROUND-TRIP') {
        if ($return_date === '') {
            $errors[] = 'Return date is required for round-trip flights.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
            $errors[] = 'Invalid return date format (expected YYYY-MM-DD).';
        } else {
            $rObj = DateTime::createFromFormat('Y-m-d', $return_date);
            if (!$rObj || $rObj->format('Y-m-d') !== $return_date) {
                $errors[] = 'Return date is invalid.';
            } elseif (isset($dObj) && $rObj < $dObj) {
                $errors[] = 'Return date cannot be before departure date.';
            }
        }
    }
}

// passenger count validation (applies regardless)
if ($passengerCount < 1) {
    $errors[] = 'At least one passenger is required to compute seats.';
}


if (!empty($errors)) {
    echo "<h3>Validation errors in save_booking.php</h3><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul><p><a href=\"ticket.php?id=" . htmlspecialchars($quiz_id) . "\">Back to ticket</a></p>";
    exit;
}

/* -------------------------
   Compute adults / children / infants
--------------------------*/
$adults   = 0;
$children = 0;
$infants  = 0;

for ($i = 0; $i < $passengerCount; $i++) {
    $type = isset($specials[$i]) ? trim($specials[$i]) : '';

    if ($type === 'Infant') {
        $infants++;
    } elseif ($type === 'Child') {
        $children++;
    } elseif ($type === 'Regular') {
        $adults++;
    } else {
        $age = isset($ages[$i]) ? (int)$ages[$i] : 0;
        if ($age <= 2) $infants++;
        elseif ($age >= 3 && $age <= 12) $children++;
        else $adults++;
    }
}

/* -------------------------
   Seats & travel_class
--------------------------*/
$seats = $passengerCount;

$travel_class = '';
if ($passengerCount > 0) {
    $classes = [];
    for ($i = 0; $i < $passengerCount; $i++) {
        $cls = isset($seats_class[$i]) ? trim($seats_class[$i]) : '';
        if ($cls !== '') {
            $cls = strtoupper($cls);
            $classes[$cls] = true;
        }
    }

    $distinct = array_keys($classes);

    if (count($distinct) === 1) {
        $travel_class = $distinct[0];
    } elseif (count($distinct) > 1) {
        $travel_class = implode(', ', $distinct);
    } else {
        $travel_class = '';
    }
}

// seat numbers: convert array -> single string for DB storage
if (!is_array($seats_number)) {
    $seats_number = [$seats_number]; // just in case it's a single value
}

// Clean + uppercase each seat value
$clean_seats = [];
foreach ($seats_number as $sn) {
    $sn = strtoupper(clean($sn));
    if ($sn !== '') {
        $clean_seats[] = $sn;
    }
}

// Final value saved into DB (e.g. "12A,12B,13C")
$seat_number = implode(',', $clean_seats);

/* -------------------------
   Prepare to insert into submitted_flights
   Attempt to add legs_json column if missing, then include it in INSERT
--------------------------*/
$useLegsColumn = false;
$resCol = $mysqli->query("SHOW COLUMNS FROM `submitted_flights` LIKE 'legs_json'");
if ($resCol && $resCol->num_rows > 0) {
    $useLegsColumn = true;
} else {
    // try to add it (non-fatal)
    if ($mysqli->query("ALTER TABLE `submitted_flights` ADD COLUMN `legs_json` TEXT NULL")) {
        $useLegsColumn = true;
    } else {
        // if ALTER fails, continue without legs column
        error_log('save_booking.php: unable to add legs_json column: ' . $mysqli->error);
        $useLegsColumn = false;
    }
}

/* -------------------------
   Insert (transaction)
--------------------------*/
$mysqli->begin_transaction();

try {
    if ($useLegsColumn) {
        // 13 params:
        // i quiz_id
        // i acc_id_int
        // i adults
        // i children
        // i infants
        // s flight_type
        // s origin
        // s destination
        // s departure
        // s return_date
        // s seat_number
        // s travel_class
        // s legs_json_to_store
        $sql = "
        INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class, legs_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed (with legs): " . $mysqli->error);
        }

        $legs_json_to_store = null;
        if ($hasLegs) {
            $json = json_encode($booking_legs, JSON_UNESCAPED_UNICODE);
            $legs_json_to_store = ($json === false) ? null : $json;
        }

        // types: 5 ints, 8 strings => "iiiiissssssss"
        $types = "iiiiissssssss";
        if (!$stmt->bind_param(
            $types,
            $quiz_id,
            $acc_id_int,
            $adults,
            $children,
            $infants,
            $flight_type,
            $origin,
            $destination,
            $departure,
            $return_date,
            $seat_number,
            $travel_class,
            $legs_json_to_store
        )) {
            throw new Exception("Bind failed (with legs): " . $stmt->error);
        }

    } else {
        // fallback insert (without legs_json)
        // 12 params:
        // i quiz_id, i acc_id_int, i adults, i children, i infants,
        // s flight_type, s origin, s destination, s departure, s return_date, s seat_number, s travel_class
        $sql = "
        INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed (no legs): " . $mysqli->error);
        }

        // types: 5 ints, 7 strings => "iiiiisssssss"
        $types = "iiiiisssssss";
        if (!$stmt->bind_param(
            $types,
            $quiz_id,
            $acc_id_int,
            $adults,
            $children,
            $infants,
            $flight_type,
            $origin,
            $destination,
            $departure,
            $return_date,
            $seat_number,
            $travel_class
        )) {
            throw new Exception("Bind failed (no legs): " . $stmt->error);
        }
    }

    // Execute and commit
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $mysqli->commit();
    $stmt->close();

} catch (Exception $ex) {
    $mysqli->rollback();
    error_log("save_booking.php error: " . $ex->getMessage());
    die("Unable to save booking: " . htmlspecialchars($ex->getMessage()));
}

/* -------------------------
   Redirect / show success
--------------------------*/
header("refresh:2;url=takequiz.php");
echo "<h3>Flight submitted</h3>";
exit;
