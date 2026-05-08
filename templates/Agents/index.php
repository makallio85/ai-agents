<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Agents');
?>
<div id="agents-app">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div></div>
        <button class="btn btn-primary btn-sm" @click="showCreateModal = true">
            <i class="bi bi-plus-lg me-1"></i> New agent
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div> Loading…
            </div>
            <div v-else-if="agents.length === 0" class="p-4 text-center text-muted">
                No agents configured.
            </div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Plugin</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="agent in agents" :key="agent.id">
                            <td class="ps-4">
                                <a :href="'/agents/view/' + agent.id" class="text-decoration-none fw-medium">
                                    {{ agent.name }}
                                </a>
                            </td>
                            <td><code class="small">{{ agent.plugin_name }}</code></td>
                            <td class="text-muted small">{{ agent.description || '—' }}</td>
                            <td>
                                <span :class="agent.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ agent.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ formatDate(agent.created) }}</td>
                            <td>
                                <a :href="'/agents/view/' + agent.id" class="btn btn-sm btn-outline-secondary me-1">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create agent modal -->
    <div v-if="showCreateModal" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New agent</h5>
                    <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                </div>
                <div class="modal-body">
                    <div v-if="createError" class="alert alert-danger py-2">{{ createError }}</div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Name</label>
                        <input v-model="newAgent.name" type="text" class="form-control" placeholder="My Agent" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Plugin name</label>
                        <input v-model="newAgent.plugin_name" type="text" class="form-control" placeholder="DevOpsOrchestrator" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Description</label>
                        <textarea v-model="newAgent.description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input v-model="newAgent.is_active" class="form-check-input" type="checkbox" id="agent-active" />
                        <label class="form-check-label" for="agent-active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showCreateModal = false">Cancel</button>
                    <button class="btn btn-primary" @click="createAgent" :disabled="creating">
                        <span v-if="creating" class="spinner-border spinner-border-sm me-1"></span>
                        Create
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Agents/index')); ?>
