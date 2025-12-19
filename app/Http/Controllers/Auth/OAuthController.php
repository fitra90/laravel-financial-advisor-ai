<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Carbon\Carbon;

class OAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
                
        /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->scopes([
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.modify',
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/calendar.readonly',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent']) // Get refresh token
            ->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken ?? null,
                    'google_token_expires_at' => $googleUser->expiresIn 
                        ? Carbon::now()->addSeconds($googleUser->expiresIn)
                        : null,
                ]
            );

            // Log the user in
            Auth::login($user, true);

            return redirect('/dashboard')->with('success', 'Successfully connected to Google!');
            
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Failed to authenticate with Google: ' . $e->getMessage());
        }
    }

    /**
     * Redirect to Hubspot OAuth (we'll implement this on Day 2)
     */
    public function redirectToHubspot()
    {
        // We'll implement this tomorrow
        return redirect('/dashboard')->with('info', 'Hubspot integration coming soon!');
    }

    /**
     * Handle Hubspot OAuth callback (we'll implement this on Day 2)
     */
    public function handleHubspotCallback()
    {
        // We'll implement this tomorrow
    }

    /**
     * Logout
     */
    public function logout()
    {
        Auth::logout();
        return redirect('/')->with('success', 'Logged out successfully!');
    }
}