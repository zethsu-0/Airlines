<?php
// index.php - integrated seat picker (single style + single script)
// Drop-in file: expects templates/header.php and templates/footer.php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$default_avatar = 'assets/avatar.png';
$logged_in = !empty($_SESSION['acc_id']);
?>
<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>
<body>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <script>document.addEventListener('DOMContentLoaded', function(){ if (window.M && M.toast) M.toast({ html: <?php echo json_encode($_SESSION['flash_success']); ?> }); });</script>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <script>document.addEventListener('DOMContentLoaded', function(){ if (window.M && M.toast) M.toast({ html: <?php echo json_encode($_SESSION['flash_error']); ?> }); });</script>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- TOP BACKGROUND PARALLAX (background-image based) -->
<div class="top-parallax-bg" data-bg="assets/island2.jpg" role="banner" aria-label="Top banner">
  <div class="parallax-overlay container">
    <div class="parallax-overlay-inner">
      <div class="parallax-left">
        <h2 class="parallax-cta-title">Welcome to TOURS</h2>
        <p class="parallax-cta-sub">Practice booking flights & learn interactive skills.</p>
      </div>

      <div class="parallax-bottomleft">
        <?php if ($logged_in): ?>
          <a id="getStartedBtn" href="#booking" class="btn btn-large btn-primary">Get Started</a>
        <?php else: ?>
          <a id="getStartedBtn" href="#loginModal" class="btn btn-large btn-primary modal-trigger">Get Started</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="parallax-spacer"></div>

<div class="main-container container" id="booking">

  <!-- BOOKING CARD -->
  <div class="booking-wrap card elevated">
    <h4 class="card-heading center">Quick Booking — Practice Ticket</h4>

    <form id="quickBooking" action="generate_ticket.php" method="post" target="_blank" class="booking-form"
          style="max-width:980px;margin:0 auto;">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">

      <!-- FULL NAME -->
      <div class="row">
        <div class="input-field col s12">
          <input id="full_name" name="passenger_name" type="text" required>
          <label for="full_name">Full Name</label>
        </div>
      </div>

      <!-- AGE + PASSENGER TYPE (native select) -->
      <div class="row">
        <div class="input-field col s12 m4">
          <input id="age" name="age" type="number" min="0" max="150" required>
          <label for="age">Age</label>
        </div>

        <div class="input-field col s12 m8">
          <label class="native-label" for="passenger_type">Passenger Type</label>
          <select name="passenger_type" id="passenger_type" required class="native-select auto-passenger" aria-readonly="true">
            <option value="">Choose passenger type</option>
            <option value="Elderly">Elder</option>
            <option value="Adult">Adult</option>
            <option value="Child">Child</option>
            <option value="Infant">Infant</option>
          </select>
        </div>
      </div>

      <!-- ORIGIN + DESTINATION + DATE -->
      <div class="row">
        <div class="input-field col s12 m4">
          <input id="from" name="from" type="text" required>
          <label for="from">Origin (From)</label>
        </div>

        <div class="input-field col s12 m4">
          <input id="to" name="to" type="text" required>
          <label for="to">Destination (To)</label>
        </div>

        <div class="input-field col s12 m4">
          <input id="departure_date" name="departure_date" type="date" required>
          <label for="departure_date" class="active">Departure Date</label>
        </div>
      </div>

      <!-- GENDER + DISABILITY -->
      <div class="row">
        <div class="col s12 m6">
          <label style="display:block;margin-bottom:.5rem;">Gender (choose one)</label>
          <div class="gender-inline" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;"><input name="gender" type="radio" value="Male" required /><span>Male</span></label>
            <label style="display:flex;align-items:center;gap:6px;"><input name="gender" type="radio" value="Female" /><span>Female</span></label>
            <label style="display:flex;align-items:center;gap:6px;"><input name="gender" type="radio" value="Other" /><span>Other</span></label>
          </div>
          <small class="grey-text">Choose one.</small>
        </div>

        <div class="col s12 m6">
          <label style="display:block;margin-bottom:.5rem;">Accessibility / Disability</label>
          <p><label><input type="checkbox" id="disability_check" name="disability" value="1"><span>I have a disability / need assistance</span></label></p>
          <div id="disability_spec_wrap" style="display:none;margin-top:.5rem;">
            <div class="input-field">
              <input id="disability_spec" name="disability_spec" type="text">
              <label for="disability_spec">Please specify (assistive needs)</label>
            </div>
          </div>
        </div>
      </div>

      <!-- SEAT TYPE + SEAT NUMBER (native select + seat picker) -->
      <div class="row">
        <div class="input-field col s12 m6">
          <label class="native-label" for="seat_type">Seat Class</label>
          <select name="seat_type" id="seat_type" required class="native-select">
            <option value="">Choose seat class</option>
            <option value="Economy">Economy</option>
            <option value="Premium Economy">Premium Economy</option>
            <option value="Business">Business</option>
            <option value="First Class">First Class</option>
          </select>
        </div>

        <div class="input-field col s12 m6" style="display:flex;align-items:center;gap:10px;">
          <input id="seat_number" name="seat_number" type="text" placeholder="Seat Number (optional)" readonly>
          <button type="button" id="pickSeatBtn" class="btn-flat" title="Pick seat"></button>
        </div>
      </div>

      <!-- ACTIONS: fixed alignment -->
      <div class="row" style="margin-top:8px;">
        <div class="col s12 m6">
          <div class="actions">
            <button type="submit" class="btn waves-effect btn-primary">Generate Ticket</button>
            <button type="reset" class="btn-flat">Reset</button>
          </div>
        </div>
        <div class="col s12 m6 right-align" style="padding-top:8px;">
          <small class="grey-text">practice ticket</small>
        </div>
      </div>
    </form>
  </div>

</div> <!-- end main-container -->

