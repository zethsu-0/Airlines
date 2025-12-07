<?php
// header.php - responsive blue-gradient header with AJAX login, sidenav, ripple removal,
// password toggle, and corrected avatar lookup (sidenav + desktop use same avatar)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Compute avatar + display name once so both header and sidenav use the same values.
$default_avatar = 'assets/avatar.png';
$sn_avatar = $default_avatar;
$sn_name   = $_SESSION['acc_name'] ?? 'Guest';
<?php
// header.php - responsive blue-gradient header with AJAX login, sidenav, ripple removal,
// password toggle, and corrected avatar lookup (sidenav + desktop use same avatar)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Compute avatar + display name once so both header and sidenav use the same values.
$default_avatar = 'assets/avatar.png';
$sn_avatar = $default_avatar;
$sn_name   = $_SESSION['acc_name'] ?? 'Guest';

$acc_role = $_SESSION['acc_role'] ?? $_SESSION['role'] ?? null;

if (!empty($_SESSION['acc_id'])) {
    $acc_id = (string) $_SESSION['acc_id'];

    try {
        $dbHost = 'localhost'; $dbUser = 'root'; $dbPass = ''; $dbName = 'airlines';
        $tmpConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

        if (!$tmpConn->connect_error) {
            $tmpConn->set_charset('utf8mb4');

            // 1) Get avatar from students (same logic as students_edit.php)
            $stmt = $tmpConn->prepare("SELECT avatar FROM students WHERE student_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $acc_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['avatar'])) {
                        $sn_avatar = $row['avatar'];
                    }
                }
                $stmt->close();
            }

            // 2) Get nicer name from accounts
            $stmt2 = $tmpConn->prepare("SELECT acc_name FROM accounts WHERE acc_id = ? LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('s', $acc_id);
                $stmt2->execute();
                $r2 = $stmt2->get_result();
                if ($u = $r2->fetch_assoc()) {
                    if (!empty($u['acc_name'])) {
                        $sn_name = $u['acc_name'];
                    }
                }
                $stmt2->close();
            }

            $tmpConn->close();
        }
    } catch (Exception $e) {
        // ignore DB errors, use defaults
    }
}


