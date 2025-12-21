<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CalendarService
{
    protected $user;
    protected $client;
    protected $service;

    public function __construct(User $user)
    {
        $this->user = $user;

        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->addScope(Calendar::CALENDAR_READONLY);

        // Refresh token jika access token expired
        if ($user->google_token) {
            $client->setAccessToken($user->google_token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $newToken = $client->getAccessToken();

                    // Simpan token baru ke user
                    $user->update(['google_token' => $newToken]);
                }
            }
        }

        $this->client = $client;
        $this->service = new Calendar($client);
    }

    /**
     * Cari event berdasarkan nama orang, kata kunci, atau waktu
     */
    public function searchEvents(array $params): array
    {
        $query = $params['query'] ?? '';
        $timeMin = $params['timeMin'] ?? null; // ISO datetime
        $timeMax = $params['timeMax'] ?? null;
        $limit = $params['limit'] ?? 10;

        $optParams = [
            'maxResults' => min($limit, 50),
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'q' => $query, // pencarian di title, description, attendees
            'timeMin' => $timeMin ? Carbon::parse($timeMin)->toRfc3339String() : now()->subDays(7)->toRfc3339String(),
        ];

        if ($timeMax) {
            $optParams['timeMax'] = Carbon::parse($timeMax)->toRfc3339String();
        }

        try {
            $results = $this->service->events->listEvents('primary', $optParams);

            $events = [];
            foreach ($results->getItems() as $event) {
                $events[] = $this->formatEvent($event);
            }

            return [
                'count' => count($events),
                'events' => $events,
            ];
        } catch (\Exception $e) {
            Log::error('Calendar API error: ' . $e->getMessage());
            return ['error' => 'Failed to fetch calendar events'];
        }
    }

    private function formatEvent(Event $event): array
    {
        $start = $event->start->dateTime ?? $event->start->date;
        $end = $event->end->dateTime ?? $event->end->date;

        return [
            'summary' => $event->getSummary() ?? '(No title)',
            'start' => Carbon::parse($start)->format('Y-m-d H:i'),
            'end' => Carbon::parse($end)->format('Y-m-d H:i'),
            'location' => $event->getLocation() ?? null,
            'description' => substr($event->getDescription() ?? '', 0, 300),
            'attendees' => collect($event->getAttendees() ?? [])->pluck('email')->toArray(),
            'hangoutLink' => $event->getHangoutLink() ?? null,
        ];
    }

    /**
     * Create a new calendar event
     */
    public function createEvent(array $params): array
    {
        $summary = $params['summary'] ?? 'New Meeting';
        $start = $params['start']; // ISO datetime, e.g. 2025-12-22T10:00:00+07:00
        $end = $params['end'];     // ISO datetime
        $attendees = $params['attendees'] ?? []; // array of email strings
        $description = $params['description'] ?? null;
        $location = $params['location'] ?? null;
        $conference = $params['conference'] ?? true; // auto create Google Meet link?

        try {
            $event = new \Google\Service\Calendar\Event([
                'summary' => $summary,
                'description' => $description,
                'location' => $location,
                'start' => [
                    'dateTime' => $start,
                    'timeZone' => 'Asia/Jakarta', // sesuaikan atau deteksi dari user
                ],
                'end' => [
                    'dateTime' => $end,
                    'timeZone' => 'Asia/Jakarta',
                ],
                'attendees' => array_map(fn($email) => ['email' => $email], $attendees),
                'conferenceData' => $conference ? [
                    'createRequest' => [
                        'requestId' => 'rnd-' . uniqid(),
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                    ],
                ] : null,
                'reminders' => [
                    'useDefault' => true,
                ],
            ]);

            $calendarId = 'primary';
            $event = $this->service->events->insert($calendarId, $event, [
                'conferenceDataVersion' => $conference ? 1 : 0,
                'sendUpdates' => 'all', // kirim email invite ke attendees
            ]);

            return [
                'success' => true,
                'event_id' => $event->id,
                'summary' => $event->summary,
                'start' => Carbon::parse($event->start->dateTime)->format('Y-m-d H:i'),
                'end' => Carbon::parse($event->end->dateTime)->format('Y-m-d H:i'),
                'hangoutLink' => $event->hangoutsLink ?? null,
                'htmlLink' => $event->htmlLink,
                'message' => "Event '{$summary}' berhasil dibuat!",
            ];

        } catch (\Exception $e) {
            Log::error('Create calendar event failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}