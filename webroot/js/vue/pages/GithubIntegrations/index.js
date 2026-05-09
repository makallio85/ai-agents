(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var integrations = ref([]);
            var loading = ref(true);
            var showCreateModal = ref(false);
            var creating = ref(false);
            var createError = ref('');

            var newIntegration = ref({
                repo_owner: '',
                repo_name: '',
                token: '',
                is_active: true
            });

            async function loadIntegrations() {
                loading.value = true;
                try {
                    var data = await Api.githubIntegrations.index();
                    integrations.value = data.data || [];
                } catch (e) {
                    // silent
                } finally {
                    loading.value = false;
                }
            }

            async function createIntegration() {
                createError.value = '';
                if (!newIntegration.value.repo_owner || !newIntegration.value.repo_name || !newIntegration.value.token) {
                    createError.value = 'Owner, repository name and token are required.';
                    return;
                }
                creating.value = true;
                try {
                    await Api.githubIntegrations.create(newIntegration.value);
                    showCreateModal.value = false;
                    newIntegration.value = { repo_owner: '', repo_name: '', token: '', is_active: true };
                    await loadIntegrations();
                } catch (e) {
                    createError.value = (e && e.message) ? e.message : 'Failed to create integration.';
                } finally {
                    creating.value = false;
                }
            }

            async function deleteIntegration(id) {
                if (!confirm('Remove this GitHub integration?')) { return; }
                try {
                    await Api.githubIntegrations.del(id);
                    integrations.value = integrations.value.filter(function (i) { return i.id !== id; });
                } catch (e) {
                    alert('Failed to remove integration.');
                }
            }

            function formatDate(dateStr) {
                if (!dateStr) { return '—'; }
                var d = new Date(dateStr);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            onMounted(loadIntegrations);

            return {
                integrations: integrations,
                loading: loading,
                showCreateModal: showCreateModal,
                creating: creating,
                createError: createError,
                newIntegration: newIntegration,
                createIntegration: createIntegration,
                deleteIntegration: deleteIntegration,
                formatDate: formatDate
            };
        }
    }).mount('#github-app');
})();
