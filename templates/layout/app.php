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
            z-index: 1040;
            padding: 0;
            transition: transform .2s ease;
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
        .sidebar .nav-link.sub {
            padding-left: 3rem;
            font-size: .85rem;
        }
        .sidebar .nav-link.sub-2 {
            padding-left: 4.25rem;
            font-size: .8rem;
        }
        .sidebar .nav-link.sub-3 {
            padding-left: 5.5rem;
            font-size: .78rem;
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

        /* Sidebar toggle button — only shown on mobile via d-lg-none */
        .sidebar-toggle {
            background: transparent;
            border: 0;
            padding: .25rem .5rem;
            font-size: 1.25rem;
            line-height: 1;
            color: #495057;
        }
        .sidebar-toggle:hover { color: #1a1d23; }

        /* Backdrop shown behind the slide-in sidebar on mobile */
        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 1030;
            display: none;
        }
        .sidebar-backdrop.show { display: block; }

        /* Mobile breakpoint: sidebar slides in over the page */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 0 0 20px rgba(0, 0, 0, .3);
            }
            .sidebar.show { transform: translateX(0); }

            .main-content { margin-left: 0; }
            .page-content { padding: 1rem; }
            .topbar { padding: .625rem 1rem; }
        }

        /* Very narrow screens: hide the email on the topbar to leave room
           for the page title; sidebar profile entry already shows it. */
        @media (max-width: 480px) {
            .topbar .topbar-email { display: none; }
        }
    </style>
    <?= $this->fetch('css') ?>
</head>
<body>

<nav class="sidebar d-flex flex-column" id="appSidebar">
    <a href="<?= $this->Url->build('/dashboard') ?>" class="brand">
        🤖 AI Agents
    </a>
    <?php
        // Nav structure (issue #14):
        //   Dashboard, Agents,
        //   User Management → Users / Permissions / Messaging Requests,
        //   Integrations → GitHub → Labels,
        //   Logging
        $controller = $this->request->getParam('controller');
        $action = $this->request->getParam('action');
    ?>
    <div class="mt-2">
        <a href="<?= $this->Url->build('/dashboard') ?>"
           class="nav-link <?= $controller === 'Dashboard' && $action === 'index' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <a href="<?= $this->Url->build('/agents') ?>"
           class="nav-link <?= $controller === 'Agents' ? 'active' : '' ?>">
            <i class="bi bi-cpu"></i> Agents
        </a>

        <div class="nav-section">User Management</div>
        <a href="<?= $this->Url->build('/users') ?>"
           class="nav-link sub <?= $controller === 'Users' ? 'active' : '' ?>">
            <i class="bi bi-person"></i> Users
        </a>
        <a href="<?= $this->Url->build('/settings/permissions') ?>"
           class="nav-link sub <?= $controller === 'Settings' ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> Permissions
        </a>
        <a href="<?= $this->Url->build('/messaging-requests') ?>"
           class="nav-link sub <?= $controller === 'MessagingRequests' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Messaging Requests
        </a>

        <div class="nav-section">Integrations</div>
        <a href="<?= $this->Url->build('/integrations') ?>"
           class="nav-link sub <?= $controller === 'Integrations' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> All integrations
        </a>
        <a href="<?= $this->Url->build('/github-integrations') ?>"
           class="nav-link sub-2 <?= $controller === 'GithubIntegrations' ? 'active' : '' ?>">
            <i class="bi bi-github"></i> GitHub
        </a>
        <a href="<?= $this->Url->build('/labels') ?>"
           class="nav-link sub-3 <?= $controller === 'Labels' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Labels
        </a>

        <div class="nav-section">Logging</div>
        <a href="<?= $this->Url->build('/logs') ?>"
           class="nav-link <?= $controller === 'Logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Logs
        </a>
    </div>
    <div class="mt-auto border-top border-secondary p-3">
        <a href="<?= $this->Url->build('/profile') ?>"
           class="nav-link px-0 mb-2 <?= $this->request->getParam('controller') === 'Profile' ? 'active' : '' ?>">
            <i class="bi bi-person-circle me-1"></i>
            <?= h($this->Identity->get('email') ?? '') ?>
        </a>
        <form method="post" action="<?= $this->Url->build('/auth/logout') ?>">
            <?= $this->Form->hidden('_csrfToken', ['value' => $this->request->getAttribute('csrfToken')]) ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-left"></i> Sign out
            </button>
        </form>
        <div class="mt-2 pt-2 border-top border-secondary" style="font-size:.68rem; color:#6c757d; line-height:1.4;">
            <i class="bi bi-git me-1"></i><code style="font-size:.68rem; color:#8b949e;"><?= h($gitHash) ?></code>
            <div class="text-truncate" title="<?= h($gitMessage) ?>"><?= h($gitMessage) ?></div>
        </div>
    </div>
</nav>

<!-- Backdrop sits between the sidebar and the page on mobile; tapping it
     closes the sidebar. Hidden by default; .show is toggled by sidebar.js. -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-content">
    <div class="topbar d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <button
                type="button"
                class="sidebar-toggle d-lg-none"
                id="sidebarToggle"
                aria-label="Toggle navigation"
                aria-controls="appSidebar"
                aria-expanded="false"
            >
                <i class="bi bi-list"></i>
            </button>
            <h6 class="mb-0 fw-semibold text-truncate"><?= $this->fetch('title') ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= $this->Url->build('/profile') ?>" class="text-muted small text-decoration-none topbar-email">
                <i class="bi bi-person-circle me-1"></i>
                <?= h($this->Identity->get('email') ?? '') ?>
            </a>
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
<script>
    // Mobile sidebar toggle. Bootstrap's offcanvas would also work, but we
    // already render the sidebar as a permanent <nav> on desktop, so a
    // lightweight CSS-class toggle keeps both modes driven by the same
    // markup. The toggle button and backdrop are hidden on >=lg via CSS.
    (function () {
        var toggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('appSidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (!toggle || !sidebar || !backdrop) return;

        function open() {
            sidebar.classList.add('show');
            backdrop.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
        }
        function close() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? close() : open();
        });
        backdrop.addEventListener('click', close);

        // Tapping a nav link on mobile should dismiss the sidebar so the
        // page underneath is visible after navigation.
        sidebar.querySelectorAll('a.nav-link, a.brand').forEach(function (el) {
            el.addEventListener('click', function () {
                if (window.innerWidth < 992) close();
            });
        });

        // If the viewport grows past the breakpoint while the sidebar is
        // open, drop the .show classes so desktop styles take over cleanly.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992 && sidebar.classList.contains('show')) {
                close();
            }
        });
    })();
</script>
<?= $this->Html->script('vue/api') ?>
<?= $this->fetch('script') ?>
</body>
</html>
