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
$success_message = '';
$error_message = '';

$errors = [
    'origin' => '',
    'destination' => ''
];

if (isset($_POST['submit'])) {
    $origin = strtoupper(trim($_POST['origin']));
    $destination = strtoupper(trim($_POST['destination']));

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

        // ✅ Insert into DB only if at least one valid code exists
        if ($result_origin || $result_destination) {
            $stmt = $conn->prepare("INSERT INTO submitted_flights (origin_code, origin_airline, destination_code, destination_airline) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $origin, $origin_airline, $destination, $destination_airline);
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
    <title>Airline Route</title>
</head>
<?php include('templates/header.php') ?>

<body>
    <div class="bg-container container center">
        <form action="index.php" method="POST" autocomplete="off">
            <div class="row">
                <div class="col s4 md3">
                    <label>ORIGIN</label>
                    <input type="text" name="origin" value="<?php echo htmlspecialchars($origin); ?>">
                    <div class="red-text"><?php echo $errors['origin']; ?></div>
                </div>

                <div class="col s4 md3">
                    <label>DESTINATION</label>
                    <input type="text" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
                    <div class="red-text"><?php echo $errors['destination']; ?></div>
                </div>

                <div class="col s4 md3">
                    <div class="center submitbtn">
                        <input type="submit" name="submit" value="Submit" class="btn brand z-depth-0">
                        <input type="submit" name="clear" value="Clear" class="btn grey z-depth-0">
                    </div>
                </div>
            </div>
        </form>

    </div>

    <!-- <?php if (isset($_POST['submit'])): ?>
        <div class="container center">
            <?php if ($error){ ?>
                <div class="card-panel red lighten-3">
                    <span style="color: black; font-weight: bold;"><?php echo $error; ?></span>
                </div>
            <?php }else{ ?>
                <h3>YOU'RE GOING TO:</h3>
                <h4><?php echo htmlspecialchars($destination_city) . ', ' .htmlspecialchars($destination_country); ?></h4>
                <h3 style="color: green;"><?php echo htmlspecialchars($destination_airline); ?></h3>
                <h3>FROM:</h3>
                <h4><?php echo htmlspecialchars($origin_city) . ', ' .htmlspecialchars($origin_country); ?></h4>
                <h3 style="color: green;"><?php echo htmlspecialchars($origin_airline); ?></h3>
            <?php } ?>
        </div>
    <?php endif; ?> -->

</body>
<?php include('templates/footer.php') ?>
</html>
<style type="text/css">
    label {
        font-size: 25px;
        font-weight: bold;
        color: black;
    }
    .submitbtn {
        padding-top: 20px;
    }
    .btn{
        font-weight: bold;
        font-size: 20px;
        color: black;
        background-color: transparent;
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
    }
</style>
