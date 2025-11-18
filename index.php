<?php
  // ---------- PHP INDEX ----------
  session_start();
  if (!empty($_SESSION['flight_id'])) {
      unset($_SESSION['flight_id']); // clear last booking
  } 
  $conn = mysqli_connect('localhost', 'root', '', 'airlines');
  if (!$conn) {
      die('Connection error: ' . mysqli_connect_error());
  }
  $origin = strtoupper(trim($_POST['origin'] ?? ''));
  $destination = strtoupper(trim($_POST['destination'] ?? ''));
  $flight_date = trim($_POST['flight_date'] ?? '');
  $errors = [];

  if (isset($_POST['form_submit'])) {

  // ---------- VALIDATION ----------


  // Must be logged in to submit flights
  if (!isset($_SESSION['acc_id'])) {
    $errors['login'] = 'You must be logged in to submit a flight.';
  }

  // Origin required + 3 letters
  if (empty($origin)) {
      $errors['origin'] = 'Origin code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $origin)) {
      $errors['origin'] = 'Origin must be 3 uppercase letters.';
  }

  // Destination required + 3 letters
  if (empty($destination)) {
      $errors['destination'] = 'Destination code is required.';
  } elseif (!preg_match('/^[A-Z]{3}$/', $destination)) {
      $errors['destination'] = 'Destination must be 3 uppercase letters.';
  }

  // Origin ≠ Destination
  if ($origin === $destination && !empty($origin)) {
      $errors['destination'] = 'Destination code cannot be the same as origin.';
  }

  // Flight date required
  if (empty($flight_date)) {
      $errors['flight_date'] = 'Departure date is required.';
  } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
      $errors['flight_date'] = 'Invalid date format.';
  }

  // ---------- INSERT IF VALID ----------
  if (empty($errors)) {

      // Lookup origin airline
      $result_origin = $conn->query("SELECT AirportName FROM airports WHERE IATACode = '$origin' LIMIT 1");
      $origin_airline = ($result_origin && $result_origin->num_rows > 0) 
          ? $result_origin->fetch_assoc()['AirportName'] 
          : "Invalid code ($origin)";

      // Lookup destination airline
      $result_destination = $conn->query("SELECT AirportName FROM airports WHERE IATACode = '$destination' LIMIT 1");
      $destination_airline = ($result_destination && $result_destination->num_rows > 0) 
          ? $result_destination->fetch_assoc()['AirportName'] 
          : "Invalid code ($destination)";

      // Insert into DB
      $stmt = $conn->prepare("INSERT INTO submitted_flights (origin_code, origin_airline, destination_code, destination_airline, flight_date) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("sssss", $origin, $origin_airline, $destination, $destination_airline, $flight_date);
      $stmt->execute();
    
      $last_id = $stmt->insert_id;
      $_SESSION['flight_id'] = $last_id;

      $stmt->close();

      header("Location: ticket.php?id=$last_id");
      
      $origin = $destination = $flight_date = '';
  }
  }
  $conn->close();
?>


<!DOCTYPE html>
<html>
<?php include('templates/header.php'); ?>

