<?php
/**
 * @var \App\View\AppView $this
 *
 * Admin page for reviewing and approving WhatsApp guest users.
 *
 * Vue app at #whatsapp-guests-app fetches /api/v1/users?approval_state=...
 * and exposes approve/reject buttons. Approval is gated server-side via
 * the users:approve permission (administrator + superuser by default).
 */
$this->assign('title', 'WhatsApp Guests');
?>

<div id="whatsapp-guests-app" v-cloak>
    <div class="d-flex align-items-center mb-4">
        <div>
            <h5 class="mb-0">WhatsApp Guests</h5>
            <small class="text-muted">Approve or reject phone numbers that messaged your agents.</small>
        </div>
        <div class="ms-auto btn-group btn-group-sm">
            <button class="btn" :class="filter === 'pending' ? 'btn-primary' : 'btn-outline-secondary'" @click="setFilter('pending')">
                Pending <span v-if="counts.pending !== null" class="badge bg-light text-dark ms-1">{{ counts.pending }}</span>
            </button>
            <button class="btn" :class="filter === 'approved' ? 'btn-primary' : 'btn-outline-secondary'" @click="setFilter('approved')">Approved</button>
            <button class="btn" :class="filter === 'rejected' ? 'btn-primary' : 'btn-outline-secondary'" @click="setFilter('rejected')">Rejected</button>
            <button class="btn" :class="filter === '' ? 'btn-primary' : 'btn-outline-secondary'" @click="setFilter('')">All</button>
        </div>
    </div>

    <div v-if="loadError" class="alert alert-danger py-2 small">{{ loadError }}</div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="text-center py-5 text-muted"><div class="spinner-border"></div></div>
            <div v-else-if="users.length === 0" class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:2rem;"></i>
                <div class="mt-2">No users {{ filter ? 'in this state' : 'yet' }}.</div>
            </div>
            <div v-else class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Phone</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>State</th>
                            <th>First seen</th>
                            <th>Approved by</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id">
                            <td class="ps-3 fw-medium">{{ u.phone_number || '—' }}</td>
                            <td class="small text-muted">{{ u.username }}</td>
                            <td class="small">{{ u.role ? u.role.name : '—' }}</td>
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

<?php $this->append('script', $this->Html->script('vue/pages/WhatsappGuests/index')); ?>
