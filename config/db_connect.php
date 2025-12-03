<?php
    $conn = mysqli_connect('localhost', 'root', '', 'airlines');

    if (!$conn) {
        die('Connection error: ' . mysqli_connect_error());
    }

    $sql = "SELECT * FROM accounts";
    $result = mysqli_query($conn, $sql);


?>  