// sanitize for HTML usage
$sn_avatar_html = htmlspecialchars($sn_avatar, ENT_QUOTES);
$sn_name_html   = htmlspecialchars($sn_name, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>TOURS</title>

  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

<style>
    /* ----- Scoped header theme ----- */
    .header-scope {
      --primary1: #0052cc;
      --primary2: #1e90ff;
      --text-light: #ffffff;
      --accent: #64b5f6;
      font-family: "Roboto", sans-serif;
    }

    /* Gradient header bar */
    .header-scope nav.topbar {
      background: linear-gradient(90deg, var(--primary1), var(--primary2));
      height: 64px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.25);
    }

    /* Layout */
    .header-scope .nav-ctr {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 18px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Brand */
    .header-scope .brand {
      color: #fff !important;
      display: flex;
      align-items: center;
      font-weight: 700;
      font-size: 1.15rem;
      gap: 8px;
      text-decoration: none;
    }

    /* Desktop actions */
    .header-scope .nav-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .header-scope .btn-ticket,
    .header-scope .login-trigger {
      background: transparent;
      border: none;
      color: #fff;
      padding: 7px 12px;
      border-radius: 6px;
      font-weight: 600;
      text-transform: none;
    }

  /* ===== Button: transparent by default, blue highlight on hover/focus/active ===== */
  .header-scope .btn-ticket,
  .header-scope .login-trigger {
  background: transparent;
  color: #fff;
  border: none;
  padding: 7px 12px;
  border-radius: 6px;
  font-weight: 600;
  text-transform: none;
  transition: background 180ms ease, box-shadow 180ms ease, color 180ms ease;
  }

  /* Blue highlight on hover / focus / active */
  .header-scope .btn-ticket:hover,
  .header-scope .btn-ticket:focus,
  .header-scope .btn-ticket:active,
  .header-scope .login-trigger:hover,
  .header-scope .login-trigger:focus,
  .header-scope .login-trigger:active {
    /* small blue gradient to match header */
    background: linear-gradient(90deg, rgba(25,118,210,0.12), rgba(11,132,255,0.16));
    color: #fff;
    outline: none;
    box-shadow: 0 6px 18px rgba(11,132,255,0.08);
  }

  /* stronger blue fill for primary-like ticket if you prefer a filled hover (optional) */
  /* uncomment if you want a filled pill on hover */
  /*
  .header-scope .btn-ticket:hover,
  .header-scope .btn-ticket:focus {
    background: linear-gradient(90deg, var(--primary1), var(--primary2));
    color: #fff;
  }
  */

  /* ensure the tiny white flash from Materialize waves is removed and replaced with a blue ripple */
  .header-scope .waves-ripple,
  .header-scope span[class*="waves-ripple"] {
    display: none !important;
    opacity: 0 !important;
  }

  /* If any links still show a light background, make sure anchors inherit */
  .header-scope .nav-actions a,
  .header-scope .nav-actions a:hover,
  .header-scope .nav-actions a:focus {
    color: #fff;
    text-decoration: none;
    background: transparent;
  }

  /* Sidenav link hover should also feel blue (desktop links often mirror mobile) */
  .header-scope .sidenav li > a:hover,
  .header-scope .sidenav li > a:focus {
    background: rgba(255,255,255,0.04);
    color: #fff !important;
  }


      /* Profile */
      .header-scope .hdr-profile {
        display: flex;
        gap: 10px;
        align-items: center;
        color: #fff;
        padding: 6px 8px;
        border-radius: 8px;
        text-decoration: none;
      }

      .header-scope .hdr-profile img {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.25);
        object-fit: cover;
      }

      /* Hamburger for mobile */
      .header-scope .sidenav-trigger {
        display: none;
        color: #fff;
        font-size: 30px;
      }

      /* Mobile brand centering */
      @media (max-width: 992px) {

        .header-scope .sidenav-trigger {
          display: inline-block;
          position: absolute;
          left: 16px;
        }

        .header-scope .nav-actions {
          display: none;
        }

        .header-scope .nav-ctr {
          justify-content: center !important;
          position: relative;
        }

        .header-scope .brand {
          margin: 0 auto;
        }
      }

      /* ================= Sidenav ================= */
      .header-scope .sidenav {
        width: 270px;
        background: linear-gradient(180deg, #003d8a, #005fcc);
        padding-top: 0;
      }

      .header-scope .sidenav-header {
        text-align: center;
        padding: 28px 16px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.15);
      }

      .header-scope .sidenav-header img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.35);
        object-fit: cover;
      }

      .header-scope .sidenav-header .name {
        font-size: 1.15rem;
        font-weight: 600;
        color: #fff;
        margin-top: 10px;
      }

      .header-scope .sidenav-header .welcome {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.70);
      }

      .header-scope .sidenav li > a {
        color: #fff !important;
        font-weight: 600;
        padding-left: 20px;
        display: flex;
        gap: 12px;
        align-items: center;
      }

      /* Remove ripple / white flash */
      .header-scope .waves-ripple,
      .header-scope span[class*="waves-ripple"] {
        display: none !important;
        opacity: 0 !important;
      }

      /* ================= Modal ================= */
      .header-scope .modal { border-radius: 8px; }
      .header-scope .modal-content { padding: 24px 30px; }

      .header-scope .input-field input:focus {
        border-bottom: 2px solid var(--primary2) !important;
        box-shadow: 0 1px 0 0 var(--primary2) !important;
      }
      .header-scope .input-field input:focus + label {
        color: var(--primary2) !important;
      }

      .header-scope .input-field .prefix {
        color: var(--primary2) !important;
      }

      /* Eye icon */
      .header-scope .password-container { position: relative; }
      .header-scope .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary2);
        cursor: pointer;
      }

      .header-scope .login-btn {
        width: 100%;
        border-radius: 8px;
        background: linear-gradient(90deg, var(--primary1), var(--primary2));
        color: #fff;
        font-weight: 700;
      }

  /* ===== Login Modal Background Match Index ===== */
  .header-scope #loginModal {
    background: linear-gradient(90deg, var(--primary1), var(--primary2)) !important;
  }

  .header-scope #loginModal .modal-content {
    background: transparent !important;
    color: #fff !important;
  }

  /* make text/icons inside modal white */
  .header-scope #loginModal .input-field label,
  .header-scope #loginModal .input-field .prefix,
  .header-scope #loginModal h5 {
    color: #fff !important;
  }

  /* underline for input fields */
  .header-scope #loginModal input {
    border-bottom: 1px solid rgba(255,255,255,0.6) !important;
    color: #fff !important;
  }

  .header-scope #loginModal input:focus {
    border-bottom: 2px solid #fff !important;
    box-shadow: 0 1px 0 0 #fff !important;
  }

  .header-scope #loginModal .toggle-password {
    color: #fff !important;
  }



