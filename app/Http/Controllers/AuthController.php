<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Redirect the user to the Discord authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('discord')->setScopes(['identify'])->redirect();
    }

    /**
     * Obtain the user information from Discord.
     */
    public function callback()
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            report($e);
            return redirect('/')->with('login_notice', 'Discord sign-in failed. Please try again.');
        }

        // Accounts are only ever created by the bot, which fills in all the
        // defaults the game relies on. Never create one from the dashboard.
        $user = User::where('userID', (string) $discordUser->id)->first();

        if (!$user) {
            return redirect('/')->with('login_notice', 'You don\'t have an Amusement Club account yet. Run /daily with the bot on Discord, then come back and sign in.');
        }

        Auth::login($user, true);

        return redirect()->intended('/');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
