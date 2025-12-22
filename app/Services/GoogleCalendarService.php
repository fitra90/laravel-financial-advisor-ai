<?php

namespace App\Services;

use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\EventAttendee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
        $this->client->addScope(GoogleCalendar::CALENDAR);

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
     * Search calendar events using semantic search
     */
    public function searchEvents(array $args): array
    {
        $query = $args['query'] ?? '';
        $limit = $args['limit'] ?? 10;
        $timeMin = $args['timeMin'] ?? null;
        $timeMax = $args['timeMax'] ?? null;

        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        try {
            // Generate embedding for the search query
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);

            // Build the SQL query
            $sql = "
                SELECT 
                    event_id,
                    summary,
                    description,
                    location,
                    start_datetime,
                    end_datetime,
                    attendees,
                    organizer_name,
                    organizer_email,
                    html_link,
                    status,
                    1 - (embedding <=> :embedding) as similarity
                FROM calendar_events
                WHERE user_id = :user_id
            ";

            $params = [
                'embedding' => json_encode($queryEmbedding),
                'user_id' => $this->user->id,
            ];

            // Add time filters if provided
            if ($timeMin) {
                $sql .= " AND start_datetime >= :time_min";
                $params['time_min'] = $timeMin;
            }

            if ($timeMax) {
                $sql .= " AND start_datetime <= :time_max";
                $params['time_max'] = $timeMax;
            }

            $sql .= " ORDER BY similarity DESC LIMIT :limit";
            $params['limit'] = $limit;

            $results = DB::select($sql, $params);

            if (empty($results)) {
                return [
                    'message' => 'No meetings found matching your query',
                    'count' => 0,
                    'events' => []
                ];
            }

            // Format the results
            $events = array_map(function($event) {
                $attendees = json_decode($event->attendees, true) ?? [];
                
                return [
                    'id' => $event->event_id,
                    'title' => $event->summary,
                    'description' => $event->description,
                    'location' => $event->location,
                    'start' => Carbon::parse($event->start_datetime)->format('Y-m-d H:i'),
                    'end' => Carbon::parse($event->end_datetime)->format('Y-m-d H:i'),
                    'attendees' => $attendees,
                    'organizer' => $event->organizer_name,
                    'organizer_email' => $event->organizer_email,
                    'link' => $event->html_link,
                    'status' => $event->status,
                    'similarity' => round($event->similarity, 4),
                ];
            }, $results);

            return [
                'count' => count($events),
                'events' => $events,
            ];

        } catch (\Exception $e) {
            Log::error('Calendar search failed', [
                'user_id' => $this->user->id,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Failed to search calendar events: ' . $e->getMessage()];
        }
    }

    /**
     * Create a new calendar event
     */
    public function createEvent(array $args): array
    {
        if (!$this->user->google_calendar_token) {
            return ['error' => 'Google Calendar not connected'];
        }

        try {
            $calendarService = new GoogleCalendar($this->client);

            // Create the event
            $event = new Event([
                'summary' => $args['summary'],
                'description' => $args['description'] ?? null,
                'location' => $args['location'] ?? null,
                'start' => new EventDateTime([
                    'dateTime' => $args['start'],
                    'timeZone' => 'Asia/Jakarta', // Adjust to user's timezone
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $args['end'],
                    'timeZone' => 'Asia/Jakarta',
                ]),
            ]);

            // Add attendees if provided
            if (!empty($args['attendees'])) {
                $attendees = [];
                foreach ($args['attendees'] as $email) {
                    $attendees[] = new EventAttendee(['email' => $email]);
                }
                $event->setAttendees($attendees);
            }

            // Add Google Meet conference if requested
            if ($args['conference'] ?? true) {
                $conferenceRequest = new CreateConferenceRequest();
                $conferenceRequest->setRequestId(uniqid());
                $conferenceRequest->setConferenceSolutionKey(
                    new ConferenceSolutionKey(['type' => 'hangoutsMeet'])
                );
                
                $conferenceData = new ConferenceData();
                $conferenceData->setCreateRequest($conferenceRequest);
                $event->setConferenceData($conferenceData);
            }

            // Create the event
            $createdEvent = $calendarService->events->insert('primary', $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all', // Send email invites to attendees
            ]);

            // Store the event in our database
            $this->storeEvent($createdEvent);

            return [
                'success' => true,
                'message' => "Event created: {$args['summary']}",
                'event' => [
                    'id' => $createdEvent->getId(),
                    'summary' => $createdEvent->getSummary(),
                    'start' => $createdEvent->start->dateTime,
                    'end' => $createdEvent->end->dateTime,
                    'link' => $createdEvent->getHtmlLink(),
                    'meet_link' => $createdEvent->getConferenceData()?->getEntryPoints()[0]?->uri ?? null,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create calendar event', [
                'user_id' => $this->user->id,
                'args' => $args,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Failed to create event: ' . $e->getMessage()];
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