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
          <select name="passenger_type" id="passenger_type" required class="native-select">
            <option value="">Choose passenger type</option>
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
.booking-wrap.elevated { border-radius: 12px; padding: 18px; background: #fff; box-shadow: 0 12px 30px rgba(13,71,161,0.06); margin-bottom: 26px; }
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
.seat-map { display:flex; flex-direction:column; gap:8px; padding:16px; max-width:960px; margin:0 auto; }
.seat-row { display:flex; align-items:center; gap:8px; justify-content:center; }
.row-label { width:44px; min-width:44px; text-align:center; font-weight:600; color:#444; }
.seat { width:44px; height:44px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; user-select:none; border:1px solid rgba(0,0,0,0.12); transition: transform .08s ease, box-shadow .12s ease; background:#fff; font-weight:600; }
.seat:hover { transform: translateY(-3px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); }
.seat.selected { color:white; border-color: rgba(0,0,0,0.15); }
.seat.disabled { background:#efefef; color:#9e9e9e; cursor:not-allowed; transform:none; box-shadow:none; }
.aisle { width:28px; min-width:28px; }
.legend { display:flex; gap:12px; align-items:center; margin:8px 16px 18px; flex-wrap:wrap; justify-content:center; }
.legend .box { width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,0.12); display:inline-block;vertical-align:middle;margin-right:6px; }
.legend .box.selected { background:#26a69a; border:none; }
.legend .box.disabled { background:#efefef; color:#9e9e9e; border:none; }
.selection-summary { margin-top:12px; max-width:960px; margin-left:auto; margin-right:auto; padding:0 16px 16px; }
.cabin-header { margin-top:10px; margin-bottom:4px; text-align:left; max-width:960px; margin-left:auto; margin-right:auto; padding:0 18px; display:flex; align-items:center; gap:8px; }
.cabin-header h6 { margin:0; font-weight:600; }
.cabin-header .line { flex:1; height:1px; background: rgba(0,0,0,0.12); }
.seat.first { background-color:#e3f2fd; } .seat.business { background-color:#fff3e0; } .seat.premium { background-color:#ede7f6; } .seat.economy { background-color:#e8f5e9; }
.seat.first.selected { background-color:#1e88e5; } .seat.business.selected { background-color:#fb8c00; } .seat.premium.selected { background-color:#7e57c2; } .seat.economy.selected { background-color:#43a047; }
.cabin-header.first h6 { color:#1e88e5; } .cabin-header.business h6 { color:#fb8c00; } .cabin-header.premium h6 { color:#7e57c2; } .cabin-header.economy h6 { color:#43a047; }

@media(max-width:680px){ .seat{ width:36px;height:36px;border-radius:6px; } .row-label{ width:36px; min-width:36px; font-size:0.9rem; } }

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

  // Age -> passenger_type auto
  (function(){
    var ageEl = document.getElementById('age');
    var passengerSelect = document.getElementById('passenger_type');
    function computeType(ageNum){ if (ageNum < 2) return 'Infant'; if (ageNum <= 11) return 'Child'; return 'Adult'; }
    function handleAge(){ if (!ageEl || !passengerSelect) return; var v = ageEl.value===''?NaN:Number(ageEl.value); if(!isNaN(v)) { passengerSelect.value = computeType(v); fixFloatingLabels(); passengerSelect.dispatchEvent(new Event('change',{bubbles:true})); } }
    if (ageEl) { ageEl.addEventListener('input', function(){ clearTimeout(ageEl._t); ageEl._t = setTimeout(handleAge,140); }); handleAge(); }
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

    function markTakenSeatsDisabled() {
      const taken = getTakenSeatsExcludingCurrent();
      document.querySelectorAll('.seat').forEach(seatEl => {
        const id = seatEl.getAttribute('data-seat');
        if (taken.has(id)) {
          seatEl.classList.add('disabled');
          seatEl.setAttribute('aria-disabled','true');
        } else {
          seatEl.classList.remove('disabled');
          seatEl.removeAttribute('aria-disabled');
        }
      });
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

