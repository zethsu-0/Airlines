<head>
  <title>TOURS</title>
  <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

<style type="text/css">
  html, body { margin: 0; padding: 0; background-color: white; }

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
  
  /* header profile small avatar */
  .hdr-profile { display:flex; align-items:center; gap:8px; }
  .hdr-profile img { width:28px; height:28px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.15); }
  .hdr-profile .name { font-weight:500; color:inherit; white-space:nowrap; }
</style>
</head>

<nav class="blue">
  <div class="nav-wrapper">
    <a href="index.php" class="brand-logo center">
      <i class="material-icons hide-on-med-and-down">flight_takeoff</i>TOURS
    </a>

    <ul class="right hide-on-med-and-down" id="nav-right">
      <li><a class="btn wave-effect wave-light blue" href="takequiz.php"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a></li>

      <?php
      // ensure session is active
      if (session_status() !== PHP_SESSION_ACTIVE) session_start();

      // default avatar
      $default_avatar = 'assets/avatar.png';

      if (!empty($_SESSION['acc_id'])):
        // show compact header link to students_edit.php (avatar + name)
        $acc_id = (string)$_SESSION['acc_id'];
        $acc_name = $_SESSION['acc_name'] ?? 'Student';
        $avatar = $default_avatar;

        // try to fetch avatar from airlines.students (student_id)
        try {
          $dbHost = 'localhost'; $dbUser = 'root'; $dbPass = '';
          $conn = new mysqli($dbHost, $dbUser, $dbPass, 'airlines');
          $conn->set_charset('utf8mb4');
          $stmt = $conn->prepare("SELECT avatar FROM students WHERE student_id = ? LIMIT 1");
          if ($stmt) {
            $stmt->bind_param('s', $acc_id);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($row = $r->fetch_assoc()) {
              if (!empty($row['avatar'])) $avatar = $row['avatar'];
            }
            $stmt->close();
          }
          $conn->close();
        } catch (Exception $e) {
          // ignore DB errors, fall back to default avatar
        }

        // sanitize
        $acc_name_html = htmlspecialchars($acc_name, ENT_QUOTES);
        $avatar_html = htmlspecialchars($avatar, ENT_QUOTES);
      ?>
        <li style="display:flex;align-items:center;">
          <a href="students_edit.php" class="hdr-profile" title="Profile - edit">
            <img src="<?php echo $avatar_html; ?>" alt="avatar">
            <span class="name"><?php echo $acc_name_html; ?></span>
          </a>
        </li>
        <li><a href="logout.php">Logout</a></li>
      <?php else: ?>
        <li id="loginLi">
          <a class="waves-effect waves-light btn blue modal-trigger" href="#loginModal" id="loginBtn">
            <i class="material-icons left">login</i>Log In
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<!-- LOGIN MODAL (unchanged) -->
<div id="loginModal" class="modal">
  <div class="modal-content">
    <h4 class="center">
      <a href="index.php"><i class="modal-close material-icons left">arrow_back</i></a>
      Log In
    </h4>

    <form id="loginForm" method="POST" action="login.php" autocomplete="off" novalidate>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
// LOGIN HANDLER (AJAX for modal)
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("loginForm");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault(); // prevent normal form submit

        // Clear old error messages
        document.getElementById("err-acc_id").textContent = "";
        document.getElementById("err-password").textContent = "";
        document.getElementById("err-general").textContent = "";

        const formData = new FormData(form);

        fetch("login.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {

                // Show error inside modal
                document.getElementById("err-general").textContent = data.error || "Login failed.";
                if (window.M && M.toast)
                    M.toast({html: data.error, classes:"red"});

                return;
            }

            // SUCCESS → redirect based on role
            window.location.href = data.redirect;
        })
        .catch(err => {
            document.getElementById("err-general").textContent = "Network error: " + err;
            if (window.M && M.toast)
                M.toast({html: "Network error: " + err, classes:"red"});
        });
    });
});
</script>


<script>
  $(document).ready(function(){
    $('.modal').modal();
  });
</script>
