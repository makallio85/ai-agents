(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var logs = ref([]);
            var agents = ref([]);
            var loading = ref(true);

            var filter = ref({
                agentId: '',
                level: '',
                resultState: ''
            });

            async function loadAgents() {
                try {
                    var data = await Api.agents.index();
                    agents.value = data.data || [];
                } catch (e) {
                    // silent
                }
            }

            async function loadLogs() {
                loading.value = true;
                try {
                    var params = {};
                    if (filter.value.agentId) { params.agent_id = filter.value.agentId; }
                    if (filter.value.level) { params.level = filter.value.level; }
                    if (filter.value.resultState) { params.result_state = filter.value.resultState; }
                    var data = await Api.logs.index(params);
                    logs.value = data.data || [];
                } catch (e) {
                    logs.value = [];
                } finally {
                    loading.value = false;
                }
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

            function formatDate(dateStr) {
                if (!dateStr) { return '—'; }
                var d = new Date(dateStr);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            onMounted(function () {
                loadAgents();
                loadLogs();
            });

            return {
                logs: logs,
                agents: agents,
                loading: loading,
                filter: filter,
                loadLogs: loadLogs,
                levelBadgeClass: levelBadgeClass,
                formatDate: formatDate
            };
        }
    }).mount('#logs-app');
})();
