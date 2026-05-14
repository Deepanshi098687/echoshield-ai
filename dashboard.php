<?php

session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . es_url('login.php'));
    exit;
}

$result = [];
$screenshotPreview = '';

if (isset($_POST['message'])) {
    $message = $_POST['message'];
    $url = 'http://127.0.0.1:5000/predict';
    $data = ['message' => $message];
    $options = [
        'http' => [
            'header'  => 'Content-type: application/json',
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $result = json_decode($response, true) ?: [];

    if ($result && isset($result['toxicity_score'], $result['severity'])) {
        $user_id = $_SESSION['user_id'];
        $harasser_name = mysqli_real_escape_string($conn, $_POST['harasser_name']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $message_clean = mysqli_real_escape_string($conn, $message);
        $toxicity = floatval($result['toxicity_score']);

        if ($toxicity === 0.0) {
            $risk_level = 'SAFE';
        } elseif ($toxicity <= 2.0) {
            $risk_level = 'MEDIUM';
        } else {
            $risk_level = 'HIGH';
        }

        $result['risk_level'] = $risk_level;
        $result['confidence_score'] = min(99, max(76, 98 - round($toxicity * 3)));
        $result['harmed_category'] = $result['severity'];

        $screenshot = '';
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === 0) {
            $screenshot = basename($_FILES['screenshot']['name']);
            $temp_name = $_FILES['screenshot']['tmp_name'];
            $targetPath = __DIR__ . '/uploads/' . $screenshot;
            move_uploaded_file($temp_name, $targetPath);
            $screenshotPreview = es_url('uploads/' . $screenshot);
        }

        $sql = "INSERT INTO reports
            (victim_id, harasser_name, harasser_phone, harmful_message, toxicity_score, severity_level, risk_level, screenshot)
            VALUES
            ('$user_id', '$harasser_name', '$phone', '$message_clean', '$toxicity', '{$result['severity']}', '$risk_level', '$screenshot')";
        mysqli_query($conn, $sql);

        if ($result['severity'] === 'HIGH') {
            $alert_msg = '⚠ HIGH RISK harmful activity detected.';
            $sql2 = "INSERT INTO alerts (user_id, alert_message) VALUES ('$user_id', '$alert_msg')";
            mysqli_query($conn, $sql2);
        }

        $sql_check = "SELECT * FROM harassers WHERE harasser_name='$harasser_name'";
        $check = mysqli_query($conn, $sql_check);
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE harassers SET total_violations = total_violations + 1 WHERE harasser_name='$harasser_name'");
        }
    }
}

$username = htmlspecialchars((string)($_SESSION['username'] ?? 'Analyst'), ENT_QUOTES, 'UTF-8');
$basePath = htmlspecialchars(es_base_path());
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
<body class="page-dashboard">

<?php include __DIR__ . '/includes/nav_user.php'; ?>

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
            <a href="#monitor" class="sidebar-link"><span>🛰️</span>Live Monitor</a>
            <a href="#wellness" class="sidebar-link"><span>🧠</span>Mental Wellness</a>
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
                    <button class="btn btn-glow btn-glow-cyan">Launch Monitor</button>
                    <button class="btn btn-glow btn-glow-purple">Open Incident</button>
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
                            <strong>7,832</strong>
                            <small>Threats analyzed</small>
                        </div>
                        <div>
                            <strong>98.6%</strong>
                            <small>AI accuracy</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="status-cards row gx-3 gy-3">
            <article class="col-md-6 col-xl-3 status-card status-card--cyan">
                <span class="card-label">AI ENGINE</span>
                <h3>ACTIVE</h3>
                <p>Adaptive runtime secured</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--purple">
                <span class="card-label">Threat Level</span>
                <h3>LOW</h3>
                <p>Monitoring stable zones</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--green">
                <span class="card-label">Messages Scanned</span>
                <h3>12,491</h3>
                <p>Today</p>
            </article>
            <article class="col-md-6 col-xl-3 status-card status-card--pink">
                <span class="card-label">Users Protected</span>
                <h3>4,124</h3>
                <p>Active accounts</p>
            </article>
        </section>

        <section class="dashboard-grid row gx-4 gy-4">
            <div class="col-xl-5">
                <div class="panel glass-panel live-feed-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Real-Time Monitoring</h2>
                            <p>Live incident feed with cyberbullying alerts.</p>
                        </div>
                        <span class="badge bg-cyan text-dark">Live</span>
                    </div>
                    <div class="feed-list">
                        <article class="feed-item feed-item--danger">
                            <span class="feed-time">00:02</span>
                            <p><strong>Toxic message detected</strong> in group discussion. Threat level raised.</p>
                        </article>
                        <article class="feed-item feed-item--warning">
                            <span class="feed-time">00:08</span>
                            <p><strong>Warning issued</strong> to target account. De-escalation initiated.</p>
                        </article>
                        <article class="feed-item feed-item--success">
                            <span class="feed-time">00:14</span>
                            <p><strong>User protected</strong> by intervention bot and incident logged.</p>
                        </article>
                        <article class="feed-item feed-item--purple">
                            <span class="feed-time">00:19</span>
                            <p><strong>Screenshot uploaded</strong> for evidence capture and OCR review.</p>
                        </article>
                        <article class="feed-item feed-item--info">
                            <span class="feed-time">00:27</span>
                            <p><strong>Threat flagged</strong> by predictive model before escalation.</p>
                        </article>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="panel glass-panel analytics-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Advanced Analytics</h2>
                            <p>AI insights into harassment trends and message safety.</p>
                        </div>
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
                        <span class="status-pill status-pill--warning">Moderate</span>
                    </div>
                    <div class="threat-meter">
                        <svg viewBox="0 0 220 220" class="meter-ring">
                            <circle cx="110" cy="110" r="96" class="meter-track" />
                            <circle cx="110" cy="110" r="96" class="meter-progress" />
                        </svg>
                        <div class="meter-labels">
                            <span class="meter-value">38%</span>
                            <small>Current threat load</small>
                        </div>
                    </div>
                    <div class="meter-notes">
                        <span class="note-pill note-pill--success">Safe margin</span>
                        <span class="note-pill note-pill--warning">Adaptive scan</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
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
                                <button type="submit" class="btn btn-analyze">Analyze</button>
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
                            <strong><?php echo htmlspecialchars($result['emotion'] ?? 'Calm / Alert', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Toxicity percentage</span>
                            <strong><?php echo htmlspecialchars((string)($result['toxicity_score'] ?? '12'), ENT_QUOTES, 'UTF-8'); ?>%</strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Threat category</span>
                            <strong><?php echo htmlspecialchars($result['severity'] ?? 'Harassment', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>AI confidence</span>
                            <strong><?php echo htmlspecialchars((string)($result['confidence_score'] ?? '91'), ENT_QUOTES, 'UTF-8'); ?>%</strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Risk level</span>
                            <strong><?php echo htmlspecialchars($result['risk_level'] ?? 'Medium', ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="col-md-4 result-card result-card--glow">
                            <span>Harmful keywords</span>
                            <strong><?php echo htmlspecialchars(implode(', ', $result['detected_words'] ?? ['abuse', 'threat']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="panel glass-panel protection-panel">
                    <div class="panel-header">
                        <div>
                            <h2>User Protection Center</h2>
                            <p>Key safeguard metrics for active prevention.</p>
                        </div>
                    </div>
                    <div class="row gx-3 gy-3">
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--blue">
                            <span>Users Protected</span>
                            <strong>4,124</strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--green">
                            <span>Active Cases</span>
                            <strong>128</strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--purple">
                            <span>Reports Generated</span>
                            <strong>3,780</strong>
                        </article>
                        <article class="col-sm-6 col-lg-3 protection-card protection-card--red">
                            <span>Blocked Offenders</span>
                            <strong>632</strong>
                        </article>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="panel glass-panel reports-panel">
                    <div class="panel-header report-header">
                        <div>
                            <h2>Recent Reports</h2>
                            <p>Latest investigations and incident tracking.</p>
                        </div>
                        <div class="table-actions">
                            <div class="input-group search-group">
                                <span class="input-group-text">🔍</span>
                                <input id="reportSearch" type="text" class="form-control" placeholder="Search reports...">
                            </div>
                            <button id="exportReports" type="button" class="btn btn-export">Export</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle mb-0" id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Severity</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>RPT-4821</td>
                                    <td><span class="badge badge-pill badge-danger">High</span></td>
                                    <td>11:27</td>
                                    <td><span class="status-dot status-dot--danger">Investigating</span></td>
                                    <td>@cyberwatch</td>
                                </tr>
                                <tr>
                                    <td>RPT-4819</td>
                                    <td><span class="badge badge-pill badge-warning">Medium</span></td>
                                    <td>10:42</td>
                                    <td><span class="status-dot status-dot--success">Resolved</span></td>
                                    <td>@shieldsafe</td>
                                </tr>
                                <tr>
                                    <td>RPT-4813</td>
                                    <td><span class="badge badge-pill badge-purple">Low</span></td>
                                    <td>09:58</td>
                                    <td><span class="status-dot status-dot--info">Monitoring</span></td>
                                    <td>@guardianAI</td>
                                </tr>
                                <tr>
                                    <td>RPT-4805</td>
                                    <td><span class="badge badge-pill badge-danger">High</span></td>
                                    <td>08:33</td>
                                    <td><span class="status-dot status-dot--danger">Escalated</span></td>
                                    <td>@trustnet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<button id="chatbotButton" class="fab-button fab-button--blue">AI</button>
<button id="sosButton" class="fab-button fab-button--red">SOS</button>

<div id="chatbotModal" class="modal-backdrop">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h3>EchoShield Assistant</h3>
                <p>Mental wellness support and safety reporting.</p>
            </div>
            <button type="button" class="modal-close" data-action="close-modal">×</button>
        </div>
        <div class="modal-body">
            <p>How can I help you today? Ask me about reporting abuse, safety guidance, or next steps.</p>
            <div class="chatbot-actions">
                <button class="btn btn-sm btn-outline-cyan">Report incident</button>
                <button class="btn btn-sm btn-outline-purple">Safety tips</button>
                <button class="btn btn-sm btn-outline-pink">Wellness support</button>
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
            <button type="button" class="modal-close" data-action="close-modal">×</button>
        </div>
        <div class="modal-body">
            <p class="sos-text">Send an emergency alert to incident response and enable immediate assistance.</p>
            <div class="chatbot-actions">
                <button class="btn btn-sm btn-danger btn-sos">Activate SOS</button>
                <button class="btn btn-sm btn-outline-cyan">Call support line</button>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo htmlspecialchars(es_url('js/script.js')); ?>"></script>
</body>
</html>
<!--  -->