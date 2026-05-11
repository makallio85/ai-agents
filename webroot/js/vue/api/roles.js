/**
 * API namespace for roles and permissions management.
 */
var VueApi = VueApi || {};

VueApi.roles = {
    /**
     * Fetch all roles with their current permissions.
     * @returns {Promise<Array>}
     */
    getAll: async function () {
        var data = await Api.request('GET', 'roles');
        return data.data;
    },

    /**
     * Replace the full permission set for a role.
     * @param {number} roleId
     * @param {Array<{module: string, action: string}>} permissions
     * @returns {Promise<Object>}
     */
    updatePermissions: async function (roleId, permissions) {
        var data = await Api.request('POST', 'roles/update-permissions/' + roleId, { permissions: permissions });
        return data.data;
    },
};
