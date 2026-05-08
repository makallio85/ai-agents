(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;

    createApp({
        setup: function () {
            var step = ref('login'); // 'login' | 'mfa'
            var email = ref('');
            var password = ref('');
            var otpCode = ref('');
            var mfaUserId = ref(null);
            var loading = ref(false);
            var error = ref('');

            async function handleLogin() {
                error.value = '';
                loading.value = true;
                try {
                    var result = await Api.auth.login(email.value, password.value);
                    if (result.data && result.data.mfa_required) {
                        mfaUserId.value = result.data.user_id;
                        step.value = 'mfa';
                    } else {
                        window.location.href = '/dashboard';
                    }
                } catch (e) {
                    error.value = (e && e.message) ? e.message : 'Login failed. Please check your credentials.';
                } finally {
                    loading.value = false;
                }
            }

            async function handleMfa() {
                error.value = '';
                loading.value = true;
                try {
                    await Api.auth.verifyMfa(mfaUserId.value, otpCode.value);
                    window.location.href = '/dashboard';
                } catch (e) {
                    error.value = (e && e.message) ? e.message : 'Invalid code. Please try again.';
                    otpCode.value = '';
                } finally {
                    loading.value = false;
                }
            }

            function backToLogin() {
                step.value = 'login';
                error.value = '';
                otpCode.value = '';
                mfaUserId.value = null;
            }

            return {
                step: step,
                email: email,
                password: password,
                otpCode: otpCode,
                loading: loading,
                error: error,
                handleLogin: handleLogin,
                handleMfa: handleMfa,
                backToLogin: backToLogin
            };
        },

        template: `
            <div>
                <div v-if="error" class="alert alert-danger alert-dismissible" role="alert">
                    {{ error }}
                    <button type="button" class="btn-close" @click="error = ''"></button>
                </div>

                <form v-if="step === 'login'" @submit.prevent="handleLogin" novalidate>
                    <div class="mb-3">
                        <label for="login-email" class="form-label">Email address</label>
                        <input
                            id="login-email"
                            v-model="email"
                            type="email"
                            class="form-control"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            :disabled="loading"
                        />
                    </div>
                    <div class="mb-4">
                        <label for="login-password" class="form-label">Password</label>
                        <input
                            id="login-password"
                            v-model="password"
                            type="password"
                            class="form-control"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            :disabled="loading"
                        />
                    </div>
                    <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                        {{ loading ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>

                <form v-else-if="step === 'mfa'" @submit.prevent="handleMfa" novalidate>
                    <p class="text-muted small mb-3">
                        A verification code has been sent to your phone. Enter it below.
                    </p>
                    <div class="mb-4">
                        <label for="login-otp" class="form-label">Verification code</label>
                        <input
                            id="login-otp"
                            v-model="otpCode"
                            type="text"
                            class="form-control form-control-lg text-center letter-spacing-wide"
                            placeholder="000000"
                            maxlength="8"
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            required
                            :disabled="loading"
                        />
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-2" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                        {{ loading ? 'Verifying…' : 'Verify' }}
                    </button>
                    <button type="button" class="btn btn-link w-100 text-muted" @click="backToLogin" :disabled="loading">
                        Back to sign in
                    </button>
                </form>
            </div>
        `
    }).mount('#login-app');
})();
