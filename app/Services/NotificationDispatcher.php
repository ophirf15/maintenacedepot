<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationDispatcher
{
    public function __construct(private SettingsService $settings) {}

    public function send(string $typeKey, User $user, array $data = [], ?string $actionUrl = null): void
    {
        $type = NotificationType::query()->where('key', $typeKey)->first();
        if (! $type || ! $type->is_active) {
            return;
        }

        $channels = $this->resolvedChannels($type);

        if (in_array('in_app', $channels, true)) {
            $user->notify(new \App\Notifications\DepotAlert(
                $typeKey,
                $data['title'] ?? $type->name,
                $data['body'] ?? $type->description,
                $actionUrl,
                $type->group,
            ));
        }

        if (in_array('mail', $channels, true) && $user->email) {
            try {
                Mail::raw(
                    ($data['body'] ?? $type->description ?? $type->name)."\n".($actionUrl ?? ''),
                    function ($message) use ($user, $type, $data) {
                        $message->to($user->email)
                            ->subject($data['title'] ?? $type->name);
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('Email notification failed', ['error' => $e->getMessage()]);
            }
        }

        if (in_array('sms', $channels, true) && $user->phone && $this->settings->get('twilio', 'sms_enabled', false)) {
            $this->sendSms($user->phone, $data['title'] ?? $type->name);
        }
    }

    protected function resolvedChannels(NotificationType $type): array
    {
        $defaults = $type->default_channels ?? ['in_app'];
        $settings = NotificationSetting::query()
            ->where('notification_type_id', $type->id)
            ->where('scope_type', 'global')
            ->get();

        if ($settings->isEmpty()) {
            return $defaults;
        }

        return $settings->where('is_enabled', true)->pluck('channel')->unique()->values()->all();
    }

    protected function sendSms(string $to, string $body): void
    {
        $sid = $this->settings->get('twilio', 'account_sid');
        $token = $this->settings->get('twilio', 'auth_token');
        $from = $this->settings->get('twilio', 'from_number');

        if (! $sid || ! $token || ! $from) {
            return;
        }

        try {
            Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $body,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SMS notification failed', ['error' => $e->getMessage()]);
        }
    }
}
