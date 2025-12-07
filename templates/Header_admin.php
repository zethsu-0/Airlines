<?php
// header_admin.php (screenshot-matching version)

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Force admin requirement unless overridden
if (!isset($require_admin)) $require_admin = true;

// Redirect non-admins
if (!empty($_SESSION['acc_id']) && ($_SESSION['acc_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

// If already logged in as admin, disable modal
if (!empty($_SESSION['acc_id']) && ($_SESSION['acc_role'] ?? '') === 'admin') {
    $require_admin = false;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TOURS - Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

<style>
/* ---------- Screenshot Matching Header ---------- */

nav.admin-nav {
  background: linear-gradient(90deg, #0b63d6 0%, #0b84ff 100%);
  height: 64px;
  line-height: 64px;
  box-shadow: 0 3px 8px rgba(3,40,80,0.15);
  position: relative;
  padding: 0 20px;
}

nav.admin-nav .nav-wrapper {
  position: relative;
  height: 64px;
}

/* Centered logo + title */
.brand-logo {
  position: absolute;
  left: 50%;
  transform: translate(-50%, -50%);
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #ffffff;
  z-index: 5;
}

/* Circle icon */
.brand-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: #fff;
  display: inline-block;
  box-shadow: 0 2px 6px rgba(0,0,0,0.18);
}
.brand-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Title text */
.brand-text {
  font-size: 22px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  letter-spacing: .2px;
}

/* Right-side logout */
ul.right {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  z-index: 6;
}

/* Logout button */
.logout-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #ffffff !important;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.12);
  background: transparent !important;
  text-decoration: none;
  font-weight: 500;
  box-shadow: none !important;
  transition: .15s ease;
}
.logout-btn i {
  font-size: 18px;
}

/* Hover effect */
.logout-btn:hover {
  background: rgba(255,255,255,0.06) !important;
  border-color: rgba(255,255,255,0.23);
  transform: translateY(-1px);
}

/* Hide admin name entirely */
.admin-name { display: none !important; }

@media (max-width: 640px) {
  .brand-text { font-size: 18px; }
  .brand-icon { width: 32px; height: 32px; }
}
</style>

</head>
<body>

<!-- HEADER -->
<nav class="admin-nav">
  <div class="nav-wrapper">

    <!-- CENTERED LOGO + TITLE -->
    <a href="admin.php" class="brand-logo center bold" style="display:flex;align-items:center;gap:8px;">
      <img src="assets/logo.png" alt="logo" style="height:34px;vertical-align:middle;"> 
      Students & Quiz Management
    </a>

    <!-- RIGHT LOGOUT BUTTON -->
    <ul class="right">
      <li>
        <a id="logoutBtn" href="#!" class="logout-btn">
          <i class="material-icons">exit_to_app</i> Logout
        </a>
      </li>
    </ul>

  </div>
</nav>


<!-- ADMIN LOGIN MODAL -->
<div id="loginModal" class="modal admin-gate" style="max-width:420px;">
  <div class="modal-content">
    <h5 class="center">Admin Login</h5>

    <form id="adminLoginForm" autocomplete="off">
      <div class="input-field">
        <i class="material-icons prefix">person</i>
        <input id="acc_id" type="text">
        <label for="acc_id">Account ID</label>
        <div id="err-acc_id" class="red-text"></div>
      </div>

      <div class="input-field">
        <i class="material-icons prefix">lock</i>
        <input id="password" type="password">
        <label for="password">Password</label>
        <div id="err-password" class="red-text"></div>
      </div>

      <div id="err-general" class="red-text"></div>

      <button id="adminLoginBtn" class="btn blue waves-effect" style="width:100%;">Log In</button>
    </form>
  </div>
</div>

<div id="admin-blocker"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:998;"></div>


<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
// ---------------------- INITIALIZE ----------------------
document.addEventListener("DOMContentLoaded", function() {
  M.Modal.init(document.querySelectorAll(".modal"), { dismissible: false });

  const requireAdmin = <?= $require_admin ? "true" : "false" ?>;
  const modal = M.Modal.getInstance(document.getElementById("loginModal"));

  function openAdminModal() {
    $('#acc_id,#password').val('');
    $('#err-acc_id,#err-password,#err-general').text('');
    M.updateTextFields();
    $('#admin-blocker').show();
    modal.open();
  }

  if (requireAdmin) openAdminModal();

  // ---------------------- LOGIN ----------------------
  $("#adminLoginForm").on("submit", function(e) {
    e.preventDefault();
    $("#err-acc_id,#err-password,#err-general").text("");

    let payload = {
      acc_id: $("#acc_id").val().trim(),
      password: $("#password").val(),
      require_role: "admin"
    };

    if (!payload.acc_id) return $("#err-acc_id").text("Account ID required");
    if (!payload.password) return $("#err-password").text("Password required");

    $("#adminLoginBtn").prop("disabled", true).text("Logging in...");

    fetch("login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      $("#adminLoginBtn").prop("disabled", false).text("Log In");

      if (data.success) location.reload();
      else {
        if (data.field === "acc_id") $("#err-acc_id").text(data.msg);
        else if (data.field === "password") $("#err-password").text(data.msg);
        else $("#err-general").text(data.msg || "Login failed");
      }
    })
    .catch(() => {
      $("#err-general").text("Server error");
      $("#adminLoginBtn").prop("disabled", false).text("Log In");
    });
  });

  // ---------------------- LOGOUT ----------------------
  $("#logoutBtn").on("click", function(e){
    e.preventDefault();
    fetch("logout.php", { method:"POST" }).finally(() => {
      window.location.href = "index.php";
    });
  });
});
</script>

</body>
</html>
