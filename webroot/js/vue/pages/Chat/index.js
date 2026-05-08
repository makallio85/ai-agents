/**
 * Chat interface Vue 3 app.
 *
 * Manages chat sessions with agents and streams LLM responses via
 * Server-Sent Events. Sessions are persisted to the database so they can
 * be resumed across page loads.
 *
 * Layout: left panel = session history, right panel = message thread + input.
 *
 * Streaming flow:
 *   1. User submits a message.
 *   2. POST /api/v1/chat/message/{id} is called via Api.chat.message().
 *   3. The server saves the user message and streams assistant deltas.
 *   4. Each delta is appended to streamBuffer and displayed in the streaming
 *      bubble until a "done" event signals completion.
 *   5. loadSession() is called to re-fetch the session so the persisted
 *      assistant message replaces the streaming bubble in the thread.
 */
(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var nextTick = Vue.nextTick;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var sessions = ref([]);
            var agents = ref([]);
            var activeSession = ref(null);
            var messages = ref([]);
            var selectedAgentId = ref('');
            var inputText = ref('');
            var streaming = ref(false);
            var streamBuffer = ref('');
            var sendError = ref('');
            var loadingSessions = ref(true);

            var messagesEl = ref(null);
            var inputEl = ref(null);

            // ── Data loading ─────────────────────────────────────────

            function loadSessions() {
                loadingSessions.value = true;
                return Api.chat.index()
                    .then(function (data) { sessions.value = data.data || []; })
                    .catch(function () { sessions.value = []; })
                    .finally(function () { loadingSessions.value = false; });
            }

            function loadAgents() {
                return Api.agents.index()
                    .then(function (data) {
                        agents.value = (data.data || []).filter(function (a) { return a.is_enabled; });
                    })
                    .catch(function () { agents.value = []; });
            }

            function loadSession(id) {
                sendError.value = '';
                return Api.chat.view(id)
                    .then(function (data) {
                        activeSession.value = data.data;
                        messages.value = (data.data && data.data.chat_messages) ? data.data.chat_messages : [];
                        return nextTick();
                    })
                    .then(scrollToBottom);
            }

            // ── Session management ────────────────────────────────────

            function newSession() {
                activeSession.value = null;
                messages.value = [];
                inputText.value = '';
                sendError.value = '';
                selectedAgentId.value = '';
            }

            function startSession() {
                if (!selectedAgentId.value) return;
                Api.chat.create({ agent_id: selectedAgentId.value })
                    .then(function (data) {
                        var newS = data.data;
                        return loadSessions().then(function () {
                            return loadSession(newS.id);
                        });
                    })
                    .catch(function (err) {
                        sendError.value = err.message || 'Failed to start chat session';
                    });
            }

            function deleteSession(id) {
                if (!confirm('Delete this chat session?')) return;
                Api.chat.del(id)
                    .then(function () {
                        if (activeSession.value && activeSession.value.id === id) {
                            activeSession.value = null;
                            messages.value = [];
                        }
                        return loadSessions();
                    })
                    .catch(function (err) {
                        sendError.value = err.message || 'Failed to delete session';
                    });
            }

            // ── Messaging ─────────────────────────────────────────────

            async function sendMessage() {
                var text = inputText.value.trim();
                if (!text || streaming.value || !activeSession.value) return;

                sendError.value = '';
                inputText.value = '';
                streaming.value = true;
                streamBuffer.value = '';

                // Optimistically add user message to thread for instant feedback
                messages.value.push({
                    id: 'pending-' + Date.now(),
                    role: 'user',
                    content: text,
                    created: new Date().toISOString(),
                });
                await nextTick();
                scrollToBottom();

                try {
                    for await (var event of Api.chat.message(activeSession.value.id, text)) {
                        if (event.type === 'chunk') {
                            streamBuffer.value += event.content;
                            await nextTick();
                            scrollToBottom();
                        } else if (event.type === 'done') {
                            // Stream complete — reload session to get persisted message
                            streaming.value = false;
                            streamBuffer.value = '';
                            await loadSession(activeSession.value.id);
                            await loadSessions(); // refresh title in sidebar
                            break;
                        } else if (event.type === 'error') {
                            throw new Error(event.message || 'LLM error');
                        }
                    }
                } catch (err) {
                    sendError.value = err.message || 'Failed to send message';
                    streaming.value = false;
                    streamBuffer.value = '';
                    // Reload to restore consistent state (remove optimistic message)
                    if (activeSession.value) {
                        await loadSession(activeSession.value.id);
                    }
                }

                await nextTick();
                if (inputEl.value) {
                    inputEl.value.style.height = 'auto';
                    inputEl.value.focus();
                }
            }

            // ── UI helpers ────────────────────────────────────────────

            function scrollToBottom() {
                if (messagesEl.value) {
                    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
                }
            }

            function autoResize(event) {
                var el = event.target;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 140) + 'px';
            }

            function formatTime(iso) {
                if (!iso) return '';
                var d = new Date(iso);
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            // ── Init ──────────────────────────────────────────────────

            onMounted(function () {
                loadSessions();
                loadAgents();
            });

            return {
                sessions: sessions,
                agents: agents,
                activeSession: activeSession,
                messages: messages,
                selectedAgentId: selectedAgentId,
                inputText: inputText,
                streaming: streaming,
                streamBuffer: streamBuffer,
                sendError: sendError,
                loadingSessions: loadingSessions,
                messagesEl: messagesEl,
                inputEl: inputEl,
                loadSession: loadSession,
                newSession: newSession,
                startSession: startSession,
                deleteSession: deleteSession,
                sendMessage: sendMessage,
                autoResize: autoResize,
                formatTime: formatTime,
            };
        },
    }).mount('#chat-app');
})();