</style>
</head>

<body>
  <div class="header-scope">

  <!-- ================== TOP NAV ================== -->
  <nav class="topbar">
    <div class="nav-wrapper nav-ctr">

      <a href="#" class="sidenav-trigger" data-target="mobile-sidenav">
        <i class="material-icons">menu</i>
      </a>

      <a href="index.php" class="brand">
        <i class="material-icons">flight_takeoff</i> TOURS
      </a>

      <div class="nav-actions">
        <?php if($acc_role  === 'admin'): ?>
        <a href="admin.php" class="btn-ticket"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a>
        <?php else: ?>
        <a href="takequiz.php" class="btn-ticket"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a>
        <?php endif; ?>

        <?php if (!empty($_SESSION['acc_id'])): ?>
            <a href="students_edit.php" class="hdr-profile" title="Profile">
              <img src="<?php echo $sn_avatar_html; ?>" alt="avatar">
              <span class="name"><?php echo $sn_name_html; ?></span>
            </a>
            <a href="logout.php" class="login-trigger">Logout</a>
        <?php else: ?>
            <a class="login-trigger modal-trigger" href="#loginModal">Log In</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ================== SIDENAV ================== -->
  <ul id="mobile-sidenav" class="sidenav">

    <li class="sidenav-header">
      <img src="<?php echo $sn_avatar_html; ?>" alt="avatar">
      <div class="name"><?php echo $sn_name_html; ?></div>
      <div class="welcome">Welcome</div>
    </li>

    <li><a href="index.php"><i class="material-icons">home</i>Home</a></li>
    <li><a href="takequiz.php"><i class="material-icons">airplane_ticket</i>Get Your Ticket</a></li>

    <?php if (!empty($_SESSION['acc_id'])): ?>
      <li><a href="students_edit.php"><i class="material-icons">person</i>Profile</a></li>
      <li><a href="logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
    <?php else: ?>
      <li><a href="#loginModal" class="modal-trigger"><i class="material-icons">login</i>Log In</a></li>
    <?php endif; ?>
  </ul>

  <!-- ================== LOGIN MODAL ================== -->
<!-- ================== LOGIN MODAL ================== -->
<div id="loginModal" class="modal">
  <div class="modal-content" style="position:relative;">

    <!-- BACK BUTTON (Top-left) -->
    <button 
      type="button" 
      id="loginBackBtn"
      style="
        position:absolute;
        top:10px;
        left:10px;
        background:transparent;
        border:none;
        color:white;
        display:flex;
        align-items:center;
        font-weight:600;
        cursor:pointer;
        padding:4px 6px;
      "
    >
      <i class="material-icons" style="margin-right:4px;">arrow_back</i>
      Back
    </button>

    <h5 class="center" style="margin-top:0;">Log In</h5>

    <form id="loginForm" autocomplete="off" novalidate>
      <div class="input-field">
        <i class="material-icons prefix">person</i>
        <input type="text" name="acc_id" id="acc_id">
        <label for="acc_id">Account ID</label>
      </div>

      <div class="input-field password-container">
        <i class="material-icons prefix">lock</i>
        <input type="password" name="password" id="password">
        <label for="password">Password</label>
        <i class="material-icons toggle-password" id="togglePw">visibility</i>
      </div>

      <div id="err-general" class="red-text"></div>

      <button type="submit" class="btn login-btn">Log In</button>
    </form>
  </div>
