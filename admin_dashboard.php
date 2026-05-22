<?php

session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/data_helpers.php';

$allReports = es_get_all_reports($conn, 200);
$chartData = es_get_chart_data($conn, null);
$adminStats = es_get_admin_stats($conn);
$topHarassers = es_get_top_harassers($conn, 10);
$recentAlerts = es_get_recent_alerts($conn, 12);
$usersOverview = es_get_users_overview($conn, 15);
$modelMeta = es_model_meta();

function admin_badge(string $risk): string
{
    $risk = strtoupper($risk);
    if ($risk === 'HIGH') {
        return 'badge-danger';
    }
    if ($risk === 'MEDIUM') {
        return 'badge-warning';
    }
    return 'badge-safe';
}

function admin_label_badge(string $label): string
{
    $label = strtolower(trim($label));
    if ($label === 'hatespeech') {
        return 'badge-danger';
    }
    if ($label === 'offensive') {
        return 'badge-warning';
    }
    return 'badge-safe';
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
<body class="page-app page-admin">

<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="admin-main container-fluid py-4">
    <header class="admin-hero mb-4">
        <div>
            <span class="hero-badge">ADMIN COMMAND CENTER</span>
            <h1 class="admin-title">System breach monitor</h1>
            <p class="admin-lead">hateXplain-trained AI · live report analytics · offender tracking</p>
        </div>
        <div class="admin-hero-stats row g-3">
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Total reports</span>
                    <strong><?php echo (int) $adminStats['total_reports']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card admin-stat-card--danger">
                    <span>High risk</span>
                    <strong class="text-danger-accent"><?php echo (int) $adminStats['high_risk']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Medium risk</span>
                    <strong><?php echo (int) $adminStats['medium_risk']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Safe</span>
                    <strong><?php echo (int) $adminStats['safe_count']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Users</span>
                    <strong><?php echo (int) $adminStats['users_count']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Offenders</span>
                    <strong><?php echo (int) $adminStats['offenders_count']; ?></strong>
                </article>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <article class="admin-stat-card">
                    <span>Alerts</span>
                    <strong><?php echo (int) $adminStats['alerts_count']; ?></strong>
                </article>
            </div>
        </div>
    </header>

    <section class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="panel glass-panel admin-model-panel">
                <div class="panel-header"><h2>AI model (trained)</h2></div>
                <ul class="admin-model-list">
                    <li><span>Dataset</span><strong><?php echo htmlspecialchars((string) ($modelMeta['dataset'] ?? 'hateXplain.csv'), ENT_QUOTES, 'UTF-8'); ?></strong></li>
                    <li><span>Architecture</span><strong><?php echo htmlspecialchars((string) ($modelMeta['model'] ?? 'TF-IDF + LogisticRegression'), ENT_QUOTES, 'UTF-8'); ?></strong></li>
                    <li><span>Labels</span><strong>normal · offensive · hatespeech</strong></li>
                    <li><span>Maps to</span><strong>SAFE · MEDIUM · HIGH</strong></li>
                    <li><span>Accuracy</span><strong><?php
                        $acc = $modelMeta['accuracy_percent'] ?? null;
                        echo $acc !== null ? htmlspecialchars((string) $acc, ENT_QUOTES, 'UTF-8') . '%' : 'Run train_model.py';
                    ?></strong></li>
                    <li><span>Engine status</span><strong class="<?php echo str_contains((string) $adminStats['ai_status'], 'ACTIVE') ? 'text-success-accent' : 'text-danger-accent'; ?>"><?php echo htmlspecialchars((string) $adminStats['ai_status'], ENT_QUOTES, 'UTF-8'); ?></strong></li>
                    <li><span>Threat load</span><strong class="text-danger-accent"><?php echo (int) $adminStats['threat_percent']; ?>% (<?php echo htmlspecialchars((string) $adminStats['threat_label'], ENT_QUOTES, 'UTF-8'); ?>)</strong></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Risk distribution</h2></div>
                <div class="chart-card"><canvas id="adminChartSeverity"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Model labels (dataset)</h2></div>
                <div class="chart-card"><canvas id="adminChartLabels"></canvas></div>
            </div>
        </div>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Safe vs toxic</h2></div>
                <div class="chart-card"><canvas id="adminChartSafeToxic"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Daily reports (7d)</h2></div>
                <div class="chart-card"><canvas id="adminChartDaily"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel glass-panel">
                <div class="panel-header"><h2>Weekly activity</h2></div>
                <div class="chart-card"><canvas id="adminChartWeekly"></canvas></div>
            </div>
        </div>
    </section>

    <section class="row g-4 mb-4">
        <?php if (count($topHarassers) > 0): ?>
        <div class="col-lg-6">
            <div class="panel glass-panel h-100">
                <div class="panel-header"><h2>Top tracked offenders</h2></div>
                <div class="table-responsive terminal-table-wrap">
                    <table class="table table-dark table-borderless align-middle mb-0 terminal-table">
                        <thead>
                            <tr>
                                <th>Harasser</th>
                                <th>Violations</th>
                                <th>First seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topHarassers as $offender): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $offender['harasser_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge badge-pill badge-danger"><?php echo (int) $offender['total_violations']; ?></span></td>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string) $offender['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-lg-<?php echo count($topHarassers) > 0 ? '6' : '12'; ?>">
            <div class="panel glass-panel h-100">
                <div class="panel-header"><h2>System alerts</h2></div>
                <div class="admin-alert-list">
                    <?php if (count($recentAlerts) > 0): ?>
                        <?php foreach ($recentAlerts as $alert): ?>
                        <article class="admin-alert-item">
                            <span class="admin-alert-time"><?php echo htmlspecialchars(date('M d H:i', strtotime((string) $alert['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            <p><strong>@<?php echo htmlspecialchars((string) ($alert['username'] ?? 'user'), ENT_QUOTES, 'UTF-8'); ?></strong> — <?php echo htmlspecialchars((string) $alert['alert_message'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No alerts yet. HIGH risk reports trigger automatic alerts.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if (count($usersOverview) > 0): ?>
    <section class="panel glass-panel mb-4">
        <div class="panel-header"><h2>Registered users</h2></div>
        <div class="table-responsive terminal-table-wrap">
            <table class="table table-dark table-borderless align-middle mb-0 terminal-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersOverview as $user): ?>
                    <tr>
                        <td><?php echo (int) $user['id']; ?></td>
                        <td>@<?php echo htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge badge-pill <?php echo strtolower((string) $user['status']) === 'active' ? 'badge-safe' : 'badge-warning'; ?>"><?php echo htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string) $user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="panel glass-panel reports-panel">
        <div class="panel-header report-header">
            <div>
                <h2>Global report records</h2>
                <p>All submissions — hateXplain labels: normal / offensive / hatespeech.</p>
            </div>
            <div class="table-actions">
                <div class="input-group search-group">
                    <span class="input-group-text">🔍</span>
                    <input id="adminReportSearch" type="text" class="form-control" placeholder="Search reports...">
                </div>
                <a href="<?php echo htmlspecialchars(es_url('analytics.php')); ?>" class="btn btn-export">Full analytics</a>
                <button id="adminExportReports" type="button" class="btn btn-export">Export CSV</button>
            </div>
        </div>
        <div class="table-responsive terminal-table-wrap">
            <table class="table table-dark table-borderless align-middle mb-0 terminal-table" id="adminReportsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Harasser</th>
                        <th>Message</th>
                        <th>AI label</th>
                        <th>Toxicity</th>
                        <th>Risk</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allReports) > 0): ?>
                        <?php foreach ($allReports as $row): ?>
                            <?php
                            $risk = strtoupper((string) ($row['risk_level'] ?? 'SAFE'));
                            $modelLabel = strtolower((string) ($row['severity_level'] ?? 'normal'));
                            ?>
                            <tr>
                                <td>RPT-<?php echo (int) $row['report_id']; ?></td>
                                <td>@<?php echo htmlspecialchars((string) ($row['username'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['harasser_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="report-msg-cell"><?php echo htmlspecialchars(mb_substr((string) $row['harmful_message'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>…</td>
                                <td><span class="badge badge-pill <?php echo admin_label_badge($modelLabel); ?>"><?php echo htmlspecialchars($modelLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars((string) $row['toxicity_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge badge-pill <?php echo admin_badge($risk); ?>"><?php echo htmlspecialchars($risk, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M d H:i', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No reports in database yet.</td>
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
