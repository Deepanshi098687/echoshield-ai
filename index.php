<?php
require_once __DIR__ . '/includes/assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>EchoShield AI — Cyberbullying Detection</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-home">

<header class="site-header">
    <a class="site-logo" href="<?php echo htmlspecialchars(es_url('index.php')); ?>">EchoShield</a>
    <nav class="site-nav">
        <a href="<?php echo htmlspecialchars(es_url('login.php')); ?>" class="btn-neon">Login</a>
        <a href="<?php echo htmlspecialchars(es_url('signup.php')); ?>" class="btn-neon btn-neon--accent">Sign Up</a>
    </nav>
</header>

<main>
    <section class="hero-section">
        <div class="hero-inner">
            <p class="hero-kicker">Secure · Intelligent · Real-time</p>
            <h1 class="typewriter-line" aria-live="polite">
                <span id="typewriterText" class="brand-glow"></span><span id="typewriterCursor" class="typewriter-cursor" aria-hidden="true"></span>
            </h1>
            <p class="hero-lead">
                <strong>AI-powered</strong> cyberbullying detection and prevention.
                NLP-driven analysis to flag harmful content before it spreads.
            </p>
            <div class="buttons">
                <a href="<?php echo htmlspecialchars(es_url('login.php')); ?>" class="btn-neon">Enter platform</a>
                <a href="<?php echo htmlspecialchars(es_url('signup.php')); ?>" class="btn-neon btn-neon--accent">Create account</a>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <h2 class="text-center mb-0">Capabilities</h2>
            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="feature-card">
                        <h4>AI detection</h4>
                        <p>Real-time scoring of toxic language and context-aware signals.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <h4>Victim reporting</h4>
                        <p>Structured reports with evidence capture for faster response.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <h4>Admin monitoring</h4>
                        <p>Dashboards and alerts for teams overseeing community safety.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    EchoShield AI · <a href="<?php echo htmlspecialchars(es_url('login.php')); ?>">Login</a> · <a href="<?php echo htmlspecialchars(es_url('signup.php')); ?>">Sign up</a>
</footer>

<script src="<?php echo htmlspecialchars(es_url('js/script.js')); ?>"></script>
</body>
</html>
