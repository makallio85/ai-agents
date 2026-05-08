<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->fetch('title') ?> — AI Agents</title>
    <?= $this->Html->meta('icon') ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #1a1d23;
            color: #adb5bd;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding: 0;
        }
        .sidebar .brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #2d3139;
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: .625rem 1.5rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: .625rem;
            font-size: .9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: #2d3139;
        }
        .sidebar .nav-section {
            padding: 1rem 1.5rem .25rem;
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6c757d;
        }

        .main-content {
            margin-left: 240px;
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: .75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .page-content {
            padding: 1.5rem;
        }
    </style>
    <?= $this->fetch('css') ?>
</head>
<body>

<nav class="sidebar d-flex flex-column">
    <a href="<?= $this->Url->build('/dashboard') ?>" class="brand">
        🤖 AI Agents
    </a>
    <div class="mt-2">
        <div class="nav-section">Main</div>
        <a href="<?= $this->Url->build('/dashboard') ?>"
           class="nav-link <?= $this->request->getParam('action') === 'index' && $this->request->getParam('controller') === 'Dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Agents</div>
        <a href="<?= $this->Url->build('/agents') ?>"
           class="nav-link <?= $this->request->getParam('controller') === 'Agents' ? 'active' : '' ?>">
            <i class="bi bi-cpu"></i> Agents
        </a>
        <a href="<?= $this->Url->build('/conversations') ?>"
           class="nav-link <?= $this->request->getParam('controller') === 'Conversations' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i> Conversations
        </a>

        <div class="nav-section">Settings</div>
        <a href="<?= $this->Url->build('/github-integrations') ?>"
           class="nav-link <?= $this->request->getParam('controller') === 'GithubIntegrations' ? 'active' : '' ?>">
            <i class="bi bi-github"></i> GitHub
        </a>
        <a href="<?= $this->Url->build('/labels') ?>"
           class="nav-link <?= $this->request->getParam('controller') === 'Labels' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Labels
        </a>
        <a href="<?= $this->Url->build('/logs') ?>"
           class="nav-link <?= $this->request->getParam('controller') === 'Logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Logs
        </a>
    </div>
    <div class="mt-auto border-top border-secondary p-3">
        <form method="post" action="<?= $this->Url->build('/auth/logout') ?>">
            <?= $this->Form->hidden('_csrfToken', ['value' => $this->request->getAttribute('csrfToken')]) ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-left"></i> Sign out
            </button>
        </form>
    </div>
</nav>

<div class="main-content">
    <div class="topbar d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><?= $this->fetch('title') ?></h6>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">
                <i class="bi bi-person-circle me-1"></i>
                <?= h($this->Identity->get('email') ?? '') ?>
            </span>
        </div>
    </div>

    <div class="page-content">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>var webroot = "<?= $this->Url->build('/') ?>";</script>
<?= $this->Html->script('vue/api') ?>
<?= $this->fetch('script') ?>
</body>
</html>
