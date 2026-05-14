<?php

session_start();

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if(isset($_POST['login'])){

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";

    $result = mysqli_query($conn, $sql);

    $user = mysqli_fetch_assoc($result);

    if($user){

        if($user['status'] == "Suspended"){

            die("Your account has been suspended.");

        }

        if(password_verify($password, $user['password'])){

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: ' . es_url('dashboard.php'));
            exit;

        }else{

            echo "Wrong Password";
        }

    }else{

        echo "User Not Found";
    }
}

?>