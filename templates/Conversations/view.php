<?php
/**
 * @var \App\View\AppView $this
 * @var int $conversationId
 */
$this->assign('title', 'Conversation details');
?>
<div id="conversation-view-app">
    <div v-if="loading" class="text-center py-5 text-muted">
        <div class="spinner-border"></div>
    </div>

    <template v-else-if="conversation">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="<?= $this->Url->build('/conversations') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h5 class="mb-0">{{ conversation.title || 'Untitled #' + conversation.id }}</h5>
                <span class="badge mt-1" :class="statusBadgeClass(conversation.status)">{{ conversation.status }}</span>
            </div>
            <div class="ms-auto text-muted small">
                {{ conversation.blocks_processed }} / {{ conversation.blocks_found }} blocks processed
            </div>
        </div>

        <!-- Parsing jobs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold">Issue parsing jobs</h6>
            </div>
            <div class="card-body p-0">
                <div v-if="jobs.length === 0" class="p-4 text-center text-muted">No jobs found.</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Title</th>
                                <th>Type</th>
                                <th>Labels</th>
                                <th>Status</th>
                                <th>GitHub issue</th>
                                <th>Attempts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="job in jobs" :key="job.id">
                                <td class="ps-4">
                                    <div class="fw-medium small">{{ parsedTitle(job) }}</div>
                                    <div class="text-muted" style="font-size:.75rem">Job #{{ job.id }}</div>
                                </td>
                                <td class="small">{{ parsedType(job) }}</td>
                                <td>
                                    <span
                                        v-for="label in parsedLabels(job)"
                                        :key="label"
                                        class="badge bg-secondary me-1"
                                    >{{ label }}</span>
                                </td>
                                <td>
                                    <span :class="jobStatusBadgeClass(job.status)" class="badge">{{ job.status }}</span>
                                </td>
                                <td>
                                    <a
                                        v-if="job.github_issue_url"
                                        :href="job.github_issue_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-decoration-none small"
                                    >
                                        <i class="bi bi-box-arrow-up-right me-1"></i>#{{ job.github_issue_number }}
                                    </a>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td class="small">{{ job.attempts }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Source text -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Source text</h6>
                <button class="btn btn-sm btn-outline-secondary" @click="showSource = !showSource">
                    {{ showSource ? 'Hide' : 'Show' }}
                </button>
            </div>
            <div v-if="showSource" class="card-body">
                <pre class="bg-light rounded p-3 small" style="max-height:400px;overflow:auto;white-space:pre-wrap">{{ conversation.source_text }}</pre>
            </div>
        </div>
    </template>

    <div v-else class="alert alert-warning">Conversation not found.</div>
</div>

<script>
    var ConversationViewConfig = { conversationId: <?= (int)$conversationId ?> };
</script>
<?php $this->append('script', $this->Html->script('vue/pages/Conversations/view')); ?>
