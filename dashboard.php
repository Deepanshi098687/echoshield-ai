<?php

session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/data_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . es_url('login.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$result = [];
$screenshotPreview = '';
$reportSaved = false;
$reportError = '';

if (isset($_POST['message'])) {
    $message = trim((string) $_POST['message']);
    $result = es_predict_toxicity($message);

    if ($result && isset($result['toxicity_score'], $result['severity'])) {
        $harasser_name = mysqli_real_escape_string($conn, (string) ($_POST['harasser_name'] ?? 'Unknown'));
        $phone = mysqli_real_escape_string($conn, (string) ($_POST['phone'] ?? ''));
        $message_clean = mysqli_real_escape_string($conn, $message);
        $toxicity = floatval($result['toxicity_score']);
        $risk = es_risk_from_toxicity($toxicity);

        $result['risk_level'] = $risk['risk_level'];
        $result['confidence_score'] = min(99, max(76, 98 - (int) round($toxicity * 3)));
        $result['harmed_category'] = $result['severity'];
        $result['emotion'] = $toxicity > 0 ? 'Hostile / Alert' : 'Calm / Neutral';

        $screenshot = '';
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === 0) {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $screenshot = time() . '_' . basename((string) $_FILES['screenshot']['name']);
            $targetPath = $uploadDir . '/' . $screenshot;
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $targetPath)) {
                $screenshotPreview = es_url('uploads/' . $screenshot);
            } else {
                $screenshot = '';
            }
        }

        $severityEsc = mysqli_real_escape_string($conn, (string) $result['severity']);
        $riskEsc = mysqli_real_escape_string($conn, $risk['risk_level']);

        $sql = "INSERT INTO reports
            (victim_id, harasser_name, harasser_phone, harmful_message, toxicity_score, severity_level, risk_level, screenshot)
            VALUES
            ($user_id, '$harasser_name', '$phone', '$message_clean', $toxicity, '$severityEsc', '$riskEsc', '$screenshot')";

        if (mysqli_query($conn, $sql)) {
            $reportSaved = true;

            if ($result['severity'] === 'HIGH') {
                $alert_msg = mysqli_real_escape_string($conn, 'HIGH RISK harmful activity detected.');
                mysqli_query($conn, "INSERT INTO alerts (user_id, alert_message) VALUES ($user_id, '$alert_msg')");
            }

            if (es_table_exists($conn, 'harassers')) {
                $check = mysqli_query($conn, "SELECT id FROM harassers WHERE harasser_name='$harasser_name' LIMIT 1");
                if ($check && mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE harassers SET total_violations = total_violations + 1 WHERE harasser_name='$harasser_name'");
                } else {
                    mysqli_query($conn, "INSERT INTO harassers (harasser_name, total_violations) VALUES ('$harasser_name', 1)");
                }
            }
        } else {
            $reportError = 'Database error: could not save report. Check that reports table matches database.sql.';
        }
    } else {
        $reportError = 'AI analysis failed. Ensure Flask is running on port 5000 or use the built-in scanner.';
    }
}

$stats = es_get_dashboard_stats($conn, $user_id);
$userReports = es_get_user_reports($conn, $user_id);
$liveFeed = es_get_live_feed($conn, $user_id);
$chartData = es_get_chart_data($conn, $user_id);
$globalStats = es_get_dashboard_stats($conn, $user_id);
$allReportsCount = 0;
if (es_table_exists($conn, 'reports')) {
    $c = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM reports');
    if ($c && ($r = mysqli_fetch_assoc($c))) {
        $allReportsCount = (int) $r['c'];
    }
}

$username = htmlspecialchars((string) ($_SESSION['username'] ?? 'Analyst'), ENT_QUOTES, 'UTF-8');
$basePath = htmlspecialchars(es_base_path());

function es_badge_class(string $risk): string
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

