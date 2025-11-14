<?php
    $conn = mysqli_connect('localhost', 'root', '', 'airlines');
    $acc_conn = mysqli_connect('localhost', 'root', '', 'account');

    if (!$conn) {
        die('Connection error: ' . mysqli_connect_error());
    }

    if (!$acc_conn) {
        die('Connection error: ' . mysqli_connect_error());
    }
    $sql = "SELECT * FROM accounts";
    $result = mysqli_query($acc_conn, $sql);


?>