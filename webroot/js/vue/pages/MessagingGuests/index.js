/**
 * Messaging Guests admin page.
 *
 * Lists users with approval-state and channel (role) filters. The two
 * filter groups combine: e.g. pending + Slack shows only Slack guests
 * awaiting approval. Approve / reject calls update the row in place.
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
            var stateFilter = ref('pending');
            var channelFilter = ref('');
            var acting = ref({});

            async function load() {
                loading.value = true;
                loadError.value = '';
                try {
                    var params = {};
                    if (stateFilter.value) { params.approval_state = stateFilter.value; }
                    if (channelFilter.value) { params.role = channelFilter.value; }
                    var data = await Api.users.index(params);
                    users.value = data.data || [];

                    if (stateFilter.value === 'pending' && !channelFilter.value) {
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

            function setStateFilter(state) { stateFilter.value = state; load(); }
            function setChannelFilter(role) { channelFilter.value = role; load(); }

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
                if (!confirm('Reject ' + identifierFor(user) + '? Their messages will be silently buffered until they are approved.')) {
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

            // Map a user's role.slug to channel-specific UI bits. Phone numbers
            // come from users.phone_number; Slack identities are not on the
            // User row directly — show the U-id we encoded in username.
            function channelLabel(user) {
                var slug = (user.role && user.role.slug) || '';
                if (slug === 'whatsapp_guest') { return 'WhatsApp'; }
                if (slug === 'slack_guest') { return 'Slack'; }
                return user.role ? user.role.name : '—';
            }

            function channelIcon(user) {
                var slug = (user.role && user.role.slug) || '';
                if (slug === 'whatsapp_guest') { return 'bi-whatsapp'; }
                if (slug === 'slack_guest') { return 'bi-slack'; }
                return 'bi-person';
            }

            function channelBadge(user) {
                var slug = (user.role && user.role.slug) || '';
                if (slug === 'whatsapp_guest') { return 'bg-success-subtle text-success border'; }
                if (slug === 'slack_guest') { return 'bg-primary-subtle text-primary border'; }
                return 'bg-secondary-subtle text-secondary border';
            }

            function identifierFor(user) {
                if (user.phone_number) { return user.phone_number; }
                if (user.username && user.username.indexOf('slack_') === 0) {
                    return user.username.slice(6).toUpperCase();
                }
                return user.username || ('#' + user.id);
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
                stateFilter: stateFilter,
                channelFilter: channelFilter,
                acting: acting,
                setStateFilter: setStateFilter,
                setChannelFilter: setChannelFilter,
                approve: approve,
                reject: reject,
                channelLabel: channelLabel,
                channelIcon: channelIcon,
                channelBadge: channelBadge,
                identifierFor: identifierFor,
                stateBadge: stateBadge,
                formatDate: formatDate,
            };
        },
    }).mount('#messaging-guests-app');
})();
