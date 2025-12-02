<?php
session_start();


if (!isset($require_admin)) {
    // default behaviour: force admin popup for this page
    $require_admin = true;
}

// If session indicates already logged-in admin, don't require popup
if (!empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin') {
    $require_admin = false;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TOURS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

<style type="text/css">
  html, body { margin: 0; padding: 0; background-color: gray; }

  /* Form styles */
  .bg-container { padding: 20px; width: 90%; }
  .submitbtn { padding-top: 20px !important; }
  h2, h3 { font-weight: bold; }
  input[type="text"] { text-transform: uppercase; }

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
  nav{
    background-image: url(assets/Banner.png);
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center center;
    height: 100px;
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

  /* Admin blocker overlay & modal z-index */
  #admin-blocker {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 998;
    pointer-events: auto;
  }
  #admin-blocker.active { display: block; }
  .modal.admin-gate { z-index: 9999 !important; }
</style>
</head>

<body>
<nav class="blue">
  <div class="nav-wrapper">
    <a href="admin.php" class="brand-logo center">
      <i class="material-icons hide-on-med-and-down">flight_takeoff</i>TOURS
    </a>
  </div>
</nav>

<!-- ADMIN LOGIN MODAL (auto opens if $require_admin === true) -->
<div id="loginModal" class="modal admin-gate">
  <div class="modal-content">
    <h4 class="center">Admin Login</h4>

    <form id="adminLoginForm" method="POST" action="Ad_log.php" autocomplete="off" novalidate>
      <div class="input-field">
        <i class="material-icons prefix">person</i>
        <input type="text" name="acc_id" id="acc_id" />
        <label for="acc_id">Account ID</label>
        <div class="red-text" id="err-acc_id"></div>
      </div>

      <div class="input-field">
        <i class="material-icons prefix">lock</i>
        <input type="password" name="password" id="password" />
        <label for="password">Password</label>
        <div class="red-text" id="err-password"></div>
      </div>

      <div class="red-text" id="err-general" style="margin-bottom:12px;"></div>

      <button type="submit" class="btn blue waves-effect waves-light" style="width:100%;">Log In</button>
    </form>
  </div>
</div>

<div id="admin-blocker"></div>

<!-- JS includes (jQuery + Materialize) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
  $(document).ready(function(){
    $('.sidenav').sidenav();

    // Initialize modal as non-dismissible (user can't click outside or press ESC to close)
    var modalElems = document.querySelectorAll('.modal');
    M.Modal.init(modalElems, {
      dismissible: false,
      startingTop: '10%',
      endingTop: '10%'
    });

    var requireAdmin = <?php echo $require_admin ? 'true' : 'false'; ?>;

    function openAdminModal(){
      var modalElem = document.getElementById('loginModal');
      var instance = M.Modal.getInstance(modalElem);
      // clear fields / errors
      $('#acc_id').val(''); $('#password').val('');
      $('#err-acc_id').text(''); $('#err-password').text(''); $('#err-general').text('');
      M.updateTextFields();
      $('#admin-blocker').addClass('active');
      instance.open();
    }

    function closeAdminModal(){
      var modalElem = document.getElementById('loginModal');
      var instance = M.Modal.getInstance(modalElem);
      instance.close();
      $('#admin-blocker').removeClass('active');
    }

    if (requireAdmin) {
      // Auto-open admin login modal and block interaction with page
      openAdminModal();

      // Extra guard: prevent ESC key from doing anything
      $(document).on('keydown.adminblock', function(e){
        if (e.key === 'Escape' || e.keyCode === 27) {
          e.preventDefault();
          return false;
        }
      });
    }

    // AJAX submit the login to login.php
    $('#adminLoginForm').on('submit', function(e){
      e.preventDefault();
      $('#err-acc_id').text(''); $('#err-password').text(''); $('#err-general').text('');

      var acc_id = $('#acc_id').val().trim();
      var password = $('#password').val();

      if (!acc_id) { $('#err-acc_id').text('Account ID required'); return; }
      if (!password) { $('#err-password').text('Password required'); return; }

      var payload = {
        acc_id: acc_id,
        password: password,
        require_role: 'admin' // tell server we need admin role
      };

      fetch('Ad_log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          // success: reload page so server-side session reflects admin login
          window.location.reload();
        } else {
          // show returned errors
          if (data.field === 'acc_id') $('#err-acc_id').text(data.msg);
          else if (data.field === 'password') $('#err-password').text(data.msg);
          else $('#err-general').text(data.msg || 'Login failed');
        }
      })
      .catch(function(err){
        console.error(err);
        $('#err-general').text('Network/server error');
      });
    });

  });
</script>
</body>
</html>