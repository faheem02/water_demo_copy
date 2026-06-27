<?php
$conn = mysqli_connect("localhost", "root", "", "water_supply_system");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

$software_name = "Water Supply System";
$company_name = "I pure drinking water";
$owner_name = "Muhammad Usman";
$owner_address = "House no 99 C block Gulshan iqbal Faisalabad";
$owner_phone = "03007769500";
?>