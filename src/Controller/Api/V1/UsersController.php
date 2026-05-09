<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Model\Entity\User;
use Cake\I18n\DateTime;

/**
 * Manages users from the admin panel.
 *
 * v1 surface area is intentionally small: list users (with optional approval
 * filter), and approve / reject pending entries. Full user CRUD belongs in a
 * dedicated admin module; for now these endpoints exist so superusers can
 * triage WhatsApp guests created by the OTP onboarding flow.
 */
class UsersController extends AppController
{
    /**
     * GET /api/v1/users?approval_state=pending&role=whatsapp_guest
     *
     * Lists users filtered by approval state and / or role slug. Used by the
     * Messaging Guests admin page to show entries awaiting review across
     * channels (whatsapp_guest, slack_guest, ...).
     */
    public function index(): void
    {
        $this->requirePermission('users', 'list_pending');

        $state = (string)$this->request->getQuery('approval_state', '');
        $roleSlug = (string)$this->request->getQuery('role', '');
        $users = $this->fetchTable('Users');
        $query = $users->find()->contain(['Roles']);
        if ($state !== '') {
            $query = $query->where(['Users.approval_state' => $state]);
        }
        if ($roleSlug !== '') {
            $query = $query->matching('Roles', function ($q) use ($roleSlug) {
                return $q->where(['Roles.slug' => $roleSlug]);
            });
        }
        $rows = $query->orderByDesc('Users.created')->all()->toList();
        $this->success($rows, ['count' => count($rows)]);
    }

    /**
     * GET /api/v1/users/view/:id
     */
    public function view(int $id): void
    {
        $this->requirePermission('users', 'list_pending');
        $user = $this->fetchTable('Users')->find()
            ->contain(['Roles'])
            ->where(['Users.id' => $id])->first();
        if ($user === null) {
            $this->error('User not found', [], 404);
            return;
        }
        $this->success($user);
    }

    /**
     * POST /api/v1/users/approve/:id
     *
     * Body: { role_id? }  — optional override of which role the user lands
     * on once approved. Defaults to keeping their current role (typically
     * whatsapp_guest, which has zero permissions until further granted).
     */
    public function approve(int $id): void
    {
        $this->requirePermission('users', 'approve');
        $approver = $this->getCurrentUser();
        if ($approver === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $users = $this->fetchTable('Users');
        /** @var User|null $user */
        $user = $users->find()->where(['Users.id' => $id])->first();
        if ($user === null) {
            $this->error('User not found', [], 404);
            return;
        }
        if ($user->approval_state === User::APPROVAL_APPROVED) {
            $this->success($user);
            return;
        }

        $newRoleId = $this->request->getData('role_id');
        if ($newRoleId !== null) {
            $user->role_id = (int)$newRoleId;
        }
        $user->approval_state = User::APPROVAL_APPROVED;
        $user->is_approved = true;
        $user->approved_by_user_id = $approver->id;
        $user->approved_at = new DateTime();

        if (!$users->save($user)) {
            $this->error('Failed to approve user: ' . json_encode($user->getErrors()), [], 422);
            return;
        }
        $this->success($user);
    }

    /**
     * POST /api/v1/users/reject/:id
     *
     * Marks the user rejected. They stay in the database (so future inbound
     * from the same number doesn't re-create them as pending), but the
     * approval gate in ProcessInboundMessageJob continues to block agent
     * dispatch. The phone is effectively blacklisted for this account.
     */
    public function reject(int $id): void
    {
        $this->requirePermission('users', 'reject');
        $approver = $this->getCurrentUser();
        if ($approver === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $users = $this->fetchTable('Users');
        /** @var User|null $user */
        $user = $users->find()->where(['Users.id' => $id])->first();
        if ($user === null) {
            $this->error('User not found', [], 404);
            return;
        }

        $user->approval_state = User::APPROVAL_REJECTED;
        $user->is_approved = false;
        $user->approved_by_user_id = $approver->id;
        $user->approved_at = new DateTime();
        $user->is_active = false;

        if (!$users->save($user)) {
            $this->error('Failed to reject user: ' . json_encode($user->getErrors()), [], 422);
            return;
        }
        $this->success($user);
    }
}
