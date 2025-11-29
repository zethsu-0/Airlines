<?php
// index.php - cleaned: profile card links to students_edit.php, edit modal removed
session_start();

// Simple CSRF token (kept for other forms)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// DB config
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'airlines';    // airlines DB (students table)
$accountDbName = 'account'; // account DB (accounts table)

// Default avatar fallback
$default_avatar = 'assets/avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<?php include('templates/header.php'); ?>
<body>
<?php
// Show flash messages if any (set by students_edit.php)
if (!empty($_SESSION['flash_success'])): ?>
  <script>document.addEventListener('DOMContentLoaded', function(){ M.toast({ html: <?php echo json_encode($_SESSION['flash_success']); ?> }); });</script>
  <?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <script>document.addEventListener('DOMContentLoaded', function(){ M.toast({ html: <?php echo json_encode($_SESSION['flash_error']); ?> }); });</script>
  <?php unset($_SESSION['flash_error']); endif; ?>

<!-- Hero Section (image) -->
<section class="center-align">
  <img src="assets/island2.jpg" alt="Island" class="responsive-img">
</section>

<div class="container">
  <h3 class="center-align">May gagawin dito diko lang alam ano</h3>
  <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
  tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
  quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
  consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
  cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
  proident, sunt in culpa qui officia deserunt mollit anim id est laborum.Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
  tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
  quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
  consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
  cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
  proident, sunt in culpa qui officia deserunt mollit anim id est laborum.Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
  tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
  quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
  consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
  cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
  proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
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

<?php include('templates/footer.php'); ?>

<!-- Materialize + Page scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Init carousel(s)
  const carouselElems = document.querySelectorAll('.carousel');
  M.Carousel.init(carouselElems, { indicators: false, dist: -50, padding: 20 });

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
});

(function () {
  // If Materialize modal exists, use it. Otherwise fall back to navigating to login.php.
  document.addEventListener('click', function (ev) {
    const el = ev.target.closest && ev.target.closest('#indexLoginBtn');
    if (!el) return;
    ev.preventDefault();

    // If a login modal is present in the DOM, open it via Materialize.
    const modalEl = document.getElementById('loginModal');
    if (modalEl && window.M && M.Modal) {
      let inst = M.Modal.getInstance(modalEl);
      if (!inst) inst = M.Modal.init(modalEl);
      try { inst.open(); } catch (err) { console.warn('Modal open error', err); window.location.href = 'login.php'; }
      return;
    }

    // fallback: go to the login.php page (works if you've replaced login.php with the dual-mode file).
    window.location.href = 'login.php';
  }, { passive: false });
})();

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
  /* removed datepicker styles - kept minimal */
  .card-reveal-overlay { display:none; }
  .card-reveal-overlay.active { display:block; }
  .destination-card img { width:100%; height:180px; object-fit:cover; border-radius:8px; }
</style>
</body>
</html>
