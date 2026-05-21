<?php

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$total_reports =
mysqli_num_rows(
mysqli_query($conn,
"SELECT * FROM reports")
);

$high_risk =
mysqli_num_rows(
mysqli_query($conn,
"SELECT * FROM reports
WHERE risk_level='HIGH'")
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>Analytics — EchoShield AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-app">

<?php
if (!empty($_SESSION['user_id'])) {
    include __DIR__ . '/includes/nav_user.php';
} else {
    include __DIR__ . '/includes/nav_public.php';
}
?>

<main class="app-main container py-4 py-md-5">
    <h1 class="app-title mb-4">Safety analytics</h1>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="app-panel app-stat-block">
                <h2 class="app-subtitle h5 mb-2">Total reports</h2>
                <p class="app-metric mb-0"><?php echo (int) $total_reports; ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="app-panel app-stat-block">
                <h2 class="app-subtitle h5 mb-2">High risk cases</h2>
                <p class="app-metric mb-0"><?php echo (int) $high_risk; ?></p>
            </div>
        </div>
    </div>
</main>

</body>
</html>
