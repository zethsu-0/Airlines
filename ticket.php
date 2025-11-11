<?php
session_start();

$conn = mysqli_connect('localhost', 'root', '', 'airlines');
if (!$conn) {
    die('Connection error: ' . mysqli_connect_error());
}

// ✅ 1. Check session first
if (!isset($_SESSION['flight_id'])) {
    header("Location: index.php");
    exit;
}

// ✅ 2. Assign flight_id after confirming session exists
$flight_id = $_SESSION['flight_id'] ?? ($_GET['id'] ?? null);

if (!$flight_id) {
    header("Location: index.php");
    exit;
}
if (isset($_POST['new_booking'])) {
    unset($_SESSION['flight_id']); // only remove flight_id, keep session
}


// ✅ 3. Now safely query
$stmt = $conn->prepare("SELECT origin_code, destination_code, flight_date FROM submitted_flights WHERE id = ?");
$stmt->bind_param("i", $flight_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$flight = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>






<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>

<head>
  <meta charset="UTF-8">
  <title>Flight Booking</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/ticket.css">
</head>

<body>
  <div class="container">
    <form action="index.php" method="POST">
    <button type="submit" class="btn waves-effect waves-light blue darken-2" name="new_booking">
        Book Another Flight
    </button>
    </form>
    <h4 class="center-align">🎫 Plane Ticket Booking</h4>

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
            <div class="input-field col s6">
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
              <h6>FROM</h6>
              <span><?php echo htmlspecialchars($flight['origin_code']); ?></span>
            </div>
            <div class="input-field col s6">
              <h6>TO</h6>
              <span><?php echo htmlspecialchars($flight['destination_code']); ?></span>
            </div>
          </div>

          <div class="row">
            <div class="input-field col s6">
              <input type="text" name="seat[]" required>
              <label>Seat Type</label>
          <!-- DROPDOWN TAS RADIO BUTTON NALANG TO? OR ANOTHER PAGE PARA SA PAGPILI NG SEAT TYPE? -->
            </div>

            <div class="input-field col s6">
              <h6>DEPARTURE DATE</h6>
              <span><?php echo htmlspecialchars($flight['flight_date']); ?></span>
            </div>
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

  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
  <script>
    function initDatepickers(container) {
      M.Datepicker.init(container.querySelectorAll('.datepicker'), {
        format: 'yyyy-mm-dd',
        minDate: new Date()
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      initDatepickers(document);
      var elems = document.querySelectorAll('.dropdown-trigger');
      var instances = M.Dropdown.init(elems, options);

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

      newTicket.querySelectorAll('input').forEach(input => {
        if (['checkbox', 'radio'].includes(input.type)) input.checked = false;
        else input.value = '';
      });

      newTicket.querySelectorAll('input[type="radio"]').forEach(r => {
        const index = ticketCount - 1;
        r.name = `gender[${index}]`;
      });

      const impairmentField = newTicket.querySelector('.impairment-field');
      impairmentField.style.display = 'none';
      impairmentField.disabled = true;

      newTicket.querySelector('.counter').textContent = `Passenger ${ticketCount}`;
      newTicket.querySelector('.remove-btn').style.display = 'block';

      container.appendChild(newTicket);
      M.updateTextFields();
      initDatepickers(newTicket);
    });

    function removeTicket(btn) {
      btn.parentElement.remove();
      ticketCount--;
      document.querySelectorAll('.counter').forEach((c, i) => {
        c.textContent = `Passenger ${i + 1}`;
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
