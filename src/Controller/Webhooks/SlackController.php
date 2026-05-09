<?php
declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\Channels\Slack\Service\SlackConfigService;
use App\Channels\Slack\SlackSignatureVerifier;
use App\Controller\AppController;
use App\Messaging\Job\ProcessInboundMessageJob;
use App\Model\Entity\InboundEvent;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Queue\QueueManager;

/**
 * Receives Slack Events API webhooks.
 *
 * Single endpoint that handles three flows:
 *   - url_verification challenge (initial setup) — echoed back inline,
 *     no signature check (Slack docs explicitly allow this and the
 *     payload contains no api_app_id we could use to resolve a secret).
 *   - event_callback (normal messages, app_mentions, etc.) — signature
 *     verified using the per-agent signing_secret looked up from
 *     api_app_id, then persisted to inbound_events and queued.
 *   - everything else — acknowledged with 200 to stop retries.
 *
 * Auth + CSRF skipped: Slack is the caller, the X-Slack-Signature header
 * is the auth, and Slack doesn't speak our CSRF token.
 */
class SlackController extends AppController
{
    private SlackSignatureVerifier $verifier;
    private SlackConfigService $configService;

    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['receive']);
        $this->verifier = new SlackSignatureVerifier();
        $this->configService = new SlackConfigService();
    }

    /**
     * POST /webhooks/slack
     */
    public function receive(): Response
    {
        $rawBody = (string)$this->request->getBody();
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->response->withStatus(400)->withStringBody('bad json');
        }

        $type = (string)($payload['type'] ?? '');
        if ($type === 'url_verification') {
            $challenge = (string)($payload['challenge'] ?? '');
            return $this->response->withType('text/plain')->withStringBody($challenge);
        }

        $appId = (string)($payload['api_app_id'] ?? '');
        if ($appId === '') {
            // Slack always includes api_app_id on event_callback; missing means
            // we have nothing actionable. Audit and ack to stop retries.
            $this->persistEvent($payload, '', $rawBody, signatureValid: false, errorMessage: 'missing api_app_id');
            return $this->response->withStatus(200)->withStringBody('ok');
        }

        $config = $this->configService->findConfigByAppId($appId);
        if ($config === null) {
            $this->persistEvent($payload, $appId, $rawBody, signatureValid: false, errorMessage: 'unknown api_app_id');
            return $this->response->withStatus(200)->withStringBody('ok');
        }

        $sigHeader = $this->request->getHeaderLine('X-Slack-Signature');
        $tsHeader = $this->request->getHeaderLine('X-Slack-Request-Timestamp');
        $signatureValid = $this->verifier->verify($config->signingSecret, $rawBody, $sigHeader, $tsHeader ?: null);
        if (!$signatureValid) {
            $this->persistEvent($payload, $appId, $rawBody, signatureValid: false, errorMessage: 'invalid signature');
            return $this->response->withStatus(401)->withStringBody('invalid signature');
        }

        $event = $this->persistEvent($payload, $appId, $rawBody, signatureValid: true);
        if ($event !== null) {
            QueueManager::push(ProcessInboundMessageJob::class, [
                'inbound_event_id' => $event->id,
            ]);
        }

        // Slack expects a 2xx within 3s or it retries.
        return $this->response->withStatus(200)->withStringBody('ok');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function persistEvent(
        array $payload,
        string $appId,
        string $rawBody,
        bool $signatureValid,
        ?string $errorMessage = null,
    ): ?InboundEvent {
        $events = TableRegistry::getTableLocator()->get('InboundEvents');
        $eventId = $this->deriveEventId($payload, $rawBody);

        $existing = $events->find()->where([
            'channel' => 'slack',
            'event_id' => $eventId,
        ])->first();
        if ($existing !== null) {
            /** @var InboundEvent $existing */
            return $existing;
        }

        $entity = $events->newEntity([
            'channel' => 'slack',
            'event_id' => $eventId,
            'external_account_id' => $appId,
            'signature_valid' => $signatureValid,
            'payload' => $rawBody,
            'error_message' => $errorMessage,
        ]);
        $events->save($entity);
        /** @var InboundEvent $entity */
        return $entity;
    }

    /** @param array<string, mixed> $payload */
    private function deriveEventId(array $payload, string $rawBody): string
    {
        // Slack's Events API guarantees a top-level event_id on event_callback.
        if (!empty($payload['event_id'])) {
            return 'ev:' . substr((string)$payload['event_id'], 0, 90);
        }
        return 'h:' . substr(hash('sha256', $rawBody), 0, 90);
    }
}
