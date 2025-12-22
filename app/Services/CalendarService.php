<?php

namespace App\Services;

use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleCalendarService
{
    protected $user;
    protected $client;
    protected $embeddingService;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->embeddingService = new EmbeddingService();
        $this->initializeClient();
    }

    /**
     * Initialize Google Client
     */
    protected function initializeClient(): void
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->addScope(GoogleCalendar::CALENDAR_READONLY);

        if ($this->user->google_calendar_token) {
            $accessToken = json_decode($this->user->google_calendar_token, true);
            $this->client->setAccessToken($accessToken);

            // Refresh token if expired
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken();
                    $this->user->update([
                        'google_calendar_token' => json_encode($newToken)
                    ]);
                }
            }
        }
    }

    /**
     * Sync calendar events
     */
    public function syncEvents(array $options = []): array
    {
        if (!$this->user->google_calendar_token) {
            return [
                'success' => false,
                'message' => 'Google Calendar not connected',
            ];
        }

        try {
            $calendarService = new GoogleCalendar($this->client);
            
            // Default to sync last 30 days and next 90 days
            $timeMin = $options['time_min'] ?? Carbon::now()->subDays(30)->toRfc3339String();
            $timeMax = $options['time_max'] ?? Carbon::now()->addDays(90)->toRfc3339String();
            $maxResults = $options['max_results'] ?? 250;

            $params = [
                'maxResults' => $maxResults,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
            ];

            // Get primary calendar events
            $events = $calendarService->events->listEvents('primary', $params);
            
            $synced = 0;
            $errors = 0;

            foreach ($events->getItems() as $event) {
                try {
                    $this->storeEvent($event);
                    $synced++;
                } catch (\Exception $e) {
                    Log::error('Failed to store calendar event', [
                        'event_id' => $event->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            return [
                'success' => true,
                'synced' => $synced,
                'errors' => $errors,
                'total' => $events->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Calendar sync failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to sync calendar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Store a single event with embedding
     */
    protected function storeEvent($event): bool
    {
        $start = $event->start->dateTime ?? $event->start->date;
        $end = $event->end->dateTime ?? $event->end->date;

        // Extract attendees
        $attendees = [];
        if ($event->getAttendees()) {
            foreach ($event->getAttendees() as $attendee) {
                $attendees[] = $attendee->email;
            }
        }

        $eventData = [
            'user_id' => $this->user->id,
            'event_id' => $event->getId(),
            'calendar_id' => 'primary',
            'summary' => $event->getSummary() ?? '(No title)',
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'start_datetime' => Carbon::parse($start)->toDateTimeString(),
            'end_datetime' => Carbon::parse($end)->toDateTimeString(),
            'attendees' => $attendees,
            'status' => $event->getStatus() ?? 'confirmed',
            'organizer_email' => $event->getOrganizer()?->email,
            'organizer_name' => $event->getOrganizer()?->displayName,
            'is_recurring' => !empty($event->getRecurringEventId()),
            'recurring_event_id' => $event->getRecurringEventId(),
            'html_link' => $event->getHtmlLink(),
        ];

        return $this->embeddingService->storeEventWithEmbedding($eventData);
    }

    /**
     * Get upcoming events from Google Calendar (live)
     */
    public function getUpcomingEvents(int $days = 7): array
    {
        if (!$this->user->google_calendar_token) {
            return [];
        }

        try {
            $calendarService = new GoogleCalendar($this->client);
            
            $timeMin = Carbon::now()->toRfc3339String();
            $timeMax = Carbon::now()->addDays($days)->toRfc3339String();

            $params = [
                'maxResults' => 20,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
            ];

            $events = $calendarService->events->listEvents('primary', $params);
            
            $result = [];
            foreach ($events->getItems() as $event) {
                $start = $event->start->dateTime ?? $event->start->date;
                
                $result[] = [
                    'id' => $event->getId(),
                    'summary' => $event->getSummary(),
                    'start' => Carbon::parse($start)->toDateTimeString(),
                    'location' => $event->getLocation(),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to fetch upcoming events', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if calendar is connected
     */
    public function isConnected(): bool
    {
        return !empty($this->user->google_calendar_token);
    }
}