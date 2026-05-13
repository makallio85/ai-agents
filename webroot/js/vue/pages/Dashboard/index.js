(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var stats = ref({ agents: 0, activeAgents: 0, integrations: 0 });
            var agents = ref([]);
            var loadingAgents = ref(true);

            async function fetchData() {
                try {
                    var agentsData = await Api.agents.index();
                    var allAgents = agentsData.data || [];
                    agents.value = allAgents.slice(0, 10);
                    stats.value.agents = agentsData.meta?.total ?? allAgents.length;
                    stats.value.activeAgents = allAgents.filter(function (a) { return a.is_active; }).length;
                } catch (e) {
                    // silent
                } finally {
                    loadingAgents.value = false;
                }

                try {
                    var intData = await Api.githubIntegrations.index();
                    stats.value.integrations = (intData.data || []).length;
                } catch (e) {
                    // silent
                }
            }

            onMounted(fetchData);

            return {
                stats: stats,
                agents: agents,
                loadingAgents: loadingAgents,
            };
        }
    }).mount('#dashboard-app');
})();