</div>


</div> <!-- /header-scope -->

<!-- ================== SCRIPTS ================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {

    // init sidenav + modal
    var sidenav = document.querySelectorAll('.sidenav');
    if (sidenav.length && window.M && M.Sidenav) M.Sidenav.init(sidenav, {edge:'left', draggable:true});

    var modals = document.querySelectorAll('.modal');
    if (modals.length && window.M && M.Modal) M.Modal.init(modals);

    // toggle password visibility
    var pw = document.getElementById("password");
    var toggle = document.getElementById("togglePw");
    if (pw && toggle) {
        toggle.addEventListener("click", function(){
            pw.type = pw.type === "password" ? "text" : "password";
            toggle.textContent = pw.type === "password" ? "visibility" : "visibility_off";
        });
    }

    // AJAX login
    var loginForm = document.getElementById("loginForm");
    if (loginForm) {
      loginForm.addEventListener("submit", function(e){
          e.preventDefault();

          var formData = new FormData(this);

          fetch("login.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: { "Accept": "application/json" }
          })
          .then(function(r){ return r.text(); })
          .then(function(t){
              try {
                  var data = JSON.parse(t);
              } catch (err) {
                  console.error("Login response is not JSON:", t);
                  return;
              }
              if (!data.success) {
                  var err = document.getElementById("err-general");
                  if (err) err.textContent = data.error || "Login failed.";
                  return;
              }
              window.location.href = data.redirect;
          })
          .catch(function(err){ console.error("Login fetch error:", err); });
      });
    }
});


// Back button inside login modal
var backBtn = document.getElementById("loginBackBtn");
if (backBtn) {
    backBtn.addEventListener("click", function () {
        try {
            var instance = M.Modal.getInstance(document.getElementById("loginModal"));
            if (instance) instance.close();
        } catch (e) {}

        // redirect to home
        window.location.href = "index.php";
    });
}

</script>

</body>
</html>

$acc_role = $_SESSION['acc_role'] ?? $_SESSION['role'] ?? null;

if (!empty($_SESSION['acc_id'])) {
    $acc_id = (string) $_SESSION['acc_id'];
    // Attempt to fetch avatar and nicer name from DB (silent on failure)
    try {
        $dbHost = 'localhost'; $dbUser = 'root'; $dbPass = ''; $dbName = 'airlines';
        $tmpConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if (!$tmpConn->connect_error) {
            $tmpConn->set_charset('utf8mb4');
            $stmt = $tmpConn->prepare("SELECT avatar, acc_name, acc_role
                                        FROM students
                                        LEFT JOIN accounts ON students.student_id = accounts.acc_id
                                        WHERE students.student_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $acc_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['avatar'])) $sn_avatar = $row['avatar'];
                    // prefer acc_name (accounts) if available, otherwise keep session name
                    if (!empty($row['acc_name'])) $sn_name = $row['acc_name'];
                } else {
                    // Fallback: try to get from accounts table directly
                    $stmt2 = $tmpConn->prepare("SELECT acc_name FROM accounts WHERE acc_id = ? LIMIT 1");
                    if ($stmt2) {
                        $stmt2->bind_param('s', $acc_id);
                        $stmt2->execute();
                        $r2 = $stmt2->get_result();
                        if ($u = $r2->fetch_assoc()) {
                            if (!empty($u['acc_name'])) $sn_name = $u['acc_name'];
                        }
                        $stmt2->close();
                    }
                }
                $stmt->close();
            }
            $tmpConn->close();
        }
    } catch (Exception $e) {
        // ignore DB errors, use defaults
    }
}

