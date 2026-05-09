<?php
declare(strict_types=1);

namespace App\Messaging\Contract;

use App\Messaging\Dto\InboundEnvelope;
use App\Messaging\Dto\OutboundMessage;
use App\Messaging\Dto\SendResult;
use App\Model\Entity\Agent;
use App\Model\Entity\ChatSession;
use App\Model\Entity\InboundEvent;
use App\Model\Entity\User;

/**
 * Contract every channel (WhatsApp, email, SMS, ...) implements.
 *
 * The transport encapsulates everything provider-specific: HTTP calls,
 * signature verification, identity normalisation, parsing webhook payloads,
 * and the channel's quirks (24h windows, templates, threading). The
 * MessageDispatcher and ProcessInboundMessageJob remain channel-agnostic.
 */
interface ChannelTransportInterface
{
    /** Channel name as stored on chat_sessions.channel and chat_messages.channel. */
    public function name(): string;

    /**
     * Reactive send (default). Transports that have a messaging window
     * (e.g. WhatsApp's 24h Service window) enforce it here and throw
     * App\Messaging\Exception\OutsideMessagingWindowException when violated.
     */
    public function send(ChatSession $session, OutboundMessage $message): SendResult;

    /**
     * Proactive send — for channels with templates (WhatsApp), this uses the
     * approved template path. Channels without templates (email) treat this
     * the same as send().
     */
    public function sendProactive(ChatSession $session, OutboundMessage $message): SendResult;

    public function supportsProactive(): bool;

    /** True if unknown senders must verify (OTP) before reaching an agent. */
    public function requiresVerification(): bool;

    /**
     * Resolve which Agent should receive an inbound directed at $accountId.
     * For WhatsApp, $accountId is the phone_number_id.
     */
    public function resolveAgentByExternalAccount(string $accountId): ?Agent;

    /**
     * Resolve which User a given external identifier belongs to.
     * For WhatsApp, $identifier is the wa_id (digits, normalised to +E.164).
     * Returns null for unknown senders; caller decides whether to start
     * verification or refuse.
     */
    public function resolveUserByExternalIdentifier(string $identifier): ?User;

    /**
     * Translate a raw inbound event payload into zero or more InboundEnvelopes
     * (messages and/or status updates).
     *
     * @return InboundEnvelope[]
     */
    public function parseInbound(InboundEvent $event): array;

    /**
     * Drive identity resolution for an unknown sender.
     *
     * Two valid flows the inbound job supports:
     *
     * 1. Async / two-phase verification (e.g. WhatsApp OTP). First inbound
     *    issues a code and buffers the original body. Subsequent inbound
     *    interprets body as the submitted code. The transport replays the
     *    buffered original itself by writing a ChatMessage row directly.
     *    Return null in both phases — the inbound job does not continue
     *    processing the current envelope as a real chat message.
     *
     * 2. Single-step resolution (e.g. Slack, where the provider already
     *    authenticated the user). The transport looks up or creates a User
     *    on the spot and returns it. The inbound job then continues with
     *    that User and persists the current envelope as a normal inbound
     *    chat_messages row.
     *
     * Only called by ProcessInboundMessageJob when both
     * resolveUserByExternalIdentifier() returned null AND requiresVerification()
     * is true.
     */
    public function handleUnverifiedSender(InboundEnvelope $envelope, ?Agent $agent): ?User;
}
