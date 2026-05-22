<?php
declare(strict_types=1);

/** @return array<string, list<string>> */
function es_label_mapping(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $path = dirname(__DIR__) . '/ai_model/label_mapping.json';
    if (is_readable($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $map = $decoded;
            return $map;
        }
    }
    $map = [
        'SAFE' => ['normal', '0', 'safe', 'nothate', 'non-offensive', 'non_offensive'],
        'MEDIUM' => ['offensive', '1', 'medium', 'mild', 'abusive'],
        'HIGH' => ['hate', 'hatespeech', 'hateful', '2', 'high', 'hate_speech'],
    ];
    return $map;
}

/** @return array{risk_level: string, model_label: string} */
function es_map_label_to_risk(string $rawLabel): array
{
    $token = strtolower(trim(str_replace(['[', ']', "'", '"'], '', $rawLabel)));
    $token = str_replace('_', ' ', $token);

    foreach (es_label_mapping() as $risk => $aliases) {
        foreach ($aliases as $alias) {
            if ($token === strtolower((string) $alias)) {
                $modelLabel = match (strtoupper((string) $risk)) {
                    'SAFE' => 'normal',
                    'MEDIUM' => 'offensive',
                    default => 'hatespeech',
                };
                return ['risk_level' => strtoupper((string) $risk), 'model_label' => $modelLabel];
            }
        }
    }

    if (in_array($token, ['normal', 'safe', 'nothate'], true)) {
        return ['risk_level' => 'SAFE', 'model_label' => 'normal'];
    }
    if (in_array($token, ['offensive', 'abusive', 'medium'], true)) {
        return ['risk_level' => 'MEDIUM', 'model_label' => 'offensive'];
    }
    if (in_array($token, ['hate', 'hatespeech', 'hateful', 'high'], true)) {
        return ['risk_level' => 'HIGH', 'model_label' => 'hatespeech'];
    }

    return ['risk_level' => 'SAFE', 'model_label' => 'normal'];
}

/** @param array<string, mixed> $decoded */
function es_normalize_prediction(array $decoded): array
{
    $raw = (string) ($decoded['model_label'] ?? $decoded['severity'] ?? $decoded['label'] ?? 'normal');
    $mapped = es_map_label_to_risk($raw);
    $risk = strtoupper((string) ($decoded['risk_level'] ?? $decoded['severity'] ?? $mapped['risk_level']));
    if (!in_array($risk, ['SAFE', 'MEDIUM', 'HIGH'], true)) {
        $risk = $mapped['risk_level'];
    }

    $toxicity = isset($decoded['toxicity_score']) ? (float) $decoded['toxicity_score'] : match ($risk) {
        'HIGH' => 2.0,
        'MEDIUM' => 1.0,
        default => 0.0,
    };

    $probabilities = [];
    if (isset($decoded['probabilities']) && is_array($decoded['probabilities'])) {
        foreach ($decoded['probabilities'] as $label => $pct) {
            $probabilities[strtolower((string) $label)] = (float) $pct;
        }
    }

    return [
        'message' => (string) ($decoded['message'] ?? ''),
        'raw_prediction' => (string) ($decoded['raw_prediction'] ?? $raw),
        'model_label' => $mapped['model_label'],
        'severity' => $risk,
        'risk_level' => $risk,
        'toxicity_score' => $toxicity,
        'confidence' => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
        'probabilities' => $probabilities,
        'detected_words' => is_array($decoded['detected_words'] ?? null) ? $decoded['detected_words'] : [],
        'engine' => (string) ($decoded['engine'] ?? 'hateXplain-ml'),
    ];
}

function es_python_binary(): string
{
    $candidates = ['py -3', 'python3', 'python'];
    foreach ($candidates as $bin) {
        $out = @shell_exec($bin . ' --version 2>&1');
        if ($out && stripos($out, 'Python') !== false) {
            return $bin;
        }
    }
    return 'python';
}

