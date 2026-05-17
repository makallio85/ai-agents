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
            var toolActivity = ref([]);   // [{label, status: 'running'|'done'}]
            var sendError = ref('');
            var loadingSessions = ref(true);

            var messagesEl = ref(null);
            var inputEl = ref(null);

            // Mobile slide-in session sidebar. Desktop ignores this flag
            // (CSS makes the sidebar permanently visible >=md).
            var sidebarOpen = ref(false);
            function openSidebar() { sidebarOpen.value = true; }
            function closeSidebar() { sidebarOpen.value = false; }
            function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value; }

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
                sendError.value = '';       // clear when switching context
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

                sendError.value = '';   // clear only here, at the start of a new send
                inputText.value = '';
                streaming.value = true;
                streamBuffer.value = '';
                toolActivity.value = [];

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
                        } else if (event.type === 'tool_call') {
                            // Agent is invoking a GitHub tool — show a running indicator
                            toolActivity.value.push({ label: formatToolName(event.tool), status: 'running' });
                            await nextTick();
                            scrollToBottom();
                        } else if (event.type === 'tool_result') {
                            // Mark the matching running entry as done
                            var idx = toolActivity.value.findIndex(function (t) {
                                return t.status === 'running' && t.label === formatToolName(event.tool);
                            });
                            if (idx !== -1) {
                                toolActivity.value[idx] = { label: formatToolName(event.tool), status: 'done' };
                            }
                            await nextTick();
                            scrollToBottom();
                        } else if (event.type === 'done') {
                            // Stream complete — reload session to get persisted message
                            streaming.value = false;
                            streamBuffer.value = '';
                            toolActivity.value = [];
                            await loadSession(activeSession.value.id);
                            await loadSessions(); // refresh title in sidebar
                            break;
                        } else if (event.type === 'error') {
                            throw new Error(event.message || 'LLM error');
                        }
                    }
                } catch (err) {
                    streaming.value = false;
                    streamBuffer.value = '';
                    toolActivity.value = [];
                    sendError.value = err.message || 'Failed to send message';
                    // Reload to restore consistent state (remove optimistic message).
                    // Ignore any loadSession failure so the original error stays visible.
                    if (activeSession.value) {
                        loadSession(activeSession.value.id).catch(function () {});
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

            /**
             * Converts a snake_case tool name into a readable label.
             * e.g. "github_list_repos" → "GitHub: list repos"
             */
            function formatToolName(name) {
                if (!name) return name;
                return name
                    .replace(/^github_/, 'GitHub: ')
                    .replace(/_/g, ' ');
            }

            // ── Init ──────────────────────────────────────────────────

            // If the page is opened as /chat?agent_id=N (from the "Start chat"
            // button on the Agents view page added in issue #14), create a
            // fresh session against that agent immediately after agents and
            // sessions are loaded.
            function maybeAutoStartFromQuery() {
                var params = new URLSearchParams(window.location.search);
                var raw = params.get('agent_id');
                if (!raw) { return; }
                var id = parseInt(raw, 10);
                if (!id) { return; }
                selectedAgentId.value = id;
                startSession();
            }

            onMounted(function () {
                Promise.all([loadSessions(), loadAgents()]).then(maybeAutoStartFromQuery);
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
                toolActivity: toolActivity,
                sendError: sendError,
                loadingSessions: loadingSessions,
                messagesEl: messagesEl,
                inputEl: inputEl,
                sidebarOpen: sidebarOpen,
                openSidebar: openSidebar,
                closeSidebar: closeSidebar,
                toggleSidebar: toggleSidebar,
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
