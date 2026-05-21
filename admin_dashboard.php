<?php

session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/data_helpers.php';

$allReports = es_get_all_reports($conn, 200);
$chartData = es_get_chart_data($conn, null);

$totalReports = count($allReports);
$highRisk = 0;
$mediumRisk = 0;
$usersCount = 0;

if (es_table_exists($conn, 'reports')) {
    $h = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE risk_level = 'HIGH'");
    if ($h && ($r = mysqli_fetch_assoc($h))) {
        $highRisk = (int) $r['c'];
    }
    $m = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE risk_level = 'MEDIUM'");
    if ($m && ($r = mysqli_fetch_assoc($m))) {
        $mediumRisk = (int) $r['c'];
    }
}

$u = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM users');
if ($u && ($r = mysqli_fetch_assoc($u))) {
    $usersCount = (int) $r['c'];
}

function admin_badge(string $risk): string
{
    $risk = strtoupper($risk);
    if ($risk === 'HIGH') {
        return 'badge-danger';
    }
    if ($risk === 'MEDIUM') {
        return 'badge-warning';
    }
    return 'badge-purple';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>Admin — EchoShield AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-dashboard page-admin">

<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="admin-main container-fluid py-4">
    <header class="admin-hero mb-4">
        <div>
            <span class="hero-badge">ADMIN COMMAND CENTER</span>
            <h1 class="admin-title">System breach monitor</h1>
            <p class="admin-lead">All user reports · live threat analytics · offender tracking</p>
        </div>
        <div class="admin-hero-stats row g-3">
            <div class="col-6 col-md-3">
                <article class="admin-stat-card">
                    <span>Total reports</span>
                    <strong><?php echo $totalReports; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="admin-stat-card admin-stat-card--danger">
                    <span>High risk</span>
                    <strong class="text-danger-accent"><?php echo $highRisk; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="admin-stat-card">
                    <span>Medium risk</span>
                    <strong><?php echo $mediumRisk; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="admin-stat-card">
                    <span>Users</span>
                    <strong><?php echo $usersCount; ?></strong>
                </article>
            </div>
        </div>
    </header>

    <section class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Risk distribution</h2></div>
                <div class="chart-card"><canvas id="adminChartSeverity"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Daily reports (7d)</h2></div>
                <div class="chart-card"><canvas id="adminChartDaily"></canvas></div>
            </div>
        </div>
    </section>

    <section class="panel glass-panel reports-panel">
        <div class="panel-header report-header">
            <div>
                <h2>Global report records</h2>
                <p>Every submission from all users — same data as user folders.</p>
            </div>
            <a href="<?php echo htmlspecialchars(es_url('analytics.php')); ?>" class="btn btn-export">Analytics</a>
        </div>
        <div class="table-responsive terminal-table-wrap">
            <table class="table table-dark table-borderless align-middle mb-0 terminal-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Harasser</th>
                        <th>Message</th>
                        <th>Toxicity</th>
                        <th>Severity</th>
                        <th>Risk</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allReports) > 0): ?>
                        <?php foreach ($allReports as $row): ?>
                            <?php $risk = strtoupper((string) ($row['risk_level'] ?? 'SAFE')); ?>
                            <tr>
                                <td>RPT-<?php echo (int) $row['report_id']; ?></td>
                                <td>@<?php echo htmlspecialchars((string) ($row['username'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['harasser_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="report-msg-cell"><?php echo htmlspecialchars(mb_substr((string) $row['harmful_message'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>…</td>
                                <td><?php echo htmlspecialchars((string) $row['toxicity_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge badge-pill <?php echo admin_badge((string) $row['severity_level']); ?>"><?php echo htmlspecialchars((string) $row['severity_level'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="badge badge-pill <?php echo admin_badge($risk); ?>"><?php echo htmlspecialchars($risk, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M d H:i', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a class="btn btn-sm btn-app-danger" href="<?php echo htmlspecialchars(es_url('suspend.php?id=' . (int) $row['victim_id']), ENT_QUOTES, 'UTF-8'); ?>">Suspend user</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No reports in database yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
window.ECHO_CHART_DATA = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo htmlspecialchars(es_url('js/script.js')); ?>"></script>
</body>
</html>
