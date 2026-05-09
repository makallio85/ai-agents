<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Raw inbound webhook payload stored for audit + idempotency.
 *
 * Channel transports parse $payload into InboundEnvelope instances when
 * ProcessInboundMessageJob picks the row up.
 *
 * @property int $id
 * @property string $channel
 * @property string $event_id
 * @property string|null $external_account_id
 * @property bool $signature_valid
 * @property string $payload
 * @property \Cake\I18n\DateTime|null $processed_at
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class InboundEvent extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'channel' => true,
        'event_id' => true,
        'external_account_id' => true,
        'signature_valid' => true,
        'payload' => true,
        'processed_at' => true,
        'error_message' => true,
    ];
}
