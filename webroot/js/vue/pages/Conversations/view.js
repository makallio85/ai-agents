(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    var conversationId = (window.ConversationViewConfig && window.ConversationViewConfig.conversationId) || 0;

    createApp({
        setup: function () {
            var conversation = ref(null);
            var jobs = ref([]);
            var loading = ref(true);
            var showSource = ref(false);

            async function load() {
                loading.value = true;
                try {
                    var data = await Api.conversations.view(conversationId);
                    conversation.value = data.data || null;
                    jobs.value = (data.data && data.data.issue_parsing_jobs) ? data.data.issue_parsing_jobs : [];
                } catch (e) {
                    conversation.value = null;
                } finally {
                    loading.value = false;
                }
            }

            function parsedData(job) {
                if (!job.parsed_data) { return {}; }
                try { return JSON.parse(job.parsed_data); } catch (e) { return {}; }
            }

            function parsedTitle(job) {
                return parsedData(job).title || '—';
            }

            function parsedType(job) {
                return parsedData(job).issueType || '—';
            }

            function parsedLabels(job) {
                if (job.applied_labels) {
                    try { return JSON.parse(job.applied_labels); } catch (e) { return []; }
                }
                return parsedData(job).labels || [];
            }

            function statusBadgeClass(status) {
                var map = {
                    pending: 'bg-secondary',
                    validating: 'bg-info',
                    creating: 'bg-warning text-dark',
                    completed: 'bg-success',
                    failed: 'bg-danger'
                };
                return map[status] || 'bg-secondary';
            }

            function jobStatusBadgeClass(status) {
                return statusBadgeClass(status);
            }

            onMounted(load);

            return {
                conversation: conversation,
                jobs: jobs,
                loading: loading,
                showSource: showSource,
                parsedTitle: parsedTitle,
                parsedType: parsedType,
                parsedLabels: parsedLabels,
                statusBadgeClass: statusBadgeClass,
                jobStatusBadgeClass: jobStatusBadgeClass
            };
        }
    }).mount('#conversation-view-app');
})();
