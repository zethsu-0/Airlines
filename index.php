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

    if (empty($errors['origin']) && empty($errors['destination'])) {
    if ($origin === $destination) {
        $errors['destination'] = 'Origin and destination cannot be the same.';
    }
    }

    if (empty($flight_date)) {
    $errors['flight_date'] = 'Departure date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date)) {
        $errors['flight_date'] = 'Invalid date format.';
    }

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
            $origin = '';
            $destination = '';
            $flight_date = '';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Airline Route</title>
</head>
<?php include('templates/header.php') ?>

<body>
    <div class="bg-container container center">
        <form action="index.php" method="POST" autocomplete="off" class="card">
            <div class="row">
                <div class="col s3 md3">
                        <div class="input-field">
                        <i class="material-icons prefix">pin_drop</i>
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
    
</body>
<?php include('templates/footer.php') ?>
</html>


<style type="text/css">
    label{
        font-size: 200px;
        font-weight: bold;
        color: black;
        letter-spacing: 5px;
    }
    .btn{
        font-weight: bold;
        font-size: 20px;
        color: black;
        background-color: transparent;
    }.submitbtn{
        padding-top: 20px !important;
    }
    h2, h3{
        font-weight: bold;
    }
    body{
        background-color: gray;
    }
    input[type="text"] {
        text-transform: uppercase;
    }
    .bg-container{
        padding: 20px;
        width: 90%;
    }  

    .flatpickr-current-month {
    position: relative;
    z-index: 10;
    display: flex !important;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    
    }

    select.flatpickr-monthDropdown-months {
    display: inline-block !important;
    position: relative;
    z-index: 11;
    background: transparent;
    color: #1976d2;
    font-weight: bold;
    border: none;
    font-size: 1rem;
    text-transform: capitalize;
    }

    .flatpickr-current-month .numInputWrapper {
    display: inline-flex !important;
    align-items: center;
    position: relative;
    z-index: 11;
    background: transparent;
    margin-left: 0.2rem;
    width: 9ch;
    }

    .flatpickr-current-month input.cur-year {
    display: inline-block !important;
    color: #1976d2;
    font-weight: bold;
    border: none;
    background: transparent;
    text-align: center;
    }

    /* Inner container shouldn't overlap header */
    .flatpickr-innerContainer {
    position: relative;
    z-index: 1;
    }

    .btn:hover, .btn-large:hover, .btn-small:hover{
        background-color: #4993deff;
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const dateInput = document.getElementById("flight-date");

  // Initialize Flatpickr and save the instance
  const fp = flatpickr(dateInput, {
    dateFormat: "Y-m-d",
    altFormat: "F j",
    minDate: "today",
    allowInput: false,
    disableMobile: true,
    onReady: function() {
      M.updateTextFields();
    }
  });

  // ✅ Reapply previously entered date if validation failed
  const existingDate = "<?php echo isset($flight_date) ? $flight_date : ''; ?>";
  if (existingDate) {
    fp.setDate(existingDate, true);
  }

  // ✅ Add red border if PHP detected a flight_date error
  const hasError = "<?php echo !empty($errors['flight_date']) ? 'true' : 'false'; ?>";
  if (hasError === "true") {
    dateInput.classList.add("invalid");
  }
});
</script>