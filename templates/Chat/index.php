<?php
/**
 * Chat interface page.
 *
 * Renders the full-height two-panel chat UI: a session history sidebar on
 * the left and a streaming message thread on the right. All data is loaded
 * and updated via Vue 3 + the /api/v1/chat endpoints. No server-side data
 * is passed — the Vue app bootstraps itself via API calls on mount.
 */
$this->assign('title', 'Chat');
?>
<style>
    /* Override page-content padding for the full-height chat layout */
    .page-content { padding: 0 !important; }

    .chat-wrap {
        display: flex;
        height: calc(100vh - 57px);  /* fallback for browsers without dvh */
        height: calc(100dvh - 57px); /* dynamic viewport height — avoids the
                                         iOS Safari URL-bar overflow that made
                                         the page vertically scrollable. */
        overflow: hidden;
    }

    /* Session history sidebar */
    .chat-sessions {
        width: 220px;
        min-width: 220px;
        background: #fff;
        border-right: 1px solid #dee2e6;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Toggle button shown on mobile only; hidden on >=md via Bootstrap. */
    .chat-sessions-toggle {
        background: transparent;
        border: 0;
        padding: .25rem .5rem;
        font-size: 1.15rem;
        line-height: 1;
        color: #495057;
    }
    .chat-sessions-toggle:hover { color: #1a1d23; }

    .chat-sessions-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .4);
        z-index: 10;
        display: none;
    }
    .chat-sessions-backdrop.show { display: block; }

    /* Mobile: collapse the sessions sidebar into a slide-in panel so the
       message thread can use the full viewport width. */
    @media (max-width: 767.98px) {
        .chat-wrap { position: relative; }

        .chat-sessions {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 20;
            width: 80vw;
            min-width: 0;
            max-width: 280px;
            transform: translateX(-100%);
            transition: transform .2s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, .25);
        }
        .chat-sessions.show { transform: translateX(0); }

        .chat-main { width: 100%; }

        .chat-header { padding: .625rem .875rem; gap: .5rem; }
        .chat-header select { max-width: none; flex: 1; min-width: 0; }
        .chat-messages { padding: .875rem; }
        .msg-bubble { max-width: 85%; }
        .chat-input-area { padding: .75rem .875rem; }
    }
    .chat-sessions-header {
        padding: .875rem 1rem;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        font-size: .85rem;
        color: #495057;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-sessions-list {
        flex: 1;
        overflow-y: auto;
        padding: .5rem 0;
    }
    .session-item {
        padding: .5rem 1rem;
        cursor: pointer;
        border-left: 3px solid transparent;
        font-size: .82rem;
        color: #495057;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background .12s;
    }
    .session-item:hover { background: #f8f9fa; }
    .session-item.active {
        background: #e8f0fe;
        border-left-color: #4a6cf7;
        color: #1a1d23;
        font-weight: 500;
    }
    .session-item .session-agent {
        font-size: .72rem;
        color: #6c757d;
        margin-top: 1px;
    }
    .session-item .session-del {
        float: right;
        color: #adb5bd;
        font-size: .75rem;
        padding: 0 2px;
        opacity: 0;
        transition: opacity .12s;
    }
    .session-item:hover .session-del { opacity: 1; }
    .session-item .session-del:hover { color: #dc3545; }

    /* Main chat area */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f8f9fa;
    }
    .chat-header {
        padding: .75rem 1.25rem;
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        gap: .75rem;
        min-height: 57px;
    }
    .chat-header select {
        max-width: 240px;
        font-size: .85rem;
    }

    /* Message thread */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: .875rem;
    }
    .chat-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        gap: .5rem;
        font-size: .9rem;
    }
    .msg-row {
        display: flex;
        gap: .625rem;
    }
    .msg-row.user { flex-direction: row-reverse; }
    .msg-bubble {
        max-width: 72%;
        padding: .625rem .875rem;
        border-radius: 12px;
        font-size: .875rem;
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .msg-row.user .msg-bubble {
        background: #4a6cf7;
        color: #fff;
        border-bottom-right-radius: 3px;
    }
    .msg-row.assistant .msg-bubble {
        background: #fff;
        color: #212529;
        border: 1px solid #e9ecef;
        border-bottom-left-radius: 3px;
    }
    .msg-row.assistant .msg-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e8f0fe;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .msg-row.user .msg-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .msg-meta {
        font-size: .7rem;
        color: #adb5bd;
        margin-top: 3px;
        padding: 0 2px;
    }
    .msg-row.user .msg-meta { text-align: right; }

    /* Streaming cursor */
    .streaming-cursor {
        display: inline-block;
        width: 8px;
        height: 14px;
        background: #4a6cf7;
        border-radius: 2px;
        margin-left: 2px;
        vertical-align: middle;
        animation: blink .7s steps(2) infinite;
    }
    @keyframes blink { to { opacity: 0; } }

    /* Input area */
    .chat-input-area {
        padding: 1rem 1.25rem;
        background: #fff;
        border-top: 1px solid #dee2e6;
    }
    .chat-input-wrap {
        display: flex;
        gap: .625rem;
        align-items: flex-end;
    }
    .chat-input-wrap textarea {
        flex: 1;
        resize: none;
        border-radius: 10px;
        font-size: .875rem;
        max-height: 140px;
        overflow-y: auto;
    }
    .chat-input-wrap .btn-send {
        height: 38px;
        width: 38px;
        border-radius: 10px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .error-banner {
        background: #fff3f3;
        border: 1px solid #f5c6cb;
        color: #721c24;
        border-radius: 8px;
        padding: .5rem .875rem;
        font-size: .8rem;
        margin-bottom: .5rem;
    }

    /* Tool activity log shown inside the streaming bubble */
    .tool-activity {
        margin-bottom: .5rem;
    }
    .tool-event {
        display: flex;
        align-items: flex-start;
        gap: .4rem;
        font-size: .75rem;
        color: #6c757d;
        padding: 2px 0;
        line-height: 1.4;
    }
    .tool-event.running {
        color: #0d6efd;
    }
    .tool-event.done {
        color: #198754;
    }
    .tool-event .tool-icon {
        flex-shrink: 0;
        margin-top: 1px;
    }
    .tool-spinner {
        width: 10px;
        height: 10px;
        border: 2px solid #0d6efd44;
        border-top-color: #0d6efd;
        border-radius: 50%;
        animation: spin .6s linear infinite;
        flex-shrink: 0;
        margin-top: 3px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<div id="chat-app" v-cloak>
    <div class="chat-wrap">

        <!-- Backdrop shown on mobile when the slide-in session panel is
             open; tapping it closes the panel. Hidden via CSS on >=md. -->
        <div
            class="chat-sessions-backdrop d-md-none"
            :class="{ show: sidebarOpen }"
            @click="closeSidebar"
        ></div>

        <!-- Session history sidebar -->
        <div class="chat-sessions" :class="{ show: sidebarOpen }">
            <div class="chat-sessions-header">
                <span>History</span>
                <button class="btn btn-sm btn-primary py-0 px-2" style="font-size:.78rem;" @click="newSession(); closeSidebar();">
                    + New
                </button>
            </div>
            <div class="chat-sessions-list">
                <div v-if="loadingSessions" class="px-3 py-2 text-muted" style="font-size:.8rem;">Loading…</div>
                <div v-else-if="sessions.length === 0" class="px-3 py-2 text-muted" style="font-size:.8rem;">No chats yet</div>
                <div
                    v-for="s in sessions"
                    :key="s.id"
                    class="session-item"
                    :class="{ active: activeSession && activeSession.id === s.id }"
                    @click="loadSession(s.id); closeSidebar();"
                >
                    <span class="session-del" @click.stop="deleteSession(s.id)" title="Delete">✕</span>
                    <div>
                        <span v-if="s.channel && s.channel !== 'web'" class="badge bg-success-subtle text-success border me-1" style="font-size:.62rem;text-transform:uppercase;">{{ s.channel }}</span>
                        <span v-if="s.assignment_state === 'pending_human'" class="badge bg-warning-subtle text-warning border me-1" style="font-size:.62rem;">awaits human</span>
                        <span v-else-if="s.assignment_state === 'human'" class="badge bg-info-subtle text-info border me-1" style="font-size:.62rem;">human</span>
                        {{ s.title || 'New chat' }}
                    </div>
                    <div class="session-agent">{{ s.agent ? s.agent.name : '' }}</div>
                </div>
            </div>
        </div>

        <!-- Main chat panel -->
        <div class="chat-main">
            <!-- Header: agent selector when no session, or session agent name -->
            <div class="chat-header">
                <button
                    type="button"
                    class="chat-sessions-toggle d-md-none"
                    aria-label="Toggle chat history"
                    @click="toggleSidebar"
                >
                    <i class="bi bi-list"></i>
                </button>
                <template v-if="!activeSession">
                    <i class="bi bi-robot text-muted d-none d-md-inline"></i>
                    <select class="form-select form-select-sm" v-model="selectedAgentId">
                        <option value="">Select an agent…</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <button class="btn btn-sm btn-primary flex-shrink-0" @click="startSession" :disabled="!selectedAgentId">
                        Start chat
                    </button>
                </template>
                <template v-else>
                    <i class="bi bi-robot text-primary d-none d-md-inline"></i>
                    <span class="fw-semibold text-truncate" style="font-size:.9rem;">{{ activeSession.agent ? activeSession.agent.name : 'Agent' }}</span>
                    <span class="badge bg-light text-muted border ms-1 d-none d-sm-inline" style="font-size:.7rem;">
                        {{ activeSession.agent ? activeSession.agent.llm_provider || 'No LLM' : '' }}
                    </span>
                </template>
            </div>

            <!-- Messages thread -->
            <div class="chat-messages" ref="messagesEl">
                <div v-if="!activeSession" class="chat-empty">
                    <i class="bi bi-chat-square-dots" style="font-size:2rem;"></i>
                    <span>Select a chat or start a new one</span>
                </div>
                <template v-else>
                    <div v-if="messages.length === 0 && !streaming" class="chat-empty">
                        <i class="bi bi-chat-square-dots" style="font-size:2rem;"></i>
                        <span>Send a message to begin</span>
                    </div>
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="msg-row"
                        :class="msg.role"
                    >
                        <div class="msg-avatar">{{ msg.role === 'user' ? '👤' : '🤖' }}</div>
                        <div>
                            <div class="msg-bubble">{{ msg.content }}</div>
                            <div class="msg-meta">{{ formatTime(msg.created) }}</div>
                        </div>
                    </div>
                    <!-- Live streaming bubble (text chunks + tool activity) -->
                    <div v-if="streaming" class="msg-row assistant">
                        <div class="msg-avatar">🤖</div>
                        <div>
                            <div class="msg-bubble">
                                <!-- Tool activity log -->
                                <div v-if="toolActivity.length > 0" class="tool-activity">
                                    <div
                                        v-for="(t, i) in toolActivity"
                                        :key="i"
                                        class="tool-event"
                                        :class="t.status"
                                    >
                                        <span v-if="t.status === 'running'" class="tool-spinner"></span>
                                        <span v-else class="tool-icon">✓</span>
                                        <span>{{ t.label }}</span>
                                    </div>
                                </div>
                                <!-- Streaming text -->
                                <template v-if="streamBuffer">{{ streamBuffer }}</template>
                                <span class="streaming-cursor"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Error banner -->
            <div v-if="sendError" class="chat-input-area pb-0">
                <div class="error-banner">{{ sendError }}</div>
            </div>

            <!-- Input area (shown only when a session is active) -->
            <div v-if="activeSession" class="chat-input-area">
                <div class="chat-input-wrap">
                    <textarea
                        ref="inputEl"
                        class="form-control"
                        rows="1"
                        placeholder="Type a message… (Enter to send, Shift+Enter for newline)"
                        v-model="inputText"
                        @keydown.enter.exact.prevent="sendMessage"
                        @input="autoResize"
                        :disabled="streaming"
                    ></textarea>
                    <button
                        class="btn btn-primary btn-send"
                        @click="sendMessage"
                        :disabled="streaming || !inputText.trim()"
                        title="Send"
                    >
                        <i class="bi" :class="streaming ? 'bi-hourglass-split' : 'bi-send-fill'" style="font-size:.85rem;"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Chat/index')); ?>
