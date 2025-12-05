<?php
// save_booking.php
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

/**
 * Generate a URL-safe public id (like YouTube-ish)
 */
function generate_public_id($length = 11) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    $max = strlen($alphabet) - 1;
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $id .= $alphabet[random_int(0, $max)];
    }
    return $id;
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
$origin      = strtoupper(clean($_POST['origin'] ?? ''));
$destination = strtoupper(clean($_POST['destination'] ?? ''));
$departure   = clean($_POST['flight_date'] ?? '');
$return_date = clean($_POST['return_date'] ?? '');
$flight_type = strtoupper(clean($_POST['flight_type'] ?? 'ONE-WAY'));
if (!in_array($flight_type, ['ONE-WAY','ROUND-TRIP','MULTI-CITY'], true)) $flight_type = 'ONE-WAY';
if ($flight_type !== 'ROUND-TRIP' && $flight_type !== 'MULTI-CITY') {
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
        $legs = [];
        foreach ($decoded as $lg) {
            $o = isset($lg['origin']) ? strtoupper(trim($lg['origin'])) : '';
            $d = isset($lg['destination']) ? strtoupper(trim($lg['destination'])) : '';
            $dt = isset($lg['date']) ? trim($lg['date']) : '';
            $legs[] = ['origin'=>$o, 'destination'=>$d, 'date'=>$dt];
        }
        $booking_legs = $legs;
        $hasLegs = true;
        $flight_type = 'MULTI-CITY';
    }
}

/* -------------------------
   If multi-city legs present, derive top-level fields from legs
--------------------------*/
if ($hasLegs && is_array($booking_legs) && count($booking_legs) > 0) {
    $firstLeg = $booking_legs[0];
    $lastLeg  = $booking_legs[count($booking_legs)-1];

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
$errors = [];

if ($hasLegs) {
    foreach ($booking_legs as $i => $lg) {
        $o = $lg['origin'] ?? '';
        $d = $lg['destination'] ?? '';
        $dt = $lg['date'] ?? '';

        if ($o === '') $errors[] = "Leg #".($i+1)." origin is required.";
        if ($d === '') $errors[] = "Leg #".($i+1)." destination is required.";

        if ($quizInputType === 'airport-code') {
            if ($o !== '' && !preg_match('/^[A-Z]{3}$/', $o)) $errors[] = "Leg #".($i+1)." origin must be a 3-letter IATA code.";
            if ($d !== '' && !preg_match('/^[A-Z]{3}$/', $d)) $errors[] = "Leg #".($i+1)." destination must be a 3-letter IATA code.";
        }

        if ($o !== '' && $d !== '' && $o === $d) {
            $errors[] = "Leg #".($i+1)." origin and destination cannot be the same.";
        }

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
} else {
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
    $seats_number = [$seats_number];
}

$clean_seats = [];
foreach ($seats_number as $sn) {
    $sn = strtoupper(clean($sn));
    if ($sn !== '') {
        $clean_seats[] = $sn;
    }
}
$seat_number = implode(',', $clean_seats);

/* -------------------------
   Determine submitted_flights support: legs_json and public_id
--------------------------*/
$useLegsColumn = false;
$resCol = $mysqli->query("SHOW COLUMNS FROM `submitted_flights` LIKE 'legs_json'");
if ($resCol && $resCol->num_rows > 0) {
    $useLegsColumn = true;
}

$submitted_has_public = false;
$resPub = $mysqli->query("SHOW COLUMNS FROM `submitted_flights` LIKE 'public_id'");
if ($resPub && $resPub->num_rows > 0) {
    $submitted_has_public = true;
}

/* -------------------------
   Insert (transaction)
--------------------------*/
$mysqli->begin_transaction();

try {
    $final_public_id = null;

    // Build SQL depending on columns present
    if ($useLegsColumn && $submitted_has_public) {
        $sql = "INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class, legs_json, public_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } elseif ($useLegsColumn && !$submitted_has_public) {
        $sql = "INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class, legs_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } elseif (!$useLegsColumn && $submitted_has_public) {
        $sql = "INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class, public_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else {
        $sql = "INSERT INTO submitted_flights
            (quiz_id, acc_id, adults, children, infants, flight_type,
             origin, destination, departure, return_date, seat_number, travel_class)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    // If public_id will be inserted, we must handle rare collisions by retrying
    $maxPublicRetries = 6;
    $attempt = 0;
    $inserted = false;
    $lastError = '';

    while (!$inserted) {
        // Prepare statement
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $mysqli->error . " SQL: " . $sql);
        }

        // Prepare legs_json if needed
        $legs_json_to_store = null;
        if ($useLegsColumn && $hasLegs) {
            $json = json_encode($booking_legs, JSON_UNESCAPED_UNICODE);
            $legs_json_to_store = ($json === false) ? null : $json;
        }

        if ($submitted_has_public) {
            // generate public id (only for inserts that include public_id)
            $final_public_id = generate_public_id(11);
        }

        // Bind parameters according to selected SQL
        if ($useLegsColumn && $submitted_has_public) {
            // 14 params: i, s, i, i, i, s, s, s, s, s, s, s, s, s
            $types = "isiii" . str_repeat('s', 9); // "isiiissssssss"
            $bindVars = [
                $quiz_id,
                $acc_id,
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
                $legs_json_to_store,
                $final_public_id
            ];
        } elseif ($useLegsColumn && !$submitted_has_public) {
            // 13 params: i, s, i, i, i, s, s, s, s, s, s, s, s
            $types = "isiii" . str_repeat('s', 8); // "isiiisssssss"
            $bindVars = [
                $quiz_id,
                $acc_id,
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
            ];
        } elseif (!$useLegsColumn && $submitted_has_public) {
            // 13 params: i, s, i, i, i, s, s, s, s, s, s, s, s
            $types = "isiii" . str_repeat('s', 8); // "isiiisssssss"
            $bindVars = [
                $quiz_id,
                $acc_id,
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
                $final_public_id
            ];
        } else {
            // 12 params: i, s, i, i, i, s, s, s, s, s, s, s
            $types = "isiii" . str_repeat('s', 7); // "isiiissssss"
            $bindVars = [
                $quiz_id,
                $acc_id,
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
            ];
        }

        // Use argument unpacking to bind dynamically
        // mysqli_stmt::bind_param requires variables passed by reference,
        // so build an array of references:
        $refs = [];
        foreach ($bindVars as $k => $v) {
            $refs[$k] = &$bindVars[$k];
        }

        // Prepend types as first arg
        array_unshift($refs, $types);

        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            throw new Exception("Bind failed: " . $stmt->error);
        }

        // Execute
        if ($stmt->execute()) {
            $inserted = true;
            $inserted_id = $stmt->insert_id;
            $stmt->close();
            break;
        }

        $lastError = $stmt->error;
        $errno = $mysqli->errno;
        $stmt->close();

        // If collision on public_id (duplicate key 1062), retry
        if ($submitted_has_public && $errno === 1062 && $attempt < $maxPublicRetries) {
            $attempt++;
            continue; // regenerate public_id and try again
        }

        // otherwise fail
        throw new Exception("Execute failed: " . $lastError);
    }

    $mysqli->commit();

} catch (Exception $ex) {
    $mysqli->rollback();
    error_log("save_booking.php error: " . $ex->getMessage());
    die("Unable to save booking: " . htmlspecialchars($ex->getMessage()));
}

/* -------------------------
   Redirect / show success
--------------------------*/
$redirectUrl = 'takequiz.php';
if (!empty($final_public_id)) {
    // include public_id in query so user can copy/share the booking link
    $redirectUrl .= '?pid=' . urlencode($final_public_id);
}

// small friendly message, then redirect
header("refresh:2;url=" . $redirectUrl);
echo "<h3>Flight submitted</h3>";
if (!empty($final_public_id)) {
    echo "<p>Booking ID: <strong>" . htmlspecialchars($final_public_id) . "</strong></p>";
    echo "<p><a href=\"submitted_flights_view.php?pid=" . htmlspecialchars($final_public_id) . "\">View booking</a></p>";
}
echo "<p>Redirecting...</p>";
exit;
