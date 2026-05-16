/**
 * Agent detail and edit page.
 *
 * Displays agent configuration, LLM settings, and execution logs.
 * An inline edit form lets administrators update name, description,
 * LLM provider/model, system instructions, and config overrides
 * without leaving the page.
 *
 * Per-agent message channels (Slack, WhatsApp, ...) are loaded from the
 * unified /api/v1/message-channels endpoint and rendered uniformly under
 * the "Message channels" card. Per-channel forms are kept in `forms.<key>`
 * and edit/save state in `editing[key]`, `saving[key]`, `errors[key]` so a
 * new channel type only needs an HTML sub-panel + a default form entry.
 */
(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var reactive = Vue.reactive;
    var computed = Vue.computed;
    var onMounted = Vue.onMounted;

    var agentId = (window.AgentViewConfig && window.AgentViewConfig.agentId) || 0;

    /** Model name hints shown below the model input field per provider. */
    var MODEL_HINTS = {
        openai:    { placeholder: 'gpt-4o', hint: 'e.g. gpt-4o, gpt-4o-mini, gpt-4-turbo' },
        anthropic: { placeholder: 'claude-sonnet-4-6', hint: 'e.g. claude-opus-4-6, claude-sonnet-4-6, claude-haiku-4-5-20251001' },
        ollama:    { placeholder: 'llama3', hint: 'Any model pulled locally, e.g. llama3, mistral, phi3' },
    };

    /**
     * Default form values per channel type. Used both for the initial
     * reactive `forms` object and when the user clicks Cancel.
     */
    var DEFAULT_FORMS = {
        whatsapp: {
            phone_number_id:       '',
            display_number:        '',
            access_token:          '',
            welcome_template_name: '',
            enabled:               false,
        },
        slack: {
            app_id:         '',
            bot_user_id:    '',
            bot_token:      '',
            signing_secret: '',
            team_id:        '',
            enabled:        false,
        },
    };

    /**
     * Build a fresh form payload from the channel's current config when the
     * user opens the edit panel. Secret fields always start blank so the
     * existing encrypted value is kept unless the admin types a new one.
     */
    function buildEditForm(channel) {
        var cfg = channel.config || {};
        if (channel.key === 'whatsapp') {
            return {
                phone_number_id:       cfg.phone_number_id || '',
                display_number:        cfg.display_number || '',
                access_token:          '',
                welcome_template_name: cfg.welcome_template_name || '',
                enabled:               !!cfg.enabled,
            };
        }
        if (channel.key === 'slack') {
            return {
                app_id:         cfg.app_id || '',
                bot_user_id:    cfg.bot_user_id || '',
                bot_token:      '',
                signing_secret: '',
                team_id:        cfg.team_id || '',
                enabled:        !!cfg.enabled,
            };
        }
        return {};
    }

    createApp({
        setup: function () {
            var agent = ref(null);
            var logs = ref([]);
            var loading = ref(true);
            var loadingLogs = ref(true);

            // Edit state
            var editing = ref(false);
            var saving = ref(false);
            var saveError = ref('');
            var form = ref({
                name: '',
                description: '',
                llm_provider: '',
                llm_model: '',
                instructions: '',
                config: '',
                is_enabled: true,
            });

            // Message channels — single source of truth, keyed by channel key
            var channels = ref([]);
            var loadingChannels = ref(true);
            var channelsError = ref('');
            var channelEditing = reactive({});
            var channelSaving = reactive({});
            var channelErrors = reactive({});
            var channelForms = reactive({
                whatsapp: Object.assign({}, DEFAULT_FORMS.whatsapp),
                slack:    Object.assign({}, DEFAULT_FORMS.slack),
            });

            // ── Computed ──────────────────────────────────────────────

            var modelPlaceholder = computed(function () {
                return (MODEL_HINTS[form.value.llm_provider] || {}).placeholder || '';
            });

            var modelHint = computed(function () {
                return (MODEL_HINTS[form.value.llm_provider] || {}).hint || '';
            });

            var configError = computed(function () {
                var raw = (form.value.config || '').trim();
                if (!raw || raw === '{}') { return ''; }
                try { JSON.parse(raw); return ''; } catch (e) { return 'Invalid JSON'; }
            });

            // ── Data loading ──────────────────────────────────────────

            async function load() {
                loading.value = true;
                try {
                    var data = await Api.agents.view(agentId);
                    agent.value = data.data || null;
                } catch (e) {
                    agent.value = null;
                } finally {
                    loading.value = false;
                }
            }

            async function loadLogs() {
                loadingLogs.value = true;
                try {
                    var data = await Api.agents.logs(agentId);
                    logs.value = data.data || [];
                } catch (e) {
                    logs.value = [];
                } finally {
                    loadingLogs.value = false;
                }
            }

            /**
             * Pulls every registered channel + its admin payload in one round-trip.
             * Permission errors (chat:configure missing) leave the section empty
             * rather than surfacing a noisy alert.
             */
            async function loadChannels() {
                loadingChannels.value = true;
                channelsError.value = '';
                try {
                    var data = await Api.messageChannels.list(agentId);
                    channels.value = data.data || [];
                } catch (e) {
                    if (e.status === 403) {
                        channels.value = [];
                    } else {
                        channelsError.value = e.message || 'Failed to load channels';
                    }
                } finally {
                    loadingChannels.value = false;
                }
            }

            function openEdit(channel) {
                channelForms[channel.key] = buildEditForm(channel);
                channelErrors[channel.key] = '';
                channelEditing[channel.key] = true;
            }

            function cancelEdit(channel) {
                channelEditing[channel.key] = false;
                channelErrors[channel.key] = '';
                channelForms[channel.key] = Object.assign({}, DEFAULT_FORMS[channel.key] || {});
            }

            async function saveChannel(channel) {
                if (channelSaving[channel.key]) { return; }
                channelSaving[channel.key] = true;
                channelErrors[channel.key] = '';
                try {
                    var result = await Api.messageChannels.update(agentId, channel.key, channelForms[channel.key]);
                    if (result.data) {
                        // Replace the channel entry in-place so reactivity fires.
                        var idx = channels.value.findIndex(function (c) { return c.key === channel.key; });
                        if (idx >= 0) {
                            channels.value.splice(idx, 1, result.data);
                        }
                    }
                    channelEditing[channel.key] = false;
                } catch (err) {
                    channelErrors[channel.key] = err.message || ('Failed to save ' + (channel.label || channel.key));
                } finally {
                    channelSaving[channel.key] = false;
                }
            }

            // ── Edit ──────────────────────────────────────────────────

            /**
             * Toggle the edit panel. When opening, populate the form from
             * the currently loaded agent data so edits start from live values.
             */
            function toggleEdit() {
                if (!editing.value && agent.value) {
                    form.value = {
                        name:         agent.value.name         || '',
                        description:  agent.value.description  || '',
                        llm_provider: agent.value.llm_provider || '',
                        llm_model:    agent.value.llm_model    || '',
                        instructions: agent.value.instructions || '',
                        config:       agent.value.config       || '',
                        is_enabled:   !!agent.value.is_enabled,
                    };
                    saveError.value = '';
                }
                editing.value = !editing.value;
            }

            /**
             * Save the edited agent via PUT /api/v1/agents/update/{id}.
             * On success, reload the agent data and close the edit panel.
             */
            async function save() {
                if (saving.value || configError.value) { return; }
                saving.value = true;
                saveError.value = '';
                try {
                    await Api.agents.update(agentId, {
                        name:         form.value.name,
                        description:  form.value.description  || null,
                        llm_provider: form.value.llm_provider || null,
                        llm_model:    form.value.llm_model    || null,
                        instructions: form.value.instructions || null,
                        config:       form.value.config       || null,
                        is_enabled:   form.value.is_enabled,
                    });
                    await load();
                    editing.value = false;
                } catch (err) {
                    saveError.value = err.message || 'Failed to save agent';
                } finally {
                    saving.value = false;
                }
            }

            // ── Helpers ───────────────────────────────────────────────

            function formatDate(dateStr) {
                if (!dateStr) { return '—'; }
                var d = new Date(dateStr);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function formatJson(val) {
                if (!val) { return ''; }
                try { return JSON.stringify(JSON.parse(val), null, 2); } catch (e) { return val; }
            }

            function levelBadgeClass(level) {
                var map = {
                    info:    'bg-info text-dark',
                    error:   'bg-danger',
                    success: 'bg-success',
                    warning: 'bg-warning text-dark',
                    debug:   'bg-secondary',
                };
                return map[level] || 'bg-secondary';
            }

            // ── Init ──────────────────────────────────────────────────

            onMounted(function () {
                load();
                loadLogs();
                loadChannels();
            });

            return {
                agent: agent,
                logs: logs,
                loading: loading,
                loadingLogs: loadingLogs,
                editing: editing,
                saving: saving,
                saveError: saveError,
                form: form,
                modelPlaceholder: modelPlaceholder,
                modelHint: modelHint,
                configError: configError,
                toggleEdit: toggleEdit,
                save: save,
                formatDate: formatDate,
                formatJson: formatJson,
                levelBadgeClass: levelBadgeClass,

                // Channels (see Message channels card in the template)
                channels: channels,
                loadingChannels: loadingChannels,
                channelsError: channelsError,
                channelEditing: channelEditing,
                channelSaving: channelSaving,
                channelErrors: channelErrors,
                channelForms: channelForms,
                openEdit: openEdit,
                cancelEdit: cancelEdit,
                saveChannel: saveChannel,
            };
        },
    }).mount('#agent-view-app');
})();
