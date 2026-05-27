<?php

session_start();

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if (isset($_POST['login'])) {

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conn, 'SELECT id, username, password, status FROM users WHERE email = ? LIMIT 1');
    if ($stmt === false) {
        die('Database error.');
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($user) {

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