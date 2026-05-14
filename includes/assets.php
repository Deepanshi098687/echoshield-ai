<?php
declare(strict_types=1);

/**
 * Directory URL for the running script (always ends with /).
 * Fixes broken relative css/js when the browser URL omits a trailing slash
 * (e.g. http://localhost/EchoShield-AI vs .../EchoShield-AI/).
 */
function es_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $script = '/' . ltrim(str_replace('\\', '/', $script), '/');
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '/';
    }
    return rtrim($dir, '/') . '/';
}

/** Root-anchored path to a file under the app folder (css, js, php links). */
function es_url(string $relative_path): string
{
    $relative_path = ltrim($relative_path, '/');
    $base = es_base_path();
    if ($base === '/') {
        return '/' . $relative_path;
    }
    return rtrim($base, '/') . '/' . $relative_path;
}
