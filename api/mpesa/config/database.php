<?php

$host = "localhost";
$db   = "mixbill";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {

    die("Database Connection Failed");

}

$conn->set_charset("utf8");