// sanitize for HTML usage
$sn_avatar_html = htmlspecialchars($sn_avatar, ENT_QUOTES);
$sn_name_html   = htmlspecialchars($sn_name, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>TOURS</title>

  <link rel="stylesheet" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

<style>
    /* ----- Scoped header theme ----- */
    .header-scope {
      --primary1: #0052cc;
      --primary2: #1e90ff;
      --text-light: #ffffff;
      --accent: #64b5f6;
      font-family: "Roboto", sans-serif;
    }

    /* Gradient header bar */
    .header-scope nav.topbar {
      background: linear-gradient(90deg, var(--primary1), var(--primary2));
      height: 64px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.25);
    }

    /* Layout */
    .header-scope .nav-ctr {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 18px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Brand */
    .header-scope .brand {
      color: #fff !important;
      display: flex;
      align-items: center;
      font-weight: 700;
      font-size: 1.15rem;
      gap: 8px;
      text-decoration: none;
    }

    /* Desktop actions */
    .header-scope .nav-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .header-scope .btn-ticket,
    .header-scope .login-trigger {
      background: transparent;
      border: none;
      color: #fff;
      padding: 7px 12px;
      border-radius: 6px;
      font-weight: 600;
      text-transform: none;
    }

  /* ===== Button: transparent by default, blue highlight on hover/focus/active ===== */
  .header-scope .btn-ticket,
  .header-scope .login-trigger {
  background: transparent;
  color: #fff;
  border: none;
  padding: 7px 12px;
  border-radius: 6px;
  font-weight: 600;
  text-transform: none;
  transition: background 180ms ease, box-shadow 180ms ease, color 180ms ease;
  }

  /* Blue highlight on hover / focus / active */
  .header-scope .btn-ticket:hover,
  .header-scope .btn-ticket:focus,
  .header-scope .btn-ticket:active,
  .header-scope .login-trigger:hover,
  .header-scope .login-trigger:focus,
  .header-scope .login-trigger:active {
    /* small blue gradient to match header */
    background: linear-gradient(90deg, rgba(25,118,210,0.12), rgba(11,132,255,0.16));
    color: #fff;
    outline: none;
    box-shadow: 0 6px 18px rgba(11,132,255,0.08);
  }

  /* stronger blue fill for primary-like ticket if you prefer a filled hover (optional) */
  /* uncomment if you want a filled pill on hover */
  /*
  .header-scope .btn-ticket:hover,
  .header-scope .btn-ticket:focus {
    background: linear-gradient(90deg, var(--primary1), var(--primary2));
    color: #fff;
  }
  */

  /* ensure the tiny white flash from Materialize waves is removed and replaced with a blue ripple */
  .header-scope .waves-ripple,
  .header-scope span[class*="waves-ripple"] {
    display: none !important;
    opacity: 0 !important;
  }

  /* If any links still show a light background, make sure anchors inherit */
  .header-scope .nav-actions a,
  .header-scope .nav-actions a:hover,
  .header-scope .nav-actions a:focus {
    color: #fff;
    text-decoration: none;
    background: transparent;
  }

  /* Sidenav link hover should also feel blue (desktop links often mirror mobile) */
  .header-scope .sidenav li > a:hover,
  .header-scope .sidenav li > a:focus {
    background: rgba(255,255,255,0.04);
    color: #fff !important;
  }


      /* Profile */
      .header-scope .hdr-profile {
        display: flex;
        gap: 10px;
        align-items: center;
        color: #fff;
        padding: 6px 8px;
        border-radius: 8px;
        text-decoration: none;
      }

      .header-scope .hdr-profile img {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.25);
        object-fit: cover;
      }

      /* Hamburger for mobile */
      .header-scope .sidenav-trigger {
        display: none;
        color: #fff;
        font-size: 30px;
      }

      /* Mobile brand centering */
      @media (max-width: 992px) {

        .header-scope .sidenav-trigger {
          display: inline-block;
          position: absolute;
          left: 16px;
        }

        .header-scope .nav-actions {
          display: none;
        }

        .header-scope .nav-ctr {
          justify-content: center !important;
          position: relative;
        }

        .header-scope .brand {
          margin: 0 auto;
        }
      }

      /* ================= Sidenav ================= */
      .header-scope .sidenav {
        width: 270px;
        background: linear-gradient(180deg, #003d8a, #005fcc);
        padding-top: 0;
      }

      .header-scope .sidenav-header {
        text-align: center;
        padding: 28px 16px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.15);
      }

      .header-scope .sidenav-header img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.35);
        object-fit: cover;
      }

      .header-scope .sidenav-header .name {
        font-size: 1.15rem;
        font-weight: 600;
        color: #fff;
        margin-top: 10px;
      }

      .header-scope .sidenav-header .welcome {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.70);
      }

      .header-scope .sidenav li > a {
        color: #fff !important;
        font-weight: 600;
        padding-left: 20px;
        display: flex;
        gap: 12px;
        align-items: center;
      }

      /* Remove ripple / white flash */
      .header-scope .waves-ripple,
      .header-scope span[class*="waves-ripple"] {
        display: none !important;
        opacity: 0 !important;
      }

      /* ================= Modal ================= */
      .header-scope .modal { border-radius: 8px; }
      .header-scope .modal-content { padding: 24px 30px; }

      .header-scope .input-field input:focus {
        border-bottom: 2px solid var(--primary2) !important;
        box-shadow: 0 1px 0 0 var(--primary2) !important;
      }
      .header-scope .input-field input:focus + label {
        color: var(--primary2) !important;
      }

      .header-scope .input-field .prefix {
        color: var(--primary2) !important;
      }

      /* Eye icon */
      .header-scope .password-container { position: relative; }
      .header-scope .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary2);
        cursor: pointer;
      }

      .header-scope .login-btn {
        width: 100%;
        border-radius: 8px;
        background: linear-gradient(90deg, var(--primary1), var(--primary2));
        color: #fff;
        font-weight: 700;
      }

  /* ===== Login Modal Background Match Index ===== */
  .header-scope #loginModal {
    background: linear-gradient(90deg, var(--primary1), var(--primary2)) !important;
  }

  .header-scope #loginModal .modal-content {
    background: transparent !important;
    color: #fff !important;
  }

  /* make text/icons inside modal white */
  .header-scope #loginModal .input-field label,
  .header-scope #loginModal .input-field .prefix,
  .header-scope #loginModal h5 {
    color: #fff !important;
  }

  /* underline for input fields */
  .header-scope #loginModal input {
    border-bottom: 1px solid rgba(255,255,255,0.6) !important;
    color: #fff !important;
  }

  .header-scope #loginModal input:focus {
    border-bottom: 2px solid #fff !important;
    box-shadow: 0 1px 0 0 #fff !important;
  }

  .header-scope #loginModal .toggle-password {
    color: #fff !important;
  }


