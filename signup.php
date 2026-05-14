<?php
require_once __DIR__ . '/includes/assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>Sign Up — EchoShield AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-auth">

<div class="auth-wrap container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="auth-card">
                <h2 class="text-center">Sign Up</h2>
                <form action="signup_process.php" method="POST">
                    <input type="text" name="full_name" class="form-control mb-3" placeholder="Full Name" required autocomplete="name">
                    <input type="text" name="username" class="form-control mb-3" placeholder="Username" required autocomplete="username">
                    <input type="email" name="email" class="form-control mb-3" placeholder="Email" required autocomplete="email">
                    <input type="text" name="phone" class="form-control mb-3" placeholder="Phone Number" autocomplete="tel">
                    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required autocomplete="new-password">
                    <button type="submit" name="signup" class="btn btn-auth-primary w-100">Create Account</button>
                </form>
                <div class="text-center">
                    <a class="auth-back" href="<?php echo htmlspecialchars(es_url('index.php')); ?>">← Back to home</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
