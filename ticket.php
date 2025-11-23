<?php
// DEV: show errors while debugging (turn off in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// DB connection
$conn = mysqli_connect('localhost', 'root', '', 'airlines');
if (!$conn) {
  die('Connection error: ' . mysqli_connect_error());
}

// Load airports into $iataData (IATACode, AirportName, City, CountryRegion)
$iataData = [];

$sql = "SELECT IATACode, AirportName, City, CountryRegion FROM airports ORDER BY IATACode ASC";
if ($result = $conn->query($sql)) {
  while ($row = $result->fetch_assoc()) {
    // Normalize fields and skip empty codes
    $code = isset($row['IATACode']) ? trim($row['IATACode']) : '';
    if ($code === '') {
      continue;
    }

    $iataData[] = [
      'code'    => $code,
      'name'    => $row['AirportName'] ?? '',
      'city'    => $row['City'] ?? '',
      'country' => $row['CountryRegion'] ?? '' // using CountryRegion column name as requested
    ];
  }
  $result->free();
} else {
  // for dev: log error as HTML comment
  echo "<!-- IATA load error: " . htmlspecialchars($conn->error) . " -->";
}

// Build an associative lookup by IATA code for quick validation/labels
$iataList = [];
foreach ($iataData as $it) {
  if (!empty($it['code'])) {
    $iataList[$it['code']] = $it['name'];
  }
}

// Now process POST (validation + insert). Keep this after loading $iataList so you can validate against it.
$origin      = strtoupper(trim($_POST['origin'] ?? ''));
$destination = strtoupper(trim($_POST['destination'] ?? ''));
$flight_date = trim($_POST['flight_date'] ?? '');
$errors      = [];

if (isset($_POST['form_submit'])) {
  if (!isset($_SESSION['acc_id'])) {
    $errors['login'] = 'You must be logged in to submit a flight.';
  }

  if (empty($origin)) {
    $errors['origin'] = 'Origin code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $origin)) {
    $errors['origin'] = 'Origin must be 3 uppercase letters.';
  } elseif (!array_key_exists($origin, $iataList)) {
    $errors['origin'] = 'Unknown origin IATA code.';
  }

  if (empty($destination)) {
    $errors['destination'] = 'Destination code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $destination)) {
    $errors['destination'] = 'Destination must be 3 uppercase letters.';
  } elseif (!array_key_exists($destination, $iataList)) {
    $errors['destination'] = 'Unknown destination IATA code.';
  }

  if ($origin === $destination && !empty($origin)) {
    $errors['destination'] = 'Destination code cannot be the same as origin.';
  }

  if (empty($flight_date)) {
    $errors['flight_date'] = 'Departure date is required.';
  } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
    $errors['flight_date'] = 'Invalid date format.';
  }

  if (empty($errors)) {
    $origin_airline      = $iataList[$origin] ?? "Invalid code ($origin)";
    $destination_airline = $iataList[$destination] ?? "Invalid code ($destination)";

    $insert = $conn->prepare(
      "INSERT INTO submitted_flights (origin_code, origin_airline, destination_code, destination_airline, flight_date) VALUES (?, ?, ?, ?, ?)"
    );

    if ($insert) {
      $insert->bind_param("sssss", $origin, $origin_airline, $destination, $destination_airline, $flight_date);
      $insert->execute();
      $last_id = $conn->insert_id;
      $_SESSION['flight_id'] = $last_id;
      $insert->close();

      header("Location: ticket.php?id=" . urlencode($last_id));
      exit;
    } else {
      $errors['db'] = 'Failed to prepare insert statement: ' . $conn->error;
    }
  }
}

// Prepare $flight for later display
$flight = [
  'origin_code'      => htmlspecialchars($origin),
  'destination_code' => htmlspecialchars($destination),
  'flight_date'      => htmlspecialchars($flight_date)
];

// Keep connection open until after page renders (or close at very end).
// $conn->close(); // DO NOT close here if you still need $conn later
?>


<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>

<!-- Page-specific stylesheet (header already includes global styles) -->
<link rel="stylesheet" href="css/ticket.css">

