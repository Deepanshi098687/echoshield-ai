<?php
declare(strict_types=1);

/** @return array<string, mixed> */
function es_predict_toxicity(string $message): array
{
    $url = 'http://127.0.0.1:5000/predict';
    $payload = json_encode(['message' => $message]);
    $context = stream_context_create([
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
            'timeout' => 3,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['toxicity_score'], $decoded['severity'])) {
            return $decoded;
        }
    }

    $toxic_words = [
        'stupid', 'idiot', 'hate', 'ugly', 'loser', 'kill', 'moron', 'shut up',
        'pagal', 'bewakoof', 'ganda', 'bakwas', 'chup', 'nalayak',
    ];
    $lower = strtolower($message);
    $detected = [];
    $score = 0;
    foreach ($toxic_words as $word) {
        if (str_contains($lower, $word)) {
            $score++;
            $detected[] = $word;
        }
    }

    if ($score === 0) {
        $severity = 'SAFE';
    } elseif ($score <= 2) {
        $severity = 'MEDIUM';
    } else {
        $severity = 'HIGH';
    }

    return [
        'message' => $lower,
        'toxicity_score' => $score,
        'severity' => $severity,
        'detected_words' => $detected,
    ];
}

/** @return array<string, int|string> */
function es_risk_from_toxicity(float $toxicity): array
{
    if ($toxicity === 0.0) {
        return ['risk_level' => 'SAFE', 'label' => 'LOW'];
    }
    if ($toxicity <= 2.0) {
        return ['risk_level' => 'MEDIUM', 'label' => 'MEDIUM'];
    }
    return ['risk_level' => 'HIGH', 'label' => 'HIGH'];
}

function es_table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $res && mysqli_num_rows($res) > 0;
}

/** @return array<string, mixed> */
function es_get_dashboard_stats(mysqli $conn, int $user_id): array
{
    $stats = [
        'total_reports' => 0,
        'high_risk' => 0,
        'medium_risk' => 0,
        'messages_scanned' => 0,
        'users_protected' => 0,
        'active_cases' => 0,
        'blocked_offenders' => 0,
        'threat_percent' => 0,
        'threat_label' => 'LOW',
        'ai_status' => 'ACTIVE',
    ];

    if (!es_table_exists($conn, 'reports')) {
        return $stats;
    }

    $uid = (int) $user_id;
    $userReports = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE victim_id = $uid");
    if ($userReports && ($row = mysqli_fetch_assoc($userReports))) {
        $stats['total_reports'] = (int) $row['c'];
        $stats['messages_scanned'] = (int) $row['c'];
        $stats['active_cases'] = (int) $row['c'];
    }

    $high = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE victim_id = $uid AND risk_level = 'HIGH'");
    if ($high && ($row = mysqli_fetch_assoc($high))) {
        $stats['high_risk'] = (int) $row['c'];
    }

    $med = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE victim_id = $uid AND risk_level = 'MEDIUM'");
    if ($med && ($row = mysqli_fetch_assoc($med))) {
        $stats['medium_risk'] = (int) $row['c'];
    }

    $users = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM users');
    if ($users && ($row = mysqli_fetch_assoc($users))) {
        $stats['users_protected'] = (int) $row['c'];
    }

    if (es_table_exists($conn, 'harassers')) {
        $h = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM harassers');
        if ($h && ($row = mysqli_fetch_assoc($h))) {
            $stats['blocked_offenders'] = (int) $row['c'];
        }
    }

    $globalHigh = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE risk_level = 'HIGH'");
    $globalTotal = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM reports');
    $gH = 0;
    $gT = 0;
    if ($globalHigh && ($r = mysqli_fetch_assoc($globalHigh))) {
        $gH = (int) $r['c'];
    }
    if ($globalTotal && ($r = mysqli_fetch_assoc($globalTotal))) {
        $gT = (int) $r['c'];
    }
    if ($gT > 0) {
        $stats['threat_percent'] = (int) round(($gH / $gT) * 100);
    }
    if ($stats['threat_percent'] >= 40) {
        $stats['threat_label'] = 'HIGH';
    } elseif ($stats['threat_percent'] >= 15) {
        $stats['threat_label'] = 'MODERATE';
    } else {
        $stats['threat_label'] = 'LOW';
    }

    return $stats;
}