function es_status_class(string $risk): string
{
    $risk = strtoupper($risk);
    if ($risk === 'HIGH') {
        return 'status-dot--danger';
    }
    if ($risk === 'MEDIUM') {
        return 'status-dot--warning';
    }
    return 'status-dot--success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <base href="<?php echo $basePath; ?>">
    <title>EchoShield AI Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-dashboard" data-report-saved="<?php echo $reportSaved ? '1' : '0'; ?>">

<?php include __DIR__ . '/includes/nav_user.php'; ?>

<?php if ($reportSaved): ?>
<div id="reportToast" class="es-toast es-toast--success" role="alert">
    <span class="es-toast__icon">[ OK ]</span>
    <div>
        <strong>REPORT SUBMITTED</strong>
        <p>Record saved to your folder and admin portal.</p>
    </div>
</div>
<?php endif; ?>

<?php if ($reportError !== ''): ?>
<div id="reportToast" class="es-toast es-toast--danger" role="alert">
    <span class="es-toast__icon">[ ERR ]</span>
    <div>
        <strong>SUBMISSION FAILED</strong>
        <p><?php echo htmlspecialchars($reportError, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-shell">
    <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">⟡</div>
            <div>
                <span class="brand-title">EchoShield</span>
                <span class="brand-subtitle">AI SOC</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo htmlspecialchars(es_url('dashboard.php')); ?>" class="sidebar-link active"><span>🏠</span>Dashboard</a>
            <a href="<?php echo htmlspecialchars(es_url('analytics.php')); ?>" class="sidebar-link"><span>📈</span>Analytics</a>
            <a href="<?php echo htmlspecialchars(es_url('notifications.php')); ?>" class="sidebar-link"><span>🔔</span>Notifications</a>
            <a href="#chatbot" class="sidebar-link" data-action="open-chat"><span>🤖</span>AI Chatbot</a>
            <a href="#monitor" class="sidebar-link" data-scroll="monitor"><span>🛰️</span>Live Monitor</a>
            <a href="#wellness" class="sidebar-link" data-scroll="wellness"><span>🧠</span>Mental Wellness</a>
            <a href="#reports-records" class="sidebar-link" data-scroll="reports-records"><span>📁</span>My Reports</a>
            <a href="#sos" class="sidebar-link" data-action="open-sos"><span>🚨</span>Emergency SOS</a>
            <a href="<?php echo htmlspecialchars(es_url('logout.php')); ?>" class="sidebar-link sidebar-link--exit"><span>⏏</span>Logout</a>
        </nav>
        <div class="sidebar-footer">
            <span>Active analyst</span>
            <strong><?php echo $username; ?></strong>
        </div>
    </aside>

    <main class="dashboard-main">
        <section class="hero-panel">
            <div class="hero-copy">
                <span class="hero-badge">AI Cybersecurity Command Center</span>
                <h1 class="hero-title"><span id="typewriterText"></span><span id="typewriterCursor" class="typewriter-cursor"></span></h1>
                <p class="hero-text">Real-time cyberbullying detection, proactive threat prevention, and AI-assisted harm mitigation for safer communities.</p>
                <div class="hero-actions">
                    <button type="button" class="btn btn-glow btn-glow-cyan" data-scroll="monitor">Launch Monitor</button>
                    <button type="button" class="btn btn-glow btn-glow-purple" data-scroll="analysis">Open Incident</button>
                </div>
            </div>
            <div class="hero-panel-card">
                <div class="hero-card-header">
                    <span>Holographic shield</span>
                    <span class="status-pill status-pill--active">Online</span>
                </div>
                <div class="hero-card-body">
                    <div class="shield-graphic"></div>
                    <div class="hero-card-stats">
                        <div>
                            <strong><?php echo (int) $stats['messages_scanned']; ?></strong>
                            <small>Your scans</small>
                        </div>
                        <div>
                            <strong><?php echo (int) $stats['threat_percent']; ?>%</strong>
                            <small>Global threat load</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="status-cards row gx-3 gy-3">
            <article class="col-md-6 col-xl-3 status-card status-card--cyan">
                <span class="card-label">AI ENGINE</span>
                <h3><?php echo htmlspecialchars($stats['ai_status'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p>Adaptive runtime secured</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--purple">
                <span class="card-label">Threat Level</span>
                <h3 class="text-danger-accent"><?php echo htmlspecialchars($stats['threat_label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p>Based on live report data</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--green">
                <span class="card-label">Messages Scanned</span>
                <h3><?php echo (int) $stats['messages_scanned']; ?></h3>
                <p>Your submissions</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--pink">
                <span class="card-label">High Risk Cases</span>
                <h3 class="text-danger-accent"><?php echo (int) $stats['high_risk']; ?></h3>
                <p>Requires attention</p>
            </article>
        </section>

        <section class="dashboard-grid row gx-4 gy-4">
            <div class="col-xl-5" id="monitor">
                <div class="panel glass-panel live-feed-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Real-Time Monitoring</h2>
                            <p>Live incident feed from your submitted reports.</p>
                        </div>
                        <span class="badge bg-cyan text-dark">Live</span>
                    </div>
                    <div class="feed-list">
                        <?php if (count($liveFeed) > 0): ?>
                            <?php foreach ($liveFeed as $item): ?>
                                <article class="feed-item feed-item--<?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="feed-time"><?php echo htmlspecialchars($item['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <p><?php echo $item['text']; ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <article class="feed-item feed-item--info">
                                <span class="feed-time">--:--</span>
                                <p><strong>No incidents yet.</strong> Submit a message in the analysis form to populate this feed.</p>
                            </article>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="panel glass-panel analytics-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Advanced Analytics</h2>
                            <p>Charts driven by your report dataset.</p>
                        </div>
                        <a href="<?php echo htmlspecialchars(es_url('analytics.php')); ?>" class="btn btn-sm btn-outline-cyan">Full analytics</a>
                    </div>
                    <div class="row gx-3 gy-3">
                        <div class="col-md-6">
                            <div class="chart-card">
                                <canvas id="chartHarassmentTypes"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-card">
                                <canvas id="chartDailyReports"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-card">
                                <canvas id="chartWeeklyThreats"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-card">
                                <canvas id="chartSafeToxic"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="panel glass-panel threat-meter-panel">
                    <div class="panel-header">
                        <h2>AI Threat Meter</h2>
                        <span class="status-pill status-pill--warning"><?php echo htmlspecialchars($stats['threat_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="threat-meter" data-threat="<?php echo (int) $stats['threat_percent']; ?>">
                        <svg viewBox="0 0 220 220" class="meter-ring">
                            <circle cx="110" cy="110" r="96" class="meter-track" />
                            <circle cx="110" cy="110" r="96" class="meter-progress" />
                        </svg>
                        <div class="meter-labels">
                            <span class="meter-value"><?php echo (int) $stats['threat_percent']; ?>%</span>
                            <small>Current threat load</small>
                        </div>
                    </div>
                    <div class="meter-notes">
                        <span class="note-pill note-pill--success">Reports: <?php echo (int) $stats['total_reports']; ?></span>
                        <span class="note-pill note-pill--warning">High: <?php echo (int) $stats['high_risk']; ?></span>
                    </div>
                </div>
            </div>

            <div class="col-xl-8" id="analysis">
                <div class="panel glass-panel analysis-panel">
                    <div class="panel-header">
                        <h2>AI Analysis Form</h2>
                        <p>Submit a suspicious message and let the AI classify risk instantly.</p>
                    </div>
                    <form id="analysisForm" method="POST" enctype="multipart/form-data" class="analysis-form">
                        <div class="row gx-3 gy-3">
                            <div class="col-lg-12">
                                <label class="form-label">Message textarea</label>
                                <textarea id="message" name="message" class="form-control glass-input" rows="4" placeholder="Paste or type the suspicious message" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Harasser name</label>
                                <input id="harasser_name" name="harasser_name" type="text" class="form-control glass-input" placeholder="Name or handle" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone number</label>
                                <input id="phone" name="phone" type="text" class="form-control glass-input" placeholder="Optional phone or contact info">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Screenshot upload</label>
                                <input id="screenshot" name="screenshot" type="file" class="form-control glass-input" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Voice upload</label>
                                <input id="voice" name="voice" type="file" class="form-control glass-input" accept="audio/*">
                            </div>
                            <div class="col-12 form-footer">
                                <button type="submit" class="btn btn-analyze">Analyze &amp; Save Report</button>
                                <span id="screenshotStatus" class="upload-status">AI OCR Ready</span>
                            </div>
                        </div>
                        <div id="screenshotPreview" class="screenshot-preview">
                            <?php if ($screenshotPreview): ?>
                                <img src="<?php echo htmlspecialchars($screenshotPreview, ENT_QUOTES, 'UTF-8'); ?>" alt="Screenshot preview">
                                <div class="preview-label">Screenshot uploaded and ready for OCR.</div>
                            <?php else: ?>
                                <img src="" alt="Screenshot preview" hidden>
                                <div class="preview-label">Screenshot preview will appear here after selection.</div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="panel glass-panel result-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Sentiment Analysis Result</h2>
                            <p>AI interpretation of message intent and risk.</p>
                        </div>
                        <span class="status-pill status-pill--cyan">Insight</span>
                    </div>
                    <div class="result-cards row gx-3 gy-3">
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Emotion detection</span>
                            <strong><?php echo htmlspecialchars($result['emotion'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Toxicity score</span>
                            <strong><?php echo htmlspecialchars((string) ($result['toxicity_score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Threat category</span>
                            <strong class="<?php echo (($result['severity'] ?? '') === 'HIGH') ? 'text-danger-accent' : ''; ?>"><?php echo htmlspecialchars($result['severity'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>AI confidence</span>
                            <strong><?php echo htmlspecialchars((string) ($result['confidence_score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?><?php echo isset($result['confidence_score']) ? '%' : ''; ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Risk level</span>
                            <strong><?php echo htmlspecialchars($result['risk_level'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Harmful keywords</span>
                            <strong><?php
                                $words = $result['detected_words'] ?? [];
                                echo $words ? htmlspecialchars(implode(', ', $words), ENT_QUOTES, 'UTF-8') : '—';
                            ?></strong>
                        </article>
                    </div>
                </div>
            </div>

            <div class="col-xl-12" id="wellness">
                <div class="panel glass-panel protection-panel">
                    <div class="panel-header">
                        <div>
                            <h2>User Protection Center</h2>
                            <p>Live metrics from database — no sample data.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-pink" data-action="open-chat">Wellness chat</button>
                    </div>
                    <div class="row gx-3 gy-3">
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--blue">
                            <span>Users Registered</span>
                            <strong><?php echo (int) $stats['users_protected']; ?></strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--green">
                            <span>Your Active Cases</span>
                            <strong><?php echo (int) $stats['active_cases']; ?></strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--purple">
                            <span>All Reports (system)</span>
                            <strong><?php echo $allReportsCount; ?></strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--red">
                            <span>Tracked Offenders</span>
                            <strong><?php echo (int) $stats['blocked_offenders']; ?></strong>
                        </article>
                    </div>
                </div>
            </div>

            <div class="col-xl-12" id="reports-records">
                <div class="panel glass-panel reports-panel">
                    <div class="panel-header report-header">
                        <div>
                            <h2>My Report Records</h2>
                            <p>Your submitted incidents — synced with admin portal.</p>
                        </div>
                        <div class="table-actions">
                            <div class="input-group search-group">
                                <span class="input-group-text">🔍</span>
                                <input id="reportSearch" type="text" class="form-control" placeholder="Search reports...">
                            </div>
                            <button id="exportReports" type="button" class="btn btn-export">Export</button>
                        </div>
                    </div>
                    <div class="table-responsive terminal-table-wrap">
                        <table class="table table-dark table-borderless align-middle mb-0 terminal-table" id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Severity</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Harasser</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($userReports) > 0): ?>
                                    <?php foreach ($userReports as $rep): ?>
                                        <?php
                                        $risk = strtoupper((string) ($rep['risk_level'] ?? 'SAFE'));
                                        $badge = es_badge_class($risk);
                                        $dot = es_status_class($risk);
                                        ?>
                                        <tr>
                                            <td>RPT-<?php echo (int) $rep['report_id']; ?></td>
                                            <td><span class="badge badge-pill <?php echo $badge; ?>"><?php echo htmlspecialchars($risk, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars(date('H:i', strtotime((string) $rep['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="status-dot <?php echo $dot; ?>"><?php echo $risk === 'HIGH' ? 'Escalated' : ($risk === 'MEDIUM' ? 'Monitoring' : 'Logged'); ?></span></td>
                                            <td><?php echo htmlspecialchars((string) $rep['harasser_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="report-msg-cell"><?php echo htmlspecialchars(mb_substr((string) $rep['harmful_message'], 0, 60), ENT_QUOTES, 'UTF-8'); ?>…</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No reports yet. Submit via the analysis form above.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<button id="chatbotButton" class="fab-button fab-button--blue" type="button" aria-label="Open AI chatbot">AI</button>
<button id="sosButton" class="fab-button fab-button--red" type="button" aria-label="Emergency SOS">SOS</button>

<div id="chatbotModal" class="modal-backdrop">
    <div class="modal-panel modal-panel--terminal">
        <div class="modal-header">
            <div>
                <h3>&gt; ECHOSHIELD_TERMINAL</h3>
                <p>Mental wellness · safety · reporting assistant</p>
            </div>
            <button type="button" class="modal-close" data-action="close-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body chat-terminal-body">
            <div id="chatLog" class="chat-log">
                <div class="chat-line chat-line--system">&gt; Session started. How can I help?</div>
            </div>
            <form id="chatForm" class="chat-input-row">
                <span class="chat-prompt">&gt;</span>
                <input type="text" id="chatInput" class="chat-input" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="btn btn-sm btn-outline-cyan">Send</button>
            </form>
            <div class="chatbot-actions">
                <button type="button" class="btn btn-sm btn-outline-cyan" data-chat-prompt="How do I report an incident?">Report incident</button>
                <button type="button" class="btn btn-sm btn-outline-purple" data-chat-prompt="Give me safety tips">Safety tips</button>
                <button type="button" class="btn btn-sm btn-outline-pink" data-chat-prompt="I need wellness support">Wellness support</button>
            </div>
        </div>
    </div>
</div>

<div id="sosModal" class="modal-backdrop">
    <div class="modal-panel modal-panel--danger">
        <div class="modal-header">
            <div>
                <h3>Emergency SOS</h3>
                <p>Immediate incident escalation and crisis hotline.</p>
            </div>
            <button type="button" class="modal-close" data-action="close-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            <p class="sos-text">Send an emergency alert to incident response and enable immediate assistance.</p>
            <div class="chatbot-actions">
                <button type="button" class="btn btn-sm btn-danger btn-sos" id="activateSosBtn">Activate SOS</button>
                <a href="tel:112" class="btn btn-sm btn-outline-cyan">Call emergency (112)</a>
            </div>
            <p id="sosStatus" class="mt-3 text-danger-accent" hidden>&gt; SOS ALERT LOGGED — check Notifications.</p>
        </div>
    </div>
</div>

<div id="processingOverlay" class="processing-overlay">
    <div class="processing-card">
        <span class="processing-label">Scanning message...</span>
        <div class="processing-bar"><span></span></div>
        <ul class="processing-log">
            <li>Running NLP analysis...</li>
            <li>Calculating toxicity...</li>
            <li>Generating threat score...</li>
            <li>AI detection completed...</li>
        </ul>
    </div>
</div>

<script>
window.ECHO_CHART_DATA = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.ECHO_CHAT_API = <?php echo json_encode(es_url('api/chatbot.php')); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo htmlspecialchars(es_url('js/script.js')); ?>"></script>
</body>
</html>
