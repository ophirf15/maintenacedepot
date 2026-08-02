<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\QrLabelService;
use App\Services\SettingsService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings, private AuditLogger $audit) {}

    public function show(string $group)
    {
        if ($group === 'labels') {
            return response()->json([
                'data' => [
                    'layout' => app(QrLabelService::class)->allLayouts(),
                ],
            ]);
        }

        return response()->json(['data' => $this->settings->group($group, includeSecrets: false)]);
    }

    public function updateBranding(Request $request)
    {
        return $this->persist($request, 'branding', [
            'app_name' => 'nullable|string|max:120',
            'logo_path' => 'nullable|string|max:255',
            'favicon_path' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:9',
            'support_email' => 'nullable|email|max:190',
            'label_ownership' => 'nullable|string|max:80',
        ], publicKeys: ['app_name', 'logo_path', 'favicon_path', 'primary_color']);
    }

    /**
     * Upload app / label logo into public storage and set branding.logo_path.
     */
    public function uploadLogo(Request $request)
    {
        return $this->uploadBrandingImage($request, 'logo', 'logo_path', 'logo');
    }

    /**
     * Upload favicon into public storage and set branding.favicon_path.
     */
    public function uploadFavicon(Request $request)
    {
        return $this->uploadBrandingImage($request, 'favicon', 'favicon_path', 'favicon');
    }

    protected function uploadBrandingImage(Request $request, string $fileKey, string $settingKey, string $basename)
    {
        $mimes = $settingKey === 'favicon_path'
            ? 'jpeg,jpg,png,webp,ico'
            : 'jpeg,jpg,png,webp';

        $request->validate([
            $fileKey => "required|file|mimes:{$mimes}|max:5120",
        ]);

        $oldPath = (string) $this->settings->get('branding', $settingKey, '');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $file = $request->file($fileKey);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $path = $file->storeAs('branding', $basename.'.'.$ext, 'public');

        $this->settings->set(
            'branding',
            $settingKey,
            $path,
            'string',
            public: true,
            userId: $request->user()?->id,
        );

        $this->audit->log('settings_updated', null, null, [
            'group' => 'branding',
            'keys' => [$settingKey],
            'action' => $basename.'_uploaded',
        ]);

        return response()->json([
            'data' => [
                $settingKey => $path,
                'url' => PublicStorageUrl::path($path),
            ],
        ]);
    }

    public function updateSmtp(Request $request)
    {
        return $this->persist($request, 'smtp', [
            'host' => 'nullable|string|max:190',
            'port' => 'nullable|integer',
            'username' => 'nullable|string|max:190',
            'password' => 'nullable|string|max:255',
            'encryption' => 'nullable|in:tls,ssl,none',
            'from_address' => 'nullable|email',
            'from_name' => 'nullable|string|max:190',
        ], encryptedKeys: ['password']);
    }

    public function updateTwilio(Request $request)
    {
        return $this->persist($request, 'twilio', [
            'sms_enabled' => 'boolean',
            'account_sid' => 'nullable|string|max:190',
            'auth_token' => 'nullable|string|max:255',
            'from_number' => 'nullable|string|max:32',
        ], encryptedKeys: ['auth_token']);
    }

    public function updateSaml(Request $request)
    {
        return $this->persist($request, 'saml', [
            'enabled' => 'boolean',
            'entity_id' => 'nullable|string|max:255',
            'sso_url' => 'nullable|url',
            'slo_url' => 'nullable|url',
            'x509_cert' => 'nullable|string',
            'auto_provision' => 'boolean',
            'default_role' => 'nullable|string|max:64',
        ], encryptedKeys: ['x509_cert']);
    }

    public function updateFeatures(Request $request)
    {
        return $this->persist($request, 'features', [
            'waitlist_enabled' => 'boolean',
            'self_return_enabled' => 'boolean',
            'offline_scanning_enabled' => 'boolean',
            'capex_forecast_enabled' => 'boolean',
        ]);
    }

    public function updateDefaults(Request $request)
    {
        return $this->persist($request, 'defaults', [
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'default_property_id' => 'nullable|exists:properties,id',
            'default_role' => 'nullable|string|max:64',
            'timezone' => 'nullable|string|max:64',
        ]);
    }

    public function updateUpdates(Request $request)
    {
        return $this->persist($request, 'updates', [
            'github_repo' => 'nullable|string|max:190',
            'github_token' => 'nullable|string|max:255',
            'auto_check' => 'boolean',
        ], encryptedKeys: ['github_token']);
    }

    /**
     * Per-size label field toggles + placement options (JSON).
     */
    public function updateLabels(Request $request)
    {
        $payload = $request->validate([
            'layout' => 'required|array',
        ]);

        $knownSizes = array_keys(QrLabelService::sizes());
        $incoming = $payload['layout'];

        foreach (array_keys($incoming) as $sizeKey) {
            if (! in_array($sizeKey, $knownSizes, true)) {
                throw ValidationException::withMessages([
                    "layout.{$sizeKey}" => 'Unknown label size. Use: '.implode(', ', $knownSizes),
                ]);
            }
            if (! is_array($incoming[$sizeKey])) {
                throw ValidationException::withMessages([
                    "layout.{$sizeKey}" => 'Each size must be an object of field toggles and options.',
                ]);
            }

            $allowed = QrLabelService::allowedLayoutKeysFor($sizeKey);
            foreach (array_keys($incoming[$sizeKey]) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw ValidationException::withMessages([
                        "layout.{$sizeKey}.{$field}" => 'Unknown label field or option for this size.',
                    ]);
                }
            }
        }

        $cleaned = [];
        foreach ($knownSizes as $sizeKey) {
            $row = is_array($incoming[$sizeKey] ?? null) ? $incoming[$sizeKey] : [];
            $fields = QrLabelService::normalizeSizeLayout($sizeKey, $row);

            if (! ($fields['qr'] ?? false) && ! ($fields['numeric_id'] ?? false)) {
                throw ValidationException::withMessages([
                    "layout.{$sizeKey}" => 'Turn on at least the QR code or the 6-digit ID.',
                ]);
            }

            $cleaned[$sizeKey] = $fields;
        }

        $this->settings->set(
            'labels',
            'layout',
            $cleaned,
            'json',
            userId: $request->user()?->id,
        );

        $this->audit->log('settings_updated', null, null, ['group' => 'labels', 'keys' => ['layout']]);

        return response()->json([
            'data' => [
                'layout' => app(QrLabelService::class)->allLayouts(),
            ],
        ]);
    }

    protected function persist(Request $request, string $group, array $rules, array $encryptedKeys = [], array $publicKeys = [])
    {
        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');

            $this->settings->set(
                $group,
                $key,
                $value,
                $type,
                encrypted: in_array($key, $encryptedKeys, true),
                public: in_array($key, $publicKeys, true),
                userId: $request->user()?->id,
            );
        }

        $this->audit->log('settings_updated', null, null, ['group' => $group, 'keys' => array_keys($data)]);

        return response()->json(['data' => $this->settings->group($group)]);
    }
}
