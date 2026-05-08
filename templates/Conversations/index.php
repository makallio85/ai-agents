<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Conversations');
?>
<div id="conversations-app">

    <!-- Paste new conversation -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-plus-circle me-2 text-primary"></i>New conversation
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Agent</label>
                    <select v-model="form.agentId" class="form-select" :disabled="submitting">
                        <option value="">Select agent…</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-medium">Title (optional)</label>
                    <input v-model="form.title" type="text" class="form-control" placeholder="e.g. Sprint 42 issues" :disabled="submitting" />
                </div>
                <div class="col-12">
                    <label class="form-label small fw-medium">
                        Conversation text
                        <span class="text-muted fw-normal">— paste the full ChatGPT/OpenAI conversation</span>
                    </label>
                    <textarea
                        v-model="form.sourceText"
                        class="form-control font-monospace"
                        rows="10"
                        placeholder="Paste conversation here. Issue blocks must be delimited by:&#10;=== ISSUE START ===&#10;...&#10;=== ISSUE END ==="
                        :disabled="submitting"
                    ></textarea>
                    <div class="form-text">
                        Detected blocks: <strong>{{ detectedBlocks }}</strong>
                    </div>
                </div>
                <div v-if="submitError" class="col-12">
                    <div class="alert alert-danger mb-0 py-2">{{ submitError }}</div>
                </div>
                <div class="col-12">
                    <button
                        class="btn btn-primary"
                        @click="submitConversation"
                        :disabled="submitting || !form.agentId || !form.sourceText.trim()"
                    >
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                        {{ submitting ? 'Processing…' : 'Process conversation' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conversation list -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-semibold">All conversations</h6>
            <button class="btn btn-sm btn-outline-secondary" @click="loadConversations">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div> Loading…
            </div>
            <div v-else-if="conversations.length === 0" class="p-4 text-center text-muted">
                No conversations yet. Paste one above.
            </div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Title</th>
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Blocks</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="conv in conversations" :key="conv.id">
                            <td class="ps-4">
                                <a :href="'/conversations/view/' + conv.id" class="text-decoration-none fw-medium">
                                    {{ conv.title || 'Untitled #' + conv.id }}
                                </a>
                            </td>
                            <td class="text-muted small">{{ conv.agent?.name || '—' }}</td>
                            <td>
                                <span :class="statusBadgeClass(conv.status)" class="badge">
                                    {{ conv.status }}
                                </span>
                            </td>
                            <td>{{ conv.blocks_processed }} / {{ conv.blocks_found }}</td>
                            <td class="text-muted small">{{ formatDate(conv.created) }}</td>
                            <td>
                                <button
                                    class="btn btn-sm btn-outline-danger"
                                    @click="deleteConversation(conv.id)"
                                    title="Delete"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->Html->script('vue/pages/Conversations/index') ?>
