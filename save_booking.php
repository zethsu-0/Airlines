<?php
// save_booking.php (patched + debug logging)
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB credentials — adapt to your environment
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    error_log("save_booking.php: DB connect failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo "Database connection failed";
    exit;
}

// === DEBUG: log incoming POST/session for troubleshooting ===
error_log("=== save_booking.php incoming POST ===");
foreach ($_POST as $k => $v) {
    if (is_array($v)) {
        error_log("$k => " . json_encode($v));
    } else {
        error_log("$k => $v");
    }
}
error_log("=== save_booking.php session === " . json_encode($_SESSION));
error_log("=== end incoming payload ===");

// Helper
function clean($s) {
    return trim($s === null ? '' : (string)$s);
}

// Read posted data (with fallbacks)
$errors = [];
$acc_id = $_SESSION['acc_id'] ?? null;

// Flight fields
$flight_id = $_SESSION['flight_id'] ?? null;
$origin = strtoupper(clean($_POST['origin'] ?? ''));
$destination = strtoupper(clean($_POST['destination'] ?? ''));
$flight_date = clean($_POST['flight_date'] ?? '');

// Passenger arrays
$names = $_POST['name'] ?? [];
$ages = $_POST['age'] ?? [];
$specials = $_POST['special'] ?? [];
// Accept seat_class[] OR seat[] as fallback
$seats_class = $_POST['seat_class'] ?? $_POST['seat'] ?? [];
$seats_number = $_POST['seat_number'] ?? [];
$impairments = $_POST['impairment'] ?? [];
$genders = $_POST['gender'] ?? []; // expecting gender[0], gender[1], ...
$pwdArr = $_POST['pwd'] ?? [];

// Basic validation (flight)
if (empty($flight_id)) {
    if ($origin === '') $errors[] = 'Origin is required.';
    if (!preg_match('/^[A-Z]{3}$/', $origin)) $errors[] = 'Origin must be 3 uppercase letters.';
    if ($destination === '') $errors[] = 'Destination is required.';
    if (!preg_match('/^[A-Z]{3}$/', $destination)) $errors[] = 'Destination must be 3 uppercase letters.';
    if ($origin === $destination) $errors[] = 'Origin and destination cannot be the same.';
    if ($flight_date === '') $errors[] = 'Flight date is required.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) $errors[] = 'Invalid flight date format (expected YYYY-MM-DD).';
    else {
        $d = DateTime::createFromFormat('Y-m-d', $flight_date);
        if (!$d || $d->format('Y-m-d') !== $flight_date) $errors[] = 'Invalid flight date.';
    }
}

// Passenger validation
$passengerCount = is_array($names) ? count($names) : 0;
if ($passengerCount < 1) $errors[] = 'At least one passenger is required.';
if (!is_array($ages) || count($ages) < $passengerCount) $errors[] = 'Passenger ages are missing or incomplete.';
if (!is_array($seats_class) || count($seats_class) < $passengerCount) $errors[] = 'Passenger seat classes are missing.';
if (!is_array($seats_number) || count($seats_number) < $passengerCount) $errors[] = 'Passenger seat numbers are missing.';

for ($i = 0; $i < $passengerCount; $i++) {
    $name = clean($names[$i] ?? '');
    $age = clean($ages[$i] ?? '');
    $seatNum = clean($seats_number[$i] ?? '');
    if ($name === '') $errors[] = "Passenger " . ($i+1) . " name is required.";
    if ($age === '' || !is_numeric($age) || intval($age) < 0 || intval($age) > 130) $errors[] = "Passenger " . ($i+1) . " age is invalid.";
    if ($seatNum === '') $errors[] = "Passenger " . ($i+1) . " seat number is required.";
    // optional seat format check (commented out if you use different format)
    // if ($seatNum !== '' && !preg_match('/^\d{1,2}[A-Z]$/i', $seatNum)) $errors[] = "Passenger " . ($i+1) . " seat number format is invalid (e.g., 12A).";
}

// If validation errors, log and redirect with session errors (so you can display them)
if (!empty($errors)) {
    error_log("save_booking.php validation errors: " . json_encode($errors));
    $_SESSION['booking_errors'] = $errors;
    header('Location: ticket.php');
    exit;
}

// Begin transaction
$mysqli->begin_transaction();

