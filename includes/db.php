<?php
session_start();
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "water_supply_system";

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Include text variables
require_once __DIR__ . '/txt.php';
?>