<!-- SEAT PICKER MODAL (full cabin map) -->
<div id="seatPickerModal" class="modal modal-fixed-footer">
  <div class="modal-content">
    <h5>Seat Picker</h5>
    <p class="grey-text text-darken-1" style="margin-top:-4px;">
      First: rows 1–6 (1–2–1), Business: 7–20 (1–2–1), Premium: 25–27 (2–4–2), Economy: 30–40 (3–4–3)
    </p>

    <div id="cabinContainer"></div>
    <div id="seatMap" class="seat-map" aria-label="Seat map" role="application"></div>

    <div class="legend" style="justify-content:center;">
      <span><span class="box selected"></span> Selected</span>
      <span><span class="box disabled"></span> Taken / Unavailable</span>
    </div>
    <div class="legend" style="justify-content:center;">
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

<!-- CAROUSEL SECTION (cards use background layer) -->
<section class="hero-carousel" style="background-image: url('assets/island.jpg');">
  <div class="overlay-bg">
    <div class="container">
      <h4 class="center-align white-text hero-heading">Places You Won't Regret Visiting ✈️</h4>
      <div class="carousel">
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
        foreach ($destinations as $dest): ?>
          <div class="carousel-item">
            <div class="destination-card" style="--bg-url: url('<?php echo htmlspecialchars($dest[2], ENT_QUOTES); ?>')">
              <div class="card-bg" aria-hidden="true"></div>
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

<?php include('templates/footer.php'); ?>

<!-- SINGLE STYLE BLOCK -->
<style>
/* ---------- TOP BACKGROUND PARALLAX (background-image based) ---------- */
.top-parallax-bg {
  height: 420px;
  position: relative;
  overflow: hidden;
  background-color: #cfe8ff; /* fallback */
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center center;
  will-change: background-position;
  display: block;
}
.top-parallax-bg[data-bg] { background-image: url('assets/island2.jpg'); }

/* dim overlay */
.top-parallax-bg::before {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.32);
  z-index: 1;
}

