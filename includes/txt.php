<?php
$conn = mysqli_connect("localhost", "root", "", "water_supply_system");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

$software_name = "Water Supply System";
$company_name = "Aquize Water";
$owner_name = "Fawaz Javid";
$owner_address = "Shop no.1 J2 market Wapdatown Lahore";
$owner_phone = "03084334856";
?>