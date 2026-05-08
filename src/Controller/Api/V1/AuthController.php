<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Service\MfaService;
use App\Service\Sms\TwilioSmsProvider;
use Cake\Core\Configure;
use Cake\Event\EventInterface;

class AuthController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['login', 'verifyMfa']);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(): void
    {
        $result = $this->Authentication->getResult();

        if (!$result || !$result->isValid()) {
            $this->error('Invalid email or password', [], 401);
            return;
        }

        $user = $result->getData();

        if (!$user->is_active) {
            $this->Authentication->logout();
            $this->error('Account is disabled', [], 403);
            return;
        }

        if ($user->mfa_enabled) {
            // Trigger MFA — send SMS
            try {
                $mfaService = $this->buildMfaService();
                $mfaService->sendToken($user);

                $this->success([
                    'mfa_required' => true,
                    'user_id' => $user->id,
                ]);
            } catch (\Exception $e) {
                $this->error('Failed to send MFA code: ' . $e->getMessage(), [], 500);
            }
            return;
        }

        $users = $this->fetchTable('Users');
        $users->updateAll(['last_login_at' => new \Cake\I18n\DateTime()], ['id' => $user->id]);

        $this->success([
            'mfa_required' => false,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'role_id' => $user->role_id,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/verify-mfa
     */
    public function verifyMfa(): void
    {
        $data = $this->request->getData();
        $userId = (int)($data['user_id'] ?? 0);
        $token = (string)($data['token'] ?? '');

        if (!$userId || empty($token)) {
            $this->error('Missing user_id or token', [], 400);
            return;
        }

        /** @var \App\Model\Entity\User|null $user */
        $user = $this->fetchTable('Users')->find()->where(['Users.id' => $userId, 'Users.is_active' => true])->first();

        if ($user === null) {
            $this->error('User not found', [], 404);
            return;
        }

        $mfaService = $this->buildMfaService();

        if (!$mfaService->verifyToken($user, $token)) {
            $this->error('Invalid or expired verification code', [], 401);
            return;
        }

        $this->fetchTable('Users')->updateAll(['last_login_at' => new \Cake\I18n\DateTime()], ['id' => $user->id]);

        $this->success([
            'verified' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'role_id' => $user->role_id,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): void
    {
        $this->Authentication->logout();
        $this->success(['message' => 'Logged out successfully']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            $this->error('Not authenticated', [], 401);
            return;
        }

        $this->success([
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'role_id' => $user->role_id,
            'mfa_enabled' => $user->mfa_enabled,
        ]);
    }

    private function buildMfaService(): MfaService
    {
        $smsProvider = new TwilioSmsProvider(
            accountSid: (string)Configure::read('Twilio.accountSid'),
            authToken: (string)Configure::read('Twilio.authToken'),
            fromNumber: (string)Configure::read('Twilio.fromNumber')
        );
        return new MfaService($smsProvider);
    }
}
