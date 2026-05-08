<?php
declare(strict_types=1);

namespace App\Service\Sms;

interface SmsProviderInterface
{
    /**
     * Send an SMS message to the given phone number.
     *
     * @throws \App\Service\Sms\SmsException on delivery failure
     */
    public function send(string $to, string $message): void;
}
