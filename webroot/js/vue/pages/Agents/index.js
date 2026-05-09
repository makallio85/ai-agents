(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var agents = ref([]);
            var loading = ref(true);
            var showCreateModal = ref(false);
            var creating = ref(false);
            var createError = ref('');

            var newAgent = ref({
                name: '',
                plugin_name: '',
                description: '',
                is_active: true
            });

            async function loadAgents() {
                loading.value = true;
                try {
                    var data = await Api.agents.index();
                    agents.value = data.data || [];
                } catch (e) {
                    // silent
                } finally {
                    loading.value = false;
                }
            }

            async function createAgent() {
                createError.value = '';
                if (!newAgent.value.name || !newAgent.value.plugin_name) {
                    createError.value = 'Name and plugin name are required.';
                    return;
                }
                creating.value = true;
                try {
                    await Api.agents.create(newAgent.value);
                    showCreateModal.value = false;
                    newAgent.value = { name: '', plugin_name: '', description: '', is_active: true };
                    await loadAgents();
                } catch (e) {
                    createError.value = (e && e.message) ? e.message : 'Failed to create agent.';
                } finally {
                    creating.value = false;
                }
            }

            function formatDate(dateStr) {
                if (!dateStr) { return '—'; }
                var d = new Date(dateStr);
                return d.toLocaleDateString();
            }

            onMounted(loadAgents);

            return {
                agents: agents,
                loading: loading,
                showCreateModal: showCreateModal,
                creating: creating,
                createError: createError,
                newAgent: newAgent,
                createAgent: createAgent,
                formatDate: formatDate
            };
        }
    }).mount('#agents-app');
})();
