<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SmsLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only place that talks to an SMS gateway. Controllers and services call
 * send()/sendLater(); switching provider only needs a new branch in dispatch().
 *
 * Sending never throws: failures are written to sms_logs so financial
 * operations and case creation are never rolled back because of SMS.
 */
class SmsService
{
    public function config(): array
    {
        // Values saved in System Settings win; .env values are the fallback.
        return [
            'enabled' => Settings::bool('sms_enabled') || (bool) config('services.sms.enabled'),
            'provider' => Settings::get('sms_provider', 'africastalking'),
            'url' => Settings::get('sms_api_url', config('services.sms.url')),
            'username' => Settings::get('sms_username', config('services.sms.username')),
            'key' => Settings::get('sms_api_key', config('services.sms.key')),
            'sender' => Settings::get('sms_sender_id', config('services.sms.sender_id')),
        ];
    }

    public function send(?string $phone, string $message, string $category, ?Member $member = null): ?SmsLog
    {
        try {
            $phone = self::normalizePhone($phone);
            if (! $phone) {
                return null;
            }
            $cfg = $this->config();
            $log = SmsLog::create([
                'member_id' => $member?->id,
                'phone' => $phone,
                'message' => $message,
                'category' => $category,
                'status' => 'pending',
                'provider' => $cfg['provider'],
                'sent_by' => auth()->id(),
            ]);

            if (! $cfg['enabled'] || ! $cfg['url'] || ! $cfg['key']) {
                $log->update(['status' => 'disabled', 'response' => 'SMS sending is disabled or the gateway is not configured.']);

                return $log;
            }

            [$ok, $response, $messageId] = $this->dispatch($cfg, $phone, $message);
            $log->update([
                'status' => $ok ? 'sent' : 'failed',
                'response' => mb_substr((string) $response, 0, 2000),
                'provider_message_id' => $messageId,
            ]);

            return $log;
        } catch (\Throwable $e) {
            Log::warning('SMS sending failed: '.$e->getMessage());
            if (isset($log)) {
                $log->update(['status' => 'failed', 'response' => mb_substr($e->getMessage(), 0, 2000)]);
            }

            return $log ?? null;
        }
    }

    /**
     * Send many messages after the HTTP response has been returned to the user,
     * so creating a case for hundreds of members stays fast.
     *
     * @param array<int, array{0: ?string, 1: string, 2: ?Member}> $messages [phone, text, member]
     */
    public function sendLater(array $messages, string $category): void
    {
        if (! $messages) {
            return;
        }
        $userId = auth()->id();
        $payload = array_map(fn ($m) => [$m[0], $m[1], $m[2]?->id], $messages);

        app()->terminating(function () use ($payload, $category, $userId) {
            @set_time_limit(0);
            if ($userId) {
                auth()->onceUsingId($userId);
            }
            foreach ($payload as [$phone, $text, $memberId]) {
                $this->send($phone, $text, $category, $memberId ? Member::withTrashed()->find($memberId) : null);
            }
        });
    }

    /** Fill a template saved in settings with values. Common group values are added automatically. */
    public static function render(string $templateKey, array $vars = []): string
    {
        $vars += [
            'group' => Settings::get('short_name') ?: Settings::get('group_name'),
            'currency' => Settings::get('currency', 'KES'),
            'paybill' => Settings::get('paybill_number'),
            'account' => Settings::get('account_instructions'),
        ];
        $text = (string) Settings::get($templateKey, '');
        foreach ($vars as $k => $v) {
            $text = str_replace('{'.$k.'}', (string) $v, $text);
        }

        return trim($text);
    }

    /** Convert local numbers (07.., 01..) to international format (+2547..). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '254'.$digits;
        }

        return '+'.$digits;
    }

    private function dispatch(array $cfg, string $phone, string $message): array
    {
        if ($cfg['provider'] === 'africastalking') {
            $payload = ['username' => $cfg['username'], 'to' => $phone, 'message' => $message];
            if ($cfg['sender']) {
                $payload['from'] = $cfg['sender'];
            }
            $res = Http::asForm()->acceptJson()->timeout(20)
                ->withHeaders(['apiKey' => $cfg['key']])
                ->post($cfg['url'], $payload);
            $recipient = $res->json('SMSMessageData.Recipients.0');
            $ok = $res->successful() && in_array($recipient['statusCode'] ?? null, [100, 101, 102], true);

            return [$ok, $res->body(), $recipient['messageId'] ?? null];
        }

        // Generic JSON gateway: POST {api_key, username, sender_id, phone, message} with a Bearer token.
        $res = Http::acceptJson()->timeout(20)->withToken($cfg['key'])->post($cfg['url'], [
            'api_key' => $cfg['key'],
            'username' => $cfg['username'],
            'sender_id' => $cfg['sender'],
            'phone' => $phone,
            'message' => $message,
        ]);

        return [$res->successful(), $res->body(), $res->json('message_id') ?? $res->json('id')];
    }
}
