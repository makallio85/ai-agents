<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Permissions');
?>
<div id="permissions-app" v-cloak>

    <div v-if="loading" class="text-center text-muted py-5">
        <div class="spinner-border spinner-border-sm me-2"></div> Loading roles…
    </div>

    <template v-else>
        <div v-if="error" class="alert alert-danger py-2 mb-3">{{ error }}</div>
        <div v-if="success" class="alert alert-success py-2 mb-3">{{ success }}</div>

        <!-- Role tabs -->
        <ul class="nav nav-tabs mb-4">
            <li v-for="role in roles" :key="role.id" class="nav-item">
                <button
                    class="nav-link"
                    :class="{ active: selectedRole && selectedRole.id === role.id }"
                    @click="selectRole(role)"
                >
                    {{ role.name }}
                </button>
            </li>
        </ul>

        <template v-if="selectedRole">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <span class="fw-semibold">{{ selectedRole.name }}</span>
                    <button class="btn btn-primary btn-sm" @click="save" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>
                        Save
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width:220px">Module</th>
                                    <th v-for="action in STANDARD_ACTIONS" :key="action" class="text-center text-capitalize" style="width:100px">
                                        {{ action }}
                                    </th>
                                    <th class="text-center" style="width:80px">All</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="mod in MODULES" :key="mod.key">
                                    <td class="ps-4 fw-medium small">{{ mod.label }}</td>
                                    <td v-for="action in STANDARD_ACTIONS" :key="action" class="text-center">
                                        <div class="form-check d-flex justify-content-center mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="isChecked(mod.key, action)"
                                                @change="toggle(mod.key, action)"
                                            >
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="isModuleAllOn(mod.key)"
                                                @change="toggleModule(mod.key)"
                                                title="Toggle all actions"
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </template>

</div>

<?php $this->append('script', $this->Html->script('vue/api/roles')); ?>
<?php $this->append('script', $this->Html->script('vue/pages/Settings/permissions')); ?>
