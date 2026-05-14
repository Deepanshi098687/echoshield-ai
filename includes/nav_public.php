<?php
if (!function_exists('es_url')) {
    require_once __DIR__ . '/assets.php';
}
?>
<header class="site-header">
    <a class="site-logo" href="<?php echo htmlspecialchars(es_url('index.php')); ?>">EchoShield</a>
    <nav class="site-nav">
        <a href="<?php echo htmlspecialchars(es_url('login.php')); ?>" class="btn-neon">Login</a>
        <a href="<?php echo htmlspecialchars(es_url('signup.php')); ?>" class="btn-neon btn-neon--accent">Sign Up</a>
    </nav>
</header>
