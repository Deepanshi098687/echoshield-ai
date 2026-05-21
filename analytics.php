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
<body class="page-dashboard">

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
