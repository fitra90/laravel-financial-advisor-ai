<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    /**
     * Connect Google Calendar (OAuth callback)
     */
    public function connect()
    {
        $client = new \Google\Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->addScope(\Google\Service\Calendar::CALENDAR_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        return redirect($authUrl);
    }

    /**
     * OAuth callback handler
     */
    public function callback(Request $request)
    {
        $client = new \Google\Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));

        if ($request->has('code')) {
            $token = $client->fetchAccessTokenWithAuthCode($request->code);
            
            if (!isset($token['error'])) {
                Auth::user()->update([
                    'google_calendar_token' => json_encode($token)
                ]);

                // Trigger initial sync
                $service = new GoogleCalendarService(Auth::user());
                $service->syncEvents();

                return redirect('/dashboard')->with('success', 'Google Calendar connected successfully!');
            }
        }

        return redirect('/dashboard')->with('error', 'Failed to connect Google Calendar');
    }

    /**
     * Manual sync trigger
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        $service = new GoogleCalendarService($user);
        
        $options = [];
        if ($request->has('days_past')) {
            $options['time_min'] = now()->subDays($request->days_past)->toRfc3339String();
        }
        if ($request->has('days_future')) {
            $options['time_max'] = now()->addDays($request->days_future)->toRfc3339String();
        }

        $result = $service->syncEvents($options);

        if ($result['success']) {
            return response()->json([
                'message' => 'Calendar synced successfully',
                'data' => $result
            ]);
        }

        return response()->json([
            'message' => $result['message']
        ], 400);
    }

    /**
     * Check sync status
     */
    public function status()
    {
        $user = Auth::user();
        $service = new GoogleCalendarService($user);

        return response()->json([
            'connected' => $service->isConnected(),
            'last_sync' => $user->calendar_last_sync_at,
        ]);
    }

    /**
     * Disconnect calendar
     */
    public function disconnect()
    {
        Auth::user()->update([
            'google_calendar_token' => null,
            'calendar_last_sync_at' => null,
        ]);

        return response()->json([
            'message' => 'Google Calendar disconnected'
        ]);
    }

    /**
     * Get upcoming events
     */
    public function upcoming(Request $request)
    {
        $days = $request->get('days', 7);
        $service = new GoogleCalendarService(Auth::user());
        
        $events = $service->getUpcomingEvents($days);

        return response()->json([
            'events' => $events
        ]);
    }
}