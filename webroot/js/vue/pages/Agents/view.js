/**
 * Agent detail and edit page.
 *
 * Displays agent configuration, LLM settings, and execution logs.
 * An inline edit form lets administrators update name, description,
 * LLM provider/model, system instructions, and config overrides
 * without leaving the page.
 */
(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var computed = Vue.computed;
    var onMounted = Vue.onMounted;

    var agentId = (window.AgentViewConfig && window.AgentViewConfig.agentId) || 0;

    /** Model name hints shown below the model input field per provider. */
    var MODEL_HINTS = {
        openai:    { placeholder: 'gpt-4o', hint: 'e.g. gpt-4o, gpt-4o-mini, gpt-4-turbo' },
        anthropic: { placeholder: 'claude-sonnet-4-6', hint: 'e.g. claude-opus-4-6, claude-sonnet-4-6, claude-haiku-4-5-20251001' },
        ollama:    { placeholder: 'llama3', hint: 'Any model pulled locally, e.g. llama3, mistral, phi3' },
    };

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
            };
        },
    }).mount('#agent-view-app');
})();
