<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Dashboard');
?>
<div id="dashboard-app">
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
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
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 rounded p-3">
                            <i class="bi bi-chat-dots text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Conversations</div>
                            <div class="fs-4 fw-bold">{{ stats.conversations }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 rounded p-3">
                            <i class="bi bi-github text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Issues created</div>
                            <div class="fs-4 fw-bold">{{ stats.issues }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-danger bg-opacity-10 rounded p-3">
                            <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Failed jobs</div>
                            <div class="fs-4 fw-bold">{{ stats.failed }}</div>
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
                    <h6 class="mb-0 fw-semibold">Recent conversations</h6>
                    <a href="/conversations" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <div class="card-body p-0">
                    <div v-if="loadingConversations" class="p-4 text-center text-muted">
                        <div class="spinner-border spinner-border-sm me-2"></div> Loading…
                    </div>
                    <div v-else-if="conversations.length === 0" class="p-4 text-center text-muted">
                        No conversations yet.
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Title</th>
                                    <th>Status</th>
                                    <th>Blocks</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="conv in conversations" :key="conv.id">
                                    <td class="ps-4">
                                        <a :href="'/conversations/view/' + conv.id" class="text-decoration-none fw-medium">
                                            {{ conv.title || 'Untitled #' + conv.id }}
                                        </a>
                                    </td>
                                    <td>
                                        <span :class="statusBadgeClass(conv.status)" class="badge">
                                            {{ conv.status }}
                                        </span>
                                    </td>
                                    <td>{{ conv.blocks_processed }} / {{ conv.blocks_found }}</td>
                                    <td class="text-muted small">{{ formatDate(conv.created) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                    <h6 class="mb-0 fw-semibold">Active agents</h6>
                    <a href="/agents" class="btn btn-sm btn-outline-primary">View all</a>
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
                        :href="'/agents/view/' + agent.id"
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
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Dashboard/index')); ?>
