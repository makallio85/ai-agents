<?php
declare(strict_types=1);

namespace App\Channels\Slack;

use App\Channels\MessageChannelInterface;
use App\Channels\Slack\Service\SlackConfigService;
use InvalidArgumentException;

/**
 * Slack channel definition for the MessageChannelRegistry.
 *
 * Wraps SlackConfigService and exposes it through the uniform channel
 * contract. The validation rules here mirror the previous Slack-specific
 * controller actions (app_id + bot_user_id required, bot_token + signing
 * secret required on first save), which means the admin UI gets the same
 * 422 responses regardless of which channel type it is editing.
 */
class SlackMessageChannel implements MessageChannelInterface
{
    public function __construct(private SlackConfigService $service)
    {
    }

    public function key(): string
    {
        return 'slack';
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function description(): string
    {
        return 'One Slack App per agent. Bot token and signing secret are encrypted at rest.';
    }

    public function readForUi(int $agentId): array
    {
        return $this->service->readForUi($agentId);
    }

    public function setForAgent(int $agentId, array $data): array
    {
        $appId = trim((string)($data['app_id'] ?? ''));
        $botUserId = trim((string)($data['bot_user_id'] ?? ''));
        if ($appId === '' || $botUserId === '') {
            throw new InvalidArgumentException('app_id and bot_user_id are required');
        }

        $current = $this->service->readForUi($agentId);
        $botToken = isset($data['bot_token']) ? trim((string)$data['bot_token']) : null;
        $signingSecret = isset($data['signing_secret']) ? trim((string)$data['signing_secret']) : null;
        if (!$current['bot_token_set'] && ($botToken === null || $botToken === '')) {
            throw new InvalidArgumentException('bot_token is required on first save');
        }
        if (!$current['signing_secret_set'] && ($signingSecret === null || $signingSecret === '')) {
            throw new InvalidArgumentException('signing_secret is required on first save');
        }

        $teamId = isset($data['team_id']) ? trim((string)$data['team_id']) : null;
        $enabled = (bool)($data['enabled'] ?? false);

        $this->service->setForAgent(
            agentId: $agentId,
            appId: $appId,
            botUserId: $botUserId,
            botToken: ($botToken === null || $botToken === '') ? null : $botToken,
            signingSecret: ($signingSecret === null || $signingSecret === '') ? null : $signingSecret,
            teamId: ($teamId === null || $teamId === '') ? null : $teamId,
            enabled: $enabled,
        );

        return $this->service->readForUi($agentId);
    }
}
