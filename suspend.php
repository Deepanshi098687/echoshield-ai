<?php

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

$id = $_GET['id'];

mysqli_query($conn,
"UPDATE users
SET status='Suspended'
WHERE id='$id'");

header('Location: ' . es_url('admin_dashboard.php'));
exit;

?>