<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Labels');
?>
<div id="labels-app" v-cloak>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <p class="text-muted mb-0 small">Labels are used to automatically categorise GitHub issues parsed by agents.</p>
        <button class="btn btn-primary btn-sm" @click="showCreateModal = true">
            <i class="bi bi-plus-lg me-1"></i> New label
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div> Loading…
            </div>
            <div v-else-if="labels.length === 0" class="p-4 text-center text-muted">No labels yet.</div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Label</th>
                            <th>Slug</th>
                            <th>Keywords</th>
                            <th>Description</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="label in labels" :key="label.id">
                            <td class="ps-4">
                                <span
                                    class="badge rounded-pill"
                                    :style="{ background: label.color, color: contrastColor(label.color) }"
                                >
                                    {{ label.name }}
                                </span>
                            </td>
                            <td><code class="small">{{ label.slug }}</code></td>
                            <td class="small text-muted">{{ parseKeywords(label.keywords).join(', ') || '—' }}</td>
                            <td class="small text-muted">{{ label.description || '—' }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" @click="deleteLabel(label.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create label modal -->
    <div v-if="showCreateModal" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New label</h5>
                    <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                </div>
                <div class="modal-body">
                    <div v-if="createError" class="alert alert-danger py-2">{{ createError }}</div>
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label small fw-medium">Name</label>
                            <input v-model="newLabel.name" type="text" class="form-control" placeholder="bug" />
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium">Color</label>
                            <input v-model="newLabel.color" type="color" class="form-control form-control-color w-100" />
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Slug</label>
                            <input v-model="newLabel.slug" type="text" class="form-control" placeholder="bug" />
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <input v-model="newLabel.description" type="text" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">
                                Keywords
                                <span class="text-muted fw-normal">— comma-separated</span>
                            </label>
                            <input v-model="newLabel.keywordsRaw" type="text" class="form-control" placeholder="error, crash, exception" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showCreateModal = false">Cancel</button>
                    <button class="btn btn-primary" @click="createLabel" :disabled="creating">
                        <span v-if="creating" class="spinner-border spinner-border-sm me-1"></span>
                        Create
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Labels/index')); ?>
