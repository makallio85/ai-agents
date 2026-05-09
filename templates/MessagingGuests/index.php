<?php
/**
 * @var \App\View\AppView $this
 *
 * Admin page for reviewing and approving messaging guest users across
 * channels (WhatsApp, Slack, future).
 *
 * Vue app at #messaging-guests-app fetches /api/v1/users with optional
 * approval_state and role filters, and exposes approve/reject buttons.
 * Approval is gated server-side by users:approve (administrator + superuser).
 */
$this->assign('title', 'Messaging Guests');
?>

<div id="messaging-guests-app" v-cloak>
    <div class="d-flex align-items-center mb-3">
        <div>
            <h5 class="mb-0">Messaging Guests</h5>
            <small class="text-muted">Approve or reject senders that messaged your agents from external channels.</small>
        </div>
        <div class="ms-auto btn-group btn-group-sm">
            <button class="btn" :class="stateFilter === 'pending' ? 'btn-primary' : 'btn-outline-secondary'" @click="setStateFilter('pending')">
                Pending <span v-if="counts.pending !== null" class="badge bg-light text-dark ms-1">{{ counts.pending }}</span>
            </button>
            <button class="btn" :class="stateFilter === 'approved' ? 'btn-primary' : 'btn-outline-secondary'" @click="setStateFilter('approved')">Approved</button>
            <button class="btn" :class="stateFilter === 'rejected' ? 'btn-primary' : 'btn-outline-secondary'" @click="setStateFilter('rejected')">Rejected</button>
            <button class="btn" :class="stateFilter === '' ? 'btn-primary' : 'btn-outline-secondary'" @click="setStateFilter('')">All</button>
        </div>
    </div>

    <div class="d-flex align-items-center mb-4">
        <small class="text-muted me-3">Channel</small>
        <div class="btn-group btn-group-sm">
            <button class="btn" :class="channelFilter === '' ? 'btn-secondary' : 'btn-outline-secondary'" @click="setChannelFilter('')">All</button>
            <button class="btn" :class="channelFilter === 'whatsapp' ? 'btn-success' : 'btn-outline-success'" @click="setChannelFilter('whatsapp')">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </button>
            <button class="btn" :class="channelFilter === 'slack' ? 'btn-primary' : 'btn-outline-primary'" @click="setChannelFilter('slack')">
                <i class="bi bi-slack"></i> Slack
            </button>
        </div>
    </div>

    <div v-if="loadError" class="alert alert-danger py-2 small">{{ loadError }}</div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="text-center py-5 text-muted"><div class="spinner-border"></div></div>
            <div v-else-if="users.length === 0" class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:2rem;"></i>
                <div class="mt-2">No users {{ stateFilter || channelFilter ? 'in this filter' : 'yet' }}.</div>
            </div>
            <div v-else class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Channel</th>
                            <th>Identifier</th>
                            <th>Username</th>
                            <th>State</th>
                            <th>First seen</th>
                            <th>Approved by</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id">
                            <td class="ps-3">
                                <span v-for="ch in channelsFor(u)" :key="ch" class="badge me-1" :class="channelBadge(ch)">
                                    <i class="bi" :class="channelIcon(ch)"></i> {{ channelLabel(ch) }}
                                </span>
                                <span v-if="channelsFor(u).length === 0" class="text-muted small">—</span>
                            </td>
                            <td class="small fw-medium">{{ identifierFor(u) }}</td>
                            <td class="small text-muted">{{ u.username }}</td>
                            <td>
                                <span class="badge" :class="stateBadge(u.approval_state)">{{ u.approval_state }}</span>
                            </td>
                            <td class="small text-muted">{{ formatDate(u.created) }}</td>
                            <td class="small text-muted">{{ u.approved_by_user_id ? '#' + u.approved_by_user_id : '—' }}</td>
                            <td class="text-end pe-3">
                                <button v-if="u.approval_state !== 'approved'" class="btn btn-sm btn-success me-1" @click="approve(u)" :disabled="acting[u.id]">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                                <button v-if="u.approval_state !== 'rejected'" class="btn btn-sm btn-outline-danger" @click="reject(u)" :disabled="acting[u.id]">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/MessagingGuests/index')); ?>
