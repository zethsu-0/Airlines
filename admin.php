<!DOCTYPE html>
<html>
<head>
	<?php include('templates/header.php'); ?>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="css/admin.css">
	<title>Admin</title>
</head>
<body>
<div class="page-wrap">
  <div class="layout container">
    <!-- LEFT: Create Button -->
    <div class="left-col">
      <a class="left-create modal-trigger" href="#createExamModal">CREATE QUIZ</a>
    </div>

    <!-- MIDDLE: Quizzes list -->
    <div>
      <div class="quiz-list">
        <!-- quiz cards populated by JS -->
        <div id="quizzesContainer"></div>
      </div>
    </div>

    <!-- RIGHT: Stats + Edit Students -->
    <div>
      <div class="stats-box">
        <div style="width:100%; height:140px; display:flex; justify-content:center; align-items:center;">
          <!-- small placeholder chart (user can replace with image or chart lib) -->
          <img src="/assets/pie-sample.png" alt="pie" style="max-width:140px;">
        </div>

        <div style="margin-top: 18px;">
          <h5 style="font-weight:700;">STUDENTS WHO<br>SUBMITTED</h5>
        </div>

        <div style="writing-mode: vertical-rl; transform: rotate(180deg); margin-left: auto; margin-top: 12px; font-weight:800;">
          OTHER STATS
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Create Exam Modal -->
<div id="createExamModal" class="modal">
  <div class="modal-content">
    <h4 class="center-align">🎫 Plane Ticket Booking</h4>


    <!-- FIX: make the submit actually send POST by using type="submit" and name=form_submit -->
    <form id="flightForm" action="ticket.php" method="POST" autocomplete="off">
      <div class="row">
        <!-- ORIGIN -->
        <div class="col s4 md3">
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
        <div class="col s4 md3">
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

        <div class="col s4 md3">
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
  </div>

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
  <div class="modal-content">
    <h5>Edit Quiz</h5>
    <input type="hidden" id="editQuizIndex">
    <div class="input-field">
      <input id="editQuizName" type="text">
      <label for="editQuizName">Exam/Quiz Name</label>
    </div>
    <div class="input-field">
      <input id="editQuizDeadline" type="date">
      <label for="editQuizDeadline">Deadline</label>
    </div>
    <a class="btn blue" id="saveQuizBtn">Save</a>
  </div>
</div>

<!-- Materialize & app JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var modals = document.querySelectorAll('.modal');
    M.Modal.init(modals);

    var datepickers = document.querySelectorAll('.datepicker');
    M.Datepicker.init(datepickers, {
      format: 'yyyy-mm-dd',
      minDate: new Date(),
      autoClose: true
    });

    var dropdowns = document.querySelectorAll('.dropdown-trigger');
    M.Dropdown.init(dropdowns, {
      constrainWidth: false,
      coverTrigger: false
    });
  });
</script>
</body>
</html>
<?php include('templates/footer.php'); ?>