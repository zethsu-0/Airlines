<?php
// index.php - native-select version with carousel & dropdown fixes
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$default_avatar = 'assets/avatar.png';
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

<section class="center-align">
  <img src="assets/island2.jpg" alt="Island" class="responsive-img">
</section>

<div class="container">
  <h3 class="center-align">Try Booking Now</h3>

  <form id="quickBooking" action="generate_ticket.php" method="post" target="_blank" class="card"
        style="padding:18px;max-width:850px;margin:0 auto;">
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

    <!-- SEAT TYPE + SEAT NUMBER (native select) -->
    <div class="row">
      <div class="input-field col s12 m6">
        <select name="seat_type" id="seat_type" required class="native-select">
          <option value="">Choose seat class</option>
          <option value="Economy">Economy</option>
          <option value="Premium Economy">Premium Economy</option>
          <option value="Business">Business</option>
          <option value="First Class">First Class</option>
        </select>
      </div>

      <div class="input-field col s12 m6">
        <input id="seat_number" name="seat_number" type="text" placeholder="Seat Number (optional)">
      </div>
    </div>

    <!-- SUBMIT -->
    <div class="row" style="margin-top:8px;">
      <div class="col s12 m6">
        <button type="submit" class="btn waves-effect blue">Generate Ticket</button>
        <button type="reset" class="btn-flat">Reset</button>
      </div>
      <div class="col s12 m6 right-align" style="padding-top:8px;">
        <small class="grey-text">practice ticket</small>
      </div>
    </div>
  </form>
  <p class="center-align"></p>
</div>

<!-- CAROUSEL -->
<section class="hero-carousel" style="background-image: url('assets/island.jpg');">
  <div class="overlay-bg">
    <div class="container">
      <h4 class="center-align white-text">Places You Won't Regret Visiting ✈️</h4>
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

<?php include('templates/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Carousel init (Materialize)
  try {
    if (window.M && M.Carousel) {
      const elems = document.querySelectorAll('.carousel');
      if (elems.length) M.Carousel.init(elems, { indicators: false, dist: -50, padding: 20 });
    }
  } catch (e) {
    console.warn('carousel init failed', e);
  }

  // Keep labels correct for inputs & native selects
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

  // passenger-type auto-update (native selects only)
  var ageEl = document.getElementById('age');
  var passengerSelect = document.getElementById('passenger_type');
  function computeType(ageNum){ if (ageNum < 2) return 'Infant'; if (ageNum <= 11) return 'Child'; return 'Adult'; }
  function handleAge(){ if (!ageEl || !passengerSelect) return; var v = ageEl.value===''?NaN:Number(ageEl.value); if(!isNaN(v)) { passengerSelect.value = computeType(v); fixFloatingLabels(); passengerSelect.dispatchEvent(new Event('change',{bubbles:true})); } }
  if (ageEl) { ageEl.addEventListener('input', function(){ clearTimeout(ageEl._t); ageEl._t = setTimeout(handleAge,140); }); handleAge(); }

  // disability toggle
  var disCheck = document.getElementById('disability_check');
  var disWrap = document.getElementById('disability_spec_wrap');
  var disInput = document.getElementById('disability_spec');
  function toggleDisability(){ if(!disCheck||!disWrap) return; if(disCheck.checked){ disWrap.style.display='block'; try{ if (window.M && M.updateTextFields) M.updateTextFields(); }catch(e){} } else { disWrap.style.display='none'; if(disInput) disInput.value=''; } fixFloatingLabels(); }
  if (disCheck){ disCheck.addEventListener('change', toggleDisability); toggleDisability(); }

  // final run
  setTimeout(fixFloatingLabels, 160);
});
</script>

