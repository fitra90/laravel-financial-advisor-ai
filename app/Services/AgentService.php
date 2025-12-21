<?php

namespace App\Services;

use App\Models\User;
use App\Models\Message;
use Gemini\Data\Content;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Tool;
use Gemini\Data\FunctionResponse;
use Gemini\Data\FunctionDeclaration;
use Gemini\Data\Part;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Log;

class AgentService
{
    protected $user;
    protected $ragService;
    protected $gmailService;
    protected $calendarService;
    protected $hubspotService;
    protected $model = 'gemini-2.0-flash'; // Fast and free!

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->ragService = new RAGService($user);
        
        if ($user->google_token) {
            $this->gmailService = new GmailService($user);
        }
        
        if ($user->google_token) {
            $this->calendarService = new CalendarService($user);
        }
        
        if ($user->hubspot_token) {
            $this->hubspotService = new HubspotService($user);
        }
    }

    /**
     * Get available tools for function calling (Gemini format)
     */
    protected function getTools(): Tool
    {
        return new Tool(
            functionDeclarations: [
                new FunctionDeclaration(
                    name: 'search_emails',
                    description: 'Search through the user\'s emails using semantic search. Use this when the user asks about email content, who said what, or any information that might be in emails.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'query' => new Schema(
                                type: DataType::STRING,
                                description: 'The search query to find relevant emails'
                            ),
                            'limit' => new Schema(
                                type: DataType::INTEGER,
                                description: 'Maximum number of results to return'
                            ),
                        ],
                        required: ['query']
                    )
                ),
                new FunctionDeclaration(
                    name: 'search_contacts',
                    description: 'Search through Hubspot CRM contacts. Use this when the user asks about clients, contact information, or people in their CRM.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'query' => new Schema(
                                type: DataType::STRING,
                                description: 'The search query to find relevant contacts'
                            ),
                            'limit' => new Schema(
                                type: DataType::INTEGER,
                                description: 'Maximum number of results to return'
                            ),
                        ],
                        required: ['query']
                    )
                ),
                new FunctionDeclaration(
                    name: 'send_email',
                    description: 'Send an email via Gmail. Use this when the user asks you to send an email or contact someone.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'to' => new Schema(
                                type: DataType::STRING,
                                description: 'Recipient email address'
                            ),
                            'subject' => new Schema(
                                type: DataType::STRING,
                                description: 'Email subject line'
                            ),
                            'body' => new Schema(
                                type: DataType::STRING,
                                description: 'Email body content'
                            ),
                        ],
                        required: ['to', 'subject', 'body']
                    )
                ),
                new FunctionDeclaration(
                    name: 'create_hubspot_contact',
                    description: 'Create a new contact in Hubspot CRM.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'email' => new Schema(
                                type: DataType::STRING,
                                description: 'Contact email address'
                            ),
                            'firstname' => new Schema(
                                type: DataType::STRING,
                                description: 'First name'
                            ),
                            'lastname' => new Schema(
                                type: DataType::STRING,
                                description: 'Last name'
                            ),
                            'phone' => new Schema(
                                type: DataType::STRING,
                                description: 'Phone number'
                            ),
                            'company' => new Schema(
                                type: DataType::STRING,
                                description: 'Company name'
                            ),
                        ],
                        required: ['email']
                    )
                ),
                new FunctionDeclaration(
                    name: 'add_contact_note',
                    description: 'Add a note to a Hubspot contact.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'contact_email' => new Schema(
                                type: DataType::STRING,
                                description: 'Email of the contact to add note to'
                            ),
                            'note' => new Schema(
                                type: DataType::STRING,
                                description: 'The note content to add'
                            ),
                        ],
                        required: ['contact_email', 'note']
                    )
                ),
                new FunctionDeclaration(
                    name: 'search_calendar_events',
                    description: 'Search for calendar events and meetings. Use this when the user asks about their schedule, meetings, appointments, or events with specific people.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'query' => new Schema(
                                type: DataType::STRING,
                                description: 'Search keyword: person name (e.g. "Jane Smith"), event title, or topic'
                            ),
                            'timeMin' => new Schema(
                                type: DataType::STRING,
                                nullable: true,
                                description: 'Start date/time in ISO format (e.g. 2025-12-21T00:00:00Z)'
                            ),
                            'timeMax' => new Schema(
                                type: DataType::STRING,
                                nullable: true,
                                description: 'End date/time in ISO format'
                            ),
                            'limit' => new Schema(
                                type: DataType::INTEGER,
                                nullable: true,
                                description: 'Maximum number of events to return (default 10)'
                            ),
                        ],
                        required: ['query']
                    )
                ),
                new FunctionDeclaration(
                    name: 'create_calendar_event',
                    description: 'Create a new meeting or event in the user\'s Google Calendar. Use this when the user asks to schedule, book, create, or add a meeting/event.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'summary' => new Schema(
                                type: DataType::STRING,
                                description: 'Event title or meeting name'
                            ),
                            'start' => new Schema(
                                type: DataType::STRING,
                                description: 'Start date and time in ISO format with timezone, e.g. 2025-12-22T10:00:00+07:00'
                            ),
                            'end' => new Schema(
                                type: DataType::STRING,
                                description: 'End date and time in ISO format with timezone, e.g. 2025-12-22T11:00:00+07:00'
                            ),
                            'attendees' => new Schema(
                                type: DataType::ARRAY,
                                items: new Schema(type: DataType::STRING),
                                nullable: true,
                                description: 'List of attendee email addresses'
                            ),
                            'description' => new Schema(
                                type: DataType::STRING,
                                nullable: true,
                                description: 'Optional event description or agenda'
                            ),
                            'location' => new Schema(
                                type: DataType::STRING,
                                nullable: true,
                                description: 'Optional location'
                            ),
                            'conference' => new Schema(
                                type: DataType::BOOLEAN,
                                nullable: true,
                                description: 'Create Google Meet link automatically? (default: true)'
                            ),
                        ],
                        required: ['summary', 'start', 'end']
                    )
                ),
            ]
        );

    }

    // Helper: ambil teks dari response (aman untuk complex parts)
    protected function extractText($response): string
    {
        $text = '';
        foreach ($response->parts() as $part) {
            if ($textPart = $part->text) {
                $text .= $textPart;
            }
        }
        return trim($text);
    }

    /**
     * Execute a tool function
     */
    protected function executeTool(string $toolName, array $arguments): array
    {
        Log::info("Executing tool: {$toolName}", $arguments);

        try {
            switch ($toolName) {
                case 'search_emails':
                    return $this->toolSearchEmails($arguments);
                    
                case 'search_contacts':
                    return $this->toolSearchContacts($arguments);
                    
                case 'send_email':
                    return $this->toolSendEmail($arguments);
                    
                case 'create_hubspot_contact':
                    return $this->toolCreateContact($arguments);

                case 'create_calendar_event':
                    return $this->toolCreateCalendarEvent($arguments);
                    
                case 'search_calendar_events':
                    return $this->toolSearchCalendarEvent($arguments);

                case 'add_contact_note':
                    return $this->toolAddContactNote($arguments);
                    
                default:
                    return ['error' => 'Unknown tool'];
            }
        } catch (\Exception $e) {
            Log::error("Tool execution failed: {$toolName} - " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Tool implementations (same as before)
     */
    protected function toolSearchEmails(array $args): array
    {
        $query = $args['query'];
        $limit = $args['limit'] ?? 5;

        $results = $this->ragService->searchEmails($query, $limit);

        if (empty($results)) {
            return ['message' => 'No relevant emails found'];
        }

        return [
            'count' => count($results),
            'emails' => array_map(function($email) {
                return [
                    'from' => $email['from_name'] . ' <' . $email['from_email'] . '>',
                    'subject' => $email['subject'],
                    'date' => $email['email_date'],
                    'preview' => mb_substr($email['body_text'], 0, 300),
                ];
            }, $results),
        ];
    }

    protected function toolSearchContacts(array $args): array
    {
        $query = $args['query'];
        $limit = $args['limit'] ?? 5;

        $results = $this->ragService->searchContacts($query, $limit);

        if (empty($results)) {
            return ['message' => 'No relevant contacts found'];
        }

        return [
            'count' => count($results),
            'contacts' => array_map(function($contact) {
                return [
                    'name' => trim($contact['first_name'] . ' ' . $contact['last_name']),
                    'email' => $contact['email'],
                    'phone' => $contact['phone'],
                    'company' => $contact['company'],
                    'notes' => mb_substr($contact['notes'] ?? '', 0, 200),
                ];
            }, $results),
        ];
    }

    protected function toolSendEmail(array $args): array
    {
        if (!$this->gmailService) {
            return ['error' => 'Gmail not connected'];
        }

        $result = $this->gmailService->sendEmail(
            $args['to'],
            $args['subject'],
            $args['body']
        );

        if ($result) {
            return ['success' => true, 'message' => "Email sent to {$args['to']}"];
        }

        return ['error' => 'Failed to send email'];
    }

    protected function toolCreateContact(array $args): array
    {
        if (!$this->hubspotService) {
            return ['error' => 'Hubspot not connected'];
        }

        $result = $this->hubspotService->createContact($args);

        if ($result) {
            return [
                'success' => true,
                'message' => "Contact created: {$args['email']}",
            ];
        }

        return ['error' => 'Failed to create contact'];
    }

    protected function toolAddContactNote(array $args): array
    {
        if (!$this->hubspotService) {
            return ['error' => 'Hubspot not connected'];
        }

        $contact = $this->hubspotService->searchContactByEmail($args['contact_email']);

        if (!$contact) {
            return ['error' => 'Contact not found'];
        }

        $contactId = $contact['id'];
        $result = $this->hubspotService->addNote($contactId, $args['note']);

        if ($result) {
            return [
                'success' => true,
                'message' => "Note added to {$args['contact_email']}",
            ];
        }

        return ['error' => 'Failed to add note'];
    }

    protected function toolSearchCalendarEvent(array $args): array
    {
        if (!$this->calendarService) {
            return ['error' => 'Calendar not connected'];
        }
        $results = $this->calendarService->searchEvents($args);
        return $results;
    }

    protected function toolCreateCalendarEvent(array $args): array
    {
        if (!$this->user->google_token) {
            return ['error' => 'Google account not connected'];
        }

        if (!$this->calendarService) {
            $this->calendarService = new CalendarService($this->user);
        }

        return $this->calendarService->createEvent($args);
    }

    public function chat(string $userMessage): array
    {
        try {
            // Ambil history chat (maksimal 10 pesan terakhir untuk konteks)
            $messages = Message::where('user_id', $this->user->id)
                ->latest()
                ->take(10)
                ->get()
                ->reverse();

            // Bangun history dalam format Gemini
            $history = [];
            foreach ($messages as $msg) {
                $history[] = Content::parse(
                    part: $msg->content,
                    role: $msg->role === 'assistant' ? Role::MODEL : Role::USER
                );
            }

            // Tambahkan system instruction
            $systemInstruction = Content::parse($this->getSystemPrompt());

            // Buat chat session dengan tools
            $chat = Gemini::generativeModel(model: $this->model)
                ->withSystemInstruction($systemInstruction)
                ->withTool($this->getTools())
                ->startChat(history: $history);

            // Kirim pesan user
            $response = $chat->sendMessage($userMessage);

            // Setelah $response = $chat->sendMessage($userMessage);

            $toolCalls = [];

            foreach ($response->parts() as $part) {
                if ($functionCall = $part->functionCall) {
                    $name = $functionCall->name;
                    $args = (array) $functionCall->args;

                    $result = $this->executeTool($name, $args);

                    $toolCalls[] = [
                        'tool' => $name,
                        'args' => $args,
                        'result' => $result,
                    ];

                    // Kirim function response
                    $chat->sendMessage([
                        new Part(
                            functionResponse: new FunctionResponse(
                                name: $name,
                                response: $result
                            )
                        )
                    ]);
                }
            }
            
            $finalResponse = $chat->sendMessage([]); // kirim array kosong → trigger final generation

            $finalText = $this->extractText($finalResponse);

            // Simpan respons assistant
            Message::create([
                'user_id' => $this->user->id,
                'role' => 'assistant',
                'content' => $finalText,
                'metadata' => [
                    'model' => $this->model,
                    'tool_calls' => $toolCalls,
                ],
            ]);

            return [
                'content' => $finalText ?: 'No response generated.',
                'tool_calls' => $toolCalls,
            ];

        } catch (\Exception $e) {
            Log::error('AgentService chat error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                // 'content' => 'Maaf, terjadi kesalahan teknis. Silakan coba lagi.',
                'content' => $e->getMessage(),
                'error' => true,
            ];
        }
    }

   

    /**
 * Detect if the message needs tools
 */
    protected function detectToolNeed(string $message): bool
    {
        $message = strtolower($message);
        
        // Keywords that indicate tool usage
        $toolKeywords = [
            // Search keywords
            'search', 'find', 'look for', 'show me',
            'who', 'what', 'where', 'when',
            
            // Email keywords  
            'email', 'mail', 'inbox', 'message', 'sent',

            // Calendar keywords
            'meeting', 'schedule', 'calendar', 'appointment', 'event', 'with', 'next',
            
            // Contact keywords
            'contact', 'client', 'customer', 'people',
            'hubspot', 'crm',
            
            // Action keywords
            'send', 'create', 'add', 'make',
        ];
        
        foreach ($toolKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        
        // If message is a question about their data, use tools
        if (preg_match('/\b(my|our)\b.*\b(email|contact|client)/i', $message)) {
            return true;
        }
        
        return false;
    }

    /**
     * Call Gemini with function calling
     */
    protected function callGemini(string $userMessage, array $history, int $maxIterations = 5): array
    {
        $iterations = 0;
        $toolResults = [];

        // 1. Setup model
        $model = Gemini::generativeModel(model: $this->model);

        // 2. Tambahkan tools jika diperlukan
        $needsTools = $this->detectToolNeed($userMessage);
        if ($needsTools) {
            $model = $model->withTool($this->getTools());
        }

        // 3. Start chat dengan history yang benar (pastikan $history adalah array of Content)
        $chat = $model->startChat(history: $history);

        // 4. Kirim pesan user pertama
        $response = $chat->sendMessage($userMessage);

        // 5. Loop function calling
        while ($iterations < $maxIterations) {
            $iterations++;

            // Deteksi apakah ada function call di respons terbaru
            $functionCalls = [];
            foreach ($response->parts() as $part) {
                if ($part->functionCall) {
                    $functionCalls[] = $part->functionCall;
                }
            }

            // Jika tidak ada function call lagi → keluar loop
            if (empty($functionCalls)) {
                break;
            }

            // Siapkan array of Part untuk function responses
            $functionResponseParts = [];

            foreach ($functionCalls as $call) {
                $toolName = $call->name;
                $toolArgs = (array) $call->args;

                $toolResult = $this->executeTool($toolName, $toolArgs);

                $toolResults[] = [
                    'tool' => $toolName,
                    'args' => $toolArgs,
                    'result' => $toolResult,
                ];

                // Buat Part yang berisi function response
                $functionResponseParts[] = new Part(
                    functionResponse: new FunctionResponse(
                        name: $toolName,
                        response: $toolResult // harus array/associative array
                    )
                );
            }

            // Kirim kembali function responses ke model
            $response = $chat->sendMessage($functionResponseParts);
        }

        // 6. Ekstrak teks akhir (aman untuk complex responses)
        $finalText = '';
        foreach ($response->parts() as $part) {
            if ($part->text) {
                $finalText .= $part->text;
            }
        }
        $finalText = trim($finalText);

        // 7. Simpan ke database
        if (!empty($finalText)) {
            Message::create([
                'user_id' => $this->user->id,
                'role' => 'assistant',
                'content' => $finalText,
                'metadata' => [
                    'tool_calls' => $toolResults,
                    'model' => $this->model,
                    'iterations' => $iterations,
                ],
            ]);
        }

        // 8. Return hasil
        return [
            'content' => $finalText ?: 'No response from model.',
            'tool_calls' => $toolResults,
        ];
    }

  
    protected function getSystemPrompt(): string
    {
        return "You are an AI assistant for a financial advisor named {$this->user->name}.

                You can:
                - Search emails (search_emails)
                - Search contacts (search_contacts)
                - Search calendar events (search_calendar_events)
                - CREATE new meetings/events (create_calendar_event)
                - Send emails, manage HubSpot contacts

                When user says:
                - 'buat meeting', 'schedule', 'book call', 'add event', 'set up meeting'
                → ALWAYS use create_calendar_event tool

                Be proactive. Parse dates/times intelligently (e.g. 'tomorrow 10am' → proper ISO).
                Always create Google Meet link unless user says otherwise.
                Confirm event creation with clear summary.";
    }
}