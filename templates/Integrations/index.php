<?php
/**
 * @var \App\View\AppView $this
 *
 * Integrations landing page. Lists every external service the platform
 * integrates with. Each card links to that integration's configuration
 * page; sub-resources (e.g. Labels under GitHub) are listed inline.
 */
$this->assign('title', 'Integrations');
?>

<div class="d-flex align-items-center mb-3">
    <div>
        <h5 class="mb-0">Integrations</h5>
        <small class="text-muted">External services connected to the platform.</small>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-github fs-3 me-2"></i>
                    <h6 class="mb-0 fw-semibold">GitHub</h6>
                </div>
                <p class="text-muted small mb-3">
                    Connect repositories so agents can read issues, open pull
                    requests, and synchronise labels.
                </p>
                <div class="d-flex flex-column gap-1">
                    <a href="<?= $this->Url->build('/github-integrations') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-gear me-1"></i> Manage GitHub integrations
                    </a>
                    <a href="<?= $this->Url->build('/labels') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-tags me-1"></i> Labels
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
