<?php
declare(strict_types=1);

namespace App\Channels\WhatsApp\Service;

use App\Channels\WhatsApp\WhatsAppClientInterface;
use App\Messaging\Dto\InboundEnvelope;
use App\Model\Entity\Agent;
use App\Model\Entity\ChannelVerification;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Handles the OTP handshake for unknown WhatsApp senders.
 *
 * State machine, keyed on (channel='whatsapp', external_identifier=phone):
 *   no row              -> first contact: issue OTP, persist verification with
 *                          buffered original body, send code via WhatsApp API
 *   active row, code wrong   -> increment attempts, prompt again until cap reached
 *   active row, code correct -> mark verified, link/create User, replay buffered
 *                               original message, return User
 *   expired/exhausted   -> wipe and start over on next inbound
 *
 * The buffered message is replayed by creating both ChatSession and ChatMessage
 * here (rather than going through MessageDispatcher again, which would loop).
 */
class WhatsAppOnboardingService
{
    public function __construct(
        private readonly WhatsAppClientInterface $client,
        private readonly WhatsAppConfigService $configService,
    ) {
    }

    public function handle(InboundEnvelope $envelope, ?Agent $agent): ?User
    {
        if ($agent === null) {
            return null;
        }
        $config = $this->configService->findConfigByAgentId($agent->id);
        if ($config === null) {
            return null;
        }

        $verifications = TableRegistry::getTableLocator()->get('ChannelVerifications');
        /** @var ChannelVerification|null $active */
        $active = $verifications->find('active', channel: 'whatsapp', externalIdentifier: $envelope->externalIdentifier)
            ->first();

        if ($active === null) {
            $this->startVerification($envelope, $agent, $config);
            return null;
        }

        $maxAttempts = (int)Configure::read('Channels.whatsapp.maxOtpAttempts', 5);
        if ($active->attempts >= $maxAttempts) {
            // Cap reached — wipe the row so the next inbound starts a fresh flow.
            $verifications->delete($active);
            $this->client->sendText(
                $config->phoneNumberId,
                $config->accessToken,
                $envelope->externalIdentifier,
                'Too many incorrect codes. Please send any message to start over.',
            );
            return null;
        }

        $submittedCode = trim($envelope->body);
        if (!password_verify($submittedCode, $active->code_hash)) {
            $active->attempts += 1;
            $verifications->save($active);
            $remaining = max(0, $maxAttempts - $active->attempts);
            $this->client->sendText(
                $config->phoneNumberId,
                $config->accessToken,
                $envelope->externalIdentifier,
                "That code didn't match. {$remaining} attempt(s) left — please reply with your code.",
            );
            return null;
        }

        // Code correct — mark verified, resolve / create the user, replay buffered message.
        $active->verified = true;
        $active->verified_at = new DateTime();
        $verifications->save($active);

        $user = $this->resolveOrCreateUser($envelope->externalIdentifier);
        if ($user === null) {
            return null;
        }

        if (!empty($active->pending_payload)) {
            $this->replayBuffered($user, $agent, $envelope, (string)$active->pending_payload);
        }

        return $user;
    }

    private function startVerification(InboundEnvelope $envelope, Agent $agent, WhatsAppAgentConfig $config): void
    {
        $code = $this->generateCode();
        $ttl = (int)Configure::read('Channels.whatsapp.otpTtl', 600);

        $verifications = TableRegistry::getTableLocator()->get('ChannelVerifications');
        $entity = $verifications->newEntity([
            'channel' => 'whatsapp',
            'external_identifier' => $envelope->externalIdentifier,
            'code_hash' => password_hash($code, PASSWORD_BCRYPT),
            'expires_at' => (new DateTime())->modify("+{$ttl} seconds"),
            'attempts' => 0,
            'verified' => false,
            'agent_id' => $agent->id,
            'pending_payload' => json_encode([
                'body' => $envelope->body,
                'content_type' => $envelope->contentType,
                'external_message_id' => $envelope->externalMessageId,
                'external_thread_id' => $envelope->externalThreadId,
                'media_url' => $envelope->mediaUrl,
                'media_mime_type' => $envelope->mediaMimeType,
                'raw' => $envelope->rawPayload,
            ]),
        ]);
        $verifications->save($entity);

        $this->client->sendText(
            $config->phoneNumberId,
            $config->accessToken,
            $envelope->externalIdentifier,
            "Welcome! Reply with this code to verify your number: {$code}",
        );
    }

