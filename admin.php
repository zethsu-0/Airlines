<?php
$conn = mysqli_connect('localhost', 'root', '', 'airlines');

if (!$conn) {
    die('Connection error: ' . mysqli_connect_error());
}

$sql = "SELECT * FROM submitted_flights ORDER BY submitted_at DESC";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="refresh" content="5">
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
    <title>Admin - Submitted Flights</title>
</head>
<body>
    <div class="container">
        <h3 class="center">Flights Log</h3>

        <?php if ($result && $result->num_rows > 0): ?>
            <table class="striped centered highlight z-depth-1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Origin Code</th>
                        <th>Origin Airline</th>
                        <th>Destination Code</th>
                        <th>Destination Airline</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['origin_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['origin_airline']); ?></td>
                            <td><?php echo htmlspecialchars($row['destination_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['destination_airline']); ?></td>
                            <td><?php echo htmlspecialchars($row['submitted_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="center">
                <h5>No flights submitted yet.</h5>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>

<style type="text/css">
    body {
        background-color: #fafafa;
    }
    h3 {
        margin-top: 40px;
        font-weight: bold;
        color: #2c3e50;
    }
    table {
        margin-top: 30px;
    }
    th {
        background-color: #2196F3;
        color: white;
        font-weight: bold;
    }
    td {
        font-size: 16px;
    }
</style>

<?php $conn->close(); ?>