</style>
</head>

<body>
  <div class="header-scope">

  <!-- ================== TOP NAV ================== -->
  <nav class="topbar">
    <div class="nav-wrapper nav-ctr">

      <a href="#" class="sidenav-trigger" data-target="mobile-sidenav">
        <i class="material-icons">menu</i>
      </a>

      <a href="index.php" class="brand">
        <i class="material-icons">flight_takeoff</i> TOURS
      </a>

      <div class="nav-actions">
        <?php if($acc_role  === 'admin'): ?>
        <a href="admin.php" class="btn-ticket"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a>
        <?php else: ?>
        <a href="takequiz.php" class="btn-ticket"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a>
        <?php endif; ?>

        <?php if (!empty($_SESSION['acc_id'])): ?>
            <a href="students_edit.php" class="hdr-profile" title="Profile">
              <img src="<?php echo $sn_avatar_html; ?>" alt="avatar">
              <span class="name"><?php echo $sn_name_html; ?></span>
            </a>
            <a href="logout.php" class="login-trigger">Logout</a>
        <?php else: ?>
            <a class="login-trigger modal-trigger" href="#loginModal">Log In</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ================== SIDENAV ================== -->
  <ul id="mobile-sidenav" class="sidenav">

    <li class="sidenav-header">
      <img src="<?php echo $sn_avatar_html; ?>" alt="avatar">
      <div class="name"><?php echo $sn_name_html; ?></div>
      <div class="welcome">Welcome</div>
    </li>

    <li><a href="index.php"><i class="material-icons">home</i>Home</a></li>
    <li><a href="takequiz.php"><i class="material-icons">airplane_ticket</i>Get Your Ticket</a></li>

    <?php if (!empty($_SESSION['acc_id'])): ?>
      <li><a href="students_edit.php"><i class="material-icons">person</i>Profile</a></li>
      <li><a href="logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
    <?php else: ?>
      <li><a href="#loginModal" class="modal-trigger"><i class="material-icons">login</i>Log In</a></li>
    <?php endif; ?>
  </ul>

  <!-- ================== LOGIN MODAL ================== -->
