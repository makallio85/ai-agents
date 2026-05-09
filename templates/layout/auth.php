<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->fetch('title') ?> — AI Agents</title>
    <?= $this->Html->meta('icon') ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .auth-card { max-width: 420px; }
        .letter-spacing-wide { letter-spacing: .25em; }
        .brand-icon { font-size: 2rem; }
    </style>
    <?= $this->fetch('css') ?>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="auth-card w-100 px-3">
        <div class="text-center mb-4">
            <div class="brand-icon mb-2">🤖</div>
            <h4 class="fw-bold mb-0">AI Agents</h4>
            <p class="text-muted small">Intelligent automation platform</p>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?= $this->fetch('content') ?>
            </div>
        </div>

        <p class="text-center text-muted small mt-3">
            &copy; <?= date('Y') ?> AI Agents Platform
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script>var webroot = "<?= $this->Url->build('/') ?>";</script>
    <?= $this->fetch('script') ?>
</body>
</html>
