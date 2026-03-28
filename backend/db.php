<?php

$servername = "localhost";
$username   = "root";
$password   = "vertrigo";
$dbase      = "synk_db";

$conn = mysqli_connect($servername, $username, $password, $dbase);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    die("Error setting database charset: " . mysqli_error($conn));
}

// PH Timezone
date_default_timezone_set("Asia/Manila");
?>
