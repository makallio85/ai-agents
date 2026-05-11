/**
 * API namespace for the authenticated user's own profile.
 */
var VueApi = VueApi || {};

VueApi.profile = {
    /**
     * Fetch the current user's profile data.
     * @returns {Promise<Object>}
     */
    get: async function () {
        var data = await Api.request('GET', 'profile');
        return data.data;
    },

    /**
     * Update name and email.
     * @param {{first_name: string, last_name: string, email: string}} payload
     * @returns {Promise<Object>}
     */
    update: async function (payload) {
        var data = await Api.request('POST', 'profile/update', payload);
        return data.data;
    },

    /**
     * Change password.
     * @param {{current_password: string, new_password: string, new_password_confirmation: string}} payload
     * @returns {Promise<Object>}
     */
    changePassword: async function (payload) {
        var data = await Api.request('POST', 'profile/change-password', payload);
        return data.data;
    },
};
