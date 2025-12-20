<?php

namespace App\Services;

use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HubspotService
{
    protected $user;
    protected $baseUrl = 'https://api.hubapi.com';

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get access token (refresh if needed)
     */
    protected function getAccessToken()
    {
        // Check if token is expired
        if ($this->user->hubspot_token_expires_at && 
            $this->user->hubspot_token_expires_at->isPast()) {
            return $this->refreshToken();
        }

        return $this->user->hubspot_token;
    }

    /**
     * Refresh the access token
     */
    protected function refreshToken()
    {
        try {
            $response = Http::asForm()->post('https://api.hubapi.com/oauth/v1/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.hubspot.client_id'),
                'client_secret' => config('services.hubspot.client_secret'),
                'refresh_token' => $this->user->hubspot_refresh_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->user->update([
                    'hubspot_token' => $data['access_token'],
                    'hubspot_refresh_token' => $data['refresh_token'] ?? $this->user->hubspot_refresh_token,
                    'hubspot_token_expires_at' => Carbon::now()->addSeconds($data['expires_in']),
                ]);

                return $data['access_token'];
            }

            throw new \Exception('Failed to refresh Hubspot token');
            
        } catch (\Exception $e) {
            Log::error('Hubspot token refresh failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all contacts
     */
    public function getContacts($limit = 100)
    {
        try {
            $token = $this->getAccessToken();
            
            $response = Http::withToken($token)->get("{$this->baseUrl}/crm/v3/objects/contacts", [
                'limit' => $limit,
                'properties' => 'firstname,lastname,email,phone,company',
            ]);

            if ($response->successful()) {
                return $response->json()['results'] ?? [];
            }

            Log::error('Hubspot getContacts failed: ' . $response->body());
            return [];
            
        } catch (\Exception $e) {
            Log::error('Hubspot getContacts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single contact by ID
     */
    public function getContact($contactId)
    {
        try {
            $token = $this->getAccessToken();
            
            $response = Http::withToken($token)->get(
                "{$this->baseUrl}/crm/v3/objects/contacts/{$contactId}",
                ['properties' => 'firstname,lastname,email,phone,company']
            );

            if ($response->successful()) {
                return $response->json();
            }

            return null;
            
        } catch (\Exception $e) {
            Log::error('Hubspot getContact error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new contact
     */
    public function createContact($data)
    {
        try {
            $token = $this->getAccessToken();
            
            $properties = [];
            
            if (isset($data['email'])) $properties['email'] = $data['email'];
            if (isset($data['firstname'])) $properties['firstname'] = $data['firstname'];
            if (isset($data['lastname'])) $properties['lastname'] = $data['lastname'];
            if (isset($data['phone'])) $properties['phone'] = $data['phone'];
            if (isset($data['company'])) $properties['company'] = $data['company'];

            $response = Http::withToken($token)->post("{$this->baseUrl}/crm/v3/objects/contacts", [
                'properties' => $properties,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Hubspot createContact failed: ' . $response->body());
            return null;
            
        } catch (\Exception $e) {
            Log::error('Hubspot createContact error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a contact
     */
    public function updateContact($contactId, $data)
    {
        try {
            $token = $this->getAccessToken();
            
            $properties = [];
            
            if (isset($data['email'])) $properties['email'] = $data['email'];
            if (isset($data['firstname'])) $properties['firstname'] = $data['firstname'];
            if (isset($data['lastname'])) $properties['lastname'] = $data['lastname'];
            if (isset($data['phone'])) $properties['phone'] = $data['phone'];
            if (isset($data['company'])) $properties['company'] = $data['company'];

            $response = Http::withToken($token)->patch(
                "{$this->baseUrl}/crm/v3/objects/contacts/{$contactId}",
                ['properties' => $properties]
            );

            if ($response->successful()) {
                return $response->json();
            }

            return null;
            
        } catch (\Exception $e) {
            Log::error('Hubspot updateContact error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Search contacts by email
     */
    public function searchContactByEmail($email)
    {
        try {
            $token = $this->getAccessToken();
            
            $response = Http::withToken($token)->post("{$this->baseUrl}/crm/v3/objects/contacts/search", [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email,
                            ],
                        ],
                    ],
                ],
                'properties' => ['firstname', 'lastname', 'email', 'phone', 'company'],
            ]);

            if ($response->successful()) {
                $results = $response->json()['results'] ?? [];
                return $results[0] ?? null;
            }

            return null;
            
        } catch (\Exception $e) {
            Log::error('Hubspot searchContactByEmail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add a note to a contact
     */
    public function addNote($contactId, $noteText)
    {
        try {
            $token = $this->getAccessToken();
            
            // First, create the note
            $noteResponse = Http::withToken($token)->post("{$this->baseUrl}/crm/v3/objects/notes", [
                'properties' => [
                    'hs_note_body' => $noteText,
                    'hs_timestamp' => now()->timestamp * 1000, // Hubspot uses milliseconds
                ],
            ]);

            if (!$noteResponse->successful()) {
                Log::error('Failed to create note: ' . $noteResponse->body());
                return null;
            }

            $noteId = $noteResponse->json()['id'];

            // Associate the note with the contact
            $associationResponse = Http::withToken($token)->put(
                "{$this->baseUrl}/crm/v3/objects/notes/{$noteId}/associations/contacts/{$contactId}/note_to_contact"
            );

            if ($associationResponse->successful()) {
                return $noteResponse->json();
            }

            return null;
            
        } catch (\Exception $e) {
            Log::error('Hubspot addNote error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get notes for a contact
     */
    public function getContactNotes($contactId)
    {
        try {
            $token = $this->getAccessToken();
            
            $response = Http::withToken($token)->get(
                "{$this->baseUrl}/crm/v3/objects/contacts/{$contactId}/associations/notes"
            );

            if ($response->successful()) {
                $noteIds = collect($response->json()['results'] ?? [])->pluck('id');
                
                if ($noteIds->isEmpty()) {
                    return [];
                }

                // Fetch note details
                $notesResponse = Http::withToken($token)->post("{$this->baseUrl}/crm/v3/objects/notes/batch/read", [
                    'properties' => ['hs_note_body', 'hs_timestamp'],
                    'inputs' => $noteIds->map(fn($id) => ['id' => $id])->toArray(),
                ]);

                if ($notesResponse->successful()) {
                    return $notesResponse->json()['results'] ?? [];
                }
            }

            return [];
            
        } catch (\Exception $e) {
            Log::error('Hubspot getContactNotes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync contacts to local database
     */
    public function syncContactsToDatabase()
    {
        try {
            $hubspotContacts = $this->getContacts(100);
            $synced = 0;

            foreach ($hubspotContacts as $hContact) {
                $properties = $hContact['properties'] ?? [];
                
                Contact::updateOrCreate(
                    [
                        'user_id' => $this->user->id,
                        'hubspot_id' => $hContact['id'],
                    ],
                    [
                        'email' => $properties['email'] ?? null,
                        'first_name' => $properties['firstname'] ?? null,
                        'last_name' => $properties['lastname'] ?? null,
                        'phone' => $properties['phone'] ?? null,
                        'company' => $properties['company'] ?? null,
                        'properties' => $properties,
                    ]
                );
                
                $synced++;
            }

            Log::info("Synced {$synced} contacts from Hubspot for user {$this->user->id}");
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Hubspot syncContactsToDatabase error: ' . $e->getMessage());
            return 0;
        }
    }
}