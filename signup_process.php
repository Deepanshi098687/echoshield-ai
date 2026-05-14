<?php

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if(isset($_POST['signup'])){

    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users
    (full_name, username, email, phone, password)

    VALUES
    ('$full_name', '$username', '$email', '$phone', '$password')";

    mysqli_query($conn, $sql);

    header('Location: ' . es_url('login.php'));
    exit;
}

?>