<body>
  <!-- Hero Section -->
  <section class="center-align">
    <img src="assets/island2.jpg" alt="Island" class="responsive-img">
  </section>

  <!-- Booking Form Card -->
  <div class="bg-container container center">
    <?php $logged_in = !empty($_SESSION['acc_id']); ?>
    <?php if (!$logged_in): ?>
    <div class="yellow-text center" style="margin-bottom: 10px;">
        Please log in to book a flight.
    </div>
    <?php endif; ?>

    <form id="flightForm" action="index.php" method="POST" autocomplete="off" class="card">
      <div class="row">
        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_takeoff</i>
            <input type="text" name="origin" class="center" id="origin" value="<?php echo htmlspecialchars($origin); ?>"<?php echo !$logged_in ? 'disabled' : ''; ?>>
            <div class="red-text"><?php echo $errors['origin'] ?? ''; ?></div>
            <label for="origin">ORIGIN</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_land</i>
            <input type="text" name="destination" class="center" id="destination" value="<?php echo htmlspecialchars($destination); ?>"<?php echo !$logged_in ? 'disabled' : ''; ?>>
            <div class="red-text"><?php echo $errors['destination'] ?? ''; ?></div>
            <label for="destination">DESTINATION</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="center">
            <div class="input-field">
              <i class="material-icons prefix">calendar_today</i>
              <input type="text" id="flight-date" name="flight_date" class="datepicker" value="<?php echo htmlspecialchars($flight_date); ?>" readonly <?php echo !$logged_in ? 'disabled' : ''; ?>>
              <label for="flight-date">DEPARTURE</label>
              <div class="red-text"><?php echo $errors['flight_date'] ?? ''; ?></div>
            </div>
          </div>
        </div>

        <div class="col s3 md3 submitbtn">
            <div class="center">
              <input type="button" id="submitBtn" name="form_submit" value="Submit" class="btn brand z-depth-0" <?php echo !$logged_in ? 'disabled' : ''; ?>>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Carousel Section -->
  <section class="hero-carousel" style="background-image: url('assets/island.jpg');">
    <div class="overlay-bg">
      <div class="container">
        <h4 class="center-align white-text">Places, You🫵 wanna to Visit</h4>

        <div class="carousel">

          <!-- Destination Cards -->
          <?php
          $destinations = [
            ["Philippines", "Boracay Island", "assets/tourist/boracay.jpg"],
            ["Singapore", "Marina Bay Sands", "assets/tourist/singapore.jpg"],
            ["Malaysia", "Petronas Towers, Kuala Lumpur", "assets/tourist/malaysia.jpg"],
            ["Thailand", "Phuket Island", "assets/tourist/thailand.jpg"],
            ["Vietnam", "Ha Long Bay", "assets/tourist/vietnam.jpg"],
            ["Indonesia", "Bali", "assets/tourist/indonesia.jpg"],
            ["Brunei", "Omar Ali Saifuddien Mosque", "assets/tourist/brunei.jpg"],
            ["Cambodia", "Angkor Wat", "assets/tourist/cambodia.jpg"],
            ["Laos", "Luang Prabang", "assets/tourist/laos.jpg"],
            ["Myanmar", "Bagan Temples", "assets/tourist/myanmar.jpg"],
            ["Timor-Leste", "Cristo Rei, Dili", "assets/tourist/timor_leste.jpg"],
            ["Japan", "Tokyo Tower", "assets/tourist/japan.jpg"],
            ["Taiwan", "Taipei 101", "assets/tourist/taiwan.jpg"],
            ["Hong Kong", "Disney Land", "assets/tourist/hong_kong.jpg"]
          ];

          foreach ($destinations as $dest) : ?>
            <div class="carousel-item">
              <div class="destination-card">
                <img src="<?php echo $dest[2]; ?>" alt="<?php echo $dest[0]; ?>">
                <div class="country-label"><?php echo $dest[0]; ?></div>
                <div class="card-reveal-overlay">
                  <div class="reveal-content">
                    <div class="card-title"><?php echo $dest[0]; ?> <span class="close-reveal">✕</span></div>
                    <p><strong>Location:</strong> <?php echo $dest[1]; ?></p>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>
    </div>
  </section>
  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal">
    <div class="modal-content center">
      <h5>Confirm Submission</h5>
      <p>Are you sure you want to submit this flight?</p>
    </div>
    <div class="modal-footer center">
      <button class="modal-close btn-flat red-text" id="cancelBtn">Cancel</button>
      <button type="button" class=" btn green" id="confirmBtn">Confirm</button>
    </div>
  </div>

  <!-- Info Section -->
  <div class="container">
    <h6>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat...</h6>

  </div>