<!-- ================== LOGIN MODAL ================== -->
<div id="loginModal" class="modal">
  <div class="modal-content" style="position:relative;">

    <!-- BACK BUTTON (Top-left) -->
    <button 
      type="button" 
      id="loginBackBtn"
      style="
        position:absolute;
        top:10px;
        left:10px;
        background:transparent;
        border:none;
        color:white;
        display:flex;
        align-items:center;
        font-weight:600;
        cursor:pointer;
        padding:4px 6px;
      "
    >
      <i class="material-icons" style="margin-right:4px;">arrow_back</i>
      Back
    </button>

    <h5 class="center" style="margin-top:0;">Log In</h5>

    <form id="loginForm" autocomplete="off" novalidate>
      <div class="input-field">
        <i class="material-icons prefix">person</i>
        <input type="text" name="acc_id" id="acc_id">
        <label for="acc_id">Account ID</label>
      </div>

      <div class="input-field password-container">
        <i class="material-icons prefix">lock</i>
        <input type="password" name="password" id="password">
        <label for="password">Password</label>
        <i class="material-icons toggle-password" id="togglePw">visibility</i>
      </div>

      <div id="err-general" class="red-text"></div>

      <button type="submit" class="btn login-btn">Log In</button>
    </form>
  </div>
</div>


</div> <!-- /header-scope -->

<!-- ================== SCRIPTS ================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="materialize/js/materialize.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {

    // init sidenav + modal
    var sidenav = document.querySelectorAll('.sidenav');
    if (sidenav.length && window.M && M.Sidenav) M.Sidenav.init(sidenav, {edge:'left', draggable:true});

    var modals = document.querySelectorAll('.modal');
    if (modals.length && window.M && M.Modal) M.Modal.init(modals);

    // toggle password visibility
    var pw = document.getElementById("password");
    var toggle = document.getElementById("togglePw");
    if (pw && toggle) {
        toggle.addEventListener("click", function(){
            pw.type = pw.type === "password" ? "text" : "password";
            toggle.textContent = pw.type === "password" ? "visibility" : "visibility_off";
        });
    }

    // AJAX login
    var loginForm = document.getElementById("loginForm");
    if (loginForm) {
      loginForm.addEventListener("submit", function(e){
          e.preventDefault();

          var formData = new FormData(this);

          fetch("login.php", {
              method: "POST",
              body: formData,
              credentials: "same-origin",
              headers: { "Accept": "application/json" }
          })
          .then(function(r){ return r.text(); })
          .then(function(t){
              try {
                  var data = JSON.parse(t);
              } catch (err) {
                  console.error("Login response is not JSON:", t);
                  return;
              }
              if (!data.success) {
                  var err = document.getElementById("err-general");
                  if (err) err.textContent = data.error || "Login failed.";
                  return;
              }
              window.location.href = data.redirect;
          })
          .catch(function(err){ console.error("Login fetch error:", err); });
      });
    }
});


// Back button inside login modal
var backBtn = document.getElementById("loginBackBtn");
if (backBtn) {
    backBtn.addEventListener("click", function () {
        try {
            var instance = M.Modal.getInstance(document.getElementById("loginModal"));
            if (instance) instance.close();
        } catch (e) {}

        // redirect to home
        window.location.href = "index.php";
    });
}

</script>

</body>
</html>

