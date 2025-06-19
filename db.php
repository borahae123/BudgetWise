<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "budgetwise"; // 👈 updated to your DB name

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
