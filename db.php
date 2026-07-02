<?php
$conn = mysqli_connect("localhost", "root", "", "complaint_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>