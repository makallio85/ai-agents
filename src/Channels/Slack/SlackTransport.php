<?php
declare(strict_types=1);

namespace App\Channels\Slack;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\Service\SlackOnboardingService;
use App\Messaging\Contract\ChannelTransportInterface;
use App\Messaging\Dto\InboundEnvelope;
use App\Messaging\Dto\OutboundMessage;
use App\Messaging\Dto\SendResult;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\InboundEvent;
use App\Model\Entity\User;
use App\Model\Entity\UserChannelIdentity;
use Cake\ORM\TableRegistry;

/**
 * Slack channel transport.
 *
 * Differences from WhatsApp:
 *   - No 24h Service window; send() and sendProactive() both post free-form
 *     messages via chat.postMessage. There are no templates.
 *   - Identity is single-step: Slack already authenticated the sender, so
 *     handleUnverifiedSender() returns a User immediately rather than
 *     issuing an OTP.
 *   - Threads: the outbound message reuses the inbound's thread_ts when
 *     present so replies stay grouped in the original thread.
 *   - Rate limiting is enforced by Slack via 429 + Retry-After; retries
 *     happen at the queue layer (SendMessageJob REQUEUEs on transient
 *     errors).
 */
class SlackTransport implements ChannelTransportInterface
{
    public const CHANNEL = 'slack';

    public function __construct(
        private readonly SlackClientInterface $client,
        private readonly SlackConfigService $configService,
        private readonly SlackOnboardingService $onboardingService,
    ) {
    }

    public function name(): string
    {
        return self::CHANNEL;
    }

    public function send(ChatSession $session, OutboundMessage $message): SendResult
    {
        $config = $this->configService->findConfigByAgentId($session->agent_id);
        if ($config === null || !$config->enabled) {
            throw new SlackException("Slack is not configured / enabled for agent {$session->agent_id}");
        }
        $channelId = $this->resolveChannelId($session, $config->botToken);
        $threadTs = $this->resolveThreadTs($session, $message);
        $response = $this->client->postMessage($config->botToken, $channelId, $message->body, $threadTs);
        return $this->buildResult($response);
    }

    public function sendProactive(ChatSession $session, OutboundMessage $message): SendResult
    {
        // Slack has no template equivalent; proactive sends are just regular
        // messages with no window restriction (Slack's rate limits and quality
        // signals govern abuse). Reuse send().
        return $this->send($session, $message);
    }

    public function supportsProactive(): bool
    {
        return true;
    }

    public function requiresVerification(): bool
    {
        // True in the contract sense: unknown senders need handleUnverifiedSender()
        // even though Slack does NOT use an OTP step. The transport returns the
        // resolved User directly so the inbound job continues normal processing.
        return true;
    }

    public function resolveAgentByExternalAccount(string $accountId): ?Agent
    {
        $config = $this->configService->findConfigByAppId($accountId);
        return $config?->agent;
    }

    public function resolveUserByExternalIdentifier(string $identifier): ?User
    {
        $identities = TableRegistry::getTableLocator()->get('UserChannelIdentities');
        /** @var UserChannelIdentity|null $row */
        $row = $identities->find('byExternal', channel: self::CHANNEL, externalId: $identifier)->first();
        return $row?->user ?? null;
    }

    public function handleUnverifiedSender(InboundEnvelope $envelope, ?Agent $agent): ?User
    {
        return $this->onboardingService->handle($envelope, $agent);
    }

    public function supportsOutboundAudio(): bool
    {
        // files.uploadV2 is a multi-step flow (request upload URL, PUT bytes,
        // complete upload, share). Out of scope for v1; the dispatcher will
        // automatically fall back to a text reply when this returns false.
        return false;
    }

    public function sendAudio(ChatSession $session, string $audioBytes, string $mime): SendResult
    {
        throw new SlackException('Outbound audio is not yet supported on Slack — set users.preferred_reply_mode to text or auto');
    }

    public function fetchMedia(ChatMessage $message): array
    {
        $sessions = TableRegistry::getTableLocator()->get('ChatSessions');
        $session = $sessions->find()->select(['agent_id'])->where(['id' => $message->chat_session_id])->firstOrFail();
        $config = $this->configService->findConfigByAgentId((int)$session->agent_id);
        if ($config === null) {
            throw new SlackException("Slack config missing for session {$message->chat_session_id}");
        }
        $url = (string)($message->media_url ?? '');
        if ($url === '') {
            throw new SlackException("Inbound message {$message->id} has no media_url");
        }
        return $this->client->downloadFile($config->botToken, $url);
    }

    public function parseInbound(InboundEvent $event): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode((string)$event->payload, true);
        if (!is_array($payload)) {
            return [];
        }

        $type = (string)($payload['type'] ?? '');
        if ($type === 'url_verification') {
            // Handled inline by the controller — should not reach the job.
            return [];
        }
        if ($type !== 'event_callback') {
            return [];
        }

        $appId = (string)($payload['api_app_id'] ?? '');
        if ($appId === '') {
            return [];
        }
        $teamId = (string)($payload['team_id'] ?? '');
        $event = (array)($payload['event'] ?? []);
        $eventType = (string)($event['type'] ?? '');

        // Ignore the bot's own messages and bot_message subtypes to prevent loops.
        if (!empty($event['bot_id']) || ($event['subtype'] ?? null) === 'bot_message') {
            return [];
        }

        if (!in_array($eventType, ['message', 'app_mention'], true)) {
            return [];
        }

        $slackUserId = (string)($event['user'] ?? '');
        $text = (string)($event['text'] ?? '');
        $ts = (string)($event['ts'] ?? '');
        $threadTs = $event['thread_ts'] ?? null;
        $channelId = (string)($event['channel'] ?? '');
        if ($slackUserId === '' || $ts === '' || $channelId === '') {
            return [];
        }

