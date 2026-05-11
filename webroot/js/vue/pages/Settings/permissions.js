(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var computed = Vue.computed;
    var onMounted = Vue.onMounted;

    var MODULES = [
        { key: 'agents',              label: 'Agents' },
        { key: 'chat',                label: 'Chat' },
        { key: 'conversations',       label: 'Conversations' },
        { key: 'users',               label: 'Users' },
        { key: 'roles',               label: 'Roles' },
        { key: 'labels',              label: 'Labels' },
        { key: 'github_integrations', label: 'GitHub Integrations' },
        { key: 'execution_history',   label: 'Execution History' },
        { key: 'agent_logs',          label: 'Agent Logs' },
        { key: 'prompt_versions',     label: 'Prompt Versions' },
    ];

    var STANDARD_ACTIONS = ['read', 'create', 'update', 'delete'];

    createApp({
        setup: function () {
            var roles        = ref([]);
            var selectedRole = ref(null);
            var checked      = ref({});   // { 'module|action': true }
            var loading      = ref(false);
            var saving       = ref(false);
            var error        = ref('');
            var success      = ref('');

            // Build the checked map from a role's permissions array
            function buildChecked(permissions) {
                var map = {};
                (permissions || []).forEach(function (p) {
                    map[p.module + '|' + p.action] = true;
                });
                return map;
            }

            async function loadRoles() {
                loading.value = true;
                error.value = '';
                try {
                    roles.value = await VueApi.roles.getAll();
                    if (roles.value.length > 0) {
                        selectRole(roles.value[0]);
                    }
                } catch (e) {
                    error.value = e.message || 'Failed to load roles';
                } finally {
                    loading.value = false;
                }
            }

            function selectRole(role) {
                selectedRole.value = role;
                checked.value = buildChecked(role.permissions);
                success.value = '';
                error.value = '';
            }

            function toggle(module, action) {
                var key = module + '|' + action;
                if (checked.value[key]) {
                    delete checked.value[key];
                } else {
                    checked.value[key] = true;
                }
            }

            function isChecked(module, action) {
                return !!checked.value[module + '|' + action];
            }

            // Toggle all actions for a module at once
            function toggleModule(module) {
                var allOn = STANDARD_ACTIONS.every(function (a) { return isChecked(module, a); });
                STANDARD_ACTIONS.forEach(function (action) {
                    var key = module + '|' + action;
                    if (allOn) {
                        delete checked.value[key];
                    } else {
                        checked.value[key] = true;
                    }
                });
            }

            function isModuleAllOn(module) {
                return STANDARD_ACTIONS.every(function (a) { return isChecked(module, a); });
            }

            async function save() {
                if (!selectedRole.value) return;
                saving.value = true;
                error.value = '';
                success.value = '';
                try {
                    var permissions = Object.keys(checked.value).map(function (key) {
                        var parts = key.split('|');
                        return { module: parts[0], action: parts[1] };
                    });
                    var updated = await VueApi.roles.updatePermissions(selectedRole.value.id, permissions);
                    // Sync the local role entry so switching back shows updated state
                    var idx = roles.value.findIndex(function (r) { return r.id === updated.id; });
                    if (idx !== -1) roles.value[idx] = updated;
                    success.value = 'Permissions saved for ' + selectedRole.value.name;
                } catch (e) {
                    error.value = e.message || 'Failed to save permissions';
                } finally {
                    saving.value = false;
                }
            }

            onMounted(loadRoles);

            return {
                roles: roles,
                selectedRole: selectedRole,
                loading: loading,
                saving: saving,
                error: error,
                success: success,
                MODULES: MODULES,
                STANDARD_ACTIONS: STANDARD_ACTIONS,
                selectRole: selectRole,
                toggle: toggle,
                isChecked: isChecked,
                toggleModule: toggleModule,
                isModuleAllOn: isModuleAllOn,
                save: save,
            };
        },
    }).mount('#permissions-app');
})();
