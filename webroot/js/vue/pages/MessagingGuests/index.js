/**
 * Messaging Guests admin page.
 *
 * Lists users with approval-state and channel filters. The channel filter
 * joins through user_channel_identities server-side, so a user with both
 * a WhatsApp and a Slack identity surfaces under either filter. Channel
 * chips on each row reflect every identity the user has registered.
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
                    if (channelFilter.value) { params.channel = channelFilter.value; }
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
            function setChannelFilter(channel) { channelFilter.value = channel; load(); }

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

            // Distinct list of channels this user has registered through.
            // Falls back to inferring "whatsapp" from a phone_number on legacy
            // rows that pre-date the user_channel_identities table.
            function channelsFor(user) {
                var identities = user.user_channel_identities || [];
                var channels = identities.map(function (i) { return i.channel; });
                if (channels.length === 0 && user.phone_number) {
                    channels.push('whatsapp');
                }
                return Array.from(new Set(channels));
            }

            function channelLabel(ch) {
                if (ch === 'whatsapp') { return 'WhatsApp'; }
                if (ch === 'slack') { return 'Slack'; }
                if (ch === 'email') { return 'Email'; }
                return ch.charAt(0).toUpperCase() + ch.slice(1);
            }

            function channelIcon(ch) {
                if (ch === 'whatsapp') { return 'bi-whatsapp'; }
                if (ch === 'slack') { return 'bi-slack'; }
                if (ch === 'email') { return 'bi-envelope'; }
                return 'bi-person';
            }

            function channelBadge(ch) {
                if (ch === 'whatsapp') { return 'bg-success-subtle text-success border'; }
                if (ch === 'slack') { return 'bg-primary-subtle text-primary border'; }
                if (ch === 'email') { return 'bg-info-subtle text-info border'; }
                return 'bg-secondary-subtle text-secondary border';
            }

            // Pick the most useful identifier for the row: the user's phone
            // (WhatsApp guests), the first Slack U-id, or the username fallback.
            function identifierFor(user) {
                if (user.phone_number) { return user.phone_number; }
                var identities = user.user_channel_identities || [];
                if (identities.length > 0) {
                    var first = identities[0];
                    return first.display_name || first.email || first.external_id;
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
                channelsFor: channelsFor,
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
