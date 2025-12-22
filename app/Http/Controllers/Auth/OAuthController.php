<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;
use HubSpot\Utils\OAuth2;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                'https://www.googleapis.com/auth/calendar',
                // 'https://www.googleapis.com/auth/calendar.events',
                // 'https://www.googleapis.com/auth/calendar.readonly',
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
     * Redirect to Hubspot OAuth
     */
    public function redirectToHubspot()
    {
        $provider = Socialite::driver('hubspot');

        $scopes = config('services.hubspot.scopes', []);

        // Use Socialite to build the HubSpot authorization redirect
        return $provider->scopes($scopes)->redirect();
    }

    /**
     * Handle Hubspot OAuth callback
     */
    public function handleHubspotCallback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/dashboard')->with('error', 'Authorization failed');
        }

        try {
            // Use Socialite to retrieve tokens from HubSpot
            $hubspotUser = Socialite::driver('hubspot')->user();

            $user = Auth::user();
            if (!$user) {
                return redirect('/')->with('error', 'Please login first!');
            }

            $user->update([
                'hubspot_token' => $hubspotUser->token,
                'hubspot_refresh_token' => $hubspotUser->refreshToken ?? null,
                'hubspot_token_expires_at' => $hubspotUser->expiresIn ? Carbon::now()->addSeconds($hubspotUser->expiresIn) : null,
            ]);

            return redirect('/dashboard')->with('success', 'HubSpot connected successfully!');

        } catch (\Exception $e) {
            return redirect('/dashboard')->with('error', 'Failed to connect: ' . $e->getMessage());
        }
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