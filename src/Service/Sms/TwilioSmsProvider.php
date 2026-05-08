<?php
declare(strict_types=1);

namespace App\Service\Sms;

use Cake\Core\Configure;
use Cake\Log\LogTrait;

class TwilioSmsProvider implements SmsProviderInterface
{
    use LogTrait;

    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private string $apiUrl;

    public function __construct(
        string $accountSid,
        string $authToken,
        string $fromNumber,
        string $apiUrl = 'https://api.twilio.com'
    ) {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
        $this->apiUrl = $apiUrl;
    }

    public function send(string $to, string $message): void
    {
        $url = "{$this->apiUrl}/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Basic ' . base64_encode("{$this->accountSid}:{$this->authToken}"),
                ],
                'content' => http_build_query([
                    'To' => $to,
                    'From' => $this->fromNumber,
                    'Body' => $message,
                ]),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new SmsException('Twilio request failed: no response');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response, true) ?? [];

        if (isset($data['error_code'])) {
            throw new SmsException("Twilio error {$data['error_code']}: {$data['message']}");
        }

        $this->log("SMS sent via Twilio to {$to}", 'info', ['scope' => 'sms']);
    }
}
