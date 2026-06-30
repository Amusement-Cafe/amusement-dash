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
            
            // Find or create the user in the "users" collection
            // mapping their Discord ID to the 'userID' field.
            $user = User::updateOrCreate([
                'userID' => $discordUser->id,
            ], [
                'username' => $discordUser->nickname ?? $discordUser->name,
            ]);
            
            Auth::login($user, true);
            
            return redirect()->intended('/');
            
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Authentication failed: ' . $e->getMessage());
        }
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
