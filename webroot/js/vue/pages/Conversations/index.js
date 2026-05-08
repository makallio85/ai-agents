(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var computed = Vue.computed;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var conversations = ref([]);
            var agents = ref([]);
            var loading = ref(true);
            var submitting = ref(false);
            var submitError = ref('');

            var form = ref({
                agentId: '',
                title: '',
                sourceText: ''
            });

            var detectedBlocks = computed(function () {
                var text = form.value.sourceText;
                if (!text) { return 0; }
                var matches = text.match(/===\s*ISSUE START\s*===/gi);
                return matches ? matches.length : 0;
            });

            async function loadConversations() {
                loading.value = true;
                try {
                    var data = await Api.conversations.index();
                    conversations.value = data.data || [];
                } catch (e) {
                    // silent
                } finally {
                    loading.value = false;
                }
            }

            async function loadAgents() {
                try {
                    var data = await Api.agents.index();
                    agents.value = (data.data || []).filter(function (a) { return a.is_active; });
                } catch (e) {
                    // silent
                }
            }

            async function submitConversation() {
                submitError.value = '';
                submitting.value = true;
                try {
                    await Api.conversations.create({
                        agent_id: form.value.agentId,
                        title: form.value.title || null,
                        source_text: form.value.sourceText
                    });
                    form.value.sourceText = '';
                    form.value.title = '';
                    await loadConversations();
                } catch (e) {
                    submitError.value = (e && e.message) ? e.message : 'Failed to submit conversation.';
                } finally {
                    submitting.value = false;
                }
            }

            async function deleteConversation(id) {
                if (!confirm('Delete this conversation and all its jobs?')) { return; }
                try {
                    await Api.conversations.del(id);
                    conversations.value = conversations.value.filter(function (c) { return c.id !== id; });
                } catch (e) {
                    alert('Failed to delete conversation.');
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

            onMounted(function () {
                loadConversations();
                loadAgents();
            });

            return {
                conversations: conversations,
                agents: agents,
                loading: loading,
                submitting: submitting,
                submitError: submitError,
                form: form,
                detectedBlocks: detectedBlocks,
                loadConversations: loadConversations,
                submitConversation: submitConversation,
                deleteConversation: deleteConversation,
                statusBadgeClass: statusBadgeClass,
                formatDate: formatDate
            };
        }
    }).mount('#conversations-app');
})();