    private function generateCode(): string
    {
        $length = (int)Configure::read('Channels.whatsapp.otpLength', 6);
        $max = (int)str_pad('1', $length + 1, '0') - 1;
        return str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function resolveOrCreateUser(string $phone): ?User
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $existing = $users->find()->where(['Users.phone_number' => $phone])->first();
        if ($existing !== null) {
            /** @var User $existing */
            return $existing;
        }

        // Auto-create a guest user so messages have something to attach to.
        // Land in approval_state='pending' on the channel-agnostic
        // 'unregistered' role until a superuser approves;
        // ProcessInboundMessageJob blocks agent dispatch on is_approved so
        // messages are buffered safely meanwhile.
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['slug' => 'unregistered'])->first();
        if ($role === null) {
            // No 'unregistered' role seeded — refuse to create rather than
            // accidentally promote a stranger to the regular user role.
            return null;
        }

        $entity = $users->newEntity([
            'email' => "wa-{$phone}@guests.local",
            'username' => "wa_{$phone}",
            'password' => bin2hex(random_bytes(16)),
            'phone_number' => $phone,
            'first_name' => 'WhatsApp',
            'last_name' => $phone,
            'role_id' => $role->id,
            'is_active' => true,
            'is_approved' => false,
            'approval_state' => User::APPROVAL_PENDING,
        ]);
        if (!$users->save($entity)) {
            return null;
        }
        /** @var User $entity */

        // Persist a user_channel_identities row so the admin UI can show
        // "WhatsApp" as the channel this user came in through, in parallel
        // with how SlackOnboardingService records its own arrivals.
        $identities = TableRegistry::getTableLocator()->get('UserChannelIdentities');
        $identity = $identities->newEntity([
            'user_id' => $entity->id,
            'channel' => 'whatsapp',
            'external_id' => $phone,
            'display_name' => trim(($entity->first_name ?? '') . ' ' . ($entity->last_name ?? '')) ?: null,
            'verified_at' => new DateTime(),
        ]);
        $identities->save($identity);

        return $entity;
    }

    private function replayBuffered(User $user, Agent $agent, InboundEnvelope $current, string $bufferedJson): void
    {
        $buffered = json_decode($bufferedJson, true);
        if (!is_array($buffered)) {
            return;
        }

        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session = $sessions->findOrCreateForChannel(
            $user->id,
            $agent->id,
            'whatsapp',
            $current->externalIdentifier,
        );
        $session->last_inbound_at = new DateTime();
        $sessions->save($session);

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $existing = $messages->find()->where([
            'channel' => 'whatsapp',
            'external_message_id' => $buffered['external_message_id'] ?? '',
            'direction' => ChatMessage::DIRECTION_INBOUND,
        ])->first();
        if ($existing !== null) {
            return;
        }

        $entity = $messages->newEntity([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'channel' => 'whatsapp',
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => (string)($buffered['body'] ?? ''),
            'content_type' => (string)($buffered['content_type'] ?? ChatMessage::CONTENT_TEXT),
            'media_url' => $buffered['media_url'] ?? null,
            'media_mime_type' => $buffered['media_mime_type'] ?? null,
            'external_message_id' => $buffered['external_message_id'] ?? null,
            'external_thread_id' => $buffered['external_thread_id'] ?? null,
            'status' => ChatMessage::STATUS_RECEIVED,
            'metadata' => isset($buffered['raw']) ? json_encode($buffered['raw']) : null,
        ]);
        $messages->save($entity);
    }
}