<body>
  <h4 class="center-align">🎫 Plane Ticket Booking</h4>

  <div class="bg-container container center">
    <!-- FIX: make the submit actually send POST by using type="submit" and name=form_submit -->
    <form id="flightForm" action="ticket.php" method="POST" autocomplete="off" class="card">
      <div class="row">

        <!-- ORIGIN -->
        <div class="col s3 md3">
          <div class="input-field" style="position:relative;">
            <i class="material-icons prefix">flight_takeoff</i>

            <!-- visible, typable input -->
            <input
              type="text"
              id="origin_autocomplete"
              class="center"
              autocomplete="off"
              value="<?php echo htmlspecialchars($origin ? ($origin . ' — ' . ($iataList[$origin] ?? '')) : ''); ?>">
            <label for="origin_autocomplete">ORIGIN</label>
            <div class="red-text"><?php echo $errors['origin'] ?? ''; ?></div>

            <!-- actual code submitted -->
            <input type="hidden" id="origin" name="origin" value="<?php echo htmlspecialchars($origin); ?>">

            <!-- suggestions dropdown container -->
            <ul id="origin_suggestions" class="custom-autocomplete-list" style="display:none; position:absolute; z-index:999; left:0; right:0; background:#fff; max-height:280px; overflow:auto; border:1px solid rgba(0,0,0,0.12); padding:0; margin-top:4px;">
            </ul>
          </div>
        </div>

        <!-- DESTINATION -->
        <div class="col s3 md3">
          <div class="input-field" style="position:relative;">
            <i class="material-icons prefix">flight_land</i>

            <input
              type="text"
              id="destination_autocomplete"
              class="center"
              autocomplete="off"
              value="<?php echo htmlspecialchars($destination ? ($destination . ' — ' . ($iataList[$destination] ?? '')) : ''); ?>">
            <label for="destination_autocomplete">DESTINATION</label>
            <div class="red-text"><?php echo $errors['destination'] ?? ''; ?></div>

            <input type="hidden" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>">

            <ul id="destination_suggestions" class="custom-autocomplete-list" style="display:none; position:absolute; z-index:999; left:0; right:0; background:#fff; max-height:280px; overflow:auto; border:1px solid rgba(0,0,0,0.12); padding:0; margin-top:4px;">
            </ul>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="center">
            <div class="input-field">
              <i class="material-icons prefix">calendar_today</i>
              <input type="text" id="flight-date" name="flight_date" class="datepicker" value="<?php echo htmlspecialchars($flight_date); ?>" readonly>
              <label for="flight-date">DEPARTURE</label>
              <div class="red-text"><?php echo $errors['flight_date'] ?? ''; ?></div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="container">
    <form action="ticket.php" method="POST">
      <button type="submit" class="btn waves-effect waves-light blue darken-2" name="new_booking">
        Book Another Flight
      </button>
    </form>

    <form id="bookingForm" method="POST" action="save_booking.php">
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
              <input type="text" name="special[]" readonly disabled placeholder="Adult/Minor/Senior">
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
              <input type="text" name="seat[]" id="seatInput" class="dropdown-trigger" data-target="dropdown_1" readonly required>
              <label for="seatInput">Seat Type</label>

              <ul id="dropdown_1" class="dropdown-content seat-options">
                <li><a data-value="Economy">Economy</a></li>
                <li><a data-value="Premium">Premium</a></li>
                <li><a data-value="Business">Business</a></li>
                <li><a data-value="First Class">First Class</a></li>
              </ul>
            </div>
          </div>

        </div>
      </div>

      <div class="add-btn">
        <button type="button" id="addTicketBtn" class="btn-floating blue">+</button>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn waves-effect waves-light">Confirm Booking</button>
      </div>
    </form>
  </div>

  <script>
    const IATA_DATA = <?php echo json_encode($iataData, JSON_UNESCAPED_UNICODE); ?>;
  </script>

