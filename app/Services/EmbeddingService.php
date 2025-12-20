<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Contact;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    // Note: The most current recommended model is 'text-embedding-004'
    protected string $model = 'text-embedding-004'; 
    protected int $dimensions = 768; // The dimensionality for text-embedding-004

    /**
     * Generate embedding for text
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            // Clean and truncate text
            $text = $this->cleanText($text);
            
            // 1. Check if the text is empty after cleaning
            if (empty($text)) {
                return null;
            }

            // Gemini embedding
            $response = Gemini::embeddingModel(model: $this->model)
                ->embedContent(content: $text);
            
            // FIX: The response for embedContent returns a single embedding object.
            // We need to access the 'values' property on that object.
            $embedding = $response->embedding->values ?? null;
            
            if (!$embedding) {
                throw new \Exception('No embedding returned from Gemini API.');
            }

            // Ensure the embedding has the correct dimensionality
            if (count($embedding) !== $this->dimensions) {
                Log::warning("Embedding dimension mismatch. Expected: {$this->dimensions}, Got: " . count($embedding));
            }

            return $embedding;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean text for embedding
     */
    protected function cleanText(string $text): string
    {
        // Replace multiple whitespaces with a single space
        $text = preg_replace('/\s+/', ' ', $text);
        // Strip HTML tags
        $text = strip_tags($text);
        // Trim leading/trailing whitespace
        $text = trim($text);
        
        // Truncate to a safe limit. text-embedding-004 has a 2048 token limit. 
        // 5000 characters is a safe approximation for text content.
        return mb_substr($text, 0, 5000);
    }

    /**
     * Embed an email and store it
     */
    public function embedEmail(Email $email): bool
    {
        try {
            $text = $this->prepareEmailText($email);
            $embedding = $this->generateEmbedding($text);
            
            if (!$embedding) {
                Log::warning("Skipping email {$email->id} due to no embedding generated.");
                return false;
            }

            // Prepare the vector string format for PostgreSQL's 'vector' type
            $vectorString = '[' . implode(',', $embedding) . ']';
            
            // Use DB::update for clarity, though DB::statement works
            $updated = DB::update(
                "UPDATE emails SET embedding = ?::vector WHERE id = ?",
                [$vectorString, $email->id]
            );

            if ($updated) {
                Log::info("Embedded email {$email->id}");
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("Failed to embed email {$email->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare email text for embedding
     */
    protected function prepareEmailText(Email $email): string
    {
        // ... (Original logic is fine)
        $parts = [];
        
        if ($email->from_name) {
            $parts[] = "From: {$email->from_name}";
        }
        
        $parts[] = "Email: {$email->from_email}";
        
        if ($email->subject) {
            $parts[] = "Subject: {$email->subject}";
        }
        
        if ($email->body_text) {
            $parts[] = "Body: {$email->body_text}";
        }
        
        if ($email->email_date) {
            // Using a specific format is good for consistency
            $parts[] = "Date: {$email->email_date->format('Y-m-d H:i:s')}"; 
        }
        
        return implode("\n", $parts);
    }

    /**
     * Embed a contact and store it
     */
    public function embedContact(Contact $contact): bool
    {
        try {
            $text = $this->prepareContactText($contact);
            $embedding = $this->generateEmbedding($text);
            
            if (!$embedding) {
                 Log::warning("Skipping contact {$contact->id} due to no embedding generated.");
                return false;
            }

            $vectorString = '[' . implode(',', $embedding) . ']';
            
            DB::update(
                "UPDATE contacts SET embedding = ?::vector WHERE id = ?",
                [$vectorString, $contact->id]
            );

            Log::info("Embedded contact {$contact->id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to embed contact {$contact->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare contact text for embedding
     */
    protected function prepareContactText(Contact $contact): string
    {
        // ... (Original logic is fine)
        $parts = [];
        
        if ($contact->first_name || $contact->last_name) {
            $parts[] = "Name: {$contact->first_name} {$contact->last_name}";
        }
        
        if ($contact->email) {
            $parts[] = "Email: {$contact->email}";
        }
        
        if ($contact->phone) {
            $parts[] = "Phone: {$contact->phone}";
        }
        
        if ($contact->company) {
            $parts[] = "Company: {$contact->company}";
        }
        
        if ($contact->notes) {
            $parts[] = "Notes: {$contact->notes}";
        }
        
        return implode("\n", $parts);
    }

    /**
     * Batch embed all emails for a user
     */
    public function embedAllEmails(int $userId): int
    {
        // It's generally better to use chunking for large datasets
        $emails = Email::where('user_id', $userId)
            ->whereNull('embedding')
            ->cursor(); // Use cursor for memory efficiency

        $embedded = 0;

        foreach ($emails as $email) {
            if ($this->embedEmail($email)) {
                $embedded++;
            }
            
            // This rate limiting is crucial for preventing 429 errors.
            sleep(4); 
        }

        Log::info("Embedded {$embedded} emails for user {$userId}");
        return $embedded;
    }

    /**
     * Batch embed all contacts for a user
     */
    public function embedAllContacts(int $userId): int
    {
        $contacts = Contact::where('user_id', $userId)
            ->whereNull('embedding')
            ->cursor(); // Use cursor for memory efficiency

        $embedded = 0;

        foreach ($contacts as $contact) {
            if ($this->embedContact($contact)) {
                $embedded++;
            }
            
            sleep(4); // Rate limit
        }

        Log::info("Embedded {$embedded} contacts for user {$userId}");
        return $embedded;
    }

    /**
     * Search similar emails using cosine similarity
     */
    public function searchSimilarEmails(string $query, int $userId, int $limit = 5): array
    {
        try {
            $embedding = $this->generateEmbedding($query);
            
            if (!$embedding) {
                return [];
            }

            $vectorString = '[' . implode(',', $embedding) . ']';

            // The 'embedding <=> ?::vector' operator is correct for pgvector 
            // and computes the **distance**. The lower the distance, the more similar.
            $results = DB::select("
                SELECT 
                    id, 
                    from_email, 
                    from_name,
                    subject, 
                    body_text,
                    email_date,
                    -- Renaming for clarity: distance is 1 - cosine_similarity
                    (embedding <=> ?::vector) as distance
                FROM emails
                WHERE user_id = ?
                    AND embedding IS NOT NULL
                ORDER BY distance ASC
                LIMIT ?
            ", [$vectorString, $userId, $limit]);

            return array_map(fn($row) => (array) $row, $results);
            
        } catch (\Exception $e) {
            Log::error('Search emails failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search similar contacts using cosine similarity
     */
    public function searchSimilarContacts(string $query, int $userId, int $limit = 5): array
    {
        try {
            $embedding = $this->generateEmbedding($query);
            
            if (!$embedding) {
                return [];
            }

            $vectorString = '[' . implode(',', $embedding) . ']';

            $results = DB::select("
                SELECT 
                    id,
                    hubspot_id,
                    email,
                    first_name,
                    last_name,
                    phone,
                    company,
                    notes,
                    (embedding <=> ?::vector) as distance
                FROM contacts
                WHERE user_id = ?
                    AND embedding IS NOT NULL
                ORDER BY distance ASC
                LIMIT ?
            ", [$vectorString, $userId, $limit]);

            return array_map(fn($row) => (array) $row, $results);
            
        } catch (\Exception $e) {
            Log::error('Search contacts failed: ' . $e->getMessage());
            return [];
        }
    }
}