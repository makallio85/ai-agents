<?php
declare(strict_types=1);

namespace App\Messaging\Job;

use App\Integration\Speech\SpeechException;
use App\Integration\Speech\SpeechToTextInterface;
use App\Messaging\Service\ChannelRegistry;
use App\Messaging\Service\InboundDispatchService;
use App\Messaging\Service\MessageDispatcher;
use App\Model\Entity\ChatMessage;
use App\Service\AgentLogService;
use Cake\ORM\TableRegistry;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

/**
 * Async audio-to-text pipeline for inbound voice messages.
 *
 * Runs after ProcessInboundMessageJob has persisted an audio chat_messages
 * row with a placeholder body. Workflow:
 *   1. Load the message with its session, agent and user
 *   2. Ask the channel transport to fetch the binary audio (uses each
 *      provider's auth — Meta media id, Slack url_private_download)
 *   3. Pass to the configured SpeechToText provider
 *   4. Update the message body with the transcript and mark received
 *   5. Route through the same InboundDispatchService the text path uses
 *      so the agent handler sees the message exactly as if the user had
 *      typed the transcribed text
 *
 * Failures fall back to a polite system reply rather than dropping the
 * conversation silently — the user knows their voice note didn't land
 * and can re-send as text.
 */
class TranscribeAudioJob implements JobInterface
{
    public static int $maxAttempts = 3;
    public static bool $shouldBeUnique = false;

    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly SpeechToTextInterface $speech,
        private readonly InboundDispatchService $dispatchService,
        private readonly MessageDispatcher $dispatcher,
        private readonly AgentLogService $logService,
    ) {
    }

    public function execute(Message $message): ?string
    {
        $messageId = (int)$message->getArgument('message_id', 0);
        if ($messageId === 0) {
            return Processor::REJECT;
        }

        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        /** @var ChatMessage|null $row */
        $row = $messages->find()
            ->contain(['ChatSessions' => ['Agents' => ['AgentContexts'], 'Users']])
            ->where(['ChatMessages.id' => $messageId])
            ->first();
        if ($row === null) {
            return Processor::REJECT;
        }
        $session = $row->chat_session;
        if ($session === null || $session->agent === null || $session->user === null) {
            return Processor::REJECT;
        }
        if (!$this->channels->has($session->channel)) {
            $row->status = ChatMessage::STATUS_FAILED;
            $row->error_code = 'no_transport';
            $messages->save($row);
            return Processor::REJECT;
        }

        $transport = $this->channels->get($session->channel);

        // 1. Download the audio bytes.
        try {
            $media = $transport->fetchMedia($row);
        } catch (\Throwable $e) {
            return $this->failGracefully(
                $row,
                'media_download_failed',
                $e->getMessage(),
                "Sorry — I couldn't download your voice note. Please send it as text and I'll help.",
                $session->agent->id,
            );
        }

        // 1b. Log download diagnostics so we can verify the audio bytes are
        //     correct before sending to Whisper.
        $downloadedBytes = strlen((string)($media['content'] ?? ''));
        $downloadedMime  = (string)($media['mime'] ?? '');
        $storedMime      = (string)($row->media_mime_type ?? '');
        $effectiveMime   = $storedMime ?: $downloadedMime ?: 'application/octet-stream';
        $this->logService->success(
            $session->agent->id,
            'transcribe-download-' . $row->id,
            'Audio downloaded',
            0,
            [
                'session_id'       => $session->id,
                'message_id'       => $row->id,
                'bytes'            => $downloadedBytes,
                'cdn_mime'         => $downloadedMime,
                'stored_mime'      => $storedMime,
                'effective_mime'   => $effectiveMime,
                'media_url'        => (string)($row->media_url ?? ''),
                'first_16_bytes_hex' => bin2hex(substr((string)($media['content'] ?? ''), 0, 16)),
            ],
            $session->user->id,
        );

        // Guard: if the download returned empty bytes the CDN likely redirected
        //     to an HTML error page (observed when file_get_contents follow_location
        //     fires in CLI / queue worker context). Fail gracefully so the user gets
        //     an apology instead of a silent Whisper 400.
        if ($downloadedBytes === 0) {
            return $this->failGracefully(
                $row,
                'media_download_failed',
                'Downloaded audio file is empty — possible CDN redirect issue in queue worker',
                "Sorry — I couldn't download your voice note. Please send it as text and I'll help.",
                $session->agent->id,
            );
        }

        // 2. Transcribe.
        try {
            // Prefer the MIME type stored on the row (from the original Slack
            // webhook event's `mimetype` field) over the HTTP Content-Type
            // returned by Slack's CDN download. The CDN often returns a generic
            // 'application/octet-stream', which maps to extension '.bin' and
            // causes Whisper to reject the file as an unsupported format.
            $result = $this->speech->transcribe(
                audio: (string)($media['content'] ?? ''),
                mimeType: (string)($row->media_mime_type ?: ($media['mime'] ?? 'application/octet-stream')),
            );
        } catch (SpeechException $e) {
            return $this->failGracefully(
                $row,
                'transcription_failed',
                $e->getMessage(),
                "Sorry — I couldn't understand the audio. Please send it as text and I'll help.",
                $session->agent->id,
            );
        } catch (\Throwable $e) {
            // Unexpected error — requeue so transient failures get retried.
            $row->error_message = $e->getMessage();
            $messages->save($row);
            return Processor::REQUEUE;
        }

        $transcript = trim($result->transcript);
        if ($transcript === '') {
            return $this->failGracefully(
                $row,
                'empty_transcript',
                'Speech-to-Text returned no transcript',
                "I couldn't hear anything in that voice note. Could you try again or send it as text?",
                $session->agent->id,
            );
        }

        // 3. Update the row with the transcript. Keep media metadata so the
        //    audio stays linkable from the conversation history.
        $row->content = $transcript;
        $row->status = ChatMessage::STATUS_RECEIVED;
        $row->error_code = null;
        $row->error_message = null;
        $existingMeta = $row->metadata ? (array)json_decode((string)$row->metadata, true) : [];
        $existingMeta['transcription'] = [
            'provider' => 'google',
            'confidence' => $result->confidence,
            'detected_language' => $result->detectedLanguage,
        ];
        $row->metadata = json_encode($existingMeta);
        $messages->save($row);

        $this->logService->success(
            $session->agent->id,
            'transcribe-' . $row->id,
            'Audio transcribed',
            0,
            [
                'session_id' => $session->id,
                'channel' => $session->channel,
                'confidence' => $result->confidence,
                'detected_language' => $result->detectedLanguage,
                'length' => mb_strlen($transcript),
            ],
            $session->user->id,
        );

        // 4. Route to the agent handler exactly like a text inbound.
        $this->dispatchService->route($session->agent, $session, $row, $session->user);

        return Processor::ACK;
    }

    private function failGracefully(
        ChatMessage $row,
        string $errorCode,
        string $errorMessage,
        string $userFacing,
        int $agentId,
    ): ?string {
        $messages = TableRegistry::getTableLocator()->get('ChatMessages');
        $row->status = ChatMessage::STATUS_FAILED;
        $row->error_code = $errorCode;
        $row->error_message = $errorMessage;
        $row->content = '[Audio message — transcription failed]';
        $messages->save($row);

        $this->logService->error(
            $agentId,
            'transcribe-' . $row->id,
            'Audio transcription failed',
            $errorMessage,
            ['session_id' => $row->chat_session_id, 'error_code' => $errorCode],
        );

        // Surface a plain-text apology to the user so they know to retry.
        // sendSystem queues through the same outbound pipeline as agent
        // replies — the user gets the message even on WhatsApp where the
        // 24h window is open thanks to their just-arrived inbound.
        $session = TableRegistry::getTableLocator()->get('ChatSessions')
            ->find()->where(['id' => $row->chat_session_id])->first();
        if ($session !== null) {
            $this->dispatcher->sendSystem($session, $userFacing);
        }

        return Processor::ACK;
    }
}
