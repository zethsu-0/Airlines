<?php
    $conn = mysqli_connect('localhost', 'Pizzatime', 'pass123' , 'ninja_pizza');

    if(!$conn){
        echo 'Connection error: ' . mysqli_connect_error();
    }
?>