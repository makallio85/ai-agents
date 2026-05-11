(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            // Profile fields
            var form = ref({ first_name: '', last_name: '', email: '' });
            var profileLoading  = ref(false);
            var profileSaving   = ref(false);
            var profileError    = ref('');
            var profileSuccess  = ref('');

            // Password fields
            var passwordForm = ref({ current_password: '', new_password: '', new_password_confirmation: '' });
            var passwordSaving  = ref(false);
            var passwordError   = ref('');
            var passwordSuccess = ref('');

            async function loadProfile() {
                profileLoading.value = true;
                profileError.value = '';
                try {
                    var data = await VueApi.profile.get();
                    form.value.first_name = data.first_name || '';
                    form.value.last_name  = data.last_name  || '';
                    form.value.email      = data.email      || '';
                } catch (e) {
                    profileError.value = e.message || 'Failed to load profile';
                } finally {
                    profileLoading.value = false;
                }
            }

            async function saveProfile() {
                profileSaving.value  = true;
                profileError.value   = '';
                profileSuccess.value = '';
                try {
                    await VueApi.profile.update(form.value);
                    profileSuccess.value = 'Profile updated successfully';
                } catch (e) {
                    profileError.value = e.message || 'Failed to save profile';
                } finally {
                    profileSaving.value = false;
                }
            }

            async function changePassword() {
                passwordSaving.value  = true;
                passwordError.value   = '';
                passwordSuccess.value = '';
                try {
                    await VueApi.profile.changePassword(passwordForm.value);
                    passwordSuccess.value = 'Password changed successfully';
                    passwordForm.value = { current_password: '', new_password: '', new_password_confirmation: '' };
                } catch (e) {
                    passwordError.value = e.message || 'Failed to change password';
                } finally {
                    passwordSaving.value = false;
                }
            }

            onMounted(loadProfile);

            return {
                form: form,
                profileLoading: profileLoading,
                profileSaving: profileSaving,
                profileError: profileError,
                profileSuccess: profileSuccess,
                passwordForm: passwordForm,
                passwordSaving: passwordSaving,
                passwordError: passwordError,
                passwordSuccess: passwordSuccess,
                saveProfile: saveProfile,
                changePassword: changePassword,
            };
        },
    }).mount('#profile-app');
})();
