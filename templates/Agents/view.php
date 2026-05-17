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

    <!--
        Integration permissions — per-agent grant set (issue #9). Each
        integration shows its catalog of named actions as a checklist; the
        deny-all default is enforced server-side (no row = no permission).
        The catalog is rendered from server data so adding a new action does
        not require a frontend redeploy.
    -->
    <div v-if="agent" class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-lock text-primary me-1"></i> Integration permissions</h6>
                    <span class="ms-3 small text-muted">What this agent is allowed to do against each integration. Deny by default.</span>
                </div>
                <div class="card-body">
                    <div v-if="loadingPermissions" class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                    <div v-else-if="permissionsError" class="alert alert-danger py-2 small mb-0">{{ permissionsError }}</div>
                    <div v-else-if="!permissionsCatalogEntries.length" class="text-muted small">No integrations are gated by permissions yet.</div>
                    <div v-else>
                        <div v-if="permissionsSaved" class="alert alert-success py-2 small mb-3">
                            <i class="bi bi-check-circle me-1"></i> Permissions saved.
                        </div>
                        <template v-for="(group, gIdx) in permissionsCatalogEntries" :key="group.integration">
                            <div :class="gIdx > 0 ? 'mt-4 pt-4 border-top' : ''">
                                <h6 class="mb-3 fw-semibold text-uppercase small text-muted">{{ group.integration }}</h6>
                                <div class="row g-2">
                                    <div v-for="item in group.actions" :key="item.action" class="col-md-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :id="'perm-' + item.action"
                                                v-model="permissionsGranted[item.action]"
                                                :disabled="savingPermissions"
                                            />
                                            <label class="form-check-label" :for="'perm-' + item.action">
                                                <span class="fw-medium">{{ item.label }}</span>
                                                <code class="ms-2 small text-muted">{{ item.action }}</code>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-sm btn-primary" @click="savePermissions" :disabled="savingPermissions || !permissionsDirty">
                                <span v-if="savingPermissions" class="spinner-border spinner-border-sm me-1"></span>
                                Save permissions
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" @click="resetPermissions" :disabled="savingPermissions || !permissionsDirty">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!--
        Message Channels — unified panel for per-agent channel configuration
        (issue #15). Each channel sub-section reads its data from the same
        /api/v1/message-channels endpoint and renders its own form. To add a
        new channel type, add a v-if block here and a MessageChannelInterface
        implementation in src/Channels/.
    -->
    <div v-if="agent" class="row g-4 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-broadcast text-primary me-1"></i> Message channels</h6>
                    <span class="ms-3 small text-muted">Per-agent configuration for each delivery channel.</span>
                </div>
                <div class="card-body">
                    <div v-if="loadingChannels" class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                    <div v-else-if="channelsError" class="alert alert-danger py-2 small mb-0">{{ channelsError }}</div>
                    <div v-else-if="!channels.length" class="text-muted small">No channel types registered.</div>
                    <div v-else>
                        <template v-for="(ch, idx) in channels" :key="ch.key">
                            <div :class="idx > 0 ? 'mt-4 pt-4 border-top' : ''">

                                <!-- WhatsApp sub-panel -->
                                <div v-if="ch.key === 'whatsapp'">
                                    <div class="d-flex align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold"><i class="bi bi-whatsapp text-success me-1"></i> {{ ch.label }}</h6>
                                        <span class="ms-3 small text-muted">{{ ch.description }}</span>
                                        <button v-if="!channelEditing[ch.key]" class="btn btn-sm btn-outline-primary ms-auto" @click="openEdit(ch)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </div>
                                    <div v-if="channelErrors[ch.key]" class="alert alert-danger py-2 small mb-3">{{ channelErrors[ch.key] }}</div>
                                    <div v-if="!ch.config.has_global_app_secret" class="alert alert-warning py-2 small mb-3">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <strong>WHATSAPP_APP_SECRET</strong> is not set in the environment. Webhook signature verification will fail until it is configured.
                                    </div>

                                    <div v-if="!channelEditing[ch.key]" class="row g-3 small">
                                        <div class="col-md-4"><div class="text-muted">Phone number ID</div><div class="fw-medium">{{ ch.config.phone_number_id || '—' }}</div></div>
                                        <div class="col-md-3"><div class="text-muted">Display number</div><div class="fw-medium">{{ ch.config.display_number || '—' }}</div></div>
                                        <div class="col-md-2"><div class="text-muted">Access token</div><div class="fw-medium">{{ ch.config.access_token_set ? 'set' : 'not set' }}</div></div>
                                        <div class="col-md-2"><div class="text-muted">Welcome template</div><div class="fw-medium">{{ ch.config.welcome_template_name || '—' }}</div></div>
                                        <div class="col-md-1"><div class="text-muted">Enabled</div><span class="badge" :class="ch.config.enabled ? 'bg-success' : 'bg-secondary'">{{ ch.config.enabled ? 'Yes' : 'No' }}</span></div>
                                    </div>

                                    <div v-else class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-medium">Phone number ID</label>
                                            <input v-model="channelForms.whatsapp.phone_number_id" type="text" class="form-control" placeholder="123456789012345" :disabled="channelSaving[ch.key]" />
                                            <div class="form-text small">Meta &rarr; WhatsApp Manager &rarr; Phone Numbers</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Display number</label>
                                            <input v-model="channelForms.whatsapp.display_number" type="text" class="form-control" placeholder="+358401234567" :disabled="channelSaving[ch.key]" />
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-medium">Access token <span class="text-muted">(leave blank to keep existing)</span></label>
                                            <input v-model="channelForms.whatsapp.access_token" type="password" class="form-control" :placeholder="ch.config.access_token_set ? '•••• already set ••••' : 'EAAG...'" :disabled="channelSaving[ch.key]" autocomplete="off" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-medium">Welcome template name</label>
                                            <input v-model="channelForms.whatsapp.welcome_template_name" type="text" class="form-control" placeholder="agent_notice" :disabled="channelSaving[ch.key]" />
                                            <div class="form-text small">Approved Meta template used for proactive sends outside the 24h window.</div>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-center pt-3">
                                            <div class="form-check form-switch">
                                                <input v-model="channelForms.whatsapp.enabled" type="checkbox" class="form-check-input" id="whatsapp-enabled" :disabled="channelSaving[ch.key]" />
                                                <label class="form-check-label small" for="whatsapp-enabled">Enabled</label>
                                            </div>
                                        </div>
                                        <div class="col-12 d-flex gap-2 pt-2">
                                            <button class="btn btn-sm btn-primary" @click="saveChannel(ch)" :disabled="channelSaving[ch.key]">
                                                <span v-if="channelSaving[ch.key]" class="spinner-border spinner-border-sm me-1"></span>
                                                Save
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" @click="cancelEdit(ch)" :disabled="channelSaving[ch.key]">Cancel</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Slack sub-panel -->
                                <div v-else-if="ch.key === 'slack'">
                                    <div class="d-flex align-items-center mb-3">
                                        <h6 class="mb-0 fw-semibold"><i class="bi bi-slack text-primary me-1"></i> {{ ch.label }}</h6>
                                        <span class="ms-3 small text-muted">{{ ch.description }}</span>
                                        <button v-if="!channelEditing[ch.key]" class="btn btn-sm btn-outline-primary ms-auto" @click="openEdit(ch)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </div>
                                    <div v-if="channelErrors[ch.key]" class="alert alert-danger py-2 small mb-3">{{ channelErrors[ch.key] }}</div>

                                    <div v-if="!channelEditing[ch.key]" class="row g-3 small">
                                        <div class="col-md-3"><div class="text-muted">App ID</div><div class="fw-medium">{{ ch.config.app_id || '—' }}</div></div>
                                        <div class="col-md-3"><div class="text-muted">Bot user ID</div><div class="fw-medium">{{ ch.config.bot_user_id || '—' }}</div></div>
                                        <div class="col-md-2"><div class="text-muted">Bot token</div><div class="fw-medium">{{ ch.config.bot_token_set ? 'set' : 'not set' }}</div></div>
                                        <div class="col-md-2"><div class="text-muted">Signing secret</div><div class="fw-medium">{{ ch.config.signing_secret_set ? 'set' : 'not set' }}</div></div>
                                        <div class="col-md-1"><div class="text-muted">Workspace</div><div class="fw-medium">{{ ch.config.team_id || '—' }}</div></div>
                                        <div class="col-md-1"><div class="text-muted">Enabled</div><span class="badge" :class="ch.config.enabled ? 'bg-success' : 'bg-secondary'">{{ ch.config.enabled ? 'Yes' : 'No' }}</span></div>
                                    </div>

                                    <div v-else class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">App ID</label>
                                            <input v-model="channelForms.slack.app_id" type="text" class="form-control" placeholder="A0XXXXXXX" :disabled="channelSaving[ch.key]" />
                                            <div class="form-text small">Slack App config &rarr; Basic Information &rarr; App ID</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Bot user ID</label>
                                            <input v-model="channelForms.slack.bot_user_id" type="text" class="form-control" placeholder="U0XXXXXXX" :disabled="channelSaving[ch.key]" />
                                            <div class="form-text small">
                                                Run <code>curl -s -H "Authorization: Bearer xoxb-your-token" https://slack.com/api/auth.test</code> — the <code>user_id</code> field is your Bot user ID. The response also contains <code>app_id</code> and <code>team_id</code>.
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Bot token <span class="text-muted">(leave blank to keep existing)</span></label>
                                            <input v-model="channelForms.slack.bot_token" type="password" class="form-control" :placeholder="ch.config.bot_token_set ? '•••• already set ••••' : 'xoxb-...'" :disabled="channelSaving[ch.key]" autocomplete="off" />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Signing secret <span class="text-muted">(leave blank to keep existing)</span></label>
                                            <input v-model="channelForms.slack.signing_secret" type="password" class="form-control" :placeholder="ch.config.signing_secret_set ? '•••• already set ••••' : 'Slack Basic Information &rarr; Signing Secret'" :disabled="channelSaving[ch.key]" autocomplete="off" />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Default workspace (team_id)</label>
                                            <input v-model="channelForms.slack.team_id" type="text" class="form-control" placeholder="T0XXXXXXX" :disabled="channelSaving[ch.key]" />
                                            <div class="form-text small">Optional — only for proactive sends to a specific workspace.</div>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-center pt-3">
                                            <div class="form-check form-switch">
                                                <input v-model="channelForms.slack.enabled" type="checkbox" class="form-check-input" id="slack-enabled" :disabled="channelSaving[ch.key]" />
                                                <label class="form-check-label small" for="slack-enabled">Enabled</label>
                                            </div>
                                        </div>
                                        <div class="col-12 d-flex gap-2 pt-2">
                                            <button class="btn btn-sm btn-primary" @click="saveChannel(ch)" :disabled="channelSaving[ch.key]">
                                                <span v-if="channelSaving[ch.key]" class="spinner-border spinner-border-sm me-1"></span>
                                                Save
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" @click="cancelEdit(ch)" :disabled="channelSaving[ch.key]">Cancel</button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="text-muted small">Channel type "{{ ch.key }}" has no UI renderer yet.</div>
                            </div>
                        </template>
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
