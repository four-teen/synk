<?php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbase      = "synk_db";

$conn = mysqli_connect($servername, $username, $password, $dbase);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// PH Timezone
date_default_timezone_set("Asia/Manila");
?>