/* overlay content */
.parallax-overlay { position:absolute; inset:0; pointer-events:none; z-index:2; }
.parallax-overlay-inner { position: relative; height:100%; display:flex; justify-content:space-between; align-items:flex-end; padding: 28px; pointer-events:none; z-index:3; }
.parallax-left { pointer-events:auto; color:#fff; max-width:60%; text-shadow:0 2px 6px rgba(0,0,0,0.35); }
.parallax-cta-title { color:#fff; margin:0 0 6px; font-size:28px; }
.parallax-cta-sub { color: rgba(255,255,255,0.92); margin:0 0 6px; }

/* Get Started button */
.parallax-bottomleft { pointer-events:auto; align-self:flex-end; }
.parallax-bottomleft .btn-primary {
  background: linear-gradient(90deg,#0d47a1,#1976d2);
  color:#fff;
  border-radius:8px;
  padding: 10px 20px;
  box-shadow: 0 8px 18px rgba(13,71,161,0.16);
  display:inline-flex;
  align-items:center;
  gap:8px;
}

/* booking card */
.main-container { padding: 28px 0 8px; }
.card-heading { margin: 6px 0 18px; font-weight:700; color:#0d47a1; }

/* destination cards */
.destination-card { position: relative; border-radius: 10px; overflow: hidden; box-shadow: 0 6px 18px rgba(9,30,66,0.06); cursor: pointer; background: #fff; margin: 0 auto; min-height: 210px; }
.destination-card .card-bg { position: absolute; inset: 0; background-image: var(--bg-url); background-repeat: no-repeat; background-size: cover; background-position: center center; transition: background-position 0.12s linear; will-change: background-position, transform; z-index: 1; }
.destination-card .country-label { position: absolute; bottom: 12px; left: 12px; background: rgba(0,0,0,0.55); color: #fff; padding: 6px 10px; border-radius: 8px; font-weight:600; text-transform:uppercase; font-size:0.9rem; z-index: 6; }
.card-reveal-overlay { position: absolute; inset: 0; background: rgba(10,20,42,0.88); color: white; display: flex; align-items: center; justify-content: center; padding: 14px; text-align: center; opacity: 0; transform: translateY(16px); pointer-events: none; visibility: hidden; transition: opacity 0.36s, transform 0.36s; z-index: 8; }
.card-reveal-overlay.active { opacity:1; transform: translateY(0); pointer-events:auto; visibility:visible; }

/* buttons */
.actions { display:flex; gap:12px; align-items:center; justify-content:flex-start; flex-wrap:wrap; }
.actions .btn-primary, .btn-primary { background: linear-gradient(90deg,#0d47a1,#1976d2) !important; color:#fff !important; border-radius:8px; padding: 10px 20px; box-shadow: 0 8px 18px rgba(13,71,161,0.16); display:inline-flex; align-items:center; justify-content:center; gap:8px; line-height:1; }

/* native select + label spacing */
.native-label { display:block !important; margin: 0 0 10px 0 !important; color: #5f6b78 !important; font-size: 0.98rem !important; transform: none !important; pointer-events: none; line-height: 1; }
.native-select { margin-top: 0 !important; padding-top: 14px !important; padding-bottom: 12px !important; min-height: 48px; box-sizing: border-box !important; }

/* Seat picker styles (copied + tuned from your ticket.php) */

/* Use your accent color variables for seat states */
:root {
  --sa-accent: #1976d2;   /* blue accent */
  --seat-bg: rgba(255,255,255,0.03);
  --seat-text: #e6eef8;
  --seat-disabled-bg: rgba(255,255,255,0.02);
  --seat-disabled-text: #88929d;
}

/* seat map container */
.seat-map { display:flex; flex-direction:column; gap:8px; padding:16px; max-width:960px; margin:0 auto; }
.seat-row { display:flex; align-items:center; gap:8px; justify-content:center; }
.row-label { width:44px; min-width:44px; text-align:center; font-weight:600; color:var(--sa-accent); }

/* seat tile */
.seat {
  width:44px;
  height:44px;
  border-radius:8px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  user-select:none;
  border:1px solid rgba(255,255,255,0.04);
  transition: transform .08s ease, box-shadow .12s ease, background .12s ease;
  background: var(--seat-bg);
  color: var(--seat-text);
  font-weight:600;
  box-shadow: 0 6px 14px rgba(2,6,23,0.28);
}

/* hover effect */
.seat:hover { transform: translateY(-4px); box-shadow: 0 16px 36px rgba(2,6,23,0.42); }

/* seat types use soft tints; keep text readable */
.seat.first { background-color: rgba(30,136,229,0.12); }
.seat.business { background-color: rgba(251,140,0,0.08); }
.seat.premium { background-color: rgba(126,87,194,0.08); }
.seat.economy { background-color: rgba(67,160,71,0.06); }

/* selected seat - use strong accent */
.seat.selected {
  color: white !important;
  background: linear-gradient(90deg, var(--sa-accent), #0b84ff) !important;
  box-shadow: 0 20px 44px rgba(11,132,255,0.22);
  transform: translateY(-6px);
  border-color: rgba(0,0,0,0.12);
}

/* disabled/muted seats */
.seat.disabled {
  background: var(--seat-disabled-bg);
  color: var(--seat-disabled-text);
  cursor:not-allowed;
  transform:none;
  box-shadow: none;
  border-color: rgba(0,0,0,0.06);
}

/* aisle spacing */
.aisle { width:28px; min-width:28px; }

/* legend */
.legend { display:flex; gap:12px; align-items:center; margin:8px 16px 18px; flex-wrap:wrap; justify-content:center; }
.legend .box { width:18px;height:18px;border-radius:4px;border:1px solid rgba(255,255,255,0.04); display:inline-block;vertical-align:middle;margin-right:6px; }
.legend .box.selected { background: linear-gradient(90deg, var(--sa-accent), #0b84ff); border:none; }
.legend .box.disabled { background: var(--seat-disabled-bg); color: var(--seat-disabled-text); border:none; }

/* summary */
.selection-summary { margin-top:12px; max-width:960px; margin-left:auto; margin-right:auto; padding:0 16px 16px; }
.cabin-header { margin-top:10px; margin-bottom:4px; text-align:left; max-width:960px; margin-left:auto; margin-right:auto; padding:0 18px; display:flex; align-items:center; gap:8px; }
.cabin-header h6 { margin:0; font-weight:600; color: var(--sa-accent); }
.cabin-header .line { flex:1; height:1px; background: rgba(255,255,255,0.04); }

/* text sizing adjustments on smaller screens */
@media(max-width:680px){ .seat{ width:36px;height:36px;border-radius:6px; } .row-label{ width:36px; min-width:36px; font-size:0.9rem; } }

/* ---------- SUPER-ADMIN DEEP NAVY THEME (use in place of page's white background rules) ---------- */
:root{
  --sa-bg: #071428;        /* deep navy */
  --sa-bg2: #071826;       /* slightly lighter */
  --sa-surface: rgba(255,255,255,0.02);
  --sa-card: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
  --sa-text: #e6eef8;
  --sa-muted: #98a0b3;
  --sa-accent: #1976d2;
  --sa-accent-2: #0b84ff;
}

:root {
  --sa-bg: #071428;
  --sa-bg2: #071826;
  --sa-text: #e6eef8;
  --sa-muted: #98a0b3;
  --sa-surface-2: rgba(255,255,255,0.03);
  --sa-surface-3: rgba(255,255,255,0.05);
  --sa-accent: #1976d2;
}
/* Make input fields transparent/dark and text readable */
.input-field input[type="text"],
.input-field input[type="number"],
.input-field input[type="date"],
.input-field input[type="password"],
.input-field textarea {
  background: transparent !important;
  color: var(--sa-text) !important;
  border-radius: 4px;
}

select.native-select,
select:not(.browser-default) /* fallback for any non-browser-default selects */ {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: var(--sa-surface-2) !important; /* dark panel */
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.04) !important;
  padding: 10px 14px !important;
  padding-right: 36px !important; /* space for arrow if present */
  border-radius: 8px !important;
  box-shadow: inset 0 2px 8px rgba(2,6,23,0.35);
  min-height: 44px;
}

/* page background */
html, body {
  margin: 0;
  padding: 0;
  background: linear-gradient(180deg, var(--sa-bg) 0%, var(--sa-bg2) 100%);
  color: var(--sa-text);
  font-family: "Roboto", "Helvetica", Arial, sans-serif;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* parallax overlay should remain dark but slightly stronger for contrast */
.top-parallax-bg::before {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(3,8,20,0.56); /* stronger dim for legibility */
  z-index: 1;
}

/* overlay content text */
.parallax-overlay-inner { z-index:3; }
.parallax-left, .parallax-cta-title, .parallax-cta-sub {
  color: var(--sa-text);
  text-shadow: 0 3px 12px rgba(0,0,0,0.6);
}

/* Get Started button (keeps accent blue) */
.parallax-bottomleft .btn-primary {
  background: linear-gradient(90deg,var(--sa-accent),var(--sa-accent-2));
  color:#fff;
  border-radius:8px;
  padding: 10px 20px;
  box-shadow: 0 8px 18px rgba(11,132,255,0.14);
}

/* main container spacing */
.main-container { padding: 28px 0 28px; }

/* booking card: use dark card surface like takequiz */
.booking-wrap.elevated {
  border-radius: 12px;
  padding: 22px;
  background: var(--sa-card);
  border: 1px solid rgba(255,255,255,0.03);
  box-shadow: 0 12px 30px rgba(2,6,23,0.6);
  color: var(--sa-text);
  margin-bottom: 26px;
}

/* card heading color */
.card-heading { margin: 6px 0 18px; font-weight:700; color: var(--sa-text); }

/* input placeholders / labels */
.input-field label { color: var(--sa-muted); }
.input-field input, .input-field textarea, select {
  color: var(--sa-text);
}

/* input underline when focused */
.input-field input[type="text"]:focus,
.input-field input[type="number"]:focus,
.input-field input[type="date"]:focus {
  border-bottom: 2px solid var(--sa-accent) !important;
  box-shadow: 0 1px 0 0 var(--sa-accent) !important;
}
.input-field input[type="text"]:focus + label,
.input-field input[type="number"]:focus + label,
.input-field input[type="date"]:focus + label {
  color: var(--sa-accent) !important;
}

/* make native labels more readable */
.native-label { color: var(--sa-muted) !important; }

/* seats styling stays but text color adjusted for dark bg */
/* (already adjusted above) */
.seat { background: #0f2232; color: var(--sa-text); border: 1px solid rgba(255,255,255,0.04); }
.seat:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(2,6,23,0.6); }
.seat.disabled { background:#10202b; color:#88929d; border-color: rgba(0,0,0,0.15); }

/* legend text */
.legend { color: var(--sa-text); opacity: 0.92; }

/* buttons: primary and transparent behaviors */
.btn-primary, .actions .btn-primary {
  background: linear-gradient(90deg,var(--sa-accent),var(--sa-accent-2)) !important;
  color: #fff !important;
  border-radius: 8px;
  padding: 10px 20px;
  box-shadow: 0 8px 18px rgba(11,132,255,0.12);
}

/* transparent / flat buttons: keep transparent, highlight blue on hover */
.btn-flat, .btn--ghost, .btn-transparent {
  background: transparent !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.04);
  border-radius: 8px;
}
.btn-flat:hover, .btn--ghost:hover, .btn-transparent:hover {
  background: linear-gradient(180deg, rgba(25,118,210,0.12), rgba(11,132,255,0.16)) !important;
  color: #fff !important;
  box-shadow: 0 8px 20px rgba(11,132,255,0.06);
}

/* form small notes */
.grey-text { color: var(--sa-muted) !important; }

/* carousel overlay / destination cards: darken surfaces */
.destination-card { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); box-shadow: 0 8px 24px rgba(2,6,23,0.6); color: var(--sa-text); }
.card-reveal-overlay { background: rgba(5,10,20,0.86); color: var(--sa-text); }

/* make chips and summary text readable */
.chips, .chip, .selection-summary, #summaryText { color: var(--sa-text); }

/* small responsive tweaks for readability */
@media (max-width: 680px) {
  .parallax-overlay-inner { padding: 18px; }
  .top-parallax-bg { height: 320px; }
  .booking-wrap.elevated { padding: 14px; }
}


  .btn {
    font-weight: bold;
    font-size: 20px;
    color: white;
    background-color: #2196f3;
  }
  .btn:hover {
    background-color: #4993de;
  }

  /* Carousel Section */
  .hero-carousel {
    position: relative;
    background: url('assets/island.jpg') center/cover fixed no-repeat;
    padding: 80px 0;
  }
  .hero-carousel .overlay-bg {
    background: rgba(0, 0, 0, 0.45);
    padding: 40px 0;
  }

  /* Destination Cards */
  .destination-card {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    cursor: pointer;
    transition: transform 0.3s ease;
    width: 90%;
    max-width: 600px;
    margin: 0 auto;
  }

  .destination-card img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
    border-radius: 15px;
  }

  .country-label {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 1rem;
  }

  .card-reveal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(20, 20, 20, 0.85);
    color: white;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    text-align: center;
    transition: opacity 0.4s ease, transform 0.4s ease;
  }

  .card-reveal-overlay.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .reveal-content { max-width: 90%; }
  .reveal-content .card-title {
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
  }

  .close-reveal { cursor: pointer; color: #fff; }

  /* Responsive Adjustments */
  @media (max-width: 768px) {
    .destination-card img { height: 130px; }
    .country-label { font-size: 0.9rem; }
  }

  @media (max-width: 480px) {
    .destination-card img { height: 110px; }
    .country-label { font-size: 0.8rem; bottom: 6px; left: 6px; }
  }
    a #tours{
    left: 50px; 
    size: 100px;
    
    }
  .page-footer {
      margin: 0;      
      padding-bottom: 0;
    }

     #urs-logo{

    height: 100px;
    width: 140px;
    

  }
  #sayd{
    left: -25;
  }
  #mat{
    color: purple;
  }

  #seatModalDoneBtn{
      background: linear-gradient(90deg,#0d47a1,#1976d2);
  color:#fff;
  border-radius:8px;
  padding: 10px 20px;
  box-shadow: 0 8px 18px rgba(13,71,161,0.16);
  display:inline-flex;
  align-items:center;
  gap:8px;
  }

  /* =========================
     NEW: HERO RESPONSIVE
     ========================= */
  @media (max-width: 992px) {
    .parallax-overlay-inner {
      flex-direction: column;
      align-items: flex-start;
      justify-content: flex-end;
      padding: 20px;
    }
    .parallax-left {
      max-width: 100%;
    }
  }

  @media (max-width: 600px) {
    .top-parallax-bg {
      height: 320px;
    }
    .parallax-overlay-inner {
      align-items: center;
      text-align: center;
    }
    .parallax-left {
      text-align: center;
      max-width: 100%;
    }
    .parallax-cta-title {
      font-size: 22px;
    }
    .parallax-cta-sub {
      font-size: 0.95rem;
    }
    .parallax-bottomleft {
      align-self: center;
      margin-top: 12px;
    }
  }

  /* =========================
     NEW: INPUT BLUE ON FOCUS
     ========================= */

  .input-field input[type="text"]:focus,
  .input-field input[type="number"]:focus,
  .input-field input[type="date"]:focus {
    border-bottom: 2px solid #1976d2 !important;
    box-shadow: 0 1px 0 0 #1976d2 !important;
  }

  .input-field input[type="text"]:focus + label,
  .input-field input[type="number"]:focus + label,
  .input-field input[type="date"]:focus + label {
    color: #1976d2 !important;
  }

  /* Native selects focus outline slightly blue */
  .native-select:focus {
    outline: 2px solid #1976d2;
    outline-offset: 2px;
    border-radius: 4px;
  }

  /* =========================
     NEW: CHECKBOX BLUE WHEN TICKED
     (Materialize pattern)
     ========================= */

  /* normal checkbox tick (outline type) */
  [type="checkbox"]:checked + span:not(.lever)::before {
    border-right: 2px solid #1976d2;
    border-bottom: 2px solid #1976d2;
  }

  /* filled-in variation if you ever add class="filled-in" */
  [type="checkbox"].filled-in:checked + span:not(.lever)::after {
    border: 2px solid #1976d2;
    background-color: #1976d2;
  }
/* Passenger type: auto only, not manually clickable */
.native-select.auto-passenger {
  pointer-events: none;      /* cannot open / click */
  cursor: default;
}
/* Hide dropdown arrow for auto passenger select */
.native-select.auto-passenger {
  -webkit-appearance: none;  /* Chrome / Safari */
  -moz-appearance: none;     /* Firefox */
  appearance: none;          /* Standard */
  background-image: none !important; /* remove any default bg arrow */
  padding-right: 8px;        /* optional: adjust padding */
}

/* Hide arrow in old Edge/IE */
.native-select.auto-passenger::-ms-expand {
  display: none;
}

/* =========================
   RADIO BUTTON BLUE + LABEL HIGHLIGHT
   ========================= */

/* Use modern accent-color so native radio dot is blue */
input[type="radio"] {
  accent-color: var(--sa-accent);
  margin-right: 6px;
}

/* When radio is checked, highlight the adjacent label text */
.gender-inline label span {
  color: var(--sa-text);
  transition: color .12s ease, font-weight .12s ease;
}

/* This selector targets the DOM structure: <label><input type="radio" /><span>Label</span></label> */
.gender-inline label input[type="radio"]:checked + span,
.gender-inline label input[type="radio"]:focus + span {
  color: var(--sa-accent);
  font-weight: 700;
}

/* fallback for older browsers: if accent-color not supported, style appearance for the label when checked */
.gender-inline label input[type="radio"] {
  /* keep layout stable */
}

/* end style block */


/* ---------- QUICK PATCH: radios + seat-number background (paste near end of your style block) ---------- */

/* Make sure CSS variables exist (safe fallback) */
:root { --sa-accent: #1976d2; --sa-surface-2: rgba(255,255,255,0.03); --sa-text: #e6eef8; }

/* --- RADIO (gender) - custom visual that uses the accent blue on checked --- */
/* HTML structure: <label><input type="radio"><span>Label text</span></label> */
.gender-inline label { position: relative; display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
.gender-inline input[type="radio"] {
  /* keep the input accessible but hide native circle */
  position: absolute;
  opacity: 0;
  width: 18px;
  height: 18px;
  margin: 0;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: auto;
}

/* draw a custom circle before the span text */
.gender-inline input[type="radio"] + span {
  position: relative;
  padding-left: 26px; /* space for the custom circle */
  color: var(--sa-text);
  font-weight: 700;
  display: inline-block;
}

/* the circle */
.gender-inline input[type="radio"] + span::before {
  content: "";
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.32);
  background: transparent;
  box-sizing: border-box;
  transition: background .12s ease, border-color .12s ease, box-shadow .12s ease;
}

/* checked state: fill with accent blue */
.gender-inline input[type="radio"]:checked + span::before {
  background: var(--sa-accent);
  border-color: var(--sa-accent);
  box-shadow: 0 6px 14px rgba(25,118,210,0.18);
}

/* focus ring for keyboard users */
.gender-inline input[type="radio"]:focus + span::before {
  outline: 3px solid rgba(11,132,255,0.12);
  outline-offset: 2px;
}

/* If other styles are aggressively overriding labels, increase specificity */
label.gender-inline input[type="radio"] + span::before { /* no-op but higher specificity */ }

/* --- Force seat-number input to dark panel --- */
/* increase specificity and use !important to override Materialize */
.input-field input#seat_number,
.input-field input#seat_number[readonly] {
  background: var(--sa-surface-2) !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.06) !important;
  box-shadow: inset 0 2px 8px rgba(2,6,23,0.32) !important;
  padding: 10px 12px !important;
  border-radius: 8px !important;
}

/* the pick button next to the input */
#pickSeatBtn { border: 1px solid rgba(255,255,255,0.04) !important; background: transparent !important; color: var(--sa-text) !important; }

/* --- make sure seat visuals are not overridden elsewhere --- */
.seat {
  background: var(--sa-surface-2) !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.04) !important;
  box-shadow: 0 10px 26px rgba(2,6,23,0.28) !important;
}

/* selected seat */
.seat.selected {
  background: var(--sa-accent) !important;
  color: #fff !important;
  border-color: rgba(0,0,0,0.12) !important;
  box-shadow: 0 12px 34px rgba(11,132,255,0.28) !important;
}

/* disabled seat */
.seat.disabled {
  background: rgba(255,255,255,0.02) !important;
  color: rgba(255,255,255,0.36) !important;
  border: 1px solid rgba(255,255,255,0.02) !important;
  box-shadow: none !important;
}

/* ===== FINAL OVERRIDE: radios + seat-number + seats =====
   Paste this at the VERY END of your <style> block (after all other rules)
   then hard-refresh the page (Ctrl+F5 / Cmd+Shift+R) */

:root {
  --sa-accent: #1976d2 !important;   /* your blue */
  --sa-text: #e6eef8 !important;
  --sa-surface-2: rgba(255,255,255,0.03) !important;
}

/* 1) Force native/nonnative radio accent to blue where supported */
input[type="radio"] {
  -webkit-appearance: radio !important;
  appearance: radio !important;
  accent-color: var(--sa-accent) !important;    /* modern browsers */
}

/* 2) Fallback custom radio visuals for browsers that ignore accent-color.
   This targets structure: <label><input type="radio" /><span>Label</span></label> */
.gender-inline label { position: relative; display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
.gender-inline label input[type="radio"] {
  /* keep input accessible but visually hidden so we can draw custom circle */
  position: absolute !important;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  margin: 0;
  opacity: 0;
  z-index: 2;
}

/* create the circle using the adjacent span */
.gender-inline label span {
  display: inline-block;
  padding-left: 28px; /* room for circle */
  color: var(--sa-text);
  font-weight: 700;
  position: relative;
}

/* outer circle */
.gender-inline label span::before {
  content: "";
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.28);
  background: transparent;
  transition: all .12s ease;
  box-sizing: border-box;
  z-index: 1;
}

/* inner dot when checked */
.gender-inline label input[type="radio"]:checked + span::before {
  background: var(--sa-accent) !important;
  border-color: var(--sa-accent) !important;
  box-shadow: 0 6px 14px rgba(25,118,210,0.18);
}

/* label color highlight when checked/focus */
.gender-inline label input[type="radio"]:checked + span,
.gender-inline label input[type="radio"]:focus + span {
  color: var(--sa-accent) !important;
  font-weight: 700;
}

/* 3) Force the seat_number input background (override Materialize) */
#seat_number,
.input-field input#seat_number,
.input-field input#seat_number[readonly] {
  background: var(--sa-surface-2) !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.06) !important;
  box-shadow: inset 0 2px 8px rgba(2,6,23,0.32) !important;
  padding: 10px 12px !important;
  border-radius: 8px !important;
  width: auto !important;
}

/* ensure the pick button looks consistent beside it */
#pickSeatBtn {
  border: 1px solid rgba(255,255,255,0.04) !important;
  background: transparent !important;
  color: var(--sa-text) !important;
  padding: 8px 10px !important;
  border-radius: 8px !important;
}

/* 4) Strong seat visuals so they show on top of other CSS */
.seat {
  background: var(--sa-surface-2) !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.04) !important;
  box-shadow: 0 10px 26px rgba(2,6,23,0.28) !important;
}

