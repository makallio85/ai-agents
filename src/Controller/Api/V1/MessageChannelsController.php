<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Channels\MessageChannelRegistry;
use App\Service\AgentLogService;
use App\Service\AgentService;
use Cake\Log\Log;
use InvalidArgumentException;

/**
 * Per-agent message channel configuration API.
 *
 * Exposes the MessageChannels concept (issue #15) as a uniform, channel-agnostic
 * REST surface: the admin UI hits one endpoint to list every configured
 * channel for an agent, and a single update endpoint keyed by channel type
 * to persist edits. The controller itself contains no channel-specific
 * knowledge — it just delegates to MessageChannelRegistry::default(), so a
 * new channel type only needs an interface implementation + registration.
 *
 * All actions are gated by the chat:configure permission, matching the old
 * per-channel endpoints they replace.
 */
class MessageChannelsController extends AppController
{
    private MessageChannelRegistry $registry;
    private AgentService $agentService;

    public function initialize(): void
    {
        parent::initialize();
        $this->registry = MessageChannelRegistry::default();
        $this->agentService = new AgentService(new AgentLogService());
    }

    /**
     * GET /api/v1/message-channels/{id}
     *
     * Returns every registered channel's metadata + admin-UI payload for the
     * given agent. The UI iterates over the result and renders a card per
     * channel, so adding a new channel type appears automatically.
     */
    public function index(int $id): void
    {
        $this->requirePermission('chat', 'configure');

        if ($this->agentService->findById($id) === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        $channels = [];
        foreach ($this->registry->all() as $channel) {
            $channels[] = [
                'key'         => $channel->key(),
                'label'       => $channel->label(),
                'description' => $channel->description(),
                'config'      => $channel->readForUi($id),
            ];
        }

        $this->success($channels, ['count' => count($channels)]);
    }

    /**
     * POST /api/v1/message-channels/update/{id}/{type}
     *
     * Persists the per-agent config for a single channel type. Channel-
     * specific validation lives in the MessageChannelInterface implementation
     * and is surfaced as a 422 when InvalidArgumentException is thrown.
     */
    public function update(int $id, string $type): void
    {
        $this->requirePermission('chat', 'configure');

        $channel = $this->registry->get($type);
        if ($channel === null) {
            $this->error("Unknown channel type: {$type}", [], 404);
            return;
        }
        if ($this->agentService->findById($id) === null) {
            $this->error('Agent not found', [], 404);
            return;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $this->request->getData();
            $config = $channel->setForAgent($id, $data);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage(), [], 422);
            return;
        }

        // Audit trail (issue #15 §Logging). The payload is intentionally
        // omitted — channel configs contain secrets and the masked readForUi
        // payload is enough to confirm what changed.
        Log::info(sprintf(
            'message-channel updated: agent_id=%d type=%s user_id=%s',
            $id,
            $channel->key(),
            (string)($this->getCurrentUser()?->id ?? 'unknown'),
        ));

        $this->success([
            'key'         => $channel->key(),
            'label'       => $channel->label(),
            'description' => $channel->description(),
            'config'      => $config,
        ]);
    }
}
