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

        $context = [
            'emails' => [],
            'contacts' => [],
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

        $context['emails'] = $emails;
        $context['contacts'] = $contacts;
        $context['summary'] = $this->formatContext($emails, $contacts);

        return $context;
    }

    /**
     * Format context for LLM
     */
    protected function formatContext(array $emails, array $contacts): string
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

        if (empty($emails) && empty($contacts)) {
            return "No relevant information found in emails or contacts.";
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
        ];
    }
}