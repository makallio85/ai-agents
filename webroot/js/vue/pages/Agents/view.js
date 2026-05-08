(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    var agentId = (window.AgentViewConfig && window.AgentViewConfig.agentId) || 0;

    createApp({
        setup: function () {
            var agent = ref(null);
            var logs = ref([]);
            var loading = ref(true);
            var loadingLogs = ref(true);

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
                    info: 'bg-info text-dark',
                    error: 'bg-danger',
                    success: 'bg-success',
                    warning: 'bg-warning text-dark',
                    debug: 'bg-secondary'
                };
                return map[level] || 'bg-secondary';
            }

            onMounted(function () {
                load();
                loadLogs();
            });

            return {
                agent: agent,
                logs: logs,
                loading: loading,
                loadingLogs: loadingLogs,
                formatDate: formatDate,
                formatJson: formatJson,
                levelBadgeClass: levelBadgeClass
            };
        }
    }).mount('#agent-view-app');
})();