/** @return array<int, array<string, mixed>> */
function es_get_user_reports(mysqli $conn, int $user_id, int $limit = 50): array
{
    if (!es_table_exists($conn, 'reports')) {
        return [];
    }
    $uid = (int) $user_id;
    $limit = (int) $limit;
    $idCol = es_reports_id_column($conn);
    $sql = "SELECT $idCol AS report_id, harasser_name, harmful_message, toxicity_score,
            severity_level, risk_level, created_at
            FROM reports WHERE victim_id = $uid ORDER BY $idCol DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @return array<int, array<string, mixed>> */
function es_get_all_reports(mysqli $conn, int $limit = 100): array
{
    if (!es_table_exists($conn, 'reports')) {
        return [];
    }
    $limit = (int) $limit;
    $idCol = es_reports_id_column($conn);
    $sql = "SELECT r.$idCol AS report_id, r.victim_id, r.harasser_name, r.harmful_message,
            r.toxicity_score, r.severity_level, r.risk_level, r.created_at, u.username
            FROM reports r
            LEFT JOIN users u ON u.id = r.victim_id
            ORDER BY r.$idCol DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @return array<int, array<string, string>> */
function es_get_live_feed(mysqli $conn, int $user_id, int $limit = 8): array
{
    $feed = [];
    if (!es_table_exists($conn, 'reports')) {
        return $feed;
    }
    $uid = (int) $user_id;
    $limit = (int) $limit;
    $idCol = es_reports_id_column($conn);
    $sql = "SELECT $idCol AS report_id, harmful_message, risk_level, severity_level, created_at
            FROM reports WHERE victim_id = $uid ORDER BY $idCol DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $feed;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $risk = strtoupper((string) ($row['risk_level'] ?? 'MEDIUM'));
        $type = 'info';
        if ($risk === 'HIGH') {
            $type = 'danger';
        } elseif ($risk === 'MEDIUM') {
            $type = 'warning';
        } elseif ($risk === 'SAFE') {
            $type = 'success';
        }
        $msg = mb_substr((string) ($row['harmful_message'] ?? ''), 0, 80);
        $feed[] = [
            'time' => date('H:i', strtotime((string) $row['created_at'])),
            'text' => '<strong>Report #' . (int) $row['report_id'] . '</strong> — ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' [' . $risk . ']',
            'type' => $type,
        ];
    }
    return $feed;
}

function es_reports_id_column(mysqli $conn): string
{
    $cols = mysqli_query($conn, 'SHOW COLUMNS FROM reports');
    if ($cols) {
        while ($col = mysqli_fetch_assoc($cols)) {
            if (($col['Field'] ?? '') === 'report_id') {
                return 'report_id';
            }
        }
    }
    return 'id';
}

/** @return array<string, mixed> */
function es_get_chart_data(mysqli $conn, ?int $user_id = null): array
{
    $empty = [
        'severity' => ['SAFE' => 0, 'MEDIUM' => 0, 'HIGH' => 0],
        'daily' => ['labels' => [], 'values' => []],
        'weekly' => ['labels' => [], 'values' => []],
        'safe_toxic' => ['safe' => 0, 'toxic' => 0],
    ];

    if (!es_table_exists($conn, 'reports')) {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = date('D', strtotime("-$i days"));
        }
        $empty['daily'] = ['labels' => $days, 'values' => array_fill(0, 7, 0)];
        $empty['weekly'] = ['labels' => ['W1', 'W2', 'W3', 'W4'], 'values' => [0, 0, 0, 0]];
        return $empty;
    }

    $where = $user_id !== null ? 'WHERE victim_id = ' . (int) $user_id : '';

    foreach (['SAFE', 'MEDIUM', 'HIGH'] as $level) {
        $levelEsc = mysqli_real_escape_string($conn, $level);
        $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $where " . ($where ? 'AND' : 'WHERE') . " risk_level = '$levelEsc'");
        if (!$q && $where) {
            $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $where AND severity_level = '$levelEsc'");
        }
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $empty['severity'][$level] = (int) $r['c'];
        }
    }

    $safe = (int) ($empty['severity']['SAFE'] ?? 0);
    $toxic = (int) ($empty['severity']['MEDIUM'] ?? 0) + (int) ($empty['severity']['HIGH'] ?? 0);
    $empty['safe_toxic'] = ['safe' => $safe, 'toxic' => max(0, $toxic)];

    $dailyLabels = [];
    $dailyValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dailyLabels[] = date('D', strtotime($date));
        $dateEsc = mysqli_real_escape_string($conn, $date);
        $clause = $where ? "$where AND DATE(created_at) = '$dateEsc'" : "WHERE DATE(created_at) = '$dateEsc'";
        $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $clause");
        $dailyValues[] = ($q && ($r = mysqli_fetch_assoc($q))) ? (int) $r['c'] : 0;
    }
    $empty['daily'] = ['labels' => $dailyLabels, 'values' => $dailyValues];

    $weeklyLabels = [];
    $weeklyValues = [];
    for ($w = 3; $w >= 0; $w--) {
        $start = date('Y-m-d', strtotime('-' . ($w * 7 + 6) . ' days'));
        $end = date('Y-m-d', strtotime('-' . ($w * 7) . ' days'));
        $weeklyLabels[] = 'Week ' . (4 - $w);
        $startEsc = mysqli_real_escape_string($conn, $start);
        $endEsc = mysqli_real_escape_string($conn, $end);
        $clause = $where
            ? "$where AND DATE(created_at) BETWEEN '$startEsc' AND '$endEsc'"
            : "WHERE DATE(created_at) BETWEEN '$startEsc' AND '$endEsc'";
        $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports $clause");
        $weeklyValues[] = ($q && ($r = mysqli_fetch_assoc($q))) ? (int) $r['c'] : 0;
    }
    $empty['weekly'] = ['labels' => $weeklyLabels, 'values' => $weeklyValues];

    return $empty;
}

