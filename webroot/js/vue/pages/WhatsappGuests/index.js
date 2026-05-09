/**
 * WhatsApp Guests admin page.
 *
 * Lists users with optional approval-state filter. Approve / reject buttons
 * call the matching API endpoints; the row's state badge updates in place.
 */
(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var users = ref([]);
            var counts = ref({ pending: null });
            var loading = ref(true);
            var loadError = ref('');
            var filter = ref('pending');
            var acting = ref({});

            async function load() {
                loading.value = true;
                loadError.value = '';
                try {
                    var params = filter.value ? { approval_state: filter.value } : {};
                    var data = await Api.users.index(params);
                    users.value = data.data || [];

                    // Always refresh the pending count for the badge, regardless of current filter.
                    if (filter.value === 'pending') {
                        counts.value.pending = users.value.length;
                    } else {
                        try {
                            var pending = await Api.users.index({ approval_state: 'pending' });
                            counts.value.pending = (pending.data || []).length;
                        } catch (e) { /* count is cosmetic; ignore */ }
                    }
                } catch (err) {
                    loadError.value = err.message || 'Failed to load users';
                    users.value = [];
                } finally {
                    loading.value = false;
                }
            }

            function setFilter(state) {
                filter.value = state;
                load();
            }

            async function approve(user) {
                if (acting.value[user.id]) { return; }
                acting.value[user.id] = true;
                try {
                    var data = await Api.users.approve(user.id, {});
                    Object.assign(user, data.data || {});
                } catch (err) {
                    loadError.value = err.message || 'Failed to approve';
                } finally {
                    acting.value[user.id] = false;
                    load();
                }
            }

            async function reject(user) {
                if (acting.value[user.id]) { return; }
                if (!confirm('Reject ' + (user.phone_number || user.username) + '? Their messages will be silently buffered until they are approved.')) {
                    return;
                }
                acting.value[user.id] = true;
                try {
                    var data = await Api.users.reject(user.id);
                    Object.assign(user, data.data || {});
                } catch (err) {
                    loadError.value = err.message || 'Failed to reject';
                } finally {
                    acting.value[user.id] = false;
                    load();
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
                counts: counts,
                loading: loading,
                loadError: loadError,
                filter: filter,
                acting: acting,
                setFilter: setFilter,
                approve: approve,
                reject: reject,
                stateBadge: stateBadge,
                formatDate: formatDate,
            };
        },
    }).mount('#whatsapp-guests-app');
})();
