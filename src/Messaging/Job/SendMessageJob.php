<?php
declare(strict_types=1);

namespace App\Messaging\Job;

use App\Messaging\Dto\OutboundMessage;
use App\Messaging\Exception\OutsideMessagingWindowException;
use App\Messaging\Service\ChannelRegistry;
use App\Model\Entity\ChatMessage;
use App\Service\AgentLogService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

/**
 * Channel-agnostic outbound delivery job.
 *
 * Loads the queued ChatMessage row, looks up its session's channel, asks
 * the matching transport to deliver it, and updates the row with the
 * provider's external_message_id + status.
 *
 * Reactive vs proactive is chosen by the dispatcher; we just call the
 * matching method on the transport. Failures are REQUEUEd up to
 * $maxAttempts so the queue worker can retry transient provider errors.
 */
class SendMessageJob implements JobInterface
{
    public static int $maxAttempts = 5;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly AgentLogService $logService,
    ) {
    }

    public function execute(Message $message): ?string
    {
        $messageId = (int)$message->getArgument('message_id', 0);
        $proactive = (bool)$message->getArgument('proactive', false);
        if ($messageId === 0) {
            return Processor::REJECT;
        }

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        /** @var ChatMessage|null $row */
        $row = $messages->find()
            ->contain(['ChatSessions'])
            ->where(['ChatMessages.id' => $messageId])
            ->first();
        if ($row === null) {
            return Processor::REJECT;
        }
        if ($row->status !== ChatMessage::STATUS_QUEUED) {
            // Already delivered or already failed; idempotent ACK.
            return Processor::ACK;
        }

        $session = $row->chat_session;
        if ($session === null) {
            $row->status = ChatMessage::STATUS_FAILED;
            $row->error_message = 'Session missing on dispatch';
            $messages->save($row);
            return Processor::REJECT;
        }
        if (!$this->channels->has($session->channel)) {
            $row->status = ChatMessage::STATUS_FAILED;
            $row->error_message = "No transport for channel '{$session->channel}'";
            $messages->save($row);
            return Processor::REJECT;
        }

        $transport = $this->channels->get($session->channel);
        $metadata = $row->metadata ? (array)json_decode((string)$row->metadata, true) : [];
        $payload = new OutboundMessage($row->content, $row->content_type ?? OutboundMessage::CONTENT_TEXT, $metadata);

        try {
            $result = $proactive
                ? $transport->sendProactive($session, $payload)
                : $transport->send($session, $payload);
        } catch (OutsideMessagingWindowException $e) {
            $row->status = ChatMessage::STATUS_FAILED;
            $row->error_code = 'window_closed';
            $row->error_message = $e->getMessage();
            $messages->save($row);
            $this->logService->error(
                $session->agent_id,
                'send-' . $row->id,
                'Outbound send rejected: messaging window closed',
                $e->getMessage(),
                ['session_id' => $session->id, 'channel' => $session->channel],
            );
            return Processor::REJECT;
        } catch (\Throwable $e) {
            $row->error_message = $e->getMessage();
            $messages->save($row);
            $this->logService->error(
                $session->agent_id,
                'send-' . $row->id,
                'Outbound send threw — requeueing',
                $e->getMessage(),
                ['session_id' => $session->id, 'channel' => $session->channel],
            );
            return Processor::REQUEUE;
        }

        $row->external_message_id = $result->externalMessageId;
        $row->external_thread_id = $result->externalThreadId;
        $row->status = $result->status;
        $row->sent_at = new DateTime();
        $row->error_code = null;
        $row->error_message = null;
        $messages->save($row);

        $this->logService->success(
            $session->agent_id,
            'send-' . $row->id,
            'Outbound message delivered to provider',
            0,
            [
                'session_id' => $session->id,
                'channel' => $session->channel,
                'external_message_id' => $result->externalMessageId,
            ],
        );

        return Processor::ACK;
    }
}
