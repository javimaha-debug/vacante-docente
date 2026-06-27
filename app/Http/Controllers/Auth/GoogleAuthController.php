<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/*
|--------------------------------------------------------------------------
| Phase 2 — Google sign-in via Laravel Socialite (SCAFFOLDED, DISABLED)
|--------------------------------------------------------------------------
|
| Everything needed to enable Google auth is in place but inert in phase 1:
|   - composer package `laravel/socialite` is installed.
|   - config/services.php has the `google` block.
|   - .env(.example) ship the (commented) GOOGLE_CLIENT_* keys.
|
| To turn it ON in phase 2:
|   1. Uncomment the use-statements and method bodies below.
|   2. Add a migration: $table->string('google_id')->nullable()->unique();
|      and $table->string('avatar')->nullable(); on the users table.
|   3. Uncomment the /auth/google routes in routes/web.php.
|   4. Fill GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI.
|   5. Optionally migrate user_lists.session_token -> user_id ownership.
|
*/

// use App\Models\User;
// use Illuminate\Support\Facades\Auth;
// use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        // return Socialite::driver('google')->redirect();

        return redirect('/'); // disabled in phase 1
    }

    /**
     * Handle the OAuth callback and sign the user in.
     */
    public function callback(): RedirectResponse
    {
        // $googleUser = Socialite::driver('google')->user();
        //
        // $user = User::updateOrCreate(
        //     ['google_id' => $googleUser->getId()],
        //     [
        //         'name' => $googleUser->getName(),
        //         'email' => $googleUser->getEmail(),
        //         'avatar' => $googleUser->getAvatar(),
        //     ]
        // );
        //
        // Auth::login($user, remember: true);

        return redirect('/'); // disabled in phase 1
    }
}
