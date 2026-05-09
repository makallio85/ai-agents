<?php
declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\Channels\WhatsApp\Service\WhatsAppConfigService;
use App\Channels\WhatsApp\WhatsAppSignatureVerifier;
use App\Controller\AppController;
use App\Messaging\Job\ProcessInboundMessageJob;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Queue\QueueManager;

/**
 * Receives Meta WhatsApp Cloud API webhooks.
 *
 * Two endpoints:
 *   GET  /webhooks/whatsapp  — verify-token challenge (one-time setup)
 *   POST /webhooks/whatsapp  — inbound messages + status updates
 *
 * The POST handler validates the X-Hub-Signature-256 header against the raw
 * request body using the per-agent app_secret resolved from the payload's
 * phone_number_id, persists an InboundEvent row, enqueues a
 * ProcessInboundMessageJob, and returns 200 immediately. Meta retries
 * aggressively on non-200 responses, so we always 200 on validated
 * deliveries — even when later parsing has nothing to do.
 *
 * Auth + CSRF are skipped: Meta is the caller, the signature *is* the auth,
 * and Meta does not know our CSRF token.
 */
class WhatsAppController extends AppController
{
    private WhatsAppSignatureVerifier $verifier;
    private WhatsAppConfigService $configService;

    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['verify', 'receive']);
        $this->verifier = new WhatsAppSignatureVerifier();
        $this->configService = new WhatsAppConfigService();
    }

    /**
     * GET /webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
     */
    public function verify(): Response
    {
        $expected = (string)Configure::read('Channels.whatsapp.verifyToken', '');
        $mode = (string)$this->request->getQuery('hub_mode', $this->request->getQuery('hub.mode', ''));
        $token = (string)$this->request->getQuery('hub_verify_token', $this->request->getQuery('hub.verify_token', ''));
        $challenge = (string)$this->request->getQuery('hub_challenge', $this->request->getQuery('hub.challenge', ''));

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return $this->response
                ->withType('text/plain')
                ->withStringBody($challenge);
        }
        return $this->response->withStatus(403)->withStringBody('forbidden');
    }

    /**
     * POST /webhooks/whatsapp
     */
    public function receive(): Response
    {
        $rawBody = (string)$this->request->getBody();
        $signatureHeader = $this->request->getHeaderLine('X-Hub-Signature-256');

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->response->withStatus(400)->withStringBody('bad json');
        }

        $phoneNumberId = $this->extractPhoneNumberId($payload);
        if ($phoneNumberId === null) {
            // No phone_number_id — nothing actionable, but ack to stop retries.
            return $this->response->withStatus(200)->withStringBody('ok');
        }

        $config = $this->configService->findConfigByPhoneNumberId($phoneNumberId);
        if ($config === null) {
            // Unknown number. Persist for audit but mark signature invalid.
            $this->persistEvent($payload, $phoneNumberId, $rawBody, signatureValid: false, errorMessage: 'unknown phone_number_id');
            return $this->response->withStatus(200)->withStringBody('ok');
        }

        $signatureValid = $this->verifier->verify($config->appSecret, $rawBody, $signatureHeader);
        if (!$signatureValid) {
            $this->persistEvent($payload, $phoneNumberId, $rawBody, signatureValid: false, errorMessage: 'invalid signature');
            return $this->response->withStatus(401)->withStringBody('invalid signature');
        }

        $event = $this->persistEvent($payload, $phoneNumberId, $rawBody, signatureValid: true);
        if ($event !== null) {
            QueueManager::push(ProcessInboundMessageJob::class, [
                'inbound_event_id' => $event->id,
            ]);
        }

        return $this->response->withStatus(200)->withStringBody('ok');
    }

    /** @param array<string, mixed> $payload */
    private function extractPhoneNumberId(array $payload): ?string
    {
        $entries = (array)($payload['entry'] ?? []);
        foreach ($entries as $entry) {
            foreach ((array)($entry['changes'] ?? []) as $change) {
                $candidate = $change['value']['metadata']['phone_number_id'] ?? null;
                if ($candidate !== null) {
                    return (string)$candidate;
                }
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function persistEvent(
        array $payload,
        string $phoneNumberId,
        string $rawBody,
        bool $signatureValid,
        ?string $errorMessage = null,
    ): ?\App\Model\Entity\InboundEvent {
        $events = TableRegistry::getTableLocator()->get('InboundEvents');
        $eventId = $this->deriveEventId($payload, $rawBody);

        // Idempotency: skip duplicate deliveries.
        $existing = $events->find()->where([
            'channel' => 'whatsapp',
            'event_id' => $eventId,
        ])->first();
        if ($existing !== null) {
            /** @var \App\Model\Entity\InboundEvent $existing */
            return $existing;
        }

        $entity = $events->newEntity([
            'channel' => 'whatsapp',
            'event_id' => $eventId,
            'external_account_id' => $phoneNumberId,
            'signature_valid' => $signatureValid,
            'payload' => $rawBody,
            'error_message' => $errorMessage,
        ]);
        $events->save($entity);
        /** @var \App\Model\Entity\InboundEvent $entity */
        return $entity;
    }

    /** @param array<string, mixed> $payload */
    private function deriveEventId(array $payload, string $rawBody): string
    {
        // Prefer the first message id; fallback to status id; final fallback hash of body.
        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['changes'] ?? []) as $change) {
                foreach ((array)($change['value']['messages'] ?? []) as $msg) {
                    if (!empty($msg['id'])) {
                        return 'msg:' . substr((string)$msg['id'], 0, 90);
                    }
                }
                foreach ((array)($change['value']['statuses'] ?? []) as $status) {
                    if (!empty($status['id'])) {
                        return 'st:' . substr((string)$status['id'], 0, 90);
                    }
                }
            }
        }
        return 'h:' . substr(hash('sha256', $rawBody), 0, 90);
    }
}