try {
    // If no flight_id create submitted_flights
    if (empty($flight_id)) {
        $origin_airline = clean($_POST['origin_airline'] ?? '');
        $destination_airline = clean($_POST['destination_airline'] ?? '');

        $stmt = $mysqli->prepare("INSERT INTO submitted_flights (acc_id, origin_code, origin_airline, destination_code, destination_airline, flight_date) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare submitted_flights failed: " . $mysqli->error);
        }
        if (!$stmt->bind_param("isssss", $acc_id, $origin, $origin_airline, $destination, $destination_airline, $flight_date)) {
            throw new Exception("Bind submitted_flights failed: " . $stmt->error);
        }
        if (!$stmt->execute()) {
            throw new Exception("Execute submitted_flights failed: " . $stmt->error);
        }
        $flight_id = $stmt->insert_id;
        $_SESSION['flight_id'] = $flight_id;
        $stmt->close();
    } else {
        // verify flight exists
        $chk = $mysqli->prepare("SELECT id FROM submitted_flights WHERE id = ?");
        if (!$chk) throw new Exception("Prepare flight check failed: " . $mysqli->error);
        $chk->bind_param("i", $flight_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) {
            $chk->close();
            throw new Exception("Referenced flight not found. Please reselect your flight.");
        }
        $chk->close();
    }

    // Insert booking
    $booking_ref = strtoupper(bin2hex(random_bytes(4)));
    $total_passengers = $passengerCount;

    $insBooking = $mysqli->prepare("INSERT INTO bookings (flight_id, acc_id, booking_ref, total_passengers, status) VALUES (?, ?, ?, ?, 'confirmed')");
    if (!$insBooking) throw new Exception("Prepare bookings failed: " . $mysqli->error);
    $insBooking->bind_param("iisi", $flight_id, $acc_id, $booking_ref, $total_passengers);
    if (!$insBooking->execute()) throw new Exception("Booking insert failed: " . $insBooking->error);
    $booking_id = $insBooking->insert_id;
    $insBooking->close();

    // Prepare seat reservation insert
    $reserveStmt = $mysqli->prepare("INSERT INTO seat_reservations (flight_id, booking_id, seat_class, seat_number) VALUES (?, ?, ?, ?)");
    if (!$reserveStmt) throw new Exception("Prepare reserveStmt failed: " . $mysqli->error);

    // Prepare passenger insert (with seat_class & seat_number)
    $insPassenger = $mysqli->prepare("INSERT INTO booking_passengers (booking_id, passenger_index, full_name, age, passenger_type, gender, seat_type, seat_class, seat_number, is_pwd, impairment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$insPassenger) throw new Exception("Prepare insPassenger failed: " . $mysqli->error);

    // Loop through passengers: reserve seat -> insert passenger -> link reservation
    for ($i = 0; $i < $passengerCount; $i++) {
        $name = clean($names[$i]);
        $age = intval($ages[$i]);
        $pass_type = clean($specials[$i] ?? '');
        $gender = clean($genders[$i] ?? '');
        $seat_class = clean($seats_class[$i] ?? '');
        $seat_number = strtoupper(clean($seats_number[$i] ?? ''));
        $imp = clean($impairments[$i] ?? '');
        $is_pwd = isset($pwdArr[$i]) ? 1 : 0;

        if ($seat_number === '') {
            throw new Exception("Passenger " . ($i+1) . " seat number is required.");
        }

        // Reserve seat (unique constraint on flight_id + seat_number prevents duplicates)
        if (!$reserveStmt->bind_param("iiss", $flight_id, $booking_id, $seat_class, $seat_number)) {
            throw new Exception("Bind reserveStmt failed: " . $reserveStmt->error);
        }
        if (!$reserveStmt->execute()) {
            // duplicate seat -> errno 1062
            if ($reserveStmt->errno === 1062) {
                throw new Exception("Seat " . $seat_number . " on this flight is already taken. Please choose another seat.");
            }
            throw new Exception("Execute reserveStmt failed: " . $reserveStmt->error);
        }
        $reservation_id = $reserveStmt->insert_id;

        // Insert passenger
        // bind types: i (booking_id), i (index), s (name), i (age), s (type), s (gender), s (seat_type), s (seat_class), s (seat_number), i (is_pwd), s (impairment)
        if (!$insPassenger->bind_param("iisisssssis",
            $booking_id,
            $i,
            $name,
            $age,
            $pass_type,
            $gender,
            $seat_class,   // seat_type column
            $seat_class,   // seat_class column (duplicated intentionally)
            $seat_number,
            $is_pwd,
            $imp
        )) {
            throw new Exception("Bind failed passenger: " . $insPassenger->error);
        }

        if (!$insPassenger->execute()) {
            throw new Exception("Execute insPassenger failed (row " . ($i+1) . "): " . $insPassenger->error);
        }
        $passenger_id = $insPassenger->insert_id;

        // Link reservation to passenger (optional)
        $upd = $mysqli->prepare("UPDATE seat_reservations SET passenger_id = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("ii", $passenger_id, $reservation_id);
            $upd->execute();
            $upd->close();
        }
    }

    // Clean up
    $reserveStmt->close();
    $insPassenger->close();

    // Commit
    $mysqli->commit();

    // Save booking info in session and redirect to confirmation
    $_SESSION['booking_id'] = $booking_id;
    $_SESSION['booking_ref'] = $booking_ref;

    header("Location: booking_confirm.php?id=" . urlencode($booking_id) . "&ref=" . urlencode($booking_ref));
    exit;

} catch (Exception $e) {
    // Rollback and log
    $mysqli->rollback();
    error_log("save_booking.php error: " . $e->getMessage());
    // Provide a user-friendly error and redirect back to ticket page
    $_SESSION['booking_errors'] = ["Server error while saving booking: " . $e->getMessage()];
    header('Location: ticket.php');
    exit;
}
