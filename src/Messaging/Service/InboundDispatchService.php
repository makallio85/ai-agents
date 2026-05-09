<?php
declare(strict_types=1);

namespace App\Messaging\Service;

use App\Model\Entity\Agent;
use App\Model\Entity\ChatMessage;
use App\Model\Entity\ChatSession;
use App\Model\Entity\User;
use App\Service\AgentLogService;
use Cake\ORM\TableRegistry;

/**
 * Shared inbound-routing logic used by both ProcessInboundMessageJob (for
 * text messages) and TranscribeAudioJob (after audio has been turned into
 * text). Centralises the approval gate, the agent-vs-human assignment
 * branch, and the handler invocation so the two jobs cannot drift.
 */
class InboundDispatchService
{
    public function __construct(
        private readonly MessageHandlerRegistry $handlers,
        private readonly MessageDispatcher $dispatcher,
        private readonly AgentLogService $logService,
    ) {
    }

    public function route(Agent $agent, ChatSession $session, ChatMessage $inbound, User $user): void
    {
        if (!$this->isApproved($user)) {
            $this->notifyPendingApproval($agent, $session, $user, $inbound);
            return;
        }

        if ($session->isHumanHandled() || $session->isPendingHuman()) {
            $this->logService->info(
                $agent->id,
                'inbound-' . $inbound->id,
                'Inbound on human-handled session; awaiting human reply',
                [
                    'session_id' => $session->id,
                    'assignment_state' => $session->assignment_state,
                    'assigned_user_id' => $session->assigned_user_id,
                ],
                $user->id,
            );
            return;
        }

        $handler = $this->handlers->resolve($agent->plugin ?? null);
        try {
            $handler->handleMessage($agent, $session, $inbound);
        } catch (\Throwable $e) {
            $this->logService->error(
                $agent->id,
                'inbound-' . $inbound->id,
                'MessageHandler threw',
                $e->getMessage(),
                ['session_id' => $session->id, 'plugin' => $agent->plugin],
                $user->id,
            );
            $this->dispatcher->sendSystem(
                $session,
                "Sorry — something went wrong handling your message. We'll look into it.",
            );
        }
    }

    private function isApproved(User $user): bool
    {
        if (isset($user->is_approved)) {
            return (bool)$user->is_approved;
        }
        return true;
    }

    private function notifyPendingApproval(Agent $agent, ChatSession $session, User $user, ChatMessage $inbound): void
    {
        $this->logService->info(
            $agent->id,
            'inbound-' . $inbound->id,
            'Inbound from unapproved user; awaiting superuser approval',
            ['session_id' => $session->id, 'user_id' => $user->id, 'phone' => $user->phone_number ?? null],
            $user->id,
        );

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $alreadyNotified = $messages->find()
            ->where([
                'chat_session_id' => $session->id,
                'role' => ChatMessage::ROLE_SYSTEM,
                'metadata LIKE' => '%pending_approval_notice%',
            ])->count() > 0;
        if ($alreadyNotified) {
            return;
        }
        $notice = $this->dispatcher->sendSystem(
            $session,
            "Thanks for messaging — your access is pending approval. We'll let you know once it's been reviewed."
        );
        $notice->metadata = json_encode(['pending_approval_notice' => true]);
        $messages->save($notice);
    }
}
