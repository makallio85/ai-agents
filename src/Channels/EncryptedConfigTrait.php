<?php
declare(strict_types=1);

namespace App\Channels;

use Cake\Utility\Security;

/**
 * Shared encrypt/decrypt helpers for channel config services.
 *
 * Both SlackConfigService and WhatsAppConfigService store sensitive
 * credentials (tokens, secrets) encrypted at rest using CakePHP's
 * Security::encrypt with the application's salt as the key. This trait
 * centralises that logic so it isn't duplicated across services.
 *
 * Security::getSalt() is used rather than Configure::read('Security.salt')
 * because bootstrap.php calls Security::setSalt() via Configure::consume(),
 * which removes the key from Configure. getSalt() is always reliable after
 * bootstrap.
 */
trait EncryptedConfigTrait
{
    private function encryptionKey(): string
    {
        return Security::getSalt();
    }

    private function encrypt(string $plain): string
    {
        return base64_encode(Security::encrypt($plain, $this->encryptionKey()));
    }

    private function decrypt(string $stored): string
    {
        $decoded = base64_decode($stored, true);
        if ($decoded === false) {
            return $stored;
        }
        $plain = Security::decrypt($decoded, $this->encryptionKey());
        return $plain !== null ? $plain : $stored;
    }
}
