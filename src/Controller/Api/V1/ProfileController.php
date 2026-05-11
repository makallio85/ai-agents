<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Model\Entity\User;

/**
 * API controller for the authenticated user's own profile.
 *
 * Every logged-in user can read and update their own name and email, and
 * change their password. No special permission gate is required beyond
 * being authenticated — these endpoints only ever operate on the caller's
 * own record.
 */
class ProfileController extends AppController
{
    /**
     * GET /api/v1/profile
     *
     * Returns the currently authenticated user's profile (without password).
     * Always reads from the database — the session identity can be stale after
     * a profile update, so we must not use getCurrentUser() as the data source.
     */
    public function view(): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        /** @var \App\Model\Entity\User|null $record */
        $record = $this->fetchTable('Users')->find()->where(['Users.id' => $user->id])->first();
        if ($record === null) {
            $this->error('User not found', [], 404);
            return;
        }

        $this->success([
            'id'         => $record->id,
            'username'   => $record->username,
            'email'      => $record->email,
            'first_name' => $record->first_name,
            'last_name'  => $record->last_name,
        ]);
    }

    /**
     * POST /api/v1/profile/update
     *
     * Updates the authenticated user's first name, last name and email.
     * Username is not editable here to avoid breaking auth assumptions.
     *
     * Body: { first_name, last_name, email }
     */
    public function update(): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $usersTable = $this->fetchTable('Users');
        /** @var User|null $record */
        $record = $usersTable->find()->where(['Users.id' => $user->id])->first();
        if ($record === null) {
            $this->error('User not found', [], 404);
            return;
        }

        $record = $usersTable->patchEntity($record, [
            'first_name' => $this->request->getData('first_name'),
            'last_name'  => $this->request->getData('last_name'),
            'email'      => $this->request->getData('email'),
        ], ['fields' => ['first_name', 'last_name', 'email']]);

        if ($record->hasErrors()) {
            $this->error('Validation failed', array_keys($record->getErrors()), 422);
            return;
        }

        if (!$usersTable->save($record)) {
            $this->error('Failed to save profile', [], 422);
            return;
        }

        // Refresh the session identity so subsequent reads return the updated values
        $this->Authentication->setIdentity($record);

        $this->success([
            'id'         => $record->id,
            'username'   => $record->username,
            'email'      => $record->email,
            'first_name' => $record->first_name,
            'last_name'  => $record->last_name,
        ]);
    }

    /**
     * POST /api/v1/profile/change-password
     *
     * Changes the authenticated user's password after verifying their current one.
     *
     * Body: { current_password, new_password, new_password_confirmation }
     *
     * Requires current password verification to prevent session-hijack attacks
     * from silently locking out the real owner.
     */
    public function changePassword(): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $currentPassword = (string)$this->request->getData('current_password', '');
        $newPassword     = (string)$this->request->getData('new_password', '');
        $confirmation    = (string)$this->request->getData('new_password_confirmation', '');

        if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
            $this->error('All password fields are required', [], 422);
            return;
        }

        if ($newPassword !== $confirmation) {
            $this->error('New password and confirmation do not match', [], 422);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->error('New password must be at least 8 characters', [], 422);
            return;
        }

        $usersTable = $this->fetchTable('Users');
        /** @var User|null $record */
        $record = $usersTable->find()->where(['Users.id' => $user->id])->first();
        if ($record === null) {
            $this->error('User not found', [], 404);
            return;
        }

        if (!password_verify($currentPassword, $record->password)) {
            $this->error('Current password is incorrect', [], 422);
            return;
        }

        $record->password = $newPassword;
        if (!$usersTable->save($record)) {
            $this->error('Failed to change password', [], 422);
            return;
        }

        $this->success(['message' => 'Password changed successfully']);
    }
}
