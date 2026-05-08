(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var stats = ref({ agents: 0, conversations: 0, issues: 0, failed: 0 });
            var conversations = ref([]);
            var agents = ref([]);
            var loadingConversations = ref(true);
            var loadingAgents = ref(true);

            async function fetchData() {
                try {
                    var agentsData = await Api.agents.index();
                    agents.value = (agentsData.data || []).slice(0, 5);
                    stats.value.agents = agentsData.meta?.total ?? agents.value.length;
                } catch (e) {
                    // silent
                } finally {
                    loadingAgents.value = false;
                }

                try {
                    var convData = await Api.conversations.index();
                    conversations.value = (convData.data || []).slice(0, 10);
                    stats.value.conversations = convData.meta?.total ?? conversations.value.length;

                    // Count issues and failed from conversations
                    var allConvs = convData.data || [];
                    stats.value.issues = allConvs.reduce(function (acc, c) {
                        return acc + (c.blocks_processed || 0);
                    }, 0);
                    stats.value.failed = allConvs.filter(function (c) {
                        return c.status === 'failed';
                    }).length;
                } catch (e) {
                    // silent
                } finally {
                    loadingConversations.value = false;
                }
            }

            function statusBadgeClass(status) {
                var map = {
                    pending: 'bg-secondary',
                    processing: 'bg-warning text-dark',
                    completed: 'bg-success',
                    failed: 'bg-danger'
                };
                return map[status] || 'bg-secondary';
            }

            function formatDate(dateStr) {
                if (!dateStr) { return '—'; }
                var d = new Date(dateStr);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            onMounted(fetchData);

            return {
                stats: stats,
                conversations: conversations,
                agents: agents,
                loadingConversations: loadingConversations,
                loadingAgents: loadingAgents,
                statusBadgeClass: statusBadgeClass,
                formatDate: formatDate
            };
        }
    }).mount('#dashboard-app');
})();
