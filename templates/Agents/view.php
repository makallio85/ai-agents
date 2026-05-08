<?php
/**
 * @var \App\View\AppView $this
 * @var int $agentId
 */
$this->assign('title', 'Agent details');
?>
<div id="agent-view-app">
    <div v-if="loading" class="text-center py-5 text-muted">
        <div class="spinner-border"></div>
    </div>

    <template v-else-if="agent">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="/agents" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h5 class="mb-0">{{ agent.name }}</h5>
                <code class="text-muted small">{{ agent.plugin_name }}</code>
            </div>
            <span class="badge ms-2" :class="agent.is_active ? 'bg-success' : 'bg-secondary'">
                {{ agent.is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">Details</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted fw-normal">ID</dt>
                            <dd class="col-sm-8">{{ agent.id }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">Description</dt>
                            <dd class="col-sm-8">{{ agent.description || '—' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">Config</dt>
                            <dd class="col-sm-8">
                                <pre v-if="agent.config" class="bg-light rounded p-2 mb-0" style="font-size:.7rem">{{ formatJson(agent.config) }}</pre>
                                <span v-else class="text-muted">—</span>
                            </dd>
                            <dt class="col-sm-4 text-muted fw-normal">Created</dt>
                            <dd class="col-sm-8">{{ formatDate(agent.created) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">Execution logs</h6>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="loadingLogs" class="p-4 text-center text-muted">
                            <div class="spinner-border spinner-border-sm me-1"></div> Loading…
                        </div>
                        <div v-else-if="logs.length === 0" class="p-4 text-center text-muted">No logs yet.</div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Level</th>
                                        <th>Message</th>
                                        <th>Result</th>
                                        <th>Duration</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="log in logs" :key="log.id">
                                        <td class="ps-3">
                                            <span :class="levelBadgeClass(log.level)" class="badge">{{ log.level }}</span>
                                        </td>
                                        <td class="small">{{ log.message }}</td>
                                        <td class="small">{{ log.result_state || '—' }}</td>
                                        <td class="small text-muted">{{ log.duration_ms != null ? log.duration_ms + 'ms' : '—' }}</td>
                                        <td class="small text-muted">{{ formatDate(log.created) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div v-else class="alert alert-warning">Agent not found.</div>
</div>

<script>
    var AgentViewConfig = { agentId: <?= (int)$agentId ?> };
</script>
<?= $this->Html->script('vue/pages/Agents/view') ?>