/** @return array<string, mixed>|null */
function es_predict_via_python_cli(string $message): ?array
{
    if (!function_exists('shell_exec')) {
        return null;
    }

    $python = es_python_binary();
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ai_model' . DIRECTORY_SEPARATOR . 'predict_cli.py';
    if (!is_readable($script)) {
        return null;
    }

    $payload = json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    $cmd = $python . ' ' . escapeshellarg($script) . ' 2>&1';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($cmd, $descriptors, $pipes, dirname($script));
    if (!is_resource($process)) {
        return null;
    }

    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (!$output) {
        return null;
    }

    $decoded = json_decode(trim($output), true);
    if (!is_array($decoded) || isset($decoded['error'])) {
        return null;
    }
    if (!isset($decoded['severity']) && !isset($decoded['model_label'])) {
        return null;
    }

    return es_normalize_prediction($decoded);
}

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
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && (isset($decoded['severity']) || isset($decoded['model_label']))) {
            return es_normalize_prediction($decoded);
        }
    }

    $cli = es_predict_via_python_cli($message);
    if ($cli !== null && ($cli['engine'] ?? '') === 'hateXplain-ml') {
        return $cli;
    }

    if ($cli !== null) {
        return $cli;
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
        $mapped = es_map_label_to_risk('normal');
    } elseif ($score <= 2) {
        $mapped = es_map_label_to_risk('offensive');
    } else {
        $mapped = es_map_label_to_risk('hatespeech');
    }

    return es_normalize_prediction([
        'message' => $lower,
        'raw_prediction' => $mapped['model_label'],
        'model_label' => $mapped['model_label'],
        'severity' => $mapped['risk_level'],
        'toxicity_score' => (float) $score,
        'detected_words' => $detected,
        'engine' => 'keyword-fallback',
    ]);
}

/** @return string */
function es_engine_label(string $engine): string
{
    return match ($engine) {
        'hateXplain-ml' => 'Trained ML model (hateXplain)',
        'keyword-fallback' => 'Offline keyword scanner (start AI server for real model)',
        default => 'AI service',
    };
}

/** @return array<string, string> */
function es_risk_from_prediction(array $prediction): array
{
    $risk = strtoupper((string) ($prediction['risk_level'] ?? $prediction['severity'] ?? 'SAFE'));
    $label = match ($risk) {
        'HIGH' => 'HIGH',
        'MEDIUM' => 'MEDIUM',
        default => 'LOW',
    };
    return ['risk_level' => $risk, 'label' => $label];
}

/** @return array<string, int|string> */
function es_risk_from_toxicity(float $toxicity): array
{
    if ($toxicity >= 2.0) {
        return ['risk_level' => 'HIGH', 'label' => 'HIGH'];
    }
    if ($toxicity >= 1.0) {
        return ['risk_level' => 'MEDIUM', 'label' => 'MEDIUM'];
    }
    return ['risk_level' => 'SAFE', 'label' => 'LOW'];
}

