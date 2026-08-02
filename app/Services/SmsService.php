<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Rest\Client;

/**
 * Sends SMS messages via Twilio. Delivery is best-effort: failures are
 * logged and swallowed rather than thrown, since a down SMS provider must
 * never block account creation, login, or password reset.
 */
class SmsService
{
    public function send(string $phoneNumber, string $message): bool
    {
        $sid = config('services.twilio.sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (! $sid || ! $authToken || ! $from) {
            Log::warning('Twilio is not configured; SMS not sent.', ['to' => $phoneNumber]);

            return false;
        }

        try {
            (new Client($sid, $authToken))->messages->create($phoneNumber, [
                'from' => $from,
                'body' => $message,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Twilio SMS send failed.', [
                'to' => $phoneNumber,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
