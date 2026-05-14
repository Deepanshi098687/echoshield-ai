<?php

session_start();

require_once __DIR__ . '/includes/assets.php';

session_destroy();

header('Location: ' . es_url('login.php'));
exit;

?>
