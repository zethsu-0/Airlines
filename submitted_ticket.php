<?php
// submitted_ticket.php
// Shows a "ticket" view based on a row from submitted_flights

session_start();

// Simple helper to escape output
function clean($v) {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
}

// --- 1. Get submission id from URL ---
$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($submission_id <= 0) {
    http_response_code(400);
    echo "Missing or invalid submission id.";
    exit;
}

// --- 2. DB connection (adjust if your credentials differ) ---
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'airlines';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: " . clean($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');

// --- 3. Load the submitted_flights row ---
$sql = "SELECT 
            id,
            quiz_id,
            acc_id,
            adults,
            children,
            infants,
            flight_type,
            origin,
            destination,
            departure,
            return_date,
            flight_number,
            seat_number,
            travel_class,
            submitted_at,
            legs_json
        FROM submitted_flights
        WHERE id = ?
        LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Query prepare failed: " . clean($mysqli->error);
    exit;
}
$stmt->bind_param('i', $submission_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$mysqli->close();

if (!$row) {
    http_response_code(404);
    echo "Submission not found.";
    exit;
}

// --- 4. Map DB fields into "ticket" variables ---

// Passenger name: use session name or generic
$passenger_name = !empty($_SESSION['acc_name']) ? clean($_SESSION['acc_name']) : 'Passenger';

// Passenger type string from adults/children/infants
$parts = [];
$adults   = (int)($row['adults']   ?? 0);
$children = (int)($row['children'] ?? 0);
$infants  = (int)($row['infants']  ?? 0);

if ($adults > 0) {
    $parts[] = $adults . ' Adult' . ($adults > 1 ? 's' : '');
}
if ($children > 0) {
    $parts[] = $children . ' Child' . ($children > 1 ? 'ren' : '');
}
if ($infants > 0) {
    $parts[] = $infants . ' Infant' . ($infants > 1 ? 's' : '');
}
$passenger_type = !empty($parts) ? implode(', ', $parts) : '—';

// Origin / destination
$from = clean($row['origin']      ?? 'Unknown');
$to   = clean($row['destination'] ?? 'Unknown');

// Dates
$departure_date_raw = $row['departure'] ?? date('Y-m-d');
$departure_date     = clean($departure_date_raw);
$return_date_raw    = $row['return_date'] ?? null;

// Human-readable date
$dep_read = @date('D, M j, Y', strtotime($departure_date_raw)) ?: $departure_date_raw;

// Flight info
$flight_type   = clean($row['flight_type']   ?? '');
$flight_number = clean($row['flight_number'] ?? '');
$seat_type     = clean($row['travel_class']  ?? 'Any');
$seat_number   = clean($row['seat_number']   ?? '');

// If no seat_number, generate a fake one
if ($seat_number === '') {
    $seat_number = strtoupper(chr(65 + rand(0, 5)) . rand(1, 39));
}

// Account / meta
$acc_id       = clean($row['acc_id']       ?? 'N/A');
$submitted_at = !empty($row['submitted_at']) ? clean($row['submitted_at']) : date('Y-m-d H:i');

// We aren't storing age, gender, disability, email, phone in this table,
// so we leave them blank / defaults for the template:
$age            = null;
$has_disability = false;
$disability_spec = 'N/A';
$email          = '';
$phone          = '';

// Ticket identifiers
$pnr        = strtoupper(substr(hash('crc32b', $submission_id . microtime()), 0, 6));
$ticket_no  = 'TKT' . substr(time() . rand(100, 999), -10);
$issued_at  = $submitted_at;

// Boarding time: if you only store a date, just choose a default hour
$boarding_time = date('H:i', strtotime($departure_date_raw . ' 09:00'));

// Optional: if you want to use legs_json later, you can decode it:
// $legs = [];
// if (!empty($row['legs_json'])) {
//     $decoded = json_decode($row['legs_json'], true);
//     if (is_array($decoded)) $legs = $decoded;
// }

// Random gate
$gate = 'G' . rand(1, 30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Submitted Ticket — <?php echo $pnr; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css?family=Inter:400,600,700" rel="stylesheet">
  <style>
    body { font-family: Inter, Arial, sans-serif; background:#f4f7fb; margin:2px; padding:0px; background-color: transparent;}
    .ticket-wrap { max-width:920px; margin:0 auto; }
    .ticket { border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08); background:#fff; }
    .top { display:flex; padding:18px 22px; align-items:center; gap:16px; background:linear-gradient(90deg,#0b66ff,#3dd6c1); color:#fff; }
    .logo { font-weight:700; font-size:18px; letter-spacing:1px; }
    .pnr { margin-left:auto; text-align:right; font-weight:600; }
    .body { display:flex; gap:14px; padding:18px;}
    .left { flex:1; }
    .right { flex:0 0 300px; }
    .route h2 { margin:0; font-size:28px; }
    .route p { margin:6px 0 0 0; color:#666; }
    .meta { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-top:12px; }
    .chip { background:#fbfdff; padding:10px; border-radius:8px; }
    .chip strong { display:block; font-size:12px; color:#222; }
    .chip span { color:#444; font-size:15px; }
    .info .row { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .footer { display:flex; gap:12px; padding:16px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
    .btn-print { background:#0b66ff; color:#fff; padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:600; }
    .small { font-size:13px; color:#666; }
    @media (max-width:820px) {
      .body { flex-direction:column; }
      .right { width:100%; }
    }

  </style>
</head>
<body>
  <div class="ticket-wrap">
    <div class="ticket" role="region" aria-label="Submitted flight ticket">
      <div class="top">
        <div class="logo">AIRLINES PRACTICE</div>
        <div style="font-size:13px;">Issued: <?php echo $issued_at; ?></div>
        <div class="pnr">
          <div style="font-size:12px;">PNR</div>
          <div style="font-size:20px;"><?php echo $pnr; ?></div>
        </div>
      </div>

      <div class="body">
        <div class="left">
          <div class="route">
            <h2><?php echo $from; ?> → <?php echo $to; ?></h2>
            <p>
              <?php echo clean($flight_type ?: 'Flight'); ?> • 
              <?php echo $dep_read; ?> • Boarding: <?php echo $boarding_time; ?>
            </p>
            <div class="meta">
              <div class="chip"><strong>Booker</strong><span><?php echo $passenger_name; ?></span></div>
              <div class="chip"><strong>Passengers</strong><span><?php echo clean($passenger_type); ?></span></div>
              <div class="chip"><strong>Disability</strong><span><?php echo $has_disability ? ($disability_spec ?: 'Yes') : 'N/A'; ?></span></div>
            </div>
          </div>

          <div style="margin-top:14px;">
            <div class="info">
              <div class="row"><strong>Account ID</strong><span><?php echo $acc_id; ?></span></div>
              <div class="row"><strong>Ticket No</strong><span><?php echo $ticket_no; ?></span></div>
              <div class="row"><strong>Submitted at</strong><span><?php echo $submitted_at; ?></span></div>
              <div class="row"><strong>Quiz ID</strong><span><?php echo clean($row['quiz_id']); ?></span></div>
              <?php if (!empty($flight_number)): ?>
              <div class="row"><strong>Flight No</strong><span><?php echo $flight_number; ?></span></div>
              <?php endif; ?>
              <?php if (!empty($return_date_raw) && $return_date_raw !== '0000-00-00'): ?>
              <div class="row"><strong>Return date</strong><span><?php echo clean($return_date_raw); ?></span></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="right">
          <div style="background:#fbfdff;padding:12px;border-radius:8px;">
            <div style="font-size:12px;color:#666;margin-bottom:8px;">FLIGHT INFO</div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Gate</strong><span><?php echo $gate; ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Seat</strong><span><?php echo $seat_number; ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Class</strong><span><?php echo $seat_type; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><strong>Boarding</strong><span><?php echo $boarding_time; ?></span></div>
          </div>

          <div style="margin-top:12px;background:#fff;padding:10px;border-radius:8px;box-shadow:0 6px 18px rgba(11,102,255,0.06);">
            <div style="font-size:12px;color:#666;margin-bottom:8px;">NOTES</div>
            <div class="small">
              This ticket is generated from your submitted practice booking (ID: <?php echo (int)$row['id']; ?>)
              and is not a real reservation.
            </div>
          </div>
        </div>
      </div>

      <div class="footer">
        <div>
          <div style="font-weight:700;"><?php echo $passenger_name; ?></div>
          <div class="small">PNR: <?php echo $pnr; ?> • Ticket: <?php echo $ticket_no; ?></div>
        </div>

        <div style="display:flex;align-items:center;gap:12px;">
          <a href="#" class="btn-print" onclick="window.print(); return false;">Print / Save as PDF</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    // allow quick print with Ctrl/Cmd+P
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
      }
    });
  </script>
</body>
</html>