/* selected seat */
.seat.selected {
  background: linear-gradient(90deg, var(--sa-accent), #0b84ff) !important;
  color: #fff !important;
  border-color: rgba(0,0,0,0.12) !important;
  box-shadow: 0 12px 34px rgba(11,132,255,0.28) !important;
  transform: translateY(-6px) !important;
}

/* disabled seat */
.seat.disabled {
  background: rgba(255,255,255,0.02) !important;
  color: rgba(255,255,255,0.36) !important;
  border: 1px solid rgba(255,255,255,0.02) !important;
  box-shadow: none !important;
  cursor: not-allowed !important;
}

/* 5) Extra specificity in case Materialize injects inline styles later */
.modal .seat,
.modal .seat.selected,
.modal input#seat_number {
  /* repeat important overrides inside modal scope */
  background: var(--sa-surface-2) !important;
  color: var(--sa-text) !important;
  border: 1px solid rgba(255,255,255,0.04) !important;
}
/* ================================
   MATCH SEAT PICKER MODAL TO DARK NAVY THEME
   ================================= */

/* Modal background */
#seatPickerModal,
#seatPickerModal .modal-content {
  background: linear-gradient(180deg, #071428, #071826) !important;
  color: var(--sa-text) !important;
  border-radius: 12px !important;
  border: 1px solid rgba(255,255,255,0.05) !important;
  box-shadow: 0 12px 30px rgba(2,6,23,0.6) !important;
}

