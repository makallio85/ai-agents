<?php
declare(strict_types=1);

namespace App\Channels;

/**
 * Contract for an agent-facing message channel (Slack, WhatsApp, ...).
 *
 * The "MessageChannels" concept (issue #15) unifies how the admin UI and API
 * expose channel configuration: every channel exposes a stable key, a label,
 * a help description, and a uniform read/save lifecycle keyed by agent id.
 *
 * Storage stays channel-specific (each channel has its own dedicated table).
 * Validation also stays channel-specific — each implementation knows which
 * fields are required and throws \InvalidArgumentException with a human
 * message when the input is incomplete. The controller layer maps that
 * exception to a 422 response, so the wire format stays consistent across
 * channel types.
 *
 * To add a new channel type, implement this interface and register the
 * instance with MessageChannelRegistry::register(). The agent view UI
 * picks it up automatically via the channels API endpoint.
 */
interface MessageChannelInterface
{
    /**
     * Stable URL-safe identifier used in API routes and the UI key map.
     * Must be lowercase alphanumeric (e.g. "slack", "whatsapp").
     */
    public function key(): string;

    /**
     * Human-readable label shown in the admin UI (e.g. "Slack").
     */
    public function label(): string;

    /**
     * Short help description shown under the channel header in the UI.
     * Plain text — the UI may render `&rarr;` style entities as-is.
     */
    public function description(): string;

    /**
     * Returns the channel's admin-UI payload for the given agent.
     *
     * Sensitive fields (tokens, secrets) MUST be masked — return a boolean
     * `*_set` flag instead of the raw value so the UI can show "already set"
     * without exposing the value back to the browser.
     *
     * @return array<string, mixed>
     */
    public function readForUi(int $agentId): array;

    /**
     * Persists the channel configuration for the given agent.
     *
     * Throws \InvalidArgumentException with a descriptive message if the
     * input is missing required fields. Returns the post-save readForUi()
     * payload so callers don't need a second round-trip.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function setForAgent(int $agentId, array $data): array;
}