/** @return array<string, string> */
function es_chatbot_reply(string $message): array
{
    $m = strtolower(trim($message));

    if ($m === '' || $m === 'hi' || $m === 'hello' || str_contains($m, 'help')) {
        return [
            'reply' => "> ECHOSHIELD TERMINAL ONLINE\n> Commands: report, safety, wellness, sos, analyze\n> Type your question or use quick actions below.",
        ];
    }
    if (str_contains($m, 'report') || str_contains($m, 'incident') || str_contains($m, 'abuse')) {
        return [
            'reply' => "> REPORT PROTOCOL:\n1. Scroll to [AI Analysis Form]\n2. Paste harmful message + harasser name\n3. Click ANALYZE — record saves to your folder & admin portal.",
        ];
    }
    if (str_contains($m, 'safety') || str_contains($m, 'tip') || str_contains($m, 'protect')) {
        return [
            'reply' => "> SAFETY OPS:\n- Screenshot evidence before blocking\n- Do not engage with harassers\n- Use Emergency SOS for immediate escalation\n- Check Notifications for HIGH risk alerts",
        ];
    }
    if (str_contains($m, 'wellness') || str_contains($m, 'mental') || str_contains($m, 'stress')) {
        return [
            'reply' => "> WELLNESS CHANNEL:\nYou are not alone. Take breaks from toxic threads.\nNational crisis lines are available 24/7 in your region.\nEchoShield logs incidents so you do not have to relive them alone.",
        ];
    }
    if (str_contains($m, 'sos') || str_contains($m, 'emergency')) {
        return [
            'reply' => "> SOS READY\nClick the red SOS button (bottom-right) to open emergency panel.\nContact local authorities if you are in immediate danger.",
        ];
    }
    if (str_contains($m, 'analyze') || str_contains($m, 'toxic') || str_contains($m, 'detect')) {
        return [
            'reply' => "> AI ENGINE: Flask model @ :5000 (fallback: local keyword scan)\nSubmit text in the analysis form — toxicity score, severity, and keywords return instantly.",
        ];
    }

    return [
        'reply' => "> UNKNOWN QUERY\nTry: \"how to report\", \"safety tips\", \"wellness\", or \"sos\".",
    ];
}
