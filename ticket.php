<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>

<head>
  <meta charset="UTF-8">
  <title>Flight Booking</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Materialize CSS -->
  <link rel="stylesheet" href="css/ticket.css">
</head>

<body>
  <div class="container">
    <h4 class="center-align">🎫 Plane Ticket Booking</h4>

    <form id="bookingForm">
      <div id="ticketContainer">
        <!-- Default Passenger -->
        <div class="ticket-card">
          <button type="button" class="remove-btn" onclick="removeTicket(this)" style="display:none;">✕</button>
          <div class="counter">Passenger 1</div>

          <div class="input-field">
            <input type="text" name="name[]" required>
            <label>Full Name</label>
          </div>

          <div class="row">
            <div class="input-field col s6">
              <input type="number" name="age[]" min="0" required>
              <label>Age</label>
            </div>
            <div class="input-field col s6">
              <select name="special[]" required>
                <option value="Regular" selected>Regular</option>
                <option value="Child">Child (Below 12)</option>
                <option value="Elderly">Elderly (60+)</option>
                <option value="PWD">PWD</option>
              </select>
              <label>Passenger Type</label>
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
              <input type="text" class="datepicker" name="date[]" required>
              <label>Departure Date</label>
            </div>
            <div class="input-field col s6">
              <input type="text" name="seat[]" required>
              <label>Seat Type</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Add More Button -->
      <div class="add-btn">
        <button type="button" id="addTicketBtn">+</button>
      </div>

      <!-- Confirm Booking -->
      <div class="form-actions">
        <button type="submit" class="btn waves-effect waves-light">
          Confirm Booking
        </button>
      </div>
    </form>
  </div>

  <!-- Materialize JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      M.FormSelect.init(document.querySelectorAll('select'));
      M.Datepicker.init(document.querySelectorAll('.datepicker'), {
        format: 'yyyy-mm-dd',
        minDate: new Date()
      });
    });

    let ticketCount = 1;
    const maxTickets = 9;

    document.getElementById('addTicketBtn').addEventListener('click', () => {
      if (ticketCount >= maxTickets) {
        M.toast({ html: 'Maximum of 9 passengers per booking!' });
        return;
      }

      const container = document.getElementById('ticketContainer');
      const newCard = document.createElement('div');
      newCard.classList.add('ticket-card');

      newCard.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeTicket(this)">✕</button>
        <div class="counter">Passenger ${ticketCount + 1}</div>
        <div class="input-field">
          <input type="text" name="name[]" required>
          <label>Full Name</label>
        </div>
        <div class="row">
          <div class="input-field col s6">
            <input type="number" name="age[]" min="0" required>
            <label>Age</label>
          </div>
          <div class="input-field col s6">
            <select name="special[]" required>
              <option value="Regular" selected>Regular</option>
              <option value="Child">Child (Below 12)</option>
              <option value="Elderly">Elderly (60+)</option>
              <option value="PWD">PWD</option>
            </select>
            <label>Passenger Type</label>
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
            <input type="text" class="datepicker" name="date[]" required>
            <label>Departure Date</label>
          </div>
          <div class="input-field col s6">
            <input type="text" name="seat[]" required>
            <label>Seat Type</label>
          </div>
        </div>
      `;

      container.appendChild(newCard);
      M.FormSelect.init(newCard.querySelectorAll('select'));
      M.Datepicker.init(newCard.querySelectorAll('.datepicker'), {
        format: 'yyyy-mm-dd',
        minDate: new Date()
      });

      ticketCount++;
    });

    function removeTicket(btn) {
      btn.parentElement.remove();
      ticketCount--;
      updateCounters();
    }

    function updateCounters() {
      document.querySelectorAll('.counter').forEach((c, i) => {
        c.textContent = `Passenger ${i + 1}`;
      });
    }

    document.getElementById('bookingForm').addEventListener('submit', e => {
      e.preventDefault();
      M.toast({ html: 'Booking data ready to send to backend!' });
    });
  </script>

  <?php include('templates/footer.php'); ?>
</body>
</html>
