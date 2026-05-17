<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'GitHub Integrations');
?>
<div id="github-app" v-cloak>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <p class="text-muted mb-0 small">Connect your GitHub repositories so agents can create issues automatically.</p>
        <button class="btn btn-primary btn-sm" @click="showCreateModal = true">
            <i class="bi bi-plus-lg me-1"></i> Add integration
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div> Loading…
            </div>
            <div v-else-if="integrations.length === 0" class="p-4 text-center text-muted">
                No GitHub integrations yet.
            </div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Repository</th>
                            <th>Status</th>
                            <th>Last used</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="integration in integrations" :key="integration.id">
                            <td class="ps-4">
                                <i class="bi bi-github me-2 text-muted"></i>
                                <strong>{{ integration.repo_owner }}</strong>/{{ integration.repo_name }}
                            </td>
                            <td>
                                <span :class="integration.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ integration.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ integration.last_used_at ? formatDate(integration.last_used_at) : 'Never' }}</td>
                            <td class="text-muted small">{{ formatDate(integration.created) }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" @click="deleteIntegration(integration.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create modal -->
    <div v-if="showCreateModal" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add GitHub integration</h5>
                    <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                </div>
                <div class="modal-body">
                    <div v-if="createError" class="alert alert-danger py-2">{{ createError }}</div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Repository owner</label>
                        <input v-model="newIntegration.repo_owner" type="text" class="form-control" placeholder="octocat" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Repository name</label>
                        <input v-model="newIntegration.repo_name" type="text" class="form-control" placeholder="my-repo" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            Personal Access Token
                            <span class="text-muted fw-normal">— stored encrypted</span>
                        </label>
                        <input v-model="newIntegration.token" type="password" class="form-control" placeholder="ghp_…" autocomplete="off" />
                    </div>
                    <div class="form-check">
                        <input v-model="newIntegration.is_active" class="form-check-input" type="checkbox" id="int-active" />
                        <label class="form-check-label" for="int-active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showCreateModal = false">Cancel</button>
                    <button class="btn btn-primary" @click="createIntegration" :disabled="creating">
                        <span v-if="creating" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/GithubIntegrations/index')); ?>
