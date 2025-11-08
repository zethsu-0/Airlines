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
		<?php include('templates/header.php');?>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
	<section class="center-align">
  <img src="assets/island2.jpg" alt="Island" class="responsive-img">
	</section>


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


<section class="hero-carousel" style="background-image: url('assets/island.jpg');">
  <div class="overlay-bg">
    <div class="container">
      <h4 class="center-align white-text">Places, You🫵 wanna to Visit</h4>

      <div class="carousel">

      <!-- Philippines -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Boracay, Philippines">
          <div class="country-label">Philippines</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Philippines <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Boracay Island</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Singapore -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Singapore">
          <div class="country-label">Singapore</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Singapore <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Marina Bay Sands</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Malaysia -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Malaysia">
          <div class="country-label">Malaysia</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Malaysia <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Petronas Towers, Kuala Lumpur</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Thailand -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Thailand">
          <div class="country-label">Thailand</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Thailand <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Phuket Island</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Vietnam -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Vietnam">
          <div class="country-label">Vietnam</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Vietnam <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Ha Long Bay</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Indonesia -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Indonesia">
          <div class="country-label">Indonesia</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Indonesia <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Bali</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Brunei -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Brunei">
          <div class="country-label">Brunei</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Brunei <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Omar Ali Saifuddien Mosque</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Cambodia -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Cambodia">
          <div class="country-label">Cambodia</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Cambodia <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Angkor Wat</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Laos -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Laos">
          <div class="country-label">Laos</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Laos <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Luang Prabang</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Myanmar -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Myanmar">
          <div class="country-label">Myanmar</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Myanmar <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Bagan Temples</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Timor-Leste -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Timor-Leste">
          <div class="country-label">Timor-Leste</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Timor-Leste <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Cristo Rei, Dili</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Japan -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Japan">
          <div class="country-label">Japan</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Japan <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Tokyo Tower</p>
            </div>
          </div>
        </div>
      </div>

      <!-- South Korea -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="South Korea">
          <div class="country-label">South Korea</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">South Korea <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Gyeongbokgung Palace, Seoul</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Taiwan -->
      <div class="carousel-item">
        <div class="destination-card">
          <img src="assets/island.jpg" alt="Taiwan">
          <div class="country-label">Taiwan</div>
          <div class="card-reveal-overlay">
            <div class="reveal-content">
              <div class="card-title">Taiwan <span class="close-reveal">✕</span></div>
              <p><strong>Location:</strong> Taipei 101</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>





	

	<div class="container">	<h6>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
	tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
	quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
	consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
	cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
	proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</h6>
	</div>

	<?php include('templates/footer.php');?>
</body>
</html>
