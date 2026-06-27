<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google OAuth callback: find or create the user, issue a
     * Sanctum token and bounce back to the SPA dashboard carrying the token.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect('/?error=oauth');
        }

        $user = User::firstOrNew(['email' => $googleUser->getEmail()]);

        if (! $user->exists) {
            // New account defaults; GVA name is filled in later by the user.
            $user->nombre_gva = null;
            $user->locale = 'es';
            $user->password = Hash::make(Str::random(40));
        }

        $user->name = $googleUser->getName() ?: ($user->name ?: $googleUser->getEmail());
        $user->avatar_url = $googleUser->getAvatar();
        $user->save();

        $token = $user->createToken('google-spa')->plainTextToken;

        return redirect('/dashboard?token='.urlencode($token));
    }

    /**
     * Revoke the caller's current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessió tancada.']);
    }
}
