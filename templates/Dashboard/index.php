<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Dashboard');
?>
<div id="dashboard-app">
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-primary bg-opacity-10 rounded p-3">
                            <i class="bi bi-cpu text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Agents</div>
                            <div class="fs-4 fw-bold">{{ stats.agents }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 rounded p-3">
                            <i class="bi bi-robot text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Active agents</div>
                            <div class="fs-4 fw-bold">{{ stats.activeAgents }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 rounded p-3">
                            <i class="bi bi-github text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">GitHub integrations</div>
                            <div class="fs-4 fw-bold">{{ stats.integrations }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                    <h6 class="mb-0 fw-semibold">Active agents</h6>
                    <a href="<?= $this->Url->build('/agents') ?>" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <div class="list-group list-group-flush">
                    <div v-if="loadingAgents" class="list-group-item text-center text-muted py-3">
                        <div class="spinner-border spinner-border-sm me-2"></div> Loading…
                    </div>
                    <div v-else-if="agents.length === 0" class="list-group-item text-center text-muted py-3">
                        No agents configured.
                    </div>
                    <a
                        v-else
                        v-for="agent in agents"
                        :key="agent.id"
                        :href="'<?= $this->Url->build('/agents/view/') ?>' + agent.id"
                        class="list-group-item list-group-item-action"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-cpu text-primary"></i>
                            <div>
                                <div class="fw-medium small">{{ agent.name }}</div>
                                <div class="text-muted" style="font-size:.75rem">{{ agent.plugin_name }}</div>
                            </div>
                            <span class="ms-auto badge" :class="agent.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ agent.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                    <h6 class="mb-0 fw-semibold">Quick links</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= $this->Url->build('/chat') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-robot me-2 text-primary"></i> Chat
                    </a>
                    <a href="<?= $this->Url->build('/github-integrations') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-github me-2 text-warning"></i> GitHub integrations
                    </a>
                    <a href="<?= $this->Url->build('/labels') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-tags me-2 text-info"></i> Labels
                    </a>
                    <a href="<?= $this->Url->build('/logs') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-journal-text me-2 text-secondary"></i> Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Dashboard/index')); ?>