/* Modal footer */
#seatPickerModal .modal-footer {
  background: rgba(255,255,255,0.02) !important;
  border-top: 1px solid rgba(255,255,255,0.05) !important;
}

/* Make the H5 title white */
#seatPickerModal h5 {
  color: var(--sa-text) !important;
  font-weight: 700;
}

/* The seat map container */
#seatMap,
#cabinContainer,
.selection-summary,
.legend {
  background: transparent !important;
  color: var(--sa-text) !important;
}

/* Row label color */
.row-label {
  color: var(--sa-accent) !important;
}

/* Modal overlay darker like takequiz */
.modal-overlay {
  background: rgba(0,0,0,0.65) !important;
}

/* Improve "Done" and "Clear" buttons */
#seatModalDoneBtn {
  background: linear-gradient(90deg,#1976d2,#0b84ff) !important;
  color: #fff !important;
  border-radius: 8px !important;
}

#clearSeatSelectionBtn {
  color: var(--sa-text) !important;
}
/* Seat type dropdown matches dark navy + blue accent */
#seat_type {
  background: var(--sa-surface-2) !important; /* same panel as other fields */
  color: var(--sa-text) !important;
  border: 1px solid rgba(25,118,210,0.55) !important; /* blue border */
  box-shadow: inset 0 2px 8px rgba(2,6,23,0.45) !important;
  min-height: 44px;
  padding: 10px 14px;
  border-radius: 8px;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}

