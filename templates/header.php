<?php
// header.php
session_start();
?>
<!doctype html>
<head>
  <title>TOURS</title>
  <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"> 
  <style>
    /* (your CSS — shortened here for brevity) */
    nav .brand-logo { left: 15px; transform: none; }
    /* ... keep the rest of your styles ... */
    #loginModal { width: 500px; padding: 25px; border-radius: 12px; }
  </style>
</head>
<body class="white lighten-4">
<nav class="blue">
  <div class="nav-wrapper">
    <a href="index.php" class="brand-logo center">
      <i class="material-icons hide-on-med-and-down">flight_takeoff</i>TOURS
    </a>
    <a href="#" class="sidenav-trigger show-on-large" data-target="mobile-menu">
      <i class="material-icons">menu</i>
    </a>

    <ul class="right hide-on-med-and-down" id="nav-right">
      <li><a class="btn wave-effect wave-light blue" href="ticket.php"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a></li>

      <?php if (!empty($_SESSION['acc_id'])): ?>
        <!-- Logged-in view -->
        <li id="userMenu">
          <a class="waves-effect waves-light" href="#!">
            <i class="material-icons left">account_circle</i>
            <?php echo htmlspecialchars($_SESSION['acc_name']); ?>
          </a>
        </li>
        <li id="logoutLi"><a href="logout.php">Logout</a></li>
      <?php else: ?>
        <!-- Not logged in -->
        <li id="loginLi">
          <a class="waves-effect waves-light btn blue modal-trigger" href="#loginModal" id="loginBtn">
            <i class="material-icons left">login</i>Log In
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </div>

  <ul class="sidenav blue-grey lighten-4" id="mobile-menu">
    <li>
      <div class="user-view">
        <img class="circle" src="assets/circle.jpg">
        <span class="blue-text name"><?php echo !empty($_SESSION['acc_name']) ? htmlspecialchars($_SESSION['acc_name']) : 'Guest'; ?></span>
      </div>
    </li>
    <li><a href="#">Travels</a></li>
    <li><a href="#">Ticket</a></li>
    <li><a href="#">Ewan</a></li>
  </ul>
</nav>

<!-- LOGIN MODAL -->
<div id="loginModal" class="modal">
  <div class="modal-content">
    <h4 class="center">
      <a href="#!"><i class="modal-close material-icons left">arrow_back</i></a>
      Log In
    </h4>

    <!-- note: no action required — JS will POST to login.php -->

    <form id="loginForm" method="POST" autocomplete="off">
      <div class="input-field">
        <i class="material-icons prefix">person</i>
        <input type="text" name="acc_id" id="acc_id">
        <label for="acc_id">Account ID</label>
        <div class="red-text" id="err-acc_id"></div>
      </div>

      <div class="input-field">
        <i class="material-icons prefix">lock</i>
        <input type="password" name="password" id="password">
        <label for="password">Password</label>
        <div class="red-text" id="err-password"></div>
      </div>

      <div class="red-text" id="err-general" style="margin-bottom:12px;"></div>

      <button type="submit" class="btn blue waves-effect waves-light" style="width:100%;">Log In</button>
    </form>
  </div>
</div>

