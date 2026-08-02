<?php

namespace App\Services;

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private AuditLogger $audit, private SettingsService $settings) {}

    public function requestMagicLink(string $email): void
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if (! $user) {
            return; // do not reveal
        }

        $token = Str::random(64);
        MagicLinkToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
        ]);

        $url = url('/login/magic?token='.$token);

        try {
            Mail::raw("Click to sign in: {$url}", function ($message) use ($user) {
                $message->to($user->email)->subject('Your Maintenance Depot login link');
            });
        } catch (\Throwable) {
            // In local/dev, token is still created; controller can return link when APP_ENV=local
        }

        // Only cache raw magic URLs on local installs — never in staging/production.
        if (app()->environment('local')) {
            cache()->put('magic_link_debug_'.$user->id, $url, now()->addMinutes(30));
        }
    }

    public function consumeMagicLink(string $token): User
    {
        $hashed = hash('sha256', $token);
        $record = MagicLinkToken::query()->where('token', $hashed)->latest()->first();

        if (! $record || ! $record->isValid()) {
            $this->audit->log('login_failed', null, null, ['method' => 'magic_link', 'reason' => 'invalid_or_expired']);
            throw ValidationException::withMessages(['token' => 'Invalid or expired magic link.']);
        }

        $record->update(['used_at' => now()]);
        $user = $record->user;
        $user->update(['last_login_at' => now()]);
        $this->audit->log('login', $user, null, ['method' => 'magic_link']);

        return $user;
    }

    public function loginPassword(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        // Always bcrypt-check (valid dummy hash) so missing users take similar time.
        $hash = $user?->password
            ?: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        if (! $user || ! Hash::check($password, $hash)) {
            $this->audit->log('login_failed', null, null, ['email' => $email]);
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $user->update(['last_login_at' => now()]);
        $this->audit->log('login', $user, null, ['method' => 'password']);

        return $user;
    }

    public function samlEnabled(): bool
    {
        return (bool) $this->settings->get('saml', 'enabled', false);
    }

    public function samlConfig(): array
    {
        return [
            'enabled' => $this->samlEnabled(),
            'entity_id' => $this->settings->get('saml', 'entity_id'),
            'sso_url' => $this->settings->get('saml', 'sso_url'),
            'slo_url' => $this->settings->get('saml', 'slo_url'),
            'x509_cert' => $this->settings->get('saml', 'x509_cert'),
            'auto_provision' => $this->settings->get('saml', 'auto_provision', true),
            'default_role' => $this->settings->get('saml', 'default_role', 'borrower'),
        ];
    }

    /**
     * SAML ACS stub is intentionally locked until signature-verified assertions
     * are wired (e.g. onelogin/php-saml). Accepting raw JSON attributes would
     * allow unauthenticated account takeover.
     */
    public function loginFromSamlAttributes(array $attrs): User
    {
        if (! $this->samlEnabled()) {
            throw ValidationException::withMessages(['saml' => 'SAML is disabled.']);
        }

        $this->audit->log('login_failed', null, null, [
            'method' => 'saml',
            'reason' => 'acs_not_signature_verified',
        ]);

        throw ValidationException::withMessages([
            'saml' => 'SAML ACS requires signature-verified assertions. Configure a SAML library integration before enabling SSO login.',
        ]);
    }
}
