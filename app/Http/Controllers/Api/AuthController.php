<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationState;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private SettingsService $settings,
        private AuditLogger $audit,
    ) {}

    public function config()
    {
        $installed = false;
        $branding = [];
        $public = [];
        $samlEnabled = false;
        $ssoUrl = null;

        if (Schema::hasTable('installation_state')) {
            $installed = (bool) (InstallationState::query()->value('is_installed') ?? false);
        }

        if (Schema::hasTable('settings')) {
            $branding = $this->settings->group('branding');
            $public = $this->settings->publicMap();
            $samlEnabled = $this->auth->samlEnabled();
            $ssoUrl = $this->settings->get('saml', 'sso_url');
        }

        return response()->json([
            'branding' => $branding,
            'public' => $public,
            'saml' => [
                'enabled' => $samlEnabled,
                'sso_url' => $ssoUrl,
            ],
            'version' => config('depot.version'),
            'installed' => $installed,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->auth->loginPassword($data['email'], $data['password']);
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function requestMagic(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $this->auth->requestMagicLink($data['email']);

        $payload = ['message' => 'If that email exists, a magic link was sent.'];

        // Never expose magic links outside local development, even if APP_DEBUG is on.
        if (app()->environment('local')) {
            $user = \App\Models\User::where('email', $data['email'])->first();
            if ($user) {
                $debug = cache()->get('magic_link_debug_'.$user->id);
                if ($debug) {
                    $payload['debug_link'] = $debug;
                }
            }
        }

        return response()->json($payload);
    }

    public function consumeMagic(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        $user = $this->auth->consumeMagicLink($data['token']);
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function samlAcs(Request $request)
    {
        $user = $this->auth->loginFromSamlAttributes($request->all());
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $this->audit->log('logout', $user, null, ['method' => 'token']);
        $user->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    protected function userPayload($user): array
    {
        $user->load(['roles', 'permissions', 'properties']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'properties' => $user->properties,
            'default_property_id' => $user->default_property_id,
        ];
    }
}
