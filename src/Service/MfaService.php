<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\User;
use App\Service\Sms\SmsProviderInterface;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Log\LogTrait;
use Cake\ORM\TableRegistry;
use RuntimeException;

class MfaService
{
    use LogTrait;

    public function __construct(
        private readonly SmsProviderInterface $smsProvider
    ) {
    }

    /**
     * Generate a one-time token and send via SMS.
     */
    public function sendToken(User $user): void
    {
        if (empty($user->phone_number)) {
            throw new RuntimeException('User has no phone number configured for MFA.');
        }

        $length = (int)Configure::read('App.mfaOtpLength', 6);
        $ttl = (int)Configure::read('App.mfaOtpTtl', 300);

        $token = $this->generateToken($length);
        $hash = password_hash($token, PASSWORD_BCRYPT);
        $expiresAt = new DateTime("+{$ttl} seconds");

        /** @var \App\Model\Table\MfaTokensTable $mfaTokens */
        $mfaTokens = TableRegistry::getTableLocator()->get('MfaTokens');

        // Invalidate old unused tokens for this user
        $mfaTokens->updateAll(
            ['used' => true],
            ['user_id' => $user->id, 'used' => false]
        );

        $entity = $mfaTokens->newEntity([
            'user_id' => $user->id,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        if (!$mfaTokens->save($entity)) {
            throw new RuntimeException('Failed to store MFA token.');
        }

        $this->smsProvider->send(
            $user->phone_number,
            "Your AI-Agents verification code: {$token}. Valid for {$ttl} seconds."
        );

        $this->log("MFA token sent to user {$user->id}", 'info', ['scope' => 'mfa']);
    }

    /**
     * Verify a submitted OTP token for the user.
     */
    public function verifyToken(User $user, string $submittedToken): bool
    {
        /** @var \App\Model\Table\MfaTokensTable $mfaTokens */
        $mfaTokens = TableRegistry::getTableLocator()->get('MfaTokens');

        $token = $mfaTokens->find('validByUser', userId: $user->id)->first();

        if ($token === null) {
            $this->log("MFA verify failed: no valid token for user {$user->id}", 'warning', ['scope' => 'mfa']);
            return false;
        }

        if (!password_verify($submittedToken, $token->token_hash)) {
            $this->log("MFA verify failed: wrong token for user {$user->id}", 'warning', ['scope' => 'mfa']);
            return false;
        }

        $token->used = true;
        $mfaTokens->save($token);

        $this->log("MFA verified successfully for user {$user->id}", 'info', ['scope' => 'mfa']);
        return true;
    }

    private function generateToken(int $length): string
    {
        $max = (int)str_pad('9', $length, '9');
        return str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