        // Detect attached audio file. Slack delivers files in `event.files`
        // (the deprecated 'file' singular is also handled). When the first
        // file is audio we surface the message as content_type='audio' so
        // ProcessInboundMessageJob defers it to TranscribeAudioJob.
        $contentType = ChatMessage::CONTENT_TEXT;
        $mediaUrl = null;
        $mediaMime = null;
        $audioFile = $this->firstAudioFile($event);
        if ($audioFile !== null) {
            $contentType = ChatMessage::CONTENT_AUDIO;
            // url_private_download is what Slack recommends for programmatic
            // downloads (sets Content-Disposition: attachment).
            $mediaUrl = (string)($audioFile['url_private_download'] ?? $audioFile['url_private'] ?? '');
            $mediaMime = (string)($audioFile['mimetype'] ?? '');
        }

        // Compose external_message_id with workspace + channel + ts so two
        // workspaces using identical ts values cannot collide.
        $externalId = "{$teamId}:{$channelId}:{$ts}";

        return [new InboundEnvelope(
            channel: self::CHANNEL,
            kind: InboundEnvelope::KIND_MESSAGE,
            externalAccountId: $appId,
            externalIdentifier: $slackUserId,
            externalMessageId: $externalId,
            contentType: $contentType,
            body: $text,
            externalThreadId: is_string($threadTs) ? $threadTs : $ts,
            mediaUrl: $mediaUrl,
            mediaMimeType: $mediaMime,
            statusUpdate: null,
            rawPayload: array_merge($event, [
                'team' => $teamId,
                'slack_channel_id' => $channelId,
                'event_type' => $eventType,
            ]),
        )];
    }

    /**
     * Returns the first audio file in the event's files[] (or singular file),
     * or null when the event has none.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null
     */
    private function firstAudioFile(array $event): ?array
    {
        $files = $event['files'] ?? null;
        if (!is_array($files) && isset($event['file']) && is_array($event['file'])) {
            $files = [$event['file']];
        }
        if (!is_array($files)) {
            return null;
        }
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $mime = strtolower((string)($file['mimetype'] ?? ''));
            $type = strtolower((string)($file['filetype'] ?? ''));
            if (str_starts_with($mime, 'audio/') || in_array($type, ['m4a', 'mp3', 'wav', 'ogg', 'webm', 'flac', 'aac', 'amr'], true)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Slack's "channel" for outbound is either a public/private channel id
     * or a DM channel id. We persist the inbound channel id in metadata; on
     * outbound we reuse it. For sessions that originated outside an inbound
     * (proactive notifications) we open a DM and cache the channel id.
     */
    private function resolveChannelId(ChatSession $session, string $botToken): string
    {
        // Pull the latest inbound message's metadata to find the slack channel id.
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $latestInbound = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'channel' => self::CHANNEL,
                'direction' => ChatMessage::DIRECTION_INBOUND,
            ])
            ->orderByDesc('created')
            ->first();
        if ($latestInbound !== null && !empty($latestInbound->metadata)) {
            $meta = json_decode((string)$latestInbound->metadata, true);
            if (is_array($meta) && !empty($meta['slack_channel_id'])) {
                return (string)$meta['slack_channel_id'];
            }
        }

        // Fallback for proactive: open a DM with the user via Slack API.
        $slackUserId = $this->slackUserIdForSession($session);
        if ($slackUserId === null) {
            throw new SlackException("Cannot resolve Slack channel for session {$session->id}");
        }
        return $this->client->openConversation($botToken, $slackUserId);
    }

    /**
     * Resolves the Slack thread_ts to use for an outbound reply.
     *
     * Preferred path: the outbound message's metadata carries 'inbound_thread_id',
     * written by MessageDispatcher::reply() at reply-creation time from the
     * triggering inbound ChatMessage. Using this stored value avoids the race
     * condition where a second user message arrives after the reply is queued
     * but before SendMessageJob runs — in that case, re-querying for the latest
     * inbound would return the second message's thread_ts and the reply would
     * appear in the wrong Slack thread.
     *
     * Fallback: if no 'inbound_thread_id' is in metadata (legacy rows or proactive
     * sends), the previous behaviour of querying for the latest inbound is used.
     */
    private function resolveThreadTs(ChatSession $session, OutboundMessage $message): ?string
    {
        $storedThreadId = $message->metadata['inbound_thread_id'] ?? null;
        if (is_string($storedThreadId) && $storedThreadId !== '') {
            return $storedThreadId;
        }

        // Legacy fallback: query for the latest inbound's thread_ts.
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $latestInbound = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'channel' => self::CHANNEL,
                'direction' => ChatMessage::DIRECTION_INBOUND,
            ])
            ->orderByDesc('created')
            ->first();
        return $latestInbound?->external_thread_id ?: null;
    }

    private function slackUserIdForSession(ChatSession $session): ?string
    {
        if (!empty($session->channel_external_id)) {
            return (string)$session->channel_external_id;
        }
        $identities = TableRegistry::getTableLocator()->get('UserChannelIdentities');
        $row = $identities->find()
            ->where(['channel' => self::CHANNEL, 'user_id' => $session->user_id])
            ->orderByDesc('created')
            ->first();
        return $row?->external_id;
    }

    /** @param array<string, mixed> $response */
    private function buildResult(array $response): SendResult
    {
        $ts = (string)($response['ts'] ?? '');
        $channelId = (string)($response['channel'] ?? '');
        return new SendResult(
            externalMessageId: ($channelId !== '' && $ts !== '') ? "{$channelId}:{$ts}" : $ts,
            externalThreadId: $response['message']['thread_ts'] ?? null,
            status: ChatMessage::STATUS_SENT,
            providerPayload: $response,
        );
    }
}
