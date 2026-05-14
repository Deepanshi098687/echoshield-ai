<?php

session_start();

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . es_url('login.php'));
    exit;
}

$user_id = mysqli_real_escape_string($conn, (string) $_SESSION['user_id']);

$sql = "SELECT * FROM alerts
WHERE user_id='$user_id'
ORDER BY alert_id DESC";

$result = mysqli_query($conn, $sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>Alerts — EchoShield AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-app">

<?php include __DIR__ . '/includes/nav_user.php'; ?>

<main class="app-main container py-4 py-md-5">
    <h1 class="app-title mb-4">Notifications</h1>
    <?php
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<div class="alert app-alert-notify border-0 mb-3" role="alert">'
                . htmlspecialchars((string) $row['alert_message'], ENT_QUOTES, 'UTF-8')
                . '</div>';
        }
    } else {
        echo '<p class="text-muted app-muted">No alerts yet.</p>';
    }
    ?>
</main>

</body>
</html>