<!-- scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
   // 1) Init Materialize components
  try {
    var sidenavs = document.querySelectorAll('.sidenav');
    M.Sidenav.init(sidenavs);
    var modals = document.querySelectorAll('.modal');
    M.Modal.init(modals);
  } catch (err) {
    // if M is undefined, Materialize wasn't loaded
    console.error('Materialize init error:', err);
  }

  // Grab modal instance (if exists)
  var loginModalElem = document.getElementById('loginModal');
  var loginModalInstance = null;
  if (loginModalElem) {
    loginModalInstance = M.Modal.getInstance(loginModalElem) || M.Modal.init(loginModalElem);
  }

  // 2) Form and error nodes
  const form = document.getElementById('loginForm');
  if (!form) {
    console.error('Login form (#loginForm) not found on page.');
    return;
  }
  const errAccId = document.getElementById('err-acc_id');
  const errPassword = document.getElementById('err-password');
  const errGeneral = document.getElementById('err-general');

  // Clear helper
  function clearErrors() {
    if (errAccId) errAccId.textContent = '';
    if (errPassword) errPassword.textContent = '';
    if (errGeneral) errGeneral.textContent = '';
  }

  // Simple HTML escape
  function escapeHtml(unsafe) {
    return String(unsafe)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // 3) Submit handler
  form.addEventListener('submit', async function(e) {
    e.preventDefault();         // prevent default form POST
    clearErrors();

    const formData = new FormData(form);

    // Basic client-side validation
    const accIdVal = (formData.get('acc_id') || '').toString().trim();
    const pwVal = (formData.get('password') || '').toString();

    if (!accIdVal) {
      if (errAccId) errAccId.textContent = 'Please enter Account ID.';
      return;
    }
    if (!pwVal) {
      if (errPassword) errPassword.textContent = 'Please enter password.';
      return;
    }

    // Debug log
    console.log('Submitting login for acc_id=', accIdVal);

    try {
      const res = await fetch('login.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' } // ask for JSON
      });

      // network-level errors
      if (!res.ok) {
        console.error('Network response not ok:', res.status, res.statusText);
        errGeneral.textContent = 'Server error (status ' + res.status + ').';
        return;
      }

      // try parse JSON
      let data;
      try {
        data = await res.json();
      } catch (parseErr) {
        console.error('Failed to parse JSON from login.php:', parseErr);
        // show server response for debugging
        const text = await res.text();
        console.error('Server returned (non-JSON):', text);
        errGeneral.textContent = 'Server returned unexpected response.';
        return;
      }

      console.log('login.php returned:', data);

      if (data.success) {
        // Close modal
        if (loginModalInstance) loginModalInstance.close();

        // Update nav UI: remove login button and add username + logout
        const navRight = document.getElementById('nav-right') || document.querySelector('.right.hide-on-med-and-down');
        if (navRight) {
          const loginLi = document.getElementById('loginLi');
          if (loginLi) loginLi.remove();

          // remove previous if present
          const prevUser = document.getElementById('userMenu');
          if (prevUser) prevUser.remove();
          const prevLogout = document.getElementById('logoutLi');
          if (prevLogout) prevLogout.remove();

          // user li
          const liUser = document.createElement('li');
          liUser.id = 'userMenu';
          const name = data.user && data.user.acc_name ? escapeHtml(data.user.acc_name) : 'User';
          liUser.innerHTML = '<a class="waves-effect waves-light" href="#!"><i class="material-icons left">account_circle</i>' + name + '</a>';
          navRight.appendChild(liUser);

          // logout li
          const liLogout = document.createElement('li');
          liLogout.id = 'logoutLi';
          liLogout.innerHTML = '<a href="logout.php">Logout</a>';
          navRight.appendChild(liLogout);
        }

        // optional toast
        if (typeof M !== 'undefined' && M.toast) M.toast({html: 'Logged in as ' + (data.user && data.user.acc_name ? data.user.acc_name : 'user')});

        form.reset();
      } else {
        // show errors returned by server
        if (data.errors) {
          if (data.errors.acc_id && errAccId) errAccId.textContent = data.errors.acc_id;
          if (data.errors.password && errPassword) errPassword.textContent = data.errors.password;
          if (data.errors.general && errGeneral) errGeneral.textContent = data.errors.general;
          // fallback: if server returns field 'username' (older code)
          if (data.errors.username && errAccId) errAccId.textContent = data.errors.username;
        } else {
          errGeneral.textContent = 'Login failed. Please try again.';
        }
      }
    } catch (err) {
      console.error('Fetch/login error:', err);
      if (errGeneral) errGeneral.textContent = 'Network error. Check console for details.';
    }
  });

  // Small debug helper: show whether login.php is reachable (optional)
  // Uncomment to ping login.php on load:
  /*
  fetch('login.php', { method: 'HEAD' })
    .then(r => console.log('login.php reachable, status', r.status))
    .catch(e => console.warn('login.php HEAD failed:', e));
  */
});
</script>