/* Focus outline in blue */
#seat_type:focus {
  outline: 2px solid var(--sa-accent);
  outline-offset: 2px;
}

/* The options inside the opened dropdown */
#seat_type option {
  background: #071428;            /* deep navy like page */
  color: var(--sa-text);
}

/* Selected option highlight */
#seat_type option:checked,
#seat_type option:focus {
  background: var(--sa-accent);
  color: #ffffff;
}
/* Remove Materialize teal highlight from seat dropdown */
.input-field select:focus,
select.native-select:focus,
#seat_type:focus {
  outline: none !important;
  box-shadow: none !important;
  -webkit-box-shadow: none !important;
  border-color: var(--sa-accent) !important; /* keep your blue border */
}
/* REMOVE MATERIALIZE TEAL RADIO HIGHLIGHT */
[type="radio"]:focus + span::after,
[type="radio"]:checked + span::after,
[type="radio"] + span::before {
  box-shadow: none !important;
  border-color: var(--sa-accent) !important; /* force blue border */
}

/* Force checked color to blue */
[type="radio"]:checked + span::before {
  border-color: var(--sa-accent) !important;
}

/* Inner dot (if visible) */
[type="radio"]:checked + span::after {
  background-color: var(--sa-accent) !important;
}

/* Kill any focus glow */
[type="radio"]:focus {
  outline: none !important;
  box-shadow: none !important;
}


