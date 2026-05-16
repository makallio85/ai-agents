/**
 * Users admin page.
 *
 * Read-only listing of every user. Approval / reject / reply-mode actions
 * live on the Messaging Requests page (which scopes to the guest-approval
 * triage flow); this page is intentionally a flat list for navigation
 * convenience under User Management.
 */
(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var users = ref([]);
            var loading = ref(true);
            var loadError = ref('');

            async function load() {
                loading.value = true;
                loadError.value = '';
                try {
                    var data = await Api.users.index({});
                    users.value = data.data || [];
                } catch (err) {
                    loadError.value = err.message || 'Failed to load users';
                    users.value = [];
                } finally {
                    loading.value = false;
                }
            }

            function stateBadge(state) {
                var map = {
                    pending: 'bg-warning text-dark',
                    approved: 'bg-success',
                    rejected: 'bg-secondary',
                };
                return map[state] || 'bg-secondary';
            }

            function formatDate(s) {
                if (!s) { return '—'; }
                var d = new Date(s);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            onMounted(load);

            return {
                users: users,
                loading: loading,
                loadError: loadError,
                stateBadge: stateBadge,
                formatDate: formatDate,
            };
        },
    }).mount('#users-app');
})();
