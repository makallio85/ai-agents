<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'My Profile');
?>
<div id="profile-app" v-cloak>

    <div v-if="profileLoading" class="text-center text-muted py-5">
        <div class="spinner-border spinner-border-sm me-2"></div> Loading…
    </div>

    <template v-else>
        <div class="row g-4">

            <!-- Profile details -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <span class="fw-semibold"><i class="bi bi-person me-2"></i>Profile Details</span>
                    </div>
                    <div class="card-body">
                        <div v-if="profileError" class="alert alert-danger py-2">{{ profileError }}</div>
                        <div v-if="profileSuccess" class="alert alert-success py-2">{{ profileSuccess }}</div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-medium">First name</label>
                                <input v-model="form.first_name" type="text" class="form-control" placeholder="First name" />
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-medium">Last name</label>
                                <input v-model="form.last_name" type="text" class="form-control" placeholder="Last name" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Email</label>
                                <input v-model="form.email" type="email" class="form-control" placeholder="email@example.com" />
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button class="btn btn-primary" @click="saveProfile" :disabled="profileSaving">
                                <span v-if="profileSaving" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check-lg me-1"></i>
                                Save changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change password -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <span class="fw-semibold"><i class="bi bi-key me-2"></i>Change Password</span>
                    </div>
                    <div class="card-body">
                        <div v-if="passwordError" class="alert alert-danger py-2">{{ passwordError }}</div>
                        <div v-if="passwordSuccess" class="alert alert-success py-2">{{ passwordSuccess }}</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-medium">Current password</label>
                                <input v-model="passwordForm.current_password" type="password" class="form-control" autocomplete="current-password" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">New password</label>
                                <input v-model="passwordForm.new_password" type="password" class="form-control" autocomplete="new-password" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Confirm new password</label>
                                <input v-model="passwordForm.new_password_confirmation" type="password" class="form-control" autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button class="btn btn-primary" @click="changePassword" :disabled="passwordSaving">
                                <span v-if="passwordSaving" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-key me-1"></i>
                                Change password
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </template>

</div>

<?php $this->append('script', $this->Html->script('vue/api/profile')); ?>
<?php $this->append('script', $this->Html->script('vue/pages/Profile/index')); ?>
