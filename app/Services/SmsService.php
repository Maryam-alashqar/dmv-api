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
            (new Client($sid, $authToken))->messages->create($this->toE164($phoneNumber), [
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

    /**
     * Twilio requires E.164 (e.g. +12025551234), but numbers are stored as
     * entered by the user (e.g. a plain 10-digit "2025551234"). The app's
     * user base is US-based, so a bare 10-digit number is assumed to be a US
     * number missing its country code; an 11-digit number starting with "1"
     * is missing only the "+". Anything already starting with "+" is passed
     * through as-is.
     */
    private function toE164(string $phoneNumber): string
    {
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        $digits = preg_replace('/\D/', '', $phoneNumber);

        return match (true) {
            strlen($digits) === 10 => "+1{$digits}",
            strlen($digits) === 11 && str_starts_with($digits, '1') => "+{$digits}",
            default => "+{$digits}",
        };
    }
}
