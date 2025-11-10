<?php
$conn = mysqli_connect('localhost','root','','airlines');
if (!$conn) { die('Connection error: ' . mysqli_connect_error()); }

// Get the flight ID from URL
$flight_id = $_GET['id'] ?? null;

if (!$flight_id) {
    // No flight specified → go back to index
    header("Location: index.php");
    exit;
}

// Fetch flight info
$sql = "SELECT origin_code, destination_code FROM submitted_flights WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $flight_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Flight not found → go back
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
    <h4 class="center-align">🎫 Plane Ticket Booking</h4>

      <!-- mababago after meron ng login -->

      <div class="container center white flight-container">
        <span><?php echo htmlspecialchars($flight['origin_code']); ?></span>
        <i class="material-icons prefix">calendar_today</i>
        <span><?php echo htmlspecialchars($flight['destination_code']); ?></span>
    </div>
      <!-- mababago after meron ng login -->
      <!-- design nalang hahahha -->


    <form id="bookingForm" method="POST" action="save_booking.php">
      <div id="ticketContainer">
        <div class="ticket-card">
          <button type="button" class="remove-btn" onclick="removeTicket(this)" style="display:none;">✕</button>
          <div class="counter">Passenger 1</div>

          <div class="input-field">
            <input type="text" name="name[]" required>
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
              <input type="text" name="from[]" required>
              <label>From</label>
            </div>
            <div class="input-field col s6">
              <input type="text" name="to[]" required>
              <label>To</label>
            </div>
          </div>

          <div class="row">
            <div class="input-field col s6">
              <input type="text" name="seat[]" required>
              <label>Seat Type</label>
            </div>
            <div class="input-field col s6">
              <input type="text" class="datepicker" name="date[]" required>
              <label>Departure Date</label>
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
