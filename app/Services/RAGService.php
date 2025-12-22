<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class RAGService
{
    protected $user;
    protected $embeddingService;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->embeddingService = new EmbeddingService();
    }

    /**
     * Get relevant context for a query
     */
    public function getContext(string $query, array $options = []): array
    {
        $emailLimit = $options['email_limit'] ?? 5;
        $contactLimit = $options['contact_limit'] ?? 3;
        $calendarLimit = $options['calendar_limit'] ?? 5;

        $context = [
            'emails' => [],
            'contacts' => [],
            'events' => [],
            'summary' => '',
        ];

        // Search emails
        $emails = $this->embeddingService->searchSimilarEmails(
            $query, 
            $this->user->id, 
            $emailLimit
        );

        // Search contacts
        $contacts = $this->embeddingService->searchSimilarContacts(
            $query, 
            $this->user->id, 
            $contactLimit
        );

        // Search calendar events
        $events = $this->embeddingService->searchSimilarEvents(
            $query,
            $this->user->id,
            $calendarLimit
        );

        $context['emails'] = $emails;
        $context['contacts'] = $contacts;
        $context['events'] = $events;
        $context['summary'] = $this->formatContext($emails, $contacts, $events);

        return $context;
    }

    /**
     * Format context for LLM
     */
    protected function formatContext(array $emails, array $contacts, array $events = []): string
    {
        $parts = [];

        // Add emails
        if (!empty($emails)) {
            $parts[] = "=== RELEVANT EMAILS ===\n";
            
            foreach ($emails as $i => $email) {
                $num = $i + 1;
                $parts[] = "Email {$num}:";
                $parts[] = "From: {$email['from_name']} <{$email['from_email']}>";
                $parts[] = "Subject: {$email['subject']}";
                $parts[] = "Date: {$email['email_date']}";
                $parts[] = "Content: " . mb_substr($email['body_text'], 0, 500);
                $parts[] = "---\n";
            }
        }

        // Add contacts
        if (!empty($contacts)) {
            $parts[] = "\n=== RELEVANT CONTACTS ===\n";
            
            foreach ($contacts as $i => $contact) {
                $num = $i + 1;
                $parts[] = "Contact {$num}:";
                $parts[] = "Name: {$contact['first_name']} {$contact['last_name']}";
                
                if ($contact['email']) {
                    $parts[] = "Email: {$contact['email']}";
                }
                
                if ($contact['phone']) {
                    $parts[] = "Phone: {$contact['phone']}";
                }
                
                if ($contact['company']) {
                    $parts[] = "Company: {$contact['company']}";
                }
                
                if ($contact['notes']) {
                    $parts[] = "Notes: " . mb_substr($contact['notes'], 0, 300);
                }
                
                $parts[] = "---\n";
            }
        }

        // Add calendar events
        if (!empty($events)) {
            $parts[] = "\n=== RELEVANT CALENDAR EVENTS ===\n";
            
            foreach ($events as $i => $event) {
                $num = $i + 1;
                $parts[] = "Event {$num}:";
                $parts[] = "Title: {$event['summary']}";
                $parts[] = "Start: {$event['start_datetime']}";
                $parts[] = "End: {$event['end_datetime']}";
                
                if (!empty($event['location'])) {
                    $parts[] = "Location: {$event['location']}";
                }
                
                if (!empty($event['attendees'])) {
                    $attendeeList = is_array($event['attendees']) 
                        ? implode(', ', $event['attendees'])
                        : $event['attendees'];
                    $parts[] = "Attendees: {$attendeeList}";
                }
                
                if (!empty($event['description'])) {
                    $parts[] = "Description: " . mb_substr($event['description'], 0, 300);
                }
                
                $parts[] = "---\n";
            }
        }

        if (empty($emails) && empty($contacts) && empty($events)) {
            return "No relevant information found in emails, contacts, or calendar.";
        }

        return implode("\n", $parts);
    }

    /**
     * Search specifically for emails
     */
    public function searchEmails(string $query, int $limit = 10): array
    {
        return $this->embeddingService->searchSimilarEmails(
            $query,
            $this->user->id,
            $limit
        );
    }

    /**
     * Search specifically for contacts
     */
    public function searchContacts(string $query, int $limit = 10): array
    {
        return $this->embeddingService->searchSimilarContacts(
            $query,
            $this->user->id,
            $limit
        );
    }

    /**
     * Search specifically for calendar events
     */
    public function searchEvents(string $query, int $limit = 10): array
    {
        return $this->embeddingService->searchSimilarEvents(
            $query,
            $this->user->id,
            $limit
        );
    }

    /**
     * Search events by date range
     */
    public function searchEventsByDateRange(string $startDate, string $endDate, int $limit = 50): array
    {
        return $this->embeddingService->searchEventsByDateRange(
            $this->user->id,
            $startDate,
            $endDate,
            $limit
        );
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(int $days = 7, int $limit = 20): array
    {
        $startDate = now()->format('Y-m-d H:i:s');
        $endDate = now()->addDays($days)->format('Y-m-d H:i:s');
        
        return $this->searchEventsByDateRange($startDate, $endDate, $limit);
    }

    /**
     * Get context formatted for display
     */
    public function getContextForDisplay(string $query): array
    {
        $context = $this->getContext($query);
        
        return [
            'emails' => array_map(function($email) {
                return [
                    'from' => $email['from_name'] . ' <' . $email['from_email'] . '>',
                    'subject' => $email['subject'],
                    'date' => $email['email_date'],
                    'preview' => mb_substr($email['body_text'], 0, 200) . '...',
                    'relevance' => round((1 - $email['distance']) * 100, 1) . '%',
                ];
            }, $context['emails']),
            
            'contacts' => array_map(function($contact) {
                return [
                    'name' => trim($contact['first_name'] . ' ' . $contact['last_name']),
                    'email' => $contact['email'],
                    'company' => $contact['company'],
                    'relevance' => round((1 - $contact['distance']) * 100, 1) . '%',
                ];
            }, $context['contacts']),

            'events' => array_map(function($event) {
                return [
                    'title' => $event['summary'],
                    'start' => $event['start_datetime'],
                    'end' => $event['end_datetime'],
                    'location' => $event['location'] ?? null,
                    'attendees' => $event['attendees'] ?? [],
                    'relevance' => round((1 - $event['distance']) * 100, 1) . '%',
                ];
            }, $context['events']),
        ];
    }
}