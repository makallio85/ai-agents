<?php
declare(strict_types=1);

namespace App\Channels\Slack;

/**
 * HTTP wrapper for the Slack Web API.
 *
 * Multi-tenant by design: per-agent bot_token is passed in to every call so
 * the same client instance can serve every Slack-enabled agent. Tests inject
 * a fake implementation; per CLAUDE.md no test ever hits the network.
 */
interface SlackClientInterface
{
    /**
     * Posts a message to a Slack channel (which for DMs is the user's IM
     * channel id, e.g. "D02..."). thread_ts is optional — when provided the
     * reply lands in the thread.
     *
     * @return array<string, mixed> Decoded provider response.
     */
    public function postMessage(string $botToken, string $channelId, string $text, ?string $threadTs = null): array;

    /**
     * Opens (or returns existing) DM channel id for a Slack user. Required
     * for first-contact proactive messages where we only know the user_id.
     *
     * @return string IM channel id (e.g. "D02ABC...").
     */
    public function openConversation(string $botToken, string $slackUserId): string;

    /**
     * Looks up Slack user metadata (display name, email if scope granted).
     *
     * @return array{id: string, name: string, real_name: ?string, email: ?string, team_id: ?string}
     */
    public function getUserInfo(string $botToken, string $slackUserId): array;

    /**
     * Downloads a Slack file using the bot token. The URL must come from
     * the Events API payload (url_private_download or url_private) — Slack
     * requires Bearer auth on these private URLs.
     *
     * @return array{content: string, mime: string}
     */
    public function downloadFile(string $botToken, string $url): array;
}
