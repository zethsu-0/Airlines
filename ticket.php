<?php
  // ticket.php (ready-to-paste)
  session_start();

  if (empty($_SESSION['acc_id'])) {
      header('Location: index.php');
      exit;
  }

  $require_login = true;
  include('config/db_connect.php');

  $studentId = $_SESSION['student_id'] ?? $_SESSION['acc_id'] ?? '';

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
      $iataMap[$it['code']]  = ['name' => $it['name'], 'city' => $it['city'], 'country' => $it['country']];
    }
  }

  function format_airport_display($code, $iataMap) {
  $code = trim(strtoupper((string)$code));
  if ($code === '') return '';
  $parts = [];
  if (!empty($iataMap[$code]['city'])) $parts[] = trim($iataMap[$code]['city']);
  if (!empty($iataMap[$code]['country'])) $parts[] = trim($iataMap[$code]['country']);
  if (!empty($iataMap[$code]['name'])) $parts[] = trim($iataMap[$code]['name']);
  if (count($parts) > 0) return implode(', ', $parts);
  return $code;
}

  $quizId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
  $descObj = null;
  $quiz = null;
  if ($quizId > 0) {
    $qStmt = $conn->prepare("SELECT id, title, section, audience, duration, quiz_code AS code FROM quizzes WHERE id = ?");
    if ($qStmt) {
      $qStmt->bind_param('i', $quizId);
      $qStmt->execute();
      $qres = $qStmt->get_result();
      $quiz = $qres->fetch_assoc();
      $qStmt->close();
    }
    if ($quiz) {
      $items = [];
      $itemSql = "SELECT id, deadline, adults, children, infants, flight_type, origin, destination, departure, return_date, flight_number, seats, travel_class
                  FROM quiz_items WHERE quiz_id = ? ORDER BY id ASC";
      $stmt = $conn->prepare($itemSql);
      if ($stmt) {
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
          $r['adults'] = isset($r['adults']) ? intval($r['adults']) : 0;
          $r['children'] = isset($r['children']) ? intval($r['children']) : 0;
          $r['infants'] = isset($r['infants']) ? intval($r['infants']) : 0;
          $items[] = $r;
        }
        $stmt->close();
      }

      $parts = [];
      $firstDeadlineRaw = '';
      $firstDestination = null;

      foreach ($items as $idx => $it) {
        $personParts = [];
        if (!empty($it['adults'])) $personParts[] = $it['adults'] . ($it['adults'] === 1 ? ' adult' : ' adults');
        if (!empty($it['children'])) $personParts[] = $it['children'] . ($it['children'] === 1 ? ' child' : ' children');
        if (!empty($it['infants'])) $personParts[] = $it['infants'] . ($it['infants'] === 1 ? ' infant' : ' infants');
        $personStr = count($personParts) ? implode(', ', $personParts) : '';

        $orgCode = strtoupper(trim($it['origin'] ?? ''));
        $dstCode = strtoupper(trim($it['destination'] ?? ''));
        $orgReadable = $orgCode ? format_airport_display($orgCode, $iataMap) : '---';
        $dstReadable = $dstCode ? format_airport_display($dstCode, $iataMap) : '---';

        $typeLabel = ($it['flight_type'] === 'roundtrip') ? 'round-trip' : 'one-way';
        $classLabel = $it['travel_class'] ? $it['travel_class'] : 'economy';

        $sentence = '';
        if ($personStr) $sentence .= $personStr . ' ';
        $sentence .= "flying from {$orgReadable} to {$dstReadable} on a {$typeLabel} flight in {$classLabel} class";

        $parts[] = $sentence;

        if ($idx === 0) {
          $firstDestination = $dstReadable !== '---' ? $dstReadable : ($dstCode ?: null);
        }

        if (empty($firstDeadlineRaw) && !empty($it['deadline'])) {
          $firstDeadlineRaw = $it['deadline'];
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
        $desc .= ' Duration: ' . intval($quiz['duration']) . ' minutes.';
      }

      $firstDeadlineFmt = '';
      if (!empty($firstDeadlineRaw)) {
        $ts = strtotime($firstDeadlineRaw);
        if ($ts !== false) $firstDeadlineFmt = date('M j, Y \@ H:i', $ts);
        else $firstDeadlineFmt = $firstDeadlineRaw;
      }

      $descObj = [
        'description'     => $desc,
        'expected_answer' => $firstDestination ?: null,
        'itemsCount'      => count($items),
        'firstDeadline'   => $firstDeadlineFmt
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
  $flight_type  = $_POST['flight_type'] ?? 'ONE-WAY';   // ONE-WAY / TWO-WAY
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

    // If not two-way, ignore any return date sent
    if ($flight_type !== 'TWO-WAY') {
      $return_date = '';
    } else {
      if (empty($return_date)) {
        $errors['return_date'] = 'Return date is required for two-way flights.';
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
            <div style="margin-top:10px; font-size:0.95em;" class="muted">Quiz: <?php echo htmlspecialchars($quiz['title']); ?> (Code: <?php echo htmlspecialchars($quiz['code'] ?? '—'); ?>)</div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div style="padding:12px; color:#666;">No quiz prompt available. Open this page with <code>?id=&lt;quiz_id&gt;</code> to see the prompt.</div>
      <?php endif; ?>

    </div>
  </div>
  <div class="bg-container container center">
    <form id="flightForm" action="ticket.php" method="POST" name="form_submit" autocomplete="off" class="card">
      <p>
        <label>
        <input name="flight_type" type="radio" value="ONE-WAY" <?php echo ($flight_type !== 'TWO-WAY') ? 'checked' : ''; ?> />
        <span>ONE-WAY</span>
        </label>
        <label>
        <input name="flight_type" type="radio" value="TWO-WAY" <?php echo ($flight_type === 'TWO-WAY') ? 'checked' : ''; ?> />
        <span>TWO-WAY</span>
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
                // show "City, Country, AirportName" if we have origin set server-side
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
              <div class="input-field col s6" id="return-date-wrapper" style="<?php echo ($flight_type === 'TWO-WAY') ? '' : 'display:none;'; ?>">
                <i class="material-icons prefix">calendar_today</i>
                <input type="text" id="return-date" name="return_date" class="datepicker" value="<?php echo htmlspecialchars($return_date); ?>" readonly>
                <label for="return-date">RETURN</label>
                <div class="red-text"><?php echo $errors['return_date'] ?? ''; ?></div>
              </div>              
            </div>
          </div>
        </div>

        <!-- TYPE -->
        <div class="col s3 md3">

        </div>
      </div>
    </form>
  </div>

  <div class="container">
    <form id="bookingForm" method="POST" action="save_booking.php">
      <!-- Hidden flight inputs -->
       <input type="hidden" name="acc_id" value="<?php echo htmlspecialchars($studentId); ?>">
      <input type="hidden" name="quiz_id" value="<?php echo $quizId ? (int)$quizId : 0; ?>">
      <input type="hidden" name="origin" id="booking_origin" value="">
      <input type="hidden" name="destination" id="booking_destination" value="">
      <input type="hidden" name="flight_date" id="booking_flight_date" value="">
      <input type="hidden" name="return_date" id="booking_return_date" value="">
      <input type="hidden" name="flight_type" id="booking_flight_type" value="">
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
              <input type="text" name="seat_number[]" placeholder="Seat (e.g., 12A)" required>
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

  <script>const IATA_DATA = <?php echo json_encode($iataData, JSON_UNESCAPED_UNICODE); ?>;</script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    window.IATA_LOOKUP = {};
    IATA_DATA.forEach(it => window.IATA_LOOKUP[it.code] = it.name);

    const elemsDate = document.querySelectorAll('.datepicker');
    M.Datepicker.init(elemsDate, { format: 'yyyy-mm-dd', minDate: new Date(), autoClose: true, });

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

    document.querySelectorAll('.seat-options a').forEach(item => {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        const value = this.getAttribute('data-value');
        const dropdown = this.closest('.dropdown-content');
        const targetId = dropdown && dropdown.getAttribute('id');
        const input = document.querySelector(`[data-target="${targetId}"]`);
        if (input) { input.value = value; M.updateTextFields(); }
      });
    });

    let ticketCount = 1;
    const maxTickets = 9;
    document.getElementById('addTicketBtn').addEventListener('click', () => {
      if (ticketCount >= maxTickets) { M.toast({ html: 'Maximum of 9 passengers per booking!' }); return; }
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
        else { input.value = ''; if (input.name === 'special[]') input.readOnly = true; }
      });

      const index = ticketCount - 1;
      newTicket.querySelectorAll('input[type="radio"]').forEach(r => r.name = `gender[${index}]`);
      const impairmentField = newTicket.querySelector('.impairment-field');
      if (impairmentField) { impairmentField.name = `impairment[${index}]`; impairmentField.style.display = 'none'; impairmentField.disabled = true; }
      const pwdCheckbox = newTicket.querySelector('input[type="checkbox"]');
      if (pwdCheckbox) pwdCheckbox.name = `pwd[${index}]`;

      newTicket.querySelector('.counter').textContent = `Passenger ${ticketCount}`;
      const rem = newTicket.querySelector('.remove-btn'); if (rem) rem.style.display = 'block';
      container.appendChild(newTicket);
      M.updateTextFields();

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
        if (index === 0) { const rem = card.querySelector('.remove-btn'); if (rem) rem.style.display = 'none'; }
      });
    }

    window.removeTicket = removeTicket;

    function checkAge(input) {
      let age = parseInt(input.value);
      if (!isNaN(age) && age > 130) { age = 130; input.value = age; }
      const card = input.closest('.ticket-card');
      const typeField = card.querySelector('input[name="special[]"]');
      if (!isNaN(age)) {
        if (age <= 2) typeField.value = 'Infant';
        else if (age >= 3 && age <= 12) typeField.value = 'Child';
        else typeField.value = 'Regular';
      } else { typeField.value = ''; }
    }
    window.checkAge = checkAge;

    function toggleImpairment(checkbox) {
      const field = checkbox.closest('.pwd-group').querySelector('.impairment-field');
      if (checkbox.checked) { field.style.display = 'inline-block'; field.disabled = false; }
      else { field.style.display = 'none'; field.disabled = true; field.value = ''; }
    }
    window.toggleImpairment = toggleImpairment;

    // Toggle return date visibility when changing type
    const returnWrapper = document.getElementById('return-date-wrapper');
    const returnInput = document.getElementById('return-date');
    document.querySelectorAll('input[name="flight_type"]').forEach(radio => {
      radio.addEventListener('change', function () {
        if (this.value === 'TWO-WAY') {
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
    }

    function buildSummaryAndOpen(flight) {
      fillBookingHiddenFlightFields(flight);

      const tickets = document.querySelectorAll('.ticket-card');
      const passengerCount = tickets.length;
      const typeLabel = (flight.flight_type === 'TWO-WAY') ? 'Two-way (round trip)' : 'One-way';

      let html = `<p><strong>Origin:</strong> ${escapeHtml(flight.origin)}</p>
                  <p><strong>Destination:</strong> ${escapeHtml(flight.destination)}</p>
                  <p><strong>Departure:</strong> ${escapeHtml(flight.flight_date)}</p>`;
      if (flight.flight_type === 'TWO-WAY') {
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

      fetch('ticket.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(resp => resp.json())
        .then(json => {
          if (!json) { M.toast({ html: 'Invalid server response' }); return; }
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
        .catch(err => { console.error('Validation error', err); M.toast({ html: 'Network or server error while validating. Try again.' }); });
    });

    cancelBtn.addEventListener('click', function (e) { e.preventDefault(); summaryModal.close(); });

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

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
      });
    }

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
</style>
