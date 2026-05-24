<?php

session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/data_helpers.php';

$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$chartData = es_get_chart_data($conn, $userId);

$total_reports = 0;
$high_risk = 0;
$medium_risk = 0;
$safe_count = 0;

if (es_table_exists($conn, 'reports')) {
    $where = $userId ? "WHERE victim_id = $userId" : '';
    $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $where");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $total_reports = (int) $r['c'];
    }
    $and = $where ? ' AND' : ' WHERE';
    foreach (['HIGH' => &$high_risk, 'MEDIUM' => &$medium_risk, 'SAFE' => &$safe_count] as $level => &$var) {
        $levelEsc = mysqli_real_escape_string($conn, $level);
        $q2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $where$and risk_level = '$levelEsc'");
        if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) {
            $var = (int) $r2['c'];
        }
    }
}

$scopeLabel = $userId ? 'Your analytics' : 'Global analytics';
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
<body class="page-app page-admin">

<!-- Hexadecimal Animated Background -->
<div class="hex-background">
    <div class="hex-stream">3F2C1E8A 5B7D4E1F 9A6C2B3F 7E1D4A5C 2F8B6E9A 4C3F1D7E 5A9B2E6C 8F4A1B3D 6E5C9F2A 7B3E1C8D 4A6F2E9B 9C5D3A1E 2E7B4F6A 5C8A1F3B 7D4E6A2F</div>
    <div class="hex-stream">B2E9F4A7 1D5C8E3F 6A9B2E7C 4F1D8A5E 3C7E6F2B 9A4C1F8D 5B2E7A3F 8C6D4A1E 2F9B5C7E 6E3A1D4B 7F5C2A9B 4E8F1D6A 3B7C5E2F 9D4A6F1C 8E2B5A3D</div>
    <div class="hex-stream">7F3A1E8C 4B6D2F9A 5E1C7D3F 8A4E2B6C 1F9B5A7E 3D6F4C2A 8E5B1F7A 4A3C8D2E 9F1E5C6B 2D7B4A8F 6E3F1A9C 5A8C2E7B 1D4F6E3A 7C9A5B2F 4E1D8F6A</div>
    <div class="hex-stream">C4F2A81E 7D5B3F6A 2E9C4F1D 8A6E3C5F 1B7A2E9D 4F5C8A2B 6D1F3E7C 9B4A6E2D 5E8F1C3A 7B2A9F4D 1E6C5B8A 3F4D7E2C 8C9A1F5B 2A7D6E4F 5F1B3C8E</div>
    <div class="hex-stream">9E1F7C4B 5A3E8D2F 6C8A1B7E 4F2D5A9C 1D6B3F8E 7A4E2C5F 8F5B1A3D 6E9C2E7B 3C1F8A4D 5D7F2E9B 8B4A6C1E 2F9A5C3D 7E1D4F6B 4C8E2A5B 9F3E1C7D</div>
</div>

<!-- Alert Overlay Warnings -->
<div class="alert-overlay alert-breach">⚠ DATA BREACH</div>
<div class="alert-overlay alert-attack">CYBER ATTACK</div>
<div class="alert-overlay alert-failed">PROTECTION FAILED</div>
<div class="alert-overlay alert-safety">SYSTEM SAFETY COMPROMISED</div>

<!-- Glowing Highlight Boxes -->
<div class="glow-highlight glow-highlight-1"></div>
<div class="glow-highlight glow-highlight-2"></div>
<div class="glow-highlight glow-highlight-3"></div>

<!-- Grid Overlay -->
<div class="grid-overlay scanline-effect"></div>

<?php
if (!empty($_SESSION['user_id'])) {
    include __DIR__ . '/includes/nav_user.php';
} else {
    include __DIR__ . '/includes/nav_public.php';
}
?>

<main class="app-main container py-4 py-md-5">
    <h1 class="app-title mb-2"><?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="text-muted mb-4">Charts from live report dataset — no manual sample values.</p>

    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="app-panel app-stat-block">
                <h2 class="app-subtitle h6 mb-2">Total reports</h2>
                <p class="app-metric mb-0"><?php echo $total_reports; ?></p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="app-panel app-stat-block admin-stat-card--danger">
                <h2 class="app-subtitle h6 mb-2">High risk</h2>
                <p class="app-metric mb-0 text-danger-accent"><?php echo $high_risk; ?></p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="app-panel app-stat-block">
                <h2 class="app-subtitle h6 mb-2">Medium risk</h2>
                <p class="app-metric mb-0"><?php echo $medium_risk; ?></p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="app-panel app-stat-block">
                <h2 class="app-subtitle h6 mb-2">Safe</h2>
                <p class="app-metric mb-0"><?php echo $safe_count; ?></p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Severity breakdown</h2></div>
                <div class="chart-card"><canvas id="chartHarassmentTypes"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Daily reports</h2></div>
                <div class="chart-card"><canvas id="chartDailyReports"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Weekly activity</h2></div>
                <div class="chart-card"><canvas id="chartWeeklyThreats"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Safe vs toxic</h2></div>
                <div class="chart-card"><canvas id="chartSafeToxic"></canvas></div>
            </div>
        </div>
    </div>

    <?php if ($userId): ?>
    <p class="mt-4"><a href="<?php echo htmlspecialchars(es_url('dashboard.php#reports-records')); ?>" class="btn btn-export">View my report folder</a></p>
    <?php endif; ?>
</main>

<script>
window.ECHO_CHART_DATA = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo htmlspecialchars(es_url('js/script.js')); ?>"></script>
</body>
</html>
