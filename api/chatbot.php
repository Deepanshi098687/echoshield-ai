<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once dirname(__DIR__) . '/includes/data_helpers.php';

$input = json_decode((string) file_get_contents('php://input'), true);
$message = is_array($input) ? trim((string) ($input['message'] ?? '')) : '';

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message required']);
    exit;
}

echo json_encode(es_chatbot_reply($message));
