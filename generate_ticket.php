<?php
// generate_ticket.php
session_start();

// helper to sanitize output
function clean($v) {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES);
}

// CSRF token check (index.php sets this token)
if (empty($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
    http_response_code(400);
    echo 'Invalid request (CSRF).';
    exit;
}

// Collect inputs safely
$passenger_name = !empty($_POST['passenger_name']) ? clean($_POST['passenger_name']) : 'Passenger';
$age = isset($_POST['age']) ? (int)$_POST['age'] : null;
$passenger_type = !empty($_POST['passenger_type']) ? clean($_POST['passenger_type']) : '—';

$from = !empty($_POST['from']) ? clean($_POST['from']) : 'Unknown';
$to = !empty($_POST['to']) ? clean($_POST['to']) : 'Unknown';
$departure_date_raw = !empty($_POST['departure_date']) ? $_POST['departure_date'] : date('Y-m-d');
$departure_date = clean($departure_date_raw);

// Gender: may come as array of checkboxes; pick first checked if any
$gender = '—';
if (!empty($_POST['gender'])) {
    if (is_array($_POST['gender'])) {
        foreach ($_POST['gender'] as $g) {
            $g = trim((string)$g);
            if ($g !== '') { $gender = clean($g); break; }
        }
    } else {
        $gender = clean($_POST['gender']);
    }
}

// Disability
$has_disability = !empty($_POST['disability']) && ($_POST['disability'] == '1' || $_POST['disability'] == 'on' || $_POST['disability'] === 1);
$disability_spec = $has_disability && !empty($_POST['disability_spec']) ? clean($_POST['disability_spec']) : 'N/A';

// Seat
$seat_type = !empty($_POST['seat_type']) ? clean($_POST['seat_type']) : 'Any';
$seat_number = !empty($_POST['seat_number']) ? clean($_POST['seat_number']) : null;

// Contact
$email = !empty($_POST['email']) ? clean($_POST['email']) : '';
$phone = !empty($_POST['phone']) ? clean($_POST['phone']) : '';

// Account id optional
$acc_id = !empty($_POST['acc_id']) ? clean($_POST['acc_id']) : 'N/A';

// Generate fake identifiers
$pnr = strtoupper(substr(hash('crc32b', $passenger_name . microtime()), 0, 6));
$ticket_no = 'TKT' . substr(time() . rand(100,999), -10);
$issued_at = date('Y-m-d H:i');
$boarding_time = date('H:i', strtotime($departure_date_raw . ' -01 hour')) ?: '09:00';
$gate = 'G' . rand(1,30);
if (!$seat_number) {
    // generate a seat if none specified
    $seat_number = strtoupper((chr(65 + rand(0,5))) . rand(1,39));
}

// Human readable departure date
$dep_read = @date('D, M j, Y', strtotime($departure_date_raw)) ?: $departure_date_raw;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Practice Ticket — <?php echo $pnr; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css?family=Inter:400,600,700" rel="stylesheet">
  <style>
    body { font-family: Inter, Arial, sans-serif; background:#f4f7fb; padding:28px; }
    .ticket-wrap { max-width:920px; margin:0 auto; }
    .ticket { border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08); background:#fff; }
    .top { display:flex; padding:18px 22px; align-items:center; gap:16px; background:linear-gradient(90deg,#0b66ff,#3dd6c1); color:#fff; }
    .logo { font-weight:700; font-size:18px; letter-spacing:1px; }
    .pnr { margin-left:auto; text-align:right; font-weight:600; }
    .body { display:flex; gap:14px; padding:18px; }
    .left { flex:1; }
    .right { flex:0 0 300px; }
    .route h2 { margin:0; font-size:28px; }
    .route p { margin:6px 0 0 0; color:#666; }
    .meta { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-top:12px; }
    .chip { background:#fbfdff; padding:10px; border-radius:8px; }
    .chip strong { display:block; font-size:12px; color:#222; }
    .chip span { color:#444; font-size:15px; }
    .info .row { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .footer { display:flex; gap:12px; padding:16px; background:#fafafa; align-items:center; justify-content:space-between; flex-wrap:wrap; }
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
    <div class="ticket" role="region" aria-label="Practice flight ticket">
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
            <h2><?php echo clean($from); ?> → <?php echo clean($to); ?></h2>
            <p><?php echo $dep_read; ?> • Boarding: <?php echo $boarding_time; ?></p>
            <div class="meta">
              <div class="chip"><strong>Passenger</strong><span><?php echo $passenger_name; ?></span></div>
              <div class="chip"><strong>Age / Type</strong><span><?php echo ($age !== null ? $age : '—') . ' / ' . $passenger_type; ?></span></div>
              <div class="chip"><strong>Gender</strong><span><?php echo $gender; ?></span></div>
              <div class="chip"><strong>Disability</strong><span><?php echo $has_disability ? ($disability_spec ?: 'Yes') : 'No'; ?></span></div>
            </div>
          </div>

          <div style="margin-top:14px;">
            <div class="info">
              <div class="row"><strong>Contact</strong><span><?php echo $email ?: $phone ?: '—'; ?></span></div>
              <div class="row"><strong>Account ID</strong><span><?php echo $acc_id; ?></span></div>
              <div class="row"><strong>Ticket No</strong><span><?php echo $ticket_no; ?></span></div>
              <div class="row"><strong>Issued at</strong><span><?php echo $issued_at; ?></span></div>
            </div>
          </div>
        </div>

        <div class="right">
          <div style="background:#fbfdff;padding:12px;border-radius:8px;">
            <div style="font-size:12px;color:#666;margin-bottom:8px;">FLIGHT INFO</div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Gate</strong><span><?php echo $gate; ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Seat</strong><span><?php echo $seat_number; ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><strong>Seat Type</strong><span><?php echo $seat_type; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><strong>Boarding</strong><span><?php echo $boarding_time; ?></span></div>
          </div>

          <div style="margin-top:12px;background:#fff;padding:10px;border-radius:8px;box-shadow:0 6px 18px rgba(11,102,255,0.06);">
            <div style="font-size:12px;color:#666;margin-bottom:8px;">NOTES</div>
            <div class="small">This ticket is generated for practice only and is not a real reservation.</div>
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
          <a href="index.php" class="small" style="text-decoration:none;color:#0b66ff;">Back to site</a>
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
