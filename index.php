<?php
// index.php (optimized)
// DEV: Save this file as UTF-8 without BOM.

session_start();

// Clear last booking (if any)
if (!empty($_SESSION['flight_id'])) {
    unset($_SESSION['flight_id']);
}

// Simple CSRF token (optional but recommended)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// DB config
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'airlines';

// Create mysqli connection with exceptions for clearer errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // In production, don't echo the exception message
    http_response_code(500);
    echo 'Database connection error.';
    exit;
}

// Helper: clean input
function post_trim_upper(string $key): string {
    $v = $_POST[$key] ?? '';
    return strtoupper(trim((string)$v));
}
function post_trim(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

$origin = post_trim_upper('origin');
$destination = post_trim_upper('destination');
$flight_date = post_trim('flight_date');
$errors = [];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submit'])) {
    // Optional CSRF check (uncomment to enforce)
    // if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    //     $errors['general'] = 'Invalid request (CSRF).';
    // }

    // must be logged in
    if (empty($_SESSION['acc_id'])) {
        $errors['login'] = 'You must be logged in to submit a flight.';
    }

    // Validate IATA codes (3 uppercase letters)
    $iataPattern = '/^[A-Z]{3}$/';
    if ($origin === '') {
        $errors['origin'] = 'Origin code is required.';
    } elseif (!preg_match($iataPattern, $origin)) {
        $errors['origin'] = 'Origin must be 3 uppercase letters.';
    }

    if ($destination === '') {
        $errors['destination'] = 'Destination code is required.';
    } elseif (!preg_match($iataPattern, $destination)) {
        $errors['destination'] = 'Destination must be 3 uppercase letters.';
    }

    if ($origin !== '' && $destination !== '' && $origin === $destination) {
        $errors['destination'] = 'Destination code cannot be the same as origin.';
    }

    // Validate date using DateTime (YYYY-MM-DD)
    if ($flight_date === '') {
        $errors['flight_date'] = 'Departure date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $flight_date);
        $validDate = $d && $d->format('Y-m-d') === $flight_date;
        if (!$validDate) {
            $errors['flight_date'] = 'Invalid date format.';
        }
    }

    // If valid, perform DB lookups + insert
    if (empty($errors)) {
        try {
            // Prepare a single statement to fetch AirportName by IATACode
            $selectStmt = $conn->prepare("SELECT AirportName FROM airports WHERE IATACode = ? LIMIT 1");

            $origin_airline = "Invalid code ($origin)";
            $destination_airline = "Invalid code ($destination)";

            // Lookup origin
            $selectStmt->bind_param('s', $origin);
            $selectStmt->execute();
            $res = $selectStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $origin_airline = $row['AirportName'];
            }
            $res->free();

            // Lookup destination
            $selectStmt->bind_param('s', $destination);
            $selectStmt->execute();
            $res = $selectStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $destination_airline = $row['AirportName'];
            }
            $res->free();
            $selectStmt->close();

            // Insert the submission using prepared statement
            $ins = $conn->prepare("INSERT INTO submitted_flights (origin_code, origin_airline, destination_code, destination_airline, flight_date) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('sssss', $origin, $origin_airline, $destination, $destination_airline, $flight_date);
            $ins->execute();

            $last_id = $ins->insert_id;
            $ins->close();

            // Store last booking and redirect
            $_SESSION['flight_id'] = $last_id;
            // safe redirect
            header('Location: ticket.php?id=' . urlencode((string)$last_id));
            $conn->close();
            exit;
        } catch (mysqli_sql_exception $e) {
            // Log $e->getMessage() to server logs in production
            $errors['general'] = 'Database error. Please try again later.';
        }
    }
}

