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
            <a href="<?= $this->Url->build('/agents') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h5 class="mb-0">{{ agent.name }}</h5>
                <code class="text-muted small">{{ agent.plugin }}</code>
            </div>
            <span class="badge ms-2" :class="agent.is_enabled ? 'bg-success' : 'bg-secondary'">
                {{ agent.is_enabled ? 'Active' : 'Inactive' }}
            </span>
            <a :href="'<?= $this->Url->build('/chat') ?>?agent_id=' + agent.id"
               class="btn btn-sm btn-primary ms-auto">
                <i class="bi bi-chat-dots me-1"></i> Start chat
            </a>
            <button class="btn btn-sm btn-outline-primary ms-2" @click="toggleEdit">
                <i class="bi" :class="editing ? 'bi-x-lg' : 'bi-pencil'"></i>
                {{ editing ? 'Cancel' : 'Edit' }}
            </button>
        </div>

        <!-- Edit form -->
        <div v-if="editing" class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold">Edit agent</h6>
            </div>
            <div class="card-body">
                <div v-if="saveError" class="alert alert-danger py-2 mb-3">{{ saveError }}</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Name</label>
                        <input v-model="form.name" type="text" class="form-control" :disabled="saving" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Description</label>
                        <input v-model="form.description" type="text" class="form-control" :disabled="saving" />
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-medium">LLM provider</label>
                        <select v-model="form.llm_provider" class="form-select" :disabled="saving">
                            <option value="">— none —</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="ollama">Ollama (local)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">LLM model</label>
                        <input v-model="form.llm_model" type="text" class="form-control"
                               :placeholder="modelPlaceholder" :disabled="saving" />
                        <div class="form-text">{{ modelHint }}</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input v-model="form.is_enabled" class="form-check-input" type="checkbox" id="edit-is-enabled" :disabled="saving" />
                            <label class="form-check-label small" for="edit-is-enabled">Enabled</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-medium">
                            System instructions
                            <span class="text-muted fw-normal">— prepended to every conversation as system prompt</span>
                        </label>
                        <textarea v-model="form.instructions" class="form-control font-monospace" rows="6"
                                  placeholder="You are a helpful assistant…" :disabled="saving"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-medium">
                            Config overrides
                            <span class="text-muted fw-normal">— JSON, e.g. {"temperature":0.7,"max_tokens":2048}</span>
                        </label>
                        <textarea v-model="form.config" class="form-control font-monospace" rows="3"
                                  placeholder="{}" :disabled="saving"></textarea>
                        <div v-if="configError" class="form-text text-danger">{{ configError }}</div>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary" @click="save" :disabled="saving || !!configError">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save changes
                    </button>
                    <button class="btn btn-outline-secondary ms-2" @click="toggleEdit" :disabled="saving">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Read-only details -->
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">Details</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-5 text-muted fw-normal">ID</dt>
                            <dd class="col-sm-7">{{ agent.id }}</dd>
                            <dt class="col-sm-5 text-muted fw-normal">LLM provider</dt>
                            <dd class="col-sm-7">
                                <span v-if="agent.llm_provider" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                    {{ agent.llm_provider }}
                                </span>
                                <span v-else class="text-danger small">Not configured</span>
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">LLM model</dt>
                            <dd class="col-sm-7">
                                <code v-if="agent.llm_model" class="small">{{ agent.llm_model }}</code>
                                <span v-else class="text-muted">—</span>
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">Description</dt>
                            <dd class="col-sm-7">{{ agent.description || '—' }}</dd>
                            <dt class="col-sm-5 text-muted fw-normal">Instructions</dt>
                            <dd class="col-sm-7">
                                <span v-if="agent.instructions" class="text-muted" style="font-size:.75rem;white-space:pre-wrap">{{ agent.instructions.slice(0, 120) }}{{ agent.instructions.length > 120 ? '…' : '' }}</span>
                                <span v-else class="text-muted">—</span>
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">Config</dt>
                            <dd class="col-sm-7">
                                <pre v-if="agent.config" class="bg-light rounded p-2 mb-0" style="font-size:.7rem">{{ formatJson(agent.config) }}</pre>
                                <span v-else class="text-muted">—</span>
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">Created</dt>
                            <dd class="col-sm-7">{{ formatDate(agent.created) }}</dd>
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

    <!-- WhatsApp configuration -->
    <div v-if="agent" class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-whatsapp text-success me-1"></i> WhatsApp</h6>
                    <span class="ms-3 small text-muted">One Meta phone number per agent. The Meta App secret is shared across all agents and lives in env (<code>WHATSAPP_APP_SECRET</code>).</span>
                    <button v-if="!whatsappEditing" class="btn btn-sm btn-outline-primary ms-auto" @click="openWhatsappEdit" :disabled="loadingWhatsapp">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="loadingWhatsapp" class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                    <div v-else>
                        <div v-if="whatsappError" class="alert alert-danger py-2 small mb-3">{{ whatsappError }}</div>
                        <div v-if="!whatsapp.has_global_app_secret" class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>WHATSAPP_APP_SECRET</strong> is not set in the environment. Webhook signature verification will fail until it is configured.
                        </div>

                        <div v-if="!whatsappEditing" class="row g-3 small">
                            <div class="col-md-4"><div class="text-muted">Phone number ID</div><div class="fw-medium">{{ whatsapp.phone_number_id || '—' }}</div></div>
                            <div class="col-md-3"><div class="text-muted">Display number</div><div class="fw-medium">{{ whatsapp.display_number || '—' }}</div></div>
                            <div class="col-md-2"><div class="text-muted">Access token</div><div class="fw-medium">{{ whatsapp.access_token_set ? 'set' : 'not set' }}</div></div>
                            <div class="col-md-2"><div class="text-muted">Welcome template</div><div class="fw-medium">{{ whatsapp.welcome_template_name || '—' }}</div></div>
                            <div class="col-md-1"><div class="text-muted">Enabled</div><span class="badge" :class="whatsapp.enabled ? 'bg-success' : 'bg-secondary'">{{ whatsapp.enabled ? 'Yes' : 'No' }}</span></div>
                        </div>

                        <div v-else class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Phone number ID</label>
                                <input v-model="whatsappForm.phone_number_id" type="text" class="form-control" placeholder="123456789012345" :disabled="savingWhatsapp" />
                                <div class="form-text small">Meta &rarr; WhatsApp Manager &rarr; Phone Numbers</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Display number</label>
                                <input v-model="whatsappForm.display_number" type="text" class="form-control" placeholder="+358401234567" :disabled="savingWhatsapp" />
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-medium">Access token <span class="text-muted">(leave blank to keep existing)</span></label>
                                <input v-model="whatsappForm.access_token" type="password" class="form-control" :placeholder="whatsapp.access_token_set ? '•••• already set ••••' : 'EAAG...'" :disabled="savingWhatsapp" autocomplete="off" />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Welcome template name</label>
                                <input v-model="whatsappForm.welcome_template_name" type="text" class="form-control" placeholder="agent_notice" :disabled="savingWhatsapp" />
                                <div class="form-text small">Approved Meta template used for proactive sends outside the 24h window.</div>
                            </div>
                            <div class="col-md-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input v-model="whatsappForm.enabled" type="checkbox" class="form-check-input" id="whatsapp-enabled" :disabled="savingWhatsapp" />
                                    <label class="form-check-label small" for="whatsapp-enabled">Enabled</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2 pt-2">
                                <button class="btn btn-sm btn-primary" @click="saveWhatsapp" :disabled="savingWhatsapp">
                                    <span v-if="savingWhatsapp" class="spinner-border spinner-border-sm me-1"></span>
                                    Save
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="cancelWhatsapp" :disabled="savingWhatsapp">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slack configuration -->
    <div v-if="agent" class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-slack text-primary me-1"></i> Slack</h6>
                    <span class="ms-3 small text-muted">One Slack App per agent. Bot token and signing secret are encrypted at rest.</span>
                    <button v-if="!slackEditing" class="btn btn-sm btn-outline-primary ms-auto" @click="openSlackEdit" :disabled="loadingSlack">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="loadingSlack" class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                    <div v-else>
                        <div v-if="slackError" class="alert alert-danger py-2 small mb-3">{{ slackError }}</div>

                        <div v-if="!slackEditing" class="row g-3 small">
                            <div class="col-md-3"><div class="text-muted">App ID</div><div class="fw-medium">{{ slack.app_id || '—' }}</div></div>
                            <div class="col-md-3"><div class="text-muted">Bot user ID</div><div class="fw-medium">{{ slack.bot_user_id || '—' }}</div></div>
                            <div class="col-md-2"><div class="text-muted">Bot token</div><div class="fw-medium">{{ slack.bot_token_set ? 'set' : 'not set' }}</div></div>
                            <div class="col-md-2"><div class="text-muted">Signing secret</div><div class="fw-medium">{{ slack.signing_secret_set ? 'set' : 'not set' }}</div></div>
                            <div class="col-md-1"><div class="text-muted">Workspace</div><div class="fw-medium">{{ slack.team_id || '—' }}</div></div>
                            <div class="col-md-1"><div class="text-muted">Enabled</div><span class="badge" :class="slack.enabled ? 'bg-success' : 'bg-secondary'">{{ slack.enabled ? 'Yes' : 'No' }}</span></div>
                        </div>

                        <div v-else class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">App ID</label>
                                <input v-model="slackForm.app_id" type="text" class="form-control" placeholder="A0XXXXXXX" :disabled="savingSlack" />
                                <div class="form-text small">Slack App config &rarr; Basic Information &rarr; App ID</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Bot user ID</label>
                                <input v-model="slackForm.bot_user_id" type="text" class="form-control" placeholder="U0XXXXXXX" :disabled="savingSlack" />
                                <div class="form-text small">
                                    Run <code>curl -s -H "Authorization: Bearer xoxb-your-token" https://slack.com/api/auth.test</code> — the <code>user_id</code> field is your Bot user ID. The response also contains <code>app_id</code> and <code>team_id</code>.
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Bot token <span class="text-muted">(leave blank to keep existing)</span></label>
                                <input v-model="slackForm.bot_token" type="password" class="form-control" :placeholder="slack.bot_token_set ? '•••• already set ••••' : 'xoxb-...'" :disabled="savingSlack" autocomplete="off" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Signing secret <span class="text-muted">(leave blank to keep existing)</span></label>
                                <input v-model="slackForm.signing_secret" type="password" class="form-control" :placeholder="slack.signing_secret_set ? '•••• already set ••••' : 'Slack Basic Information &rarr; Signing Secret'" :disabled="savingSlack" autocomplete="off" />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-medium">Default workspace (team_id)</label>
                                <input v-model="slackForm.team_id" type="text" class="form-control" placeholder="T0XXXXXXX" :disabled="savingSlack" />
                                <div class="form-text small">Optional — only for proactive sends to a specific workspace.</div>
                            </div>
                            <div class="col-md-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input v-model="slackForm.enabled" type="checkbox" class="form-check-input" id="slack-enabled" :disabled="savingSlack" />
                                    <label class="form-check-label small" for="slack-enabled">Enabled</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2 pt-2">
                                <button class="btn btn-sm btn-primary" @click="saveSlack" :disabled="savingSlack">
                                    <span v-if="savingSlack" class="spinner-border spinner-border-sm me-1"></span>
                                    Save
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="cancelSlack" :disabled="savingSlack">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="alert alert-warning">Agent not found.</div>
</div>

<script>
    var AgentViewConfig = { agentId: <?= (int)$agentId ?> };
</script>
<?php $this->append('script', $this->Html->script('vue/pages/Agents/view')); ?>
