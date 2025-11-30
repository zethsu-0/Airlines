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

$quizId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$descObj = null;
$quiz = null;

if ($quizId > 0) {
  // Use `from` / `to` as section / audience, and quiz_code as code
  $qStmt = $conn->prepare("
    SELECT id,
           title,
           `from` AS section,
           `to`   AS audience,
           duration,
           quiz_code AS code
    FROM quizzes
    WHERE id = ?
  ");
  if ($qStmt) {
    $qStmt->bind_param('i', $quizId);
    $qStmt->execute();
    $qres = $qStmt->get_result();
    $quiz = $qres->fetch_assoc();
    $qStmt->close();
  }

  if ($quiz) {
    $items = [];

    $itemSql = "
      SELECT
        id,
        adults,
        children,
        infants,
        flight_type,
        origin_iata      AS origin,
        destination_iata AS destination,
        departure_date   AS departure,
        return_date,
        flight_number,
        seats,
        travel_class
      FROM quiz_items
      WHERE quiz_id = ?
      ORDER BY id ASC
    ";
    $stmt = $conn->prepare($itemSql);
    if ($stmt) {
      $stmt->bind_param('i', $quizId);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($r = $res->fetch_assoc()) {
        $r['adults']   = isset($r['adults'])   ? (int)$r['adults']   : 0;
        $r['children'] = isset($r['children']) ? (int)$r['children'] : 0;
        $r['infants']  = isset($r['infants'])  ? (int)$r['infants']  : 0;
        $items[] = $r;
      }
      $stmt->close();
    }

    $parts = [];
    $firstDestination = null;

    foreach ($items as $idx => $it) {
      // ----- People breakdown: seats vs. infants (no seat) -----
      $seatParts = [];
      if (!empty($it['adults'])) {
        $seatParts[] = $it['adults'] . ($it['adults'] === 1 ? ' adult' : ' adults');
      }
      if (!empty($it['children'])) {
        $seatParts[] = $it['children'] . ($it['children'] === 1 ? ' child' : ' children');
      }

      $seatStr = count($seatParts) ? implode(', ', $seatParts) : '';

      $infantStr = '';
      if (!empty($it['infants'])) {
        $infantStr = $it['infants'] . ($it['infants'] === 1 ? ' infant' : ' infants');
      }

      $personStr = '';
      if ($seatStr && $infantStr) {
        $personStr = $seatStr . ' plus ' . $infantStr . ' (no separate seat)';
      } elseif ($seatStr) {
        $personStr = $seatStr;
      } elseif ($infantStr) {
        $personStr = $infantStr . ' (no separate seat)';
      }

      // ----- Airports -----
      $orgCode = strtoupper(trim($it['origin'] ?? ''));
      $dstCode = strtoupper(trim($it['destination'] ?? ''));
      $orgReadable = $orgCode ? format_airport_display($orgCode, $iataMap) : '---';
      $dstReadable = $dstCode ? format_airport_display($dstCode, $iataMap) : '---';

      // ----- Flight type / class -----
      $ftRaw = strtoupper(trim($it['flight_type'] ?? ''));
      $typeLabel = ($ftRaw === 'ROUND-TRIP' || $ftRaw === 'ROUNDTRIP' || $ftRaw === 'RT')
        ? 'round-trip'
        : 'one-way';

      $classRaw = strtolower(trim($it['travel_class'] ?? 'economy'));
      switch ($classRaw) {
        case 'first':
        case 'first class':
          $classLabel = 'First Class';
          break;
        case 'business':
          $classLabel = 'Business';
          break;
        case 'premium':
        case 'premium economy':
          $classLabel = 'Premium Economy';
          break;
        default:
          $classLabel = 'Economy';
      }

      $sentence = '';
      if ($personStr) {
        $sentence .= $personStr . ' ';
      }
      $sentence .= "flying from {$orgReadable} to {$dstReadable} on a {$typeLabel} flight in {$classLabel} class";

      $parts[] = $sentence;

      if ($idx === 0) {
        $firstDestination = $dstReadable !== '---' ? $dstReadable : ($dstCode ?: null);
      }
    }

    if (count($parts) === 1) {
      $desc = 'Book ' . $parts[0] . '.';
    } elseif (count($parts) > 1) {
      $sentences = array_map(function($p){ return $p . '.'; }, $parts);
      $desc = 'Book the following flights: ' . implode(' ', $sentences);
    } else {
      $desc = 'Book the indicated destinations.';
    }

    if (!empty($quiz['duration'])) {
      $desc .= ' Duration: ' . (int)$quiz['duration'] . ' minutes.';
    }

    $descObj = [
      'description'     => $desc,
      'expected_answer' => $firstDestination ?: null,
      'itemsCount'      => count($items),
      'firstDeadline'   => ''
    ];
  } else {
    http_response_code(404);
    echo "Quiz not found or you do not have access to it.";
    exit;
  }
}

// POST values
$origin       = strtoupper(trim($_POST['origin'] ?? ''));
$destination  = strtoupper(trim($_POST['destination'] ?? ''));
$flight_date  = trim($_POST['flight_date'] ?? '');
$flight_type  = $_POST['flight_type'] ?? 'ONE-WAY';
$return_date  = trim($_POST['return_date'] ?? '');
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_SESSION['acc_id'])) {
    $errors['login'] = 'You must be logged in to submit a flight.';
  }

  if (empty($origin)) {
    $errors['origin'] = 'Origin code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $origin)) {
    $errors['origin'] = 'Origin must be 3 uppercase letters.';
  }

  if (empty($destination)) {
    $errors['destination'] = 'Destination code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $destination)) {
    $errors['destination'] = 'Destination must be 3 uppercase letters.';
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

  if (!empty($_POST['ajax_validate'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok'     => empty($errors),
      'errors' => $errors,
      'flight' => [
        'origin'       => $origin,
        'destination'  => $destination,
        'flight_date'  => $flight_date,
        'return_date'  => $return_date,
        'flight_type'  => $flight_type
      ]
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
      </p>
      <div class="row">
        <!-- ORIGIN -->
        <div class="col s4 md3">
          <div class="input-field" style="position:relative;">
            <i class="material-icons prefix">flight_takeoff</i>
            <input type="text" id="origin_autocomplete" class="center" autocomplete="off"
              placeholder="e.g. MNL"
              value="<?php 
                if (!empty($origin)) {
                  echo htmlspecialchars(format_airport_display($origin, $iataMap));
                } else {
                  echo '';
                }
              ?>">
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
              value="<?php 
                if (!empty($destination)) {
                  echo htmlspecialchars(format_airport_display($destination, $iataMap));
                } else {
                  echo '';
                }
              ?>">
            <label for="destination_autocomplete">DESTINATION</label>
            <div class="red-text"><?php echo $errors['destination'] ?? ''; ?></div>
            <input type="hidden" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
          </div>
        </div>

        <!-- DATES -->
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

        <div class="col s3 md3"></div>
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

    initPlainIataInput('origin_autocomplete', 'origin');
    initPlainIataInput('destination_autocomplete', 'destination');

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
        else typeField.value = 'Regular';
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

    function fillBookingHiddenFlightFields(flight) {
      const bOrigin = document.getElementById('booking_origin');
      const bDestination = document.getElementById('booking_destination');
      const bDate = document.getElementById('booking_flight_date');
      const bReturn = document.getElementById('booking_return_date');
      const bType = document.getElementById('booking_flight_type');
      const bOriginAir = document.getElementById('booking_origin_airline');
      const bDestAir = document.getElementById('booking_destination_airline');

      const originCode = flight.origin || document.getElementById('origin').value || '';
      const destCode = flight.destination || document.getElementById('destination').value || '';
      const depDate = flight.flight_date || document.getElementById('flight-date').value || '';
      const retDate = flight.return_date || (document.getElementById('return-date') ? document.getElementById('return-date').value : '');
      const typeRadio = document.querySelector('input[name="flight_type"]:checked');
      const typeVal = flight.flight_type || (typeRadio ? typeRadio.value : 'ONE-WAY');

      if (bOrigin) bOrigin.value = originCode;
      if (bDestination) bDestination.value = destCode;
      if (bDate) bDate.value = depDate;
      if (bReturn) bReturn.value = retDate;
      if (bType) bType.value = typeVal;
      if (bOriginAir) bOriginAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[originCode]) || '';
      if (bDestAir) bDestAir.value = (window.IATA_LOOKUP && window.IATA_LOOKUP[destCode]) || '';
    }

    function buildSummaryAndOpen(flight) {
      fillBookingHiddenFlightFields(flight);

      const tickets = document.querySelectorAll('.ticket-card');
      const passengerCount = tickets.length;
      const typeLabel = (flight.flight_type === 'ROUND-TRIP') ? 'ROUND-TRIP (round trip)' : 'One-way';

      let html = `<p><strong>Origin:</strong> ${escapeHtml(flight.origin)}</p>
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

      const fd = new FormData(flightForm || document.createElement('form'));
      fd.set('form_submit', '1');
      fd.set('ajax_validate', '1');

      fetch('ticket.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
        .then(resp => resp.json())
        .then(json => {
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

      confirmBtn.disabled = true;
      summaryModal.close();
      setTimeout(() => bookingForm.submit(), 120);
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
