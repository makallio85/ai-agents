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
        height: calc(100vh - 57px); /* subtract topbar height */
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
</style>

<div id="chat-app" v-cloak>
    <div class="chat-wrap">

        <!-- Session history sidebar -->
        <div class="chat-sessions">
            <div class="chat-sessions-header">
                <span>History</span>
                <button class="btn btn-sm btn-primary py-0 px-2" style="font-size:.78rem;" @click="newSession">
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
                    @click="loadSession(s.id)"
                >
                    <span class="session-del" @click.stop="deleteSession(s.id)" title="Delete">✕</span>
                    <div>{{ s.title || 'New chat' }}</div>
                    <div class="session-agent">{{ s.agent ? s.agent.name : '' }}</div>
                </div>
            </div>
        </div>

        <!-- Main chat panel -->
        <div class="chat-main">
            <!-- Header: agent selector when no session, or session agent name -->
            <div class="chat-header">
                <template v-if="!activeSession">
                    <i class="bi bi-robot text-muted"></i>
                    <select class="form-select form-select-sm" v-model="selectedAgentId">
                        <option value="">Select an agent…</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <button class="btn btn-sm btn-primary" @click="startSession" :disabled="!selectedAgentId">
                        Start chat
                    </button>
                </template>
                <template v-else>
                    <i class="bi bi-robot text-primary"></i>
                    <span class="fw-semibold" style="font-size:.9rem;">{{ activeSession.agent ? activeSession.agent.name : 'Agent' }}</span>
                    <span class="badge bg-light text-muted border ms-1" style="font-size:.7rem;">
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
                    <!-- Live streaming bubble -->
                    <div v-if="streaming" class="msg-row assistant">
                        <div class="msg-avatar">🤖</div>
                        <div>
                            <div class="msg-bubble">
                                {{ streamBuffer }}<span class="streaming-cursor"></span>
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