/** @return array<string, mixed> */
function es_model_meta(): array
{
    $path = dirname(__DIR__) . '/ai_model/model_meta.json';
    $defaults = [
        'dataset' => 'hateXplain.csv',
        'model' => 'TF-IDF + LogisticRegression',
        'labels' => ['normal', 'offensive', 'hatespeech'],
        'accuracy_percent' => null,
    ];
    if (!is_readable($path)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return array_merge($defaults, $decoded);
}

function es_ai_engine_online(): bool
{
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents('http://127.0.0.1:5000/health', false, $ctx);
    if ($response === false) {
        return false;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) && ($decoded['status'] ?? '') === 'ok' && !empty($decoded['model_loaded']);
}

/** @return array{ok: bool, message: string} */
function es_get_ai_status(): array
{
    $modelPath = dirname(__DIR__) . '/ai_model/trained_model/cyberbullying_model.pkl';
    if (!is_readable($modelPath)) {
        return [
            'ok' => false,
            'message' => 'Trained model missing. Run: py -3 ai_model/training/train_model.py',
        ];
    }

    if (es_ai_engine_online()) {
        return ['ok' => true, 'message' => 'hateXplain ML model active (Flask :5000)'];
    }

    $probe = es_predict_via_python_cli('hello world');
    if ($probe !== null && ($probe['engine'] ?? '') === 'hateXplain-ml') {
        return ['ok' => true, 'message' => 'hateXplain ML model active (Python CLI)'];
    }

    return [
        'ok' => false,
        'message' => 'Start real AI: double-click ai_model/start_ai.bat OR run: py -3 -m pip install -r ai_model/requirements.txt',
    ];
}

/** @return array<string, mixed> */
function es_get_admin_stats(mysqli $conn): array
{
    $stats = [
        'total_reports' => 0,
        'high_risk' => 0,
        'medium_risk' => 0,
        'safe_count' => 0,
        'users_count' => 0,
        'offenders_count' => 0,
        'alerts_count' => 0,
        'threat_percent' => 0,
        'threat_label' => 'LOW',
        'ai_status' => es_ai_engine_online() ? 'ACTIVE (hateXplain)' : 'OFFLINE (fallback)',
    ];

    if (es_table_exists($conn, 'reports')) {
        $q = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM reports');
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $stats['total_reports'] = (int) $r['c'];
        }
        foreach (['HIGH' => 'high_risk', 'MEDIUM' => 'medium_risk', 'SAFE' => 'safe_count'] as $level => $key) {
            $levelEsc = mysqli_real_escape_string($conn, $level);
            $q2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM reports WHERE risk_level = '$levelEsc'");
            if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) {
                $stats[$key] = (int) $r2['c'];
            }
        }
        $gH = (int) $stats['high_risk'];
        $gT = (int) $stats['total_reports'];
        if ($gT > 0) {
            $stats['threat_percent'] = (int) round(($gH / $gT) * 100);
        }
    }

    if (es_table_exists($conn, 'users')) {
        $u = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM users');
        if ($u && ($r = mysqli_fetch_assoc($u))) {
            $stats['users_count'] = (int) $r['c'];
        }
    }

    if (es_table_exists($conn, 'harassers')) {
        $h = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM harassers');
        if ($h && ($r = mysqli_fetch_assoc($h))) {
            $stats['offenders_count'] = (int) $r['c'];
        }
    }

    if (es_table_exists($conn, 'alerts')) {
        $a = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM alerts');
        if ($a && ($r = mysqli_fetch_assoc($a))) {
            $stats['alerts_count'] = (int) $r['c'];
        }
    }

    if ($stats['threat_percent'] >= 40) {
        $stats['threat_label'] = 'HIGH';
    } elseif ($stats['threat_percent'] >= 15) {
        $stats['threat_label'] = 'MODERATE';
    }

    return $stats;
}

/** @return array<int, array<string, mixed>> */
function es_get_top_harassers(mysqli $conn, int $limit = 10): array
{
    if (!es_table_exists($conn, 'harassers')) {
        return [];
    }
    $limit = (int) $limit;
    $sql = "SELECT harasser_name, total_violations, created_at
            FROM harassers ORDER BY total_violations DESC, created_at DESC LIMIT $limit";
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
function es_get_recent_alerts(mysqli $conn, int $limit = 12): array
{
    if (!es_table_exists($conn, 'alerts')) {
        return [];
    }
    $limit = (int) $limit;
    $sql = "SELECT a.alert_id, a.alert_message, a.created_at, u.username
            FROM alerts a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.alert_id DESC LIMIT $limit";
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
function es_get_users_overview(mysqli $conn, int $limit = 15): array
{
    if (!es_table_exists($conn, 'users')) {
        return [];
    }
    $limit = (int) $limit;
    $sql = "SELECT id, username, email, status, created_at FROM users ORDER BY id DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
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
        'labels' => ['normal' => 0, 'offensive' => 0, 'hatespeech' => 0],
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

    foreach (['normal', 'offensive', 'hatespeech'] as $modelLabel) {
        $labelEsc = mysqli_real_escape_string($conn, $modelLabel);
        $qLabel = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM reports $where " . ($where ? 'AND' : 'WHERE') . " LOWER(severity_level) = '$labelEsc'"
        );
        if ($qLabel && ($r = mysqli_fetch_assoc($qLabel))) {
            $empty['labels'][$modelLabel] = (int) $r['c'];
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
            'reply' => "> AI ENGINE: hateXplain model (TF-IDF + LogisticRegression) @ :5000\nLabels: normal · offensive · hatespeech → SAFE / MEDIUM / HIGH",
        ];
    }

    return [
        'reply' => "> UNKNOWN QUERY\nTry: \"how to report\", \"safety tips\", \"wellness\", or \"sos\".",
    ];
}
