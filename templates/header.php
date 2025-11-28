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
  

</style>
</head>

<nav class="blue">
  <div class="nav-wrapper">
    <a href="index.php" class="brand-logo center">
      <i class="material-icons hide-on-med-and-down">flight_takeoff</i>TOURS
    </a>

    <ul class="right hide-on-med-and-down" id="nav-right">
      <li><a class="btn wave-effect wave-light blue" href="ticket.php"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a></li>

      <?php if (!empty($_SESSION['acc_id'])): ?>
        <!-- Logged-in view -->
        <li id="userMenu">
          <a class="waves-effect waves-light" href="index.php">
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


</nav>

<!-- LOGIN MODAL -->
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
  $(document).ready(function(){
    $('.modal').modal();  
  });

</script>