</style>

<!-- SINGLE SCRIPT BLOCK -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Materialize init
  try {
    M.Modal.init(document.querySelectorAll('.modal'));
    // init non-native selects only
    const selectElems = Array.from(document.querySelectorAll('select')).filter(s => !s.classList.contains('native-select'));
    if (selectElems.length) M.FormSelect.init(selectElems);
    // carousel
    const car = document.querySelectorAll('.carousel');
    if (car.length) M.Carousel.init(car, { indicators:false, dist:-50, padding:24 });
  } catch(e) { console.warn('Materialize init error', e); }

  // Set top background
  (function(){ const top = document.querySelector('.top-parallax-bg'); if (top && top.dataset && top.dataset.bg) top.style.backgroundImage = "url('"+top.dataset.bg+"')"; })();

  // Fix labels for native selects
  function fixFloatingLabels() {
    try { if (window.M && typeof M.updateTextFields === 'function') M.updateTextFields(); } catch(e){}
    document.querySelectorAll('select.native-select, input[type="text"], input[type="number"], input[type="date"]').forEach(function(el){
      var id = el.id; if (!id) return;
      var lbl = document.querySelector('label[for="'+id+'"]') || document.querySelector('.native-label[for="'+id+'"]');
      if (!lbl) return;
      var hasValue = !!el.value && el.value !== '';
      if (hasValue) lbl.classList.add('active'); else lbl.classList.remove('active');
    });
  }
  setTimeout(fixFloatingLabels, 120);