// Close connection at the end of the script (if not already closed)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>
<body>
  <!-- Hero Section -->
  <section class="center-align">
    <img src="assets/island2.jpg" alt="Island" class="responsive-img">
  </section>

  <!-- Booking Form Card -->
  <div class="bg-container container center">
    <form id="flightForm" action="index.php" method="POST" autocomplete="off" class="card" novalidate>
      <!-- CSRF token (optional) -->
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
      <div class="row">
        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_takeoff</i>
            <input type="text" name="origin" class="center" id="origin" value="<?php echo htmlspecialchars($origin, ENT_QUOTES); ?>">
            <div class="red-text"><?php echo htmlspecialchars($errors['origin'] ?? '', ENT_QUOTES); ?></div>
            <label for="origin">ORIGIN</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_land</i>
            <input type="text" name="destination" class="center" id="destination" value="<?php echo htmlspecialchars($destination, ENT_QUOTES); ?>">
            <div class="red-text"><?php echo htmlspecialchars($errors['destination'] ?? '', ENT_QUOTES); ?></div>
            <label for="destination">DESTINATION</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="center">
            <div class="input-field">
              <i class="material-icons prefix">calendar_today</i>
              <input type="text" id="flight-date" name="flight_date" class="datepicker" value="<?php echo htmlspecialchars($flight_date, ENT_QUOTES); ?>" readonly>
              <label for="flight-date">DEPARTURE</label>
              <div class="red-text"><?php echo htmlspecialchars($errors['flight_date'] ?? '', ENT_QUOTES); ?></div>
            </div>
          </div>
        </div>

        <div class="col s3 md3 submitbtn">
          <div class="center">
            <input type="button" id="submitBtn" name="form_submit" value="Submit" class="btn brand z-depth-0">
          </div>
        </div>
      </div>
      <div class="red-text center"><?php echo htmlspecialchars($errors['general'] ?? '', ENT_QUOTES); ?></div>
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
                <img src="<?php echo htmlspecialchars($dest[2], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($dest[0], ENT_QUOTES); ?>">
                <div class="country-label"><?php echo htmlspecialchars($dest[0], ENT_QUOTES); ?></div>
                <div class="card-reveal-overlay">
                  <div class="reveal-content">
                    <div class="card-title"><?php echo htmlspecialchars($dest[0], ENT_QUOTES); ?> <span class="close-reveal">✕</span></div>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($dest[1], ENT_QUOTES); ?></p>
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
      <button type="button" class="btn green" id="confirmBtn">Confirm</button>
    </div>
  </div>

  <!-- Info Section -->
  <div class="container">
    <h6>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat...</h6>
  </div>

<?php include('templates/footer.php'); ?>

<!-- Materialize + Page scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // CACHE DOM
  const carouselElems = document.querySelectorAll('.carousel');
  M.Carousel.init(carouselElems, { indicators: false, dist: -50, padding: 20 });

  // safely get instance only for first carousel (if present)
  const carouselElement = document.querySelector('.carousel');
  const carouselInstance = carouselElement ? M.Carousel.getInstance(carouselElement) || M.Carousel.init(carouselElement) : null;
  const items = carouselElement ? Array.from(carouselElement.querySelectorAll('.carousel-item')) : [];
  const cards = carouselElement ? Array.from(carouselElement.querySelectorAll('.destination-card')) : [];

  // hide overlays
  const hideAll = () => {
    document.querySelectorAll('.card-reveal-overlay.active').forEach(o => o.classList.remove('active'));
  };

  if (cards.length && carouselInstance) {
    cards.forEach((card, idx) => {
      const overlay = card.querySelector('.card-reveal-overlay');
      const closeBtn = card.querySelector('.close-reveal');

      const img = card.querySelector('img');
      if (img) {
        img.addEventListener('click', e => {
          e.stopPropagation();
          hideAll();
          carouselInstance.set(idx);
          // small delay to allow carousel to center
          setTimeout(() => overlay?.classList.add('active'), 400);
        }, { passive: true });
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', e => {
          e.stopPropagation();
          overlay?.classList.remove('active');
        }, { passive: true });
      }
    });

    // reduce frequency of center-detection to 250ms
    let lastIdx = -1;
    setInterval(() => {
      if (!carouselElement) return;
      const rect = carouselElement.getBoundingClientRect();
      const cx = rect.left + rect.width / 2;
      const idx = items.reduce((best, el, i) => {
        const r = el.getBoundingClientRect();
        const d = Math.abs((r.left + r.width / 2) - cx);
        return d < best[0] ? [d, i] : best;
      }, [Infinity, 0])[1];
      if (idx !== lastIdx) {
        hideAll();
        lastIdx = idx;
      }
    }, 250);

    // clicking outside reveals should hide overlays
    ['touchstart', 'mousedown', 'click'].forEach(evt => {
      carouselElement.addEventListener(evt, e => {
        if (!e.target.closest('.destination-card')) hideAll();
      }, { passive: true });
    });
  }

  // DATE PICKER
  const dateElems = document.querySelectorAll('.datepicker');
  M.Datepicker.init(dateElems, { format: 'yyyy-mm-dd', autoClose: true, minDate: new Date() });

  // MODAL + FORM SUBMIT
  const modalEl = document.querySelector('#confirmModal');
  const modalInst = modalEl ? M.Modal.getInstance(modalEl) || M.Modal.init(modalEl) : null;
  const form = document.getElementById('flightForm');
  const submitBtn = document.getElementById('submitBtn');
  const confirmBtn = document.getElementById('confirmBtn');

  function setText(node, text) { if (node) node.textContent = text; }

  function validateForm() {
    // Use cached nodes
    const originInput = document.getElementById('origin');
    const destinationInput = document.getElementById('destination');
    const dateInput = document.getElementById('flight-date');

    // guard
    if (!originInput || !destinationInput || !dateInput) return false;

    const originErr = originInput.nextElementSibling;
    const destinationErr = destinationInput.nextElementSibling;
    const dateErr = dateInput.nextElementSibling;

    setText(originErr, '');
    setText(destinationErr, '');
    setText(dateErr, '');

    let valid = true;
    const origin = originInput.value.trim().toUpperCase();
    const destination = destinationInput.value.trim().toUpperCase();
    const flightDate = dateInput.value.trim();

    if (!origin) { setText(originErr, 'Origin code is required.'); valid = false; }
    else if (!/^[A-Z]{3}$/.test(origin)) { setText(originErr, 'Origin must be 3 uppercase letters.'); valid = false; }

    if (!destination) { setText(destinationErr, 'Destination code is required.'); valid = false; }
    else if (!/^[A-Z]{3}$/.test(destination)) { setText(destinationErr, 'Destination must be 3 uppercase letters.'); valid = false; }

    if (origin && destination && origin === destination) { setText(destinationErr, 'Destination cannot be the same as origin.'); valid = false; }

    if (!flightDate) { setText(dateErr, 'Departure date is required.'); valid = false; }

    return valid;
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', function (e) {
      e.preventDefault();
      // require login client-side (nav updated on login). Still validated server-side.
      if (!document.getElementById('userMenu')) {
        M.toast({ html: 'Please log in to continue.' });
        return;
      }
      if (validateForm() && modalInst) modalInst.open();
    });
  }

  if (confirmBtn && form && modalInst) {
    confirmBtn.addEventListener('click', function () {
      // ensure only one hidden marker input exists
      const existing = form.querySelector('input[name="form_submit"]');
      if (existing) existing.remove();

      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'form_submit';
      hidden.value = 'true';
      form.appendChild(hidden);

      modalInst.close();
      // Use native submit to avoid duplicate behavior
      form.submit();
    });
  }
});
</script>