</body>
</html>
  <!-- Materialize JS -->
  
  <script>
    document.addEventListener('DOMContentLoaded', function () {
  // ==============================
  // 1. Initialize Materialize Carousel
  // ==============================
  
  const carouselElems = document.querySelectorAll('.carousel');
  M.Carousel.init(carouselElems, { indicators: false, dist: -50, padding: 20 });

  const carouselElem = document.querySelector('.carousel');
  if (carouselElem) {
    const instance = M.Carousel.getInstance(carouselElem);
    const items = [...carouselElem.querySelectorAll('.carousel-item')];
    const cards = [...carouselElem.querySelectorAll('.destination-card')];

    const hideAll = () => document.querySelectorAll('.card-reveal-overlay.active')
      .forEach(o => o.classList.remove('active'));

    cards.forEach((card, index) => {
      const overlay = card.querySelector('.card-reveal-overlay');
      const closeBtn = card.querySelector('.close-reveal');

      card.querySelector('img')?.addEventListener('click', e => {
        e.stopPropagation();
        hideAll();
        instance.set(index);
        setTimeout(() => overlay?.classList.add('active'), 400);
      });

      closeBtn?.addEventListener('click', e => {
        e.stopPropagation();
        overlay?.classList.remove('active');
      });
    });

    let last = -1;
    setInterval(() => {
      const cr = carouselElem.getBoundingClientRect();
      const cx = cr.left + cr.width / 2;
      const idx = items.reduce((b, it, i) => {
        const r = it.getBoundingClientRect();
        const d = Math.abs((r.left + r.width / 2) - cx);
        return d < b[0] ? [d, i] : b;
      }, [Infinity, 0])[1];
      if (idx !== last) { hideAll(); last = idx; }
    }, 150);

    ['touchstart', 'mousedown', 'click'].forEach(evt => {
      carouselElem.addEventListener(evt, e => {
        if (!e.target.closest('.destination-card')) hideAll();
      }, { passive: true });
    });
  }

  // ==============================
  // 2. DATE PICKER
  // ==============================
  console.log('running datepicker init');
  var elems = document.querySelectorAll('.datepicker');
  var instances = M.Datepicker.init(elems, {
    format: 'yyyy-mm-dd',
    autoClose: true,
    minDate: new Date(),
  });

  // ==============================
  // 3. Initialize Materialize Modal + Form Submit
  // ==============================
  const modalElem = document.querySelector('#confirmModal');
  const modalInstance = M.Modal.init(modalElem);
  const form = document.getElementById('flightForm');
  const submitBtn = document.getElementById('submitBtn');
  const confirmBtn = document.getElementById('confirmBtn');
  const cancelBtn = document.getElementById('cancelBtn');

  // Validation function
  function validateForm() {
    const originInput = document.getElementById('origin');
    const destinationInput = document.getElementById('destination');
    const dateInput = document.getElementById('flight-date');

    const originErrorDiv = originInput.nextElementSibling;
    const destinationErrorDiv = destinationInput.nextElementSibling;
    const dateErrorDiv = dateInput.nextElementSibling;

    let isValid = true;

    // Clear previous errors
    originErrorDiv.textContent = '';
    destinationErrorDiv.textContent = '';
    dateErrorDiv.textContent = '';

    const origin = originInput.value.trim().toUpperCase();
    const destination = destinationInput.value.trim().toUpperCase();
    const flightDate = dateInput.value.trim();

    // Validation rules
    if (!origin) {
      originErrorDiv.textContent = 'Origin code is required.';
      isValid = false;
    } else if (!/^[A-Z]{3}$/.test(origin)) {
      originErrorDiv.textContent = 'Origin must be 3 uppercase letters.';
      isValid = false;
    }

    if (!destination) {
      destinationErrorDiv.textContent = 'Destination code is required.';
      isValid = false;
    } else if (!/^[A-Z]{3}$/.test(destination)) {
      destinationErrorDiv.textContent = 'Destination must be 3 uppercase letters.';
      isValid = false;
    }

    if (origin && destination && origin === destination) {
      destinationErrorDiv.textContent = 'Destination cannot be the same as origin.';
      isValid = false;
    }

    if (!flightDate) {
      dateErrorDiv.textContent = 'Departure date is required.';
      isValid = false;
    }

    return isValid;
  }

  // When user clicks Submit
  submitBtn.addEventListener('click', function (e) {
    e.preventDefault();

        // User must be logged in
    if (!document.getElementById('userMenu')) {
        M.toast({html: 'Please log in to continue.'});
        return;
    }

    if (validateForm()) {
      modalInstance.open(); // Show confirm modal only if valid
    }
  });

  // When user clicks Confirm
  confirmBtn.addEventListener('click', function () {
  // Remove any old hidden input
  let old = form.querySelector('input[name="form_submit"]');
  if (old) old.remove();

  // Add hidden input for PHP
  const hiddenSubmit = document.createElement('input');
  hiddenSubmit.type = 'hidden';
  hiddenSubmit.name = 'form_submit';
  hiddenSubmit.value = 'true';
  form.appendChild(hiddenSubmit);

  modalInstance.close();

  // ✅ Safe native submission
  HTMLFormElement.prototype.submit.call(form);
});

});

</script>


