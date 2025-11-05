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

$error = '';

if (isset($_POST['submit'])) {
    $origin = trim($_POST['origin']);
    $destination = trim($_POST['destination']);

    $origin = strtoupper($_POST['origin']);
    $destination = strtoupper($_POST['destination']);

    $sql_origin = "SELECT AirportName, City, CountryRegion FROM airports WHERE IATACode  = '$origin' LIMIT 1";
    $result_origin = $conn->query($sql_origin);

    if ($result_origin && $result_origin->num_rows > 0) {
        $row = $result_origin->fetch_assoc();
        $origin_city = $row['City'];
        $origin_airline = $row['AirportName'];
        $origin_country = $row['CountryRegion'];
    } else {
        $error .= " Origin code '$origin' not found in database.<br>";
    }


    $sql_destination = "SELECT AirportName, City,CountryRegion  FROM airports WHERE IATACode = '$destination' LIMIT 1";
    $result_destination = $conn->query($sql_destination);


    if ($result_destination && $result_destination->num_rows > 0) {
        $row = $result_destination->fetch_assoc();
        $destination_airline = $row['AirportName'];
        $destination_city = $row['City'];
        $destination_country = $row['CountryRegion'];
        $destination_found = true;
    } else {
        $error .= " Destination code '$destination' not found in database.<br>";
    }
    if (empty($error)) {
        $stmt = $conn->prepare("
            INSERT INTO submitted_flights (origin_code, destination_code, origin_airline, destination_airline)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $origin, $destination, $origin_airline, $destination_airline);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
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
<body>
    <div class="container center">
        <form action="index1.php" method="POST" autocomplete="off">
            <div class="row">
                <div class="col s4 md3">
                    <label>ORIGIN</label>
                    <input type="text" name="origin">
                </div>
                <div class="col s4 md3">
                    <label>DESTINATION</label>
                    <input type="text" name="destination">
                </div>
                <div class="col s4 md3">
                    <div class="center submitbtn">
                        <input type="submit" name="submit" value="submit" class="btn brand z-depth-0">
                        <input type="submit" name="clear" value="Clear" class="btn grey lighten-1 z-depth-0" style="margin-left:10px;">
    </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- <?php if (isset($_POST['submit'])): ?>
        <div class="container center">
            <?php if ($error): ?>
                <div class="card-panel red lighten-3">
                    <span style="color: black; font-weight: bold;"><?php echo $error; ?></span>
                </div>
            <?php else: ?>
                <h3>YOU'RE GOING TO:</h3>
                <h4><?php echo htmlspecialchars($destination_city) . ', ' .htmlspecialchars($destination_country); ?></h4>
                <h3 style="color: green;"><?php echo htmlspecialchars($destination_airline); ?></h3>
                <h3>FROM:</h3>
                <h4><?php echo htmlspecialchars($origin_city) . ', ' .htmlspecialchars($origin_country); ?></h4>
                <h3 style="color: green;"><?php echo htmlspecialchars($origin_airline); ?></h3>
            <?php endif; ?>
        </div>
    <?php endif; ?> -->

</body>
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
</style>
