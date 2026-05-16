<?php
/**
 * @var \App\View\AppView $this
 *
 * Users admin page. Vue app at #users-app fetches /api/v1/users (no
 * filter) so every row in users — operators, agents' service accounts,
 * messaging guests — is visible in one place. Approval and reject
 * actions live on MessagingRequests; this page is read-only for now.
 */
$this->assign('title', 'Users');
?>

<div id="users-app" v-cloak>
    <div class="d-flex align-items-center mb-3">
        <div>
            <h5 class="mb-0">Users</h5>
            <small class="text-muted">All registered users across all channels and roles.</small>
        </div>
    </div>

    <div v-if="loadError" class="alert alert-danger py-2 small">{{ loadError }}</div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div v-if="loading" class="text-center py-5 text-muted"><div class="spinner-border"></div></div>
            <div v-else-if="users.length === 0" class="text-center py-5 text-muted">
                <i class="bi bi-person" style="font-size:2rem;"></i>
                <div class="mt-2">No users yet.</div>
            </div>
            <div v-else class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Approval</th>
                            <th>Active</th>
                            <th class="pe-3">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id">
                            <td class="ps-3 text-muted small">#{{ u.id }}</td>
                            <td class="small fw-medium">{{ u.username }}</td>
                            <td class="small text-muted">{{ u.email }}</td>
                            <td class="small">{{ u.role && u.role.name || '—' }}</td>
                            <td>
                                <span class="badge" :class="stateBadge(u.approval_state)">{{ u.approval_state }}</span>
                            </td>
                            <td>
                                <span class="badge" :class="u.is_active ? 'bg-success' : 'bg-secondary'">
                                    {{ u.is_active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="small text-muted pe-3">{{ formatDate(u.created) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $this->append('script', $this->Html->script('vue/pages/Users/index')); ?>
