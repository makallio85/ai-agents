<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Logs');
?>
<div id="logs-app">
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-medium mb-1">Agent</label>
                    <select v-model="filter.agentId" class="form-select form-select-sm">
                        <option value="">All agents</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-medium mb-1">Level</label>
                    <select v-model="filter.level" class="form-select form-select-sm">
                        <option value="">All levels</option>
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="debug">Debug</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-medium mb-1">Result</label>
                    <select v-model="filter.resultState" class="form-select form-select-sm">
                        <option value="">All results</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                        <option value="retried">Retried</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100" @click="loadLogs">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-semibold">Execution logs</h6>
            <button class="btn btn-sm btn-outline-secondary" @click="loadLogs">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div> Loading…
            </div>
            <div v-else-if="logs.length === 0" class="p-4 text-center text-muted">No logs found.</div>
            <div v-else class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Level</th>
                            <th>Agent</th>
                            <th>Message</th>
                            <th>Result</th>
                            <th>Duration</th>
                            <th>Execution ID</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id" :class="log.level === 'error' ? 'table-danger' : ''">
                            <td class="ps-3">
                                <span :class="levelBadgeClass(log.level)" class="badge">{{ log.level }}</span>
                            </td>
                            <td class="small">{{ log.agent?.name || log.agent_id }}</td>
                            <td class="small">
                                {{ log.message }}
                                <div v-if="log.error_message" class="text-danger" style="font-size:.7rem">{{ log.error_message }}</div>
                            </td>
                            <td class="small">{{ log.result_state || '—' }}</td>
                            <td class="small text-muted">{{ log.duration_ms != null ? log.duration_ms + 'ms' : '—' }}</td>
                            <td>
                                <code v-if="log.execution_id" style="font-size:.65rem">{{ log.execution_id.slice(0, 8) }}…</code>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="small text-muted">{{ formatDate(log.created) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->Html->script('vue/pages/Logs/index') ?>