<script>
(function () {
  // delegated login submit handler — prevents double submits
  document.addEventListener('submit', async function (e) {
    const form = e.target;
    if (!form || form.id !== 'loginForm') return;

    e.preventDefault();
    if (form.dataset.sending === '1') return;
    form.dataset.sending = '1';

    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.innerHTML;
      submitBtn.innerHTML = 'Logging in...';
    }

    const errAccId = document.getElementById('err-acc_id');
    const errPassword = document.getElementById('err-password');
    const errGeneral = document.getElementById('err-general');
    if (errAccId) errAccId.textContent = '';
    if (errPassword) errPassword.textContent = '';
    if (errGeneral) errGeneral.textContent = '';

    const fd = new FormData(form);
    const acc = (fd.get('acc_id') || '').toString().trim();
    const pw = (fd.get('password') || '').toString();

    if (!acc) { if (errAccId) errAccId.textContent = 'Please enter Account ID.'; cleanup(); return; }
    if (!pw) { if (errPassword) errPassword.textContent = 'Please enter password.'; cleanup(); return; }

    try {
      const res = await fetch(form.action || 'login.php', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      if (!res.ok) {
        if (errGeneral) errGeneral.textContent = 'Server error. Try again.';
        cleanup();
        return;
      }

      const data = await res.json();
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

      // close modal if present
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
        liUser.innerHTML = `<a class="waves-effect waves-light" href="#!"><i class="material-icons left">account_circle</i>${data.user?.acc_name || 'User'}</a>`;
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
      if (submitBtn) {
        submitBtn.disabled = false;
        if (submitBtn.dataset.origText) submitBtn.innerHTML = submitBtn.dataset.origText;
      }
      form.dataset.sending = '0';
    }
  }, true);
})();
</script>

<style>
  .datepicker-date-display { display: none; }
  .datepicker-modal { width: 344px; border-radius: 20px; color: blue; }
  .datepicker-cancel, .datepicker-done { color: blue !important; }
  .datepicker-controls .select-month { width: 90px !important; }
  select.datepicker-select { display: none !important; }
</style>
</body>
</html>