<script>
    // Initialize materialize components (datepicker)
    document.addEventListener('DOMContentLoaded', function () {
      // Datepickers
      const elemsDate = document.querySelectorAll('.datepicker');
      M.Datepicker.init(elemsDate, {
        format: 'yyyy-mm-dd',
        minDate: new Date()
      });

      // Initialize dropdowns for seat selection
      const dropdowns = document.querySelectorAll('.dropdown-trigger');
      M.Dropdown.init(dropdowns);

      // Make clicking a seat option put the text in the input box
      document.querySelectorAll('.seat-options a').forEach(item => {
        item.addEventListener('click', function (e) {
          e.preventDefault();
          const value = this.getAttribute('data-value');
          const dropdown = this.closest('.dropdown-content');
          const targetId = dropdown.getAttribute('id');
          const input = document.querySelector(`[data-target="${targetId}"]`);
          input.value = value;
          M.updateTextFields(); // update label animation
        });
      });

      // Build display label from item
      function buildLabel(item) {
        const cityCountry = [item.city, item.country].filter(Boolean).join(', ');
        return item.code + ' — ' + item.name + (cityCountry ? ' (' + cityCountry + ')' : '');
      }

      // Escape helper
      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
          return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m]);
        });
      }

      // Substring filter over code, name, city, country (case-insensitive)
      function filterIata(query, limit = 12) {
        const q = query.trim().toLowerCase();
        if (!q) return [];
        const out = [];
        for (let i = 0; i < IATA_DATA.length; i++) {
          const it = IATA_DATA[i];
          if (
            (it.code && it.code.toLowerCase().indexOf(q) !== -1) ||
            (it.name && it.name.toLowerCase().indexOf(q) !== -1) ||
            (it.city && it.city.toLowerCase().indexOf(q) !== -1) ||
            (it.country && it.country.toLowerCase().indexOf(q) !== -1)
          ) {
            out.push(it);
            if (out.length >= limit) break;
          }
        }
        return out;
      }

      // Build an autocomplete bound to display input + hidden input + suggestion box
      function makeAutocomplete(displayId, hiddenId, suggestionsId) {
        const display = document.getElementById(displayId);
        const hidden = document.getElementById(hiddenId);
        const sugBox = document.getElementById(suggestionsId);

        let current = [];
        let active = -1;

        function render(items) {
          current = items;
          active = -1;
          if (!items.length) {
            sugBox.style.display = 'none';
            sugBox.innerHTML = '';
            return;
          }
          sugBox.innerHTML = items.map((it, idx) => {
            const label = buildLabel(it);
            return `\n              <li data-idx="${idx}" style="list-style:none; padding:10px; cursor:pointer; border-bottom:1px solid rgba(0,0,0,0.04);">\n                <div style="font-weight:600; margin-bottom:3px;">${escapeHtml(it.code)} — ${escapeHtml(it.name)}</div>\n                <div style="font-size:0.85em; color:#666;">${escapeHtml([it.city, it.country].filter(Boolean).join(', '))}</div>\n              </li>`;
          }).join('');
          sugBox.style.display = 'block';
        }

        function choose(idx) {
          if (idx < 0 || idx >= current.length) return;
          const it = current[idx];
          display.value = buildLabel(it);
          hidden.value = it.code;
          // If using Materialize floating labels
          if (window.M && M.updateTextFields) M.updateTextFields();
          close();
        }

        function close() {
          sugBox.style.display = 'none';
          sugBox.innerHTML = '';
          current = [];
          active = -1;
        }

        display.addEventListener('input', function () {
          const q = display.value;
          if (!q.trim()) {
            hidden.value = '';
            render([]);
            return;
          }
          const items = filterIata(q);
          render(items);
        });

        display.addEventListener('keydown', function (e) {
          if (sugBox.style.display === 'none') return;
          const max = current.length - 1;
          if (e.key === 'ArrowDown') {
            active = Math.min(max, active + 1);
            highlight();
            e.preventDefault();
          } else if (e.key === 'ArrowUp') {
            active = Math.max(0, active - 1);
            highlight();
            e.preventDefault();
          } else if (e.key === 'Enter') {
            if (active >= 0) {
              choose(active);
              e.preventDefault();
            } else {
              // try to interpret typed 3-letter code
              tryExtract(display, hidden);
            }
          } else if (e.key === 'Escape') {
            close();
          }
        });

        function highlight() {
          const lis = Array.from(sugBox.querySelectorAll('li'));
          lis.forEach((li, i) => li.style.background = (i === active ? 'rgba(0,0,0,0.04)' : ''));
          const el = lis[active];
          if (el) el.scrollIntoView({ block: 'nearest' });
        }

        sugBox.addEventListener('click', function (ev) {
          const li = ev.target.closest('li[data-idx]');
          if (!li) return;
          const idx = parseInt(li.getAttribute('data-idx'), 10);
          choose(idx);
        });

        display.addEventListener('blur', function () {
          setTimeout(() => { // allow click to register
            tryExtract(display, hidden);
            close();
          }, 160);
        });
      }

      // Try to parse a typed value: accept 3-letter code or exact label
      function tryExtract(display, hidden) {
        const val = display.value.trim();
        if (!val) { hidden.value = ''; return; }

        // 1) If starts with 3-letter code
        const m = val.match(/^([A-Za-z]{3})\b/);
        if (m) {
          hidden.value = m[1].toUpperCase();
          return;
        }

        // 2) If matches exactly one label, set it
        const lower = val.toLowerCase();
        for (let i = 0; i < IATA_DATA.length; i++) {
          const it = IATA_DATA[i];
          const label = buildLabel(it).toLowerCase();
          if (label === lower) {
            hidden.value = it.code;
            return;
          }
        }

        // no match found
        hidden.value = '';
      }

      // Initialize both autocompletes
      makeAutocomplete('origin_autocomplete', 'origin', 'origin_suggestions');
      makeAutocomplete('destination_autocomplete', 'destination', 'destination_suggestions');

    });

    let ticketCount = 1;
    const maxTickets = 9;

    document.getElementById('addTicketBtn').addEventListener('click', () => {
      if (ticketCount >= maxTickets) {
        M.toast({ html: 'Maximum of 9 passengers per booking!' });
        return;
      }

      const container = document.getElementById('ticketContainer');
      const firstTicket = container.querySelector('.ticket-card');
      const newTicket = firstTicket.cloneNode(true);
      ticketCount++;

      // Generate unique ID for the new dropdown
      const newDropdownId = 'dropdown_' + ticketCount;
      
      // Update dropdown trigger and target
      const seatInput = newTicket.querySelector('input[name="seat[]"]');
      const dropdownContent = newTicket.querySelector('.dropdown-content');
      
      seatInput.setAttribute('data-target', newDropdownId);
      dropdownContent.setAttribute('id', newDropdownId);

      // Clear inputs and reset radios/checkboxes
      newTicket.querySelectorAll('input').forEach(input => {
        if (['checkbox', 'radio'].includes(input.type)) input.checked = false;
        else {
          input.value = '';
          if (input.name === 'special[]') { input.readOnly = false; input.disabled = true; input.placeholder = 'Adult/Minor/Senior'; }
        }
      });

      // Update radio names to keep them unique per passenger
      const index = ticketCount - 1; // Define index here
      newTicket.querySelectorAll('input[type="radio"]').forEach(r => {
        r.name = `gender[${index}]`;
      });

      // Update impairment field name and reset
      const impairmentField = newTicket.querySelector('.impairment-field');
      impairmentField.name = `impairment[${index}]`; // Now index is defined
      impairmentField.style.display = 'none';
      impairmentField.disabled = true;

      // Update PWD checkbox name
      const pwdCheckbox = newTicket.querySelector('input[type="checkbox"]');
      pwdCheckbox.name = `pwd[${index}]`; // Now index is defined

      newTicket.querySelector('.counter').textContent = `Passenger ${ticketCount}`;
      newTicket.querySelector('.remove-btn').style.display = 'block';

      container.appendChild(newTicket);
      M.updateTextFields();
      
      // Reinitialize dropdown for new ticket with unique ID
      const newDropdownTrigger = newTicket.querySelector('.dropdown-trigger');
      M.Dropdown.init(newDropdownTrigger);
      
      // Add click event for the new dropdown options
      newTicket.querySelectorAll('.seat-options a').forEach(item => {
        item.addEventListener('click', function(e) {
          e.preventDefault();
          const value = this.getAttribute('data-value');
          const dropdown = this.closest('.dropdown-content');
          const targetId = dropdown.getAttribute('id');
          const input = document.querySelector(`[data-target="${targetId}"]`);
          input.value = value;
          M.updateTextFields();
        });
      });
    });

    function removeTicket(btn) {
      const card = btn.closest('.ticket-card');
      if (!card || ticketCount <= 1) return;
      card.remove();
      ticketCount--;
      
      // Renumber remaining tickets and update IDs
      document.querySelectorAll('.ticket-card').forEach((card, index) => {
        const newIndex = index + 1;
        card.querySelector('.counter').textContent = `Passenger ${newIndex}`;
        
        // Update dropdown IDs
        const seatInput = card.querySelector('input[name="seat[]"]');
        const dropdownContent = card.querySelector('.dropdown-content');
        const newDropdownId = 'dropdown_' + newIndex;
        
        seatInput.setAttribute('data-target', newDropdownId);
        dropdownContent.setAttribute('id', newDropdownId);
        
        // Update radio names
        card.querySelectorAll('input[type="radio"]').forEach(r => {
          r.name = `gender[${index}]`;
        });
        
        // Update impairment field names
        const impairmentField = card.querySelector('.impairment-field');
        impairmentField.name = `impairment[${index}]`;
        
        // Update PWD checkbox names
        const pwdCheckbox = card.querySelector('input[type="checkbox"]');
        pwdCheckbox.name = `pwd[${index}]`;
        
        if (index === 0) {
          card.querySelector('.remove-btn').style.display = 'none';
        }
      });
    }

    function checkAge(input) {
      let age = parseInt(input.value);
      if (!isNaN(age) && age > 130) { age = 130; input.value = age; }
      const card = input.closest('.ticket-card');
      const typeField = card.querySelector('input[name="special[]"]');
      if (!isNaN(age)) {
        if (age <= 17) typeField.value = 'Minor';
        else if (age >= 60) typeField.value = 'Senior';
        else typeField.value = 'Regular';
      } else { typeField.value = ''; }
    }

    function toggleImpairment(checkbox) {
      const field = checkbox.closest('.pwd-group').querySelector('.impairment-field');
      if (checkbox.checked) { field.style.display = 'inline-block'; field.disabled = false; }
      else { field.style.display = 'none'; field.disabled = true; field.value = ''; }
    }
  </script>

  <?php include('templates/footer.php'); ?>
</body>
</html>