<script>
(function () {
  // delegated submit handler for login form — prevents double submits
  document.addEventListener('submit', async function (e) {
    const form = e.target;
    if (!form || form.id !== 'loginForm') return;

    e.preventDefault();

    // Prevent duplicate sends
    if (form.dataset.sending === '1') {
      console.warn('[login] duplicate submit suppressed');
      return;
    }
    form.dataset.sending = '1';

    // Disable submit button for UX
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.innerHTML;
      submitBtn.innerHTML = 'Logging in...';
    }

    // Error nodes
    const errAccId = document.getElementById('err-acc_id');
    const errPassword = document.getElementById('err-password');
    const errGeneral = document.getElementById('err-general');
    if (errAccId) errAccId.textContent = '';
    if (errPassword) errPassword.textContent = '';
    if (errGeneral) errGeneral.textContent = '';

    const fd = new FormData(form);
    const acc = (fd.get('acc_id') || '').toString().trim();
    const pw  = (fd.get('password') || '').toString();

    if (!acc) { if (errAccId) errAccId.textContent = 'Please enter Account ID.'; cleanup(); return; }
    if (!pw)  { if (errPassword) errPassword.textContent = 'Please enter password.'; cleanup(); return; }

    try {
      console.log('[login] sending fetch to', form.action || 'login.php');
      const res = await fetch(form.action || 'login.php', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      // If the server returned a redirect or non-JSON, fall back gracefully
      if (!res.ok) {
        console.warn('[login] fetch returned non-ok', res.status);
        if (errGeneral) errGeneral.textContent = 'Server error. Try again.';
        cleanup();
        return;
      }

      // parse response
      const data = await res.json();
      console.log('[login] server response', data);

      if (!data.success) {
        if (data.errors) {
          if (data.errors.acc_id && errAccId) errAccId.textContent = data.errors.acc_id;
          if (data.errors.password && errPassword) errPassword.textContent = data.errors.password;
          if (data.errors.general && errGeneral) errGeneral.textContent = data.errors.general;
        } else if (errGeneral) {
          errGeneral.textContent = 'Login failed. Try again.';
        }
        cleanup();
        return;
      }

      // success: close modal and update nav (same as before)
      try {
        const modalElem = document.getElementById('loginModal');
        if (modalElem && window.M) {
          let inst = M.Modal.getInstance(modalElem);
          if (!inst) inst = M.Modal.init(modalElem);
          if (inst && typeof inst.close === 'function') inst.close();
        }
      } catch (err) { console.warn('[login] modal close error', err); }

      const navRight = document.getElementById('nav-right') || document.querySelector('.right.hide-on-med-and-down');
      if (navRight) {
        document.getElementById('loginLi')?.remove();
        document.getElementById('userMenu')?.remove();
        document.getElementById('logoutLi')?.remove();

        const liUser = document.createElement('li');
        liUser.id = 'userMenu';
        liUser.innerHTML = `<a class="waves-effect waves-light" href="#!"><i class="material-icons left">account_circle</i>${data.user?.acc_name||'User'}</a>`;
        navRight.appendChild(liUser);

        const liLogout = document.createElement('li');
        liLogout.id = 'logoutLi';
        liLogout.innerHTML = `<a href="logout.php">Logout</a>`;
        navRight.appendChild(liLogout);
      }

      if (window.M && M.toast) M.toast({ html: 'Logged in as ' + (data.user?.acc_name || 'user') });

      form.reset();
      cleanup();
      return;

    } catch (err) {
      console.error('[login] fetch error', err);
      if (errGeneral) errGeneral.textContent = 'Network/server error.';
      cleanup();
    }

    function cleanup() {
      // re-enable submit & clear sending flag
      if (submitBtn) {
        submitBtn.disabled = false;
        if (submitBtn.dataset.origText) submitBtn.innerHTML = submitBtn.dataset.origText;
      }
      form.dataset.sending = '0';
    }
  }, true);
})();
</script>




<?php include('templates/footer.php'); ?>
<style>
   .datepicker-date-display{
    display: none;
  }
  .datepicker-modal{
    width: 344px;
    border-radius: 20px;
    color: blue;
  }
  .datepicker-cancel, .datepicker-done{
        color: blue !important;
  }
  .datepicker-controls .select-month {
    width: 90px !important;
  }
  select.datepicker-select{
    display: none !important;
  }
</style>