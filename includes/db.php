<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "echoshield_ai";

$conn = mysqli_connect($host, $user, $password, $database);

if(!$conn){
    die("Database Connection Failed");
}

?>