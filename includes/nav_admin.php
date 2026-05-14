<?php
if (!function_exists('es_url')) {
    require_once __DIR__ . '/assets.php';
}
?>
<header class="site-header">
    <a class="site-logo" href="<?php echo htmlspecialchars(es_url('index.php')); ?>">EchoShield</a>
    <nav class="site-nav">
        <a href="<?php echo htmlspecialchars(es_url('admin_dashboard.php')); ?>" class="btn-neon">Admin</a>
        <a href="<?php echo htmlspecialchars(es_url('analytics.php')); ?>" class="btn-neon btn-neon--accent">Analytics</a>
        <a href="<?php echo htmlspecialchars(es_url('index.php')); ?>" class="btn-neon">Home</a>
    </nav>
</header>
