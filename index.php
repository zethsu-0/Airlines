
<?php
$conn = mysqli_connect('localhost', 'root', '', 'airlines');

if (!$conn) {
    die('Connection error: ' . mysqli_connect_error());
}

$origin = '';
$destination = '';
$origin_airline = '';
$destination_airline = '';
$origin_city = '';
$destination_city = '';
$origin_country = '';
$destination_country = '';
$flight_date = '';
$success_message = '';
$error_message = '';

$errors = [
    'origin' => '',
    'destination' => ''
];

if (isset($_POST['submit'])) {
    $origin = strtoupper(trim($_POST['origin']));
    $destination = strtoupper(trim($_POST['destination']));
    $flight_date = trim($_POST['flight_date']);

    // ✅ Validate Origin input
    if (empty($origin)) {
        $errors['origin'] = 'Origin code is required.';
    } elseif (!preg_match('/^[A-Z]{3}$/', $origin)) {
        $errors['origin'] = 'Origin must be a valid IATA code (3 uppercase letters).';
    }

    // ✅ Validate Destination input
    if (empty($destination)) {
        $errors['destination'] = 'Destination code is required.';
    } elseif (!preg_match('/^[A-Z]{3}$/', $destination)) {
        $errors['destination'] = 'Destination must be a valid IATA code (3 uppercase letters).';
    }

    if (empty($flight_date)) {
    $errors['flight_date'] = 'Departure date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
        $errors['flight_date'] = 'Invalid date format.';
    }

    // ✅ Continue only if no input validation errors
    if (!array_filter($errors)) {

        // Check origin in DB
        $sql_origin = "SELECT AirportName, City, CountryRegion FROM airports WHERE IATACode = '$origin' LIMIT 1";
        $result_origin = $conn->query($sql_origin);

        if ($result_origin && $result_origin->num_rows > 0) {
            $row = $result_origin->fetch_assoc();
            $origin_city = $row['City'];
            $origin_airline = $row['AirportName'];
            $origin_country = $row['CountryRegion'];
        } else {
            $origin_airline = "Invalid code ($origin)";
            $error_message .= " Origin code not found.";
        }

        // Check destination in DB
        $sql_destination = "SELECT AirportName, City, CountryRegion FROM airports WHERE IATACode = '$destination' LIMIT 1";
        $result_destination = $conn->query($sql_destination);

        if ($result_destination && $result_destination->num_rows > 0) {
            $row = $result_destination->fetch_assoc();
            $destination_city = $row['City'];
            $destination_airline = $row['AirportName'];
            $destination_country = $row['CountryRegion'];
        } else {
            $destination_airline = "Invalid code ($destination)";
            $error_message .= " Destination code not found.";
        }

        if ($result_origin || $result_destination) {
            $stmt = $conn->prepare("INSERT INTO submitted_flights (origin_code, origin_airline, destination_code, destination_airline, flight_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $origin, $origin_airline, $destination, $destination_airline, $flight_date);
            $stmt->execute();
            $stmt->close();
            $success_message = "Flight submitted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if (isset($_POST['clear'])) {
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$conn->close();
?>



<!DOCTYPE html>
<html>
<?php include('templates/header.php'); ?>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/index.css">
  <title>Travel Booking</title>
</head>

<body>

  <!-- Hero Section -->
  <section class="center-align">
    <img src="assets/island2.jpg" alt="Island" class="responsive-img">
  </section>

  <!-- Booking Form Card -->
  <div class="bg-container container center">
    <form action="index.php" method="POST" autocomplete="off" class="card">
      <div class="row">
        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_takeoff</i>
            <input type="text" name="origin" class="center" value="<?php echo htmlspecialchars($origin); ?>">
            <div class="red-text"><?php echo $errors['origin']; ?></div>
            <label for="origin">ORIGIN</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="input-field">
            <i class="material-icons prefix">flight_land</i>
            <input type="text" name="destination" class="center" value="<?php echo htmlspecialchars($destination); ?>">
            <div class="red-text"><?php echo $errors['destination']; ?></div>
            <label for="destination">DESTINATION</label>
          </div>
        </div>

        <div class="col s3 md3">
          <div class="center">
            <div class="input-field">
              <i class="material-icons prefix">calendar_today</i>
              <input type="text" id="flight-date" name="flight_date" class="datepicker-input" readonly>
              <label for="flight-date">DEPARTURE</label>
              <div class="red-text"><?php echo $errors['flight_date'] ?? ''; ?></div>
            </div>
          </div>
        </div>

        <div class="col s3 md3 submitbtn">
          <div class="center">
            <input type="submit" name="submit" value="Submit" class="btn brand z-depth-0">
          </div>
        </div>
      </div>
    </form>
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
                <img src="<?php echo $dest[2]; ?>" alt="<?php echo $dest[0]; ?>">
                <div class="country-label"><?php echo $dest[0]; ?></div>
                <div class="card-reveal-overlay">
                  <div class="reveal-content">
                    <div class="card-title"><?php echo $dest[0]; ?> <span class="close-reveal">✕</span></div>
                    <p><strong>Location:</strong> <?php echo $dest[1]; ?></p>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>
    </div>
  </section>

  <!-- Info Section -->
  <div class="container">
    <h6>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat...</h6>
  </div>

  <!-- Materialize JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const carouselElems = document.querySelectorAll('.carousel');
      M.Carousel.init(carouselElems, { indicators: false, dist: -50, padding: 20 });

      const carouselElem = document.querySelector('.carousel');
      if (!carouselElem) return;

      const instance = M.Carousel.getInstance(carouselElem);
      const items = [...carouselElem.querySelectorAll('.carousel-item')];
      const cards = [...carouselElem.querySelectorAll('.destination-card')];

      const hideAll = () => document.querySelectorAll('.card-reveal-overlay.active')
        .forEach(o => o.classList.remove('active'));

      cards.forEach((card, index) => {
        const overlay = card.querySelector('.card-reveal-overlay');
        const closeBtn = card.querySelector('.close-reveal');

        card.querySelector('img')?.addEventListener('click', e => {
          e.stopPropagation();
          hideAll();
          instance.set(index);
          setTimeout(() => overlay?.classList.add('active'), 400);
        });

        closeBtn?.addEventListener('click', e => {
          e.stopPropagation();
          overlay?.classList.remove('active');
        });
      });

      let last = -1;
      setInterval(() => {
        const cr = carouselElem.getBoundingClientRect();
        const cx = cr.left + cr.width / 2;
        const idx = items.reduce((b, it, i) => {
          const r = it.getBoundingClientRect();
          const d = Math.abs((r.left + r.width / 2) - cx);
          return d < b[0] ? [d, i] : b;
        }, [Infinity, 0])[1];
        if (idx !== last) { hideAll(); last = idx; }
      }, 150);

      ['touchstart', 'mousedown', 'click'].forEach(evt => {
        carouselElem.addEventListener(evt, e => {
          if (!e.target.closest('.destination-card')) hideAll();
        }, { passive: true });
      });

      if (typeof flatpickr !== "undefined") {
        flatpickr("#flight-date", {
          dateFormat: "Y-m-d",
          altFormat: "F j",
          minDate: "today",
          allowInput: false,
          onReady: function () { M.updateTextFields(); }
        });
      }
    });
  </script>

  <?php include('templates/footer.php'); ?>

</body>
</html>
