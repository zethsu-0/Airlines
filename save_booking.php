<?php
// save_booking.php - ONLY inserts into submitted_flights
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

function clean($s) {
    return trim($s === null ? '' : (string)$s);
}

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
   Flight fields (from hidden inputs in bookingForm)
--------------------------*/
$origin      = strtoupper(clean($_POST['origin'] ?? ''));
$destination = strtoupper(clean($_POST['destination'] ?? ''));
$departure   = clean($_POST['flight_date'] ?? '');
$return_date = clean($_POST['return_date'] ?? '');
$flight_type = strtolower(clean($_POST['flight_type'] ?? 'ONE-WAY'));
if (!in_array($flight_type, ['ONE-WAY', 'ROUND-TRIP'], true)) {
    $flight_type = 'ONE-WAY';
}
if ($flight_type !== 'ROUND-TRIP') {
    $return_date = '';
}

// no field in form, so leave empty for now
$flight_number = "";

/* -------------------------
   Passengers (for counts/seats/travel_class)
--------------------------*/
$names        = $_POST['name'] ?? [];
$ages         = $_POST['age'] ?? [];
$specials     = $_POST['special'] ?? [];        // "Infant", "Child", "Regular"
$seats_class  = $_POST['seat_class'] ?? $_POST['seat'] ?? [];  // "Economy", etc.
$seats_number = $_POST['seat_number'] ?? [];

$passengerCount = is_array($names) ? count($names) : 0;

/* -------------------------
   Basic validation
--------------------------*/
$errors = [];

if ($origin === '') $errors[] = 'Origin is required.';
if (!preg_match('/^[A-Z]{3}$/', $origin)) $errors[] = 'Origin must be 3 uppercase letters.';

if ($destination === '') $errors[] = 'Destination is required.';
if (!preg_match('/^[A-Z]{3}$/', $destination)) $errors[] = 'Destination must be 3 uppercase letters.';

if ($origin === $destination && $origin !== '') {
    $errors[] = 'Origin and destination cannot be the same.';
}

if ($departure === '') {
    $errors[] = 'Departure date is required.';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure)) {
    $errors[] = 'Invalid departure date format (expected YYYY-MM-DD).';
}

if ($flight_type === 'ROUND-TRIP') {
    if ($return_date === '') {
        $errors[] = 'Return date is required for round-trip flights.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
        $errors[] = 'Invalid return date format (expected YYYY-MM-DD).';
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
        if ($cls !== '') $classes[$cls] = true;
    }
    $distinct = array_keys($classes);
    if (count($distinct) === 1) {
        $travel_class = strtoupper($distinct[0]);
    } elseif (count($distinct) > 1) {
        $travel_class = 'MIXED';
    } else {
        $travel_class = '';
    }
}
// seat numbers: convert array -> single string for DB storage
$seats_number = $_POST['seat_number'] ?? [];

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


$stmt = $mysqli->prepare("
    INSERT INTO submitted_flights
        (quiz_id, acc_id, adults, children, infants, flight_type,
         origin, destination, departure, return_date, seat_number, travel_class)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    die("Prepare failed: " . htmlspecialchars($mysqli->error));
}

// types: quiz_id(i), acc_id(s), adults(i), children(i), infants(i),
//        flight_type(s), origin(s), destination(s), departure(s),
//        return_date(s), flight_number(s), seats(i), travel_class(s)
if (!$stmt->bind_param(
    "isiiisssssss",
    $quiz_id,       // i
    $acc_id,        // s
    $adults,        // i
    $children,      // i
    $infants,       // i
    $flight_type,   // s
    $origin,        // s
    $destination,   // s
    $departure,     // s
    $return_date,   // s
    $seat_number,   // s
    $travel_class   // s
)) {
    die("Bind failed: " . htmlspecialchars($stmt->error));
}
printf("quiz_id=%d, acc_id='%s'\n", $quiz_id, $acc_id);
var_dump([
    $quiz_id, $acc_id, $adults, $children, $infants,
    $flight_type, $origin, $destination, $departure,
    $return_date, $seat_number, $travel_class
]);

if (!$stmt->execute()) {
    die("Execute failed: " . htmlspecialchars($stmt->error));
} else {
    echo "Inserted acc_id = " . htmlspecialchars($acc_id);
}

$stmt->close();

echo "<h3>Flight submission saved to submitted_flights!</h3>";
echo "<p><a href=\"ticket.php?id=" . htmlspecialchars($quiz_id) . "\">Back to ticket</a></p>";

