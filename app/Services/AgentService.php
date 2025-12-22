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
    protected $model = 'gemini-2.5-flash-lite'; // Fast and free!

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->ragService = new RAGService($user);
        
        if ($user->google_token) {
            $this->gmailService = new GmailService($user);
        }
        
        if ($user->google_token) {
            $this->calendarService = new GoogleCalendarService($user);
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
                    name: 'get_all_contacts',
                    description: 'Get all contacts with their email addresses. Use this when user wants to invite multiple people to a meeting or needs to see all available contacts.',
                    parameters: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'limit' => new Schema(
                                type: DataType::INTEGER,
                                nullable: true,
                                description: 'Maximum number of contacts to return (default 50)'
                            ),
                        ],
                        required: []
                    )
                ),
                new FunctionDeclaration(
                    name: 'search_contacts',
                    description: 'Search through Hubspot CRM contacts by name, email, company, or any search term. Use this when the user asks about specific clients, contact information, or people in their CRM. For getting ALL contacts at once (e.g., for inviting to meetings), use get_all_contacts instead.',
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

    protected function extractText($response): string
    {
        if (!$response) {
            return '';
        }

        try {
            $text = '';
            
            // Handle if response is already a string
            if (is_string($response)) {
                return trim($response);
            }

            // Try to get parts (for GenerateContentResponse objects)
            if (method_exists($response, 'parts')) {
                foreach ($response->parts() as $part) {
                    if (isset($part->text) && is_string($part->text)) {
                        $text .= $part->text;
                    }
                }
            }
            
            // Try to get text directly from candidate
            if (empty($text) && method_exists($response, 'candidates') && !empty($response->candidates())) {
                $candidate = $response->candidates()[0];
                if (method_exists($candidate, 'content') && $candidate->content()) {
                    foreach ($candidate->content()->parts() as $part) {
                        if (isset($part->text) && is_string($part->text)) {
                            $text .= $part->text;
                        }
                    }
                }
            }

            // Try to get text directly (legacy method)
            if (empty($text) && method_exists($response, 'text')) {
                $text = $response->text();
            }

            return trim($text);
        } catch (\Exception $e) {
            Log::error('Error in extractText', [
                'error' => $e->getMessage(),
                'response_type' => get_class($response)
            ]);
            return '';
        }
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
                
                case 'get_all_contacts':
                    return $this->toolGetAllContacts($arguments);
                
                case 'search_calendar_events':
                    // return $this->toolSearchCalendarEvent($arguments);
                    $result = $this->toolSearchCalendarEvent($arguments);
                    if (!is_array($result)) {
                        $result = ['error' => 'Invalid response format'];
                    }

                    return $result; // Always array[file:1]

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

    protected function toolGetAllContacts(array $args): array
    {
        $limit = $args['limit'] ?? 50;
        
        // Get contacts from RAG service or directly from database
        $results = $this->ragService->getAllContacts($limit);
        
        // Or if RAGService doesn't have getAllContacts, do direct query:
        // $contacts = \App\Models\Contact::where('user_id', $this->user->id)
        //     ->limit($limit)
        //     ->get()
        //     ->toArray();

        if (empty($results)) {
            return ['message' => 'No contacts found'];
        }

        return [
            'count' => count($results),
            'contacts' => array_map(function($contact) {
                return [
                    'name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
                    'email' => $contact['email'] ?? '',
                    'company' => $contact['company'] ?? '',
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
        if (empty($results) || !is_array($results)) {
            return ['message' => 'No meetings found matching your query', 'count' => 0, 'events' => []];
        }

        return $results; // Ensure top-level array[file:1]
    }

    protected function toolCreateCalendarEvent(array $args): array
    {
        if (!$this->user->google_token) {
            return ['error' => 'Google account not connected'];
        }

        if (!$this->calendarService) {
            $this->calendarService = new GoogleCalendarService($this->user);
        }

        return $this->calendarService->createEvent($args);
    }

    public function chat(string $userMessage): array
    {
        try {
            // Get chat history (max 10 recent messages for context)
            $messages = Message::where('user_id', $this->user->id)
                ->latest()
                ->take(10)
                ->get()
                ->reverse();

            // Build history in Gemini format - FIXED
            $history = [];
            foreach ($messages as $msg) {
                // Determine role correctly
                $role = $msg->role === 'assistant' ? Role::MODEL : Role::USER;
                
                $history[] = Content::parse(
                    $msg->content,  // part (positional)
                    $role           // role (positional)
                );
            }

            // Add system instruction - FIXED
            $systemInstruction = Content::parse($this->getSystemPrompt());

            // Create chat session with tools
            $chat = Gemini::generativeModel(model: $this->model)
                ->withSystemInstruction($systemInstruction)
                ->withTool($this->getTools())
                ->startChat(history: $history);

            // Send user message
            $response = $chat->sendMessage($userMessage);

            $toolCalls = [];
            $maxIterations = 5;
            $iterations = 0;

            // Handle function calling loop
            while ($iterations < $maxIterations) {
                $iterations++;
                
                $hasFunctionCalls = false;

                // Check for function calls in response
                try {
                    if (!$response || !method_exists($response, 'parts')) {
                        break;
                    }

                    foreach ($response->parts() as $part) {
                        if ($functionCall = $part->functionCall) {
                            $hasFunctionCalls = true;
                            $name = $functionCall->name;
                            $args = (array) $functionCall->args;

                            Log::info("Executing function: {$name}", ['args' => $args]);

                            // Execute the tool
                            $result = $this->executeTool($name, $args);
                            
                            // Ensure result is a simple array (important for Gemini SDK)
                            if (!is_array($result)) {
                                $result = ['result' => (string)$result];
                            }

                            $toolCalls[] = [
                                'tool' => $name,
                                'args' => $args,
                                'result' => $result,
                            ];

                            // Send function response
                            try {
                                // Create FunctionResponse Part
                                $functionResponsePart = new Part(
                                    functionResponse: new FunctionResponse(
                                        name: $name,
                                        response: $result
                                    )
                                );
                                
                                // Send function response - WRAP IN CONTENT
                                $functionResponseContent = new Content(
                                    parts: [$functionResponsePart],
                                    role: Role::USER
                                );
                                
                                $response = $chat->sendMessage($functionResponseContent);
                            } catch (\Exception $e) {
                                Log::error('Error sending function response', [
                                    'function' => $name,
                                    'error' => $e->getMessage(),
                                    'result_type' => gettype($result),
                                ]);
                                throw $e;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error in function calling loop', [
                        'error' => $e->getMessage(),
                        'iteration' => $iterations
                    ]);
                    
                    // Generate fallback response
                    $fallbackText = $this->generateFallbackResponse($toolCalls);
                    
                    Message::create([
                        'user_id' => $this->user->id,
                        'role' => 'assistant',
                        'content' => $fallbackText,
                        'metadata' => [
                            'model' => $this->model,
                            'tool_calls' => $toolCalls,
                            'error' => $e->getMessage(),
                        ],
                    ]);

                    return [
                        'content' => $fallbackText,
                        'tool_calls' => $toolCalls,
                    ];
                }

                // If no function calls, we're done
                if (!$hasFunctionCalls) {
                    break;
                }
            }

            // Extract final text from the last response
            $finalText = '';
            try {
                $finalText = $this->extractText($response);
            } catch (\Exception $e) {
                Log::error('Error extracting text from response', [
                    'error' => $e->getMessage()
                ]);
            }

            // If still empty after tool calls, use fallback
            if (empty($finalText) && !empty($toolCalls)) {
                $finalText = $this->generateFallbackResponse($toolCalls);
            }

            // If completely empty
            if (empty($finalText)) {
                $finalText = 'I apologize, but I was unable to generate a proper response. Please try rephrasing your question.';
            }

            // Save assistant response
            Message::create([
                'user_id' => $this->user->id,
                'role' => 'assistant',
                'content' => $finalText,
                'metadata' => [
                    'model' => $this->model,
                    'tool_calls' => $toolCalls,
                    'iterations' => $iterations,
                ],
            ]);

            return [
                'content' => $finalText,
                'tool_calls' => $toolCalls,
            ];

        } catch (\Exception $e) {
            Log::error('AgentService chat error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'content' => 'Sorry, a technical error occurred: ' . $e->getMessage(),
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a fallback response when AI fails to respond
     */
    protected function generateFallbackResponse(array $toolCalls): string
    {
        if (empty($toolCalls)) {
            return "I encountered an issue processing your request. Please try again.";
        }

        $response = "Here's what I found:\n\n";

        foreach ($toolCalls as $call) {
            $toolName = $call['tool'];
            $result = $call['result'];

            switch ($toolName) {
                case 'search_calendar_events':
                    if (isset($result['events']) && !empty($result['events'])) {
                        $response .= "📅 Calendar Events:\n";
                        foreach ($result['events'] as $event) {
                            $response .= "• {$event['title']} on {$event['start']}\n";
                            if (!empty($event['attendees'])) {
                                $response .= "  Attendees: " . implode(', ', $event['attendees']) . "\n";
                            }
                        }
                    } else {
                        $response .= "No calendar events found.\n";
                    }
                    break;

                case 'search_emails':
                    if (isset($result['emails']) && !empty($result['emails'])) {
                        $response .= "📧 Emails ({$result['count']} found):\n";
                        foreach ($result['emails'] as $email) {
                            $response .= "• From {$email['from']}: {$email['subject']}\n";
                        }
                    } else {
                        $response .= "No emails found.\n";
                    }
                    break;

                case 'search_contacts':
                    if (isset($result['contacts']) && !empty($result['contacts'])) {
                        $response .= "👤 Contacts ({$result['count']} found):\n";
                        foreach ($result['contacts'] as $contact) {
                            $response .= "• {$contact['name']} - {$contact['email']}\n";
                        }
                    } else {
                        $response .= "No contacts found.\n";
                    }
                    break;

                case 'send_email':
                    if (isset($result['success']) && $result['success']) {
                        $response .= "✅ {$result['message']}\n";
                    } else {
                        $response .= "❌ Failed to send email.\n";
                    }
                    break;

                case 'create_calendar_event':
                    if (isset($result['success']) && $result['success']) {
                        $response .= "✅ {$result['message']}\n";
                        if (isset($result['event']['meet_link'])) {
                            $response .= "🔗 Meet link: {$result['event']['meet_link']}\n";
                        }
                    } else {
                        $response .= "❌ Failed to create event.\n";
                    }
                    break;

                default:
                    if (isset($result['error'])) {
                        $response .= "❌ Error: {$result['error']}\n";
                    } elseif (isset($result['message'])) {
                        $response .= "ℹ️ {$result['message']}\n";
                    }
            }

            $response .= "\n";
        }

        return trim($response);
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
                You have access to these tools:
                - search_emails: Find information in emails
                - search_contacts: Find client/contact information by name, company, or any search term
                - get_all_contacts: Get ALL contacts with their email addresses (use this when user wants to see all contacts or invite multiple people)
                - search_calendar_events: Find meetings and appointments
                - create_calendar_event: Schedule new meetings/events
                - send_email: Send emails
                - create_hubspot_contact: Add new CRM contacts
                - add_contact_note: Add notes to contacts

                IMPORTANT WORKFLOW INSTRUCTIONS:
                
                1. When user asks to schedule a meeting or create an event:
                - FIRST use 'get_all_contacts' to see available contacts
                - Then ask user to select which contacts to invite
                - OR use 'search_contacts' if they mention specific names
                
                2. For calendar queries:
                - When user asks 'when is my meeting with X?' → Use search_calendar_events with query='X'
                - When user says 'schedule meeting', 'book call', 'create event' → Use create_calendar_event
                
                3. Proactive behavior:
                - If user mentions multiple people, automatically get all contacts first
                - Suggest specific contacts based on context
                - Ask clarifying questions: 'Who would you like to invite? I can show you all available contacts.'

                Current date: " . now()->toDateString() . "
                Current time: " . now()->toTimeString() . "

                Be helpful, proactive, and provide clear summaries. Always suggest next steps.";
    }
}