<!-- Robust carousel-card behavior — avoids blinking & race conditions -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  try {
    const carouselElement = document.querySelector('.carousel');
    if (!carouselElement || typeof window.M === 'undefined') return;

    const carouselInstance = M.Carousel.getInstance(carouselElement) ||
                             M.Carousel.init(carouselElement, { indicators: false, dist: -50, padding: 20 });

    const items = Array.from(carouselElement.querySelectorAll('.carousel-item'));
    const cards = Array.from(carouselElement.querySelectorAll('.destination-card'));
    if (!items.length || !cards.length) return;

    function hideAllReveals() {
      document.querySelectorAll('.card-reveal-overlay.active').forEach(o => o.classList.remove('active'));
      activeOverlayIndex = null;
    }

    let activeOverlayIndex = null;
    let ignoreUntil = 0;
    const USER_IGNORE_MS = 900;
    const OPEN_DELAY_MS = 520;
    const CHECK_INTERVAL = 600;

    cards.forEach((card, idx) => {
      const overlay = card.querySelector('.card-reveal-overlay');
      const closeBtn = card.querySelector('.close-reveal');
      const img = card.querySelector('img');

      if (img) {
        img.addEventListener('click', function (e) {
          e.stopPropagation();
          ignoreUntil = Date.now() + USER_IGNORE_MS;
          hideAllReveals();
          try { carouselInstance.set(idx); } catch (err) { console.warn('carousel set failed', err); }
          setTimeout(() => {
            overlay?.classList.add('active');
            activeOverlayIndex = idx;
            ignoreUntil = Date.now() + USER_IGNORE_MS;
          }, OPEN_DELAY_MS);
        }, { passive: true });
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          overlay?.classList.remove('active');
          activeOverlayIndex = null;
          ignoreUntil = Date.now() + (USER_IGNORE_MS / 2);
        }, { passive: true });
      }
    });

    ['click', 'touchstart', 'mousedown'].forEach(evt => {
      document.addEventListener(evt, function (e) {
        if (!e.target.closest('.destination-card')) {
          hideAllReveals();
          ignoreUntil = Date.now() + (USER_IGNORE_MS / 2);
        }
      }, { passive: true });
    });

    let lastIdx = -1;
    setInterval(() => {
      try {
        if (Date.now() < ignoreUntil) return;
        if (activeOverlayIndex !== null) return;
        const rect = carouselElement.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const idx = items.reduce((best, el, i) => {
          const r = el.getBoundingClientRect();
          const d = Math.abs((r.left + r.width / 2) - cx);
          return d < best[0] ? [d, i] : best;
        }, [Infinity, 0])[1];

        if (idx !== lastIdx) {
          hideAllReveals();
          lastIdx = idx;
        }
      } catch (err) {
        console.warn('carousel center-detect error', err);
      }
    }, CHECK_INTERVAL);

  } catch (err) {
    console.warn('carousel card behavior failed', err);
  }
});
</script>

<!-- smoother overlay transitions + native-select single chevron -->
<style>
/* smooth overlay transitions + slight backdrop feel */
.card-reveal-overlay {
  position: absolute;
  inset: 0;
  background: rgba(20,20,20,0.88);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px;
  text-align: center;
  opacity: 0;
  transform: translateY(18px);
  pointer-events: none;
  visibility: hidden;
  transition: opacity 0.36s cubic-bezier(.2,.8,.2,1), transform 0.36s cubic-bezier(.2,.8,.2,1);
}
.card-reveal-overlay.active {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
  visibility: visible;
}

/* style native selects (single chevron, neat spacing) */
.native-select {
  width: 100%;
  padding: 10px 36px 10px 12px; /* leave space on right for chevron */
  border: 1px solid #d0d0d0;
  border-radius: 6px;
  background: white;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  font-size: 1rem;
  margin-top: 6px;
  box-sizing: border-box;
  color: #222;
  line-height: 1.2;
}

/* single chevron using one gradient and placed once */
.native-select {
  background-image: linear-gradient(45deg, transparent 50%, #444 50%);
  background-position: calc(100% - 18px) center;
  background-size: 8px 8px;
  background-repeat: no-repeat;
}

/* small visual help for labels we've floated */
.native-label {
  display: block;
  margin-bottom: 6px;
  color: #9e9e9e;
}

/* keep carousel visuals */
.destination-card img { width:100%; height:180px; object-fit:cover; border-radius:8px; }
.gender-inline label { font-size: 0.95rem; }
@media (max-width:600px){ .gender-inline { gap:10px } }
</style>

</body>
</html>
