<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /** OAuth providers the app can use (enabled individually via config). */
    private const PROVIDERS = ['google', 'microsoft'];

    /**
     * Register a new account with email + password and issue a Sanctum token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model casts 'password' => 'hashed', so pass it plain.
            'password' => $data['password'],
            'locale' => 'es',
        ]);

        return response()->json([
            'token' => $user->createToken('password-spa')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    /**
     * Log in with email + password and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => 'Esta cuenta ha sido suspendida. Contacta con soporte.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken('password-spa')->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * Which login methods are available (for the SPA to render the buttons).
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'password' => true,
            'providers' => array_values(array_filter(
                self::PROVIDERS,
                fn (string $p) => $this->providerEnabled($p),
            )),
        ]);
    }

    /**
     * Redirect to a social provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! $this->providerEnabled($provider)) {
            return redirect('/?error=oauth_provider');
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Throwable $e) {
            return redirect('/?error=oauth');
        }
    }

    /**
     * Handle a social provider callback: find or create the user, issue a
     * Sanctum token and bounce back to the SPA dashboard carrying the token.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! $this->providerEnabled($provider)) {
            return redirect('/?error=oauth_provider');
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect('/?error=oauth');
        }

        $email = $social->getEmail();
        if (! $email) {
            return redirect('/?error=oauth_email');
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->nombre_gva = null;
            $user->locale = 'es';
            $user->password = Hash::make(Str::random(40));
        }

        $user->name = $social->getName() ?: ($user->name ?: $email);
        if ($social->getAvatar()) {
            $user->avatar_url = $social->getAvatar();
        }

        // Platform owner is always promoted to super-admin on login.
        if ($email === 'j.madrid@loggex.es' && $user->role !== 'superadmin') {
            $user->role = 'superadmin';
        }

        $user->save();

        $token = $user->createToken($provider.'-spa')->plainTextToken;

        // Don't put the long-lived token in the URL (leaks via history/Referer/
        // logs). Hand over a single-use, short-lived code the SPA exchanges for
        // the real token via POST.
        $code = Str::random(48);
        Cache::put('oauth_code:'.$code, ['token' => $token, 'uid' => $user->id], now()->addSeconds(120));

        return redirect('/dashboard?code='.$code);
    }

    /**
     * Exchange a one-time OAuth code (from the callback redirect) for the real
     * Sanctum token. The code is single-use and expires after 2 minutes.
     */
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $payload = Cache::pull('oauth_code:'.$data['code']);
        if (! $payload || empty($payload['token'])) {
            throw ValidationException::withMessages(['code' => 'Código inválido o caducado.']);
        }

        return response()->json([
            'token' => $payload['token'],
            'user' => User::find($payload['uid']),
        ]);
    }

    /**
     * Revoke the caller's current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessió tancada.']);
    }

    /**
     * A provider is usable when its OAuth client credentials are configured.
     */
    private function providerEnabled(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true)
            && (bool) config("services.{$provider}.client_id");
    }

    // --- Backwards-compatible Google entry points (named routes) ---

    public function redirectToGoogle(): RedirectResponse
    {
        return $this->redirect('google');
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        return $this->callback('google');
    }
}