// Age -> passenger_type auto (Infant, Child, Adult, Elderly)
(function(){
  var ageEl = document.getElementById('age');
  var passengerSelect = document.getElementById('passenger_type');

  // 0–1: Infant, 2–11: Child, 12–58: Adult, 59+: Elderly
  function computeType(ageNum) {
    if (ageNum < 2)  return 'Infant';
    if (ageNum <= 11) return 'Child';
    if (ageNum <= 58) return 'Adult';
    return 'Elderly';          // must match <option value="Elderly">
  }

  function handleAge() {
    if (!ageEl || !passengerSelect) return;
    var v = ageEl.value === '' ? NaN : Number(ageEl.value);

    if (!isNaN(v)) {
      passengerSelect.value = computeType(v);
    } else {
      // if age is cleared, reset passenger type
      passengerSelect.value = '';
    }

    // refresh labels
    if (typeof fixFloatingLabels === 'function') {
      fixFloatingLabels();
    }
    passengerSelect.dispatchEvent(new Event('change', { bubbles: true }));
  }

  if (ageEl) {
    ageEl.addEventListener('input', function () {
      clearTimeout(ageEl._t);
      ageEl._t = setTimeout(handleAge, 140);
    });
    // run once on load (in case there is an existing age)
    handleAge();
  }
})();



  // Disability toggle
  (function(){
    var disCheck = document.getElementById('disability_check');
    var disWrap = document.getElementById('disability_spec_wrap');
    var disInput = document.getElementById('disability_spec');
    function toggleDisability(){ if(!disCheck||!disWrap) return; if(disCheck.checked){ disWrap.style.display='block'; try{ if (window.M && M.updateTextFields) M.updateTextFields(); }catch(e){} } else { disWrap.style.display='none'; if(disInput) disInput.value=''; } fixFloatingLabels(); }
    if (disCheck){ disCheck.addEventListener('change', toggleDisability); toggleDisability(); }
  })();

  // Get started behavior
  (function(){
    const getBtn = document.getElementById('getStartedBtn');
    if (getBtn && <?php echo $logged_in ? 'true' : 'false'; ?>) {
      getBtn.addEventListener('click', function (e) { e.preventDefault(); const target = document.getElementById('booking'); if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    }
  })();

  // ---------------- SEAT PICKER ----------------
  (function(){
    // cabin config (same mapping as your ticket.php)
    const CABINS = [
      { key: 'first', name: 'First Class', className: 'first', startRow:1, endRow:6, letters:['A','', 'D','G','','K'] },
      { key: 'business', name: 'Business Class', className: 'business', startRow:7, endRow:20, letters:['A','', 'D','G','','K'] },
      { key: 'premium', name: 'Premium Economy', className: 'premium', startRow:25, endRow:27, letters:['A','B','','D','E','F','G','','J','K'] },
      { key: 'economy', name: 'Economy', className: 'economy', startRow:30, endRow:40, letters:['A','B','C','','D','E','F','G','','H','J','K'] }
    ];

    // set of seats considered taken (example). In production you'd fetch real data.
    const takenSeats = new Set(['1A','1B','2C','10D','15A']);

    let seatPickerModalInstance = null;
    let seatNumberTargetInput = null;
    let activeCabinKeyForSelection = null;
    let selectedSeats = new Set();

    const seatMapEl = document.getElementById('seatMap');
    const cabinContainerEl = document.getElementById('cabinContainer');
    const selectedChipsEl = document.getElementById('selectedChips');
    const summaryTextEl = document.getElementById('summaryText');

    // helpers
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

    function updateSeatSummary() {
      selectedChipsEl.innerHTML = '';
      const seats = Array.from(selectedSeats);
      if (seats.length) {
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.textContent = seats[0];
        selectedChipsEl.appendChild(chip);
        summaryTextEl.textContent = `Selected seat: ${seats[0]}`;
      } else {
        summaryTextEl.textContent = 'No seat selected.';
      }
    }

    function getTakenSeatsExcludingCurrent() {
      // gather seat_number inputs on page (if multiple passengers) and treat them as taken except the one we're editing
      const set = new Set();
      document.querySelectorAll('input[name="seat_number"]').forEach(inp => {
        if (inp === seatNumberTargetInput) return;
        const v = (inp.value || '').trim().toUpperCase();
        if (v) set.add(v);
      });
      // include global taken example
      takenSeats.forEach(s => set.add(s));
      return set;
    }

    function onSeatClick(ev, seatBtn) {
      if (seatBtn.classList.contains('disabled')) return;
      const seatCabinKey = seatBtn.getAttribute('data-cabin-key');
      if (activeCabinKeyForSelection && seatCabinKey !== activeCabinKeyForSelection) {
        if (M && M.toast) M.toast({ html: 'Please pick a seat in the selected class only.' });
        return;
      }
      const seatId = seatBtn.getAttribute('data-seat');
      // single selection: clear previous
      document.querySelectorAll('.seat.selected').forEach(el => el.classList.remove('selected'));
      selectedSeats.clear();
      seatBtn.classList.add('selected');
      selectedSeats.add(seatId);
      updateSeatSummary();

      // fill input and close
      if (seatNumberTargetInput) {
        seatNumberTargetInput.value = seatId;
        try { M.updateTextFields && M.updateTextFields(); } catch(e){}
        if (seatPickerModalInstance && seatPickerModalInstance.close) seatPickerModalInstance.close();
      }
    }

    function generateSeatLayout() {
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
            seatBtn.setAttribute('data-cabin-key', cabin.key);
            seatBtn.setAttribute('aria-pressed', 'false');
            seatBtn.setAttribute('title', `${seatId} – ${cabin.name}`);
            seatBtn.addEventListener('click', (ev) => onSeatClick(ev, seatBtn));
            seatBtn.addEventListener('keydown', (ev) => {
              if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault(); seatBtn.click();
              }
            });
            rowEl.appendChild(seatBtn);
          });

          seatMapEl.appendChild(rowEl);
        }
      });

      applyCabinFilter('economy'); // default view
    }

    function applyCabinFilter(filterKey) {
      document.querySelectorAll('.seat-row').forEach(row => {
        const key = row.getAttribute('data-cabin-key');
        row.style.display = (filterKey === 'all' || key === filterKey) ? 'flex' : 'none';
      });
      document.querySelectorAll('.cabin-header').forEach(header => {
        const key = header.getAttribute('data-cabin-key');
        header.style.display = (filterKey === 'all' || key === filterKey) ? 'flex' : 'none';
      });
    }

    // init modal and layout
    const seatPickerElem = document.getElementById('seatPickerModal');
    if (seatPickerElem && M && M.Modal) {
      seatPickerModalInstance = M.Modal.init(seatPickerElem, {dismissible:true});
      generateSeatLayout();
    }

    // pickSeatBtn wiring
    const pickBtn = document.getElementById('pickSeatBtn');
    const seatNumberInput = document.getElementById('seat_number');
    if (pickBtn && seatNumberInput) {
      pickBtn.addEventListener('click', function(e){
        e.preventDefault();
        seatNumberTargetInput = seatNumberInput;
        // decide cabin from seat_type
        const st = (document.getElementById('seat_type').value || '').toLowerCase();
        let cabinKey = 'economy';
        if (st.includes('first')) cabinKey = 'first';
        else if (st.includes('business')) cabinKey = 'business';
        else if (st.includes('premium')) cabinKey = 'premium';
        activeCabinKeyForSelection = cabinKey;
        applyCabinFilter(cabinKey);
        // mark taken
        markTakenSeatsDisabled();
        // preselect current
        selectedSeats.clear();
        const curr = (seatNumberInput.value || '').trim().toUpperCase();
        if (curr) {
          const el = document.querySelector('.seat[data-seat="'+curr+'"]');
          if (el && !el.classList.contains('disabled')) { el.classList.add('selected'); selectedSeats.add(curr); updateSeatSummary(); }
        } else {
          updateSeatSummary();
        }
        if (seatPickerModalInstance) seatPickerModalInstance.open();
      }, {passive:true});
    }

    // clicking the seat_number input also opens the picker
    if (seatNumberInput) {
      seatNumberInput.addEventListener('click', function(){ pickBtn.click(); });
      seatNumberInput.addEventListener('focus', function(){ pickBtn.click(); });
    }

    // clear & done handlers
    const clearBtn = document.getElementById('clearSeatSelectionBtn');
    if (clearBtn) clearBtn.addEventListener('click', function(e){ e.preventDefault(); document.querySelectorAll('.seat.selected').forEach(s=>s.classList.remove('selected')); selectedSeats.clear(); updateSeatSummary(); });

    const doneBtn = document.getElementById('seatModalDoneBtn');
    if (doneBtn) doneBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (seatNumberTargetInput) {
        const chosen = Array.from(selectedSeats)[0] || '';
        if (chosen) seatNumberTargetInput.value = chosen;
        try { M.updateTextFields && M.updateTextFields(); } catch(e){}
      }
    });

    // disable seats that are in other seat_number inputs on page (if you later add multiple passenger forms)
    function markTakenSeatsDisabled() {
      const taken = getTakenSeatsExcludingCurrent();
      document.querySelectorAll('.seat').forEach(seatEl => {
        const id = seatEl.getAttribute('data-seat');
        if (taken.has(id)) {
          seatEl.classList.add('disabled'); seatEl.setAttribute('aria-disabled','true');
        } else {
          seatEl.classList.remove('disabled'); seatEl.removeAttribute('aria-disabled');
        }
      });
    }

  })(); // end seat picker

}); // DOMContentLoaded
</script>
</body>
</html>

