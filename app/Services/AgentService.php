<?php

namespace App\Services;

use App\Models\User;
use App\Models\Message;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\Parts\TextPart;
use Gemini\Resources\Parts\FunctionCallPart;
use Gemini\Resources\Parts\FunctionResponsePart;
use Illuminate\Support\Facades\Log;

class AgentService
{
    protected $user;
    protected $ragService;
    protected $gmailService;
    protected $hubspotService;
    protected $model = 'gemini-2.0-flash-exp'; // Fast and free!

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->ragService = new RAGService($user);
        
        if ($user->google_token) {
            $this->gmailService = new GmailService($user);
        }
        
        if ($user->hubspot_token) {
            $this->hubspotService = new HubspotService($user);
        }
    }

    /**
     * Get available tools for function calling (Gemini format)
     */
    protected function getTools(): array
    {
        return [
            'search_emails' => [
                'name' => 'search_emails',
                'description' => 'Search through the user\'s emails using semantic search. Use this when the user asks about email content, who said what, or any information that might be in emails.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query to find relevant emails',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            'search_contacts' => [
                'name' => 'search_contacts',
                'description' => 'Search through Hubspot CRM contacts. Use this when the user asks about clients, contact information, or people in their CRM.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query to find relevant contacts',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            'send_email' => [
                'name' => 'send_email',
                'description' => 'Send an email via Gmail. Use this when the user asks you to send an email or contact someone.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => [
                            'type' => 'string',
                            'description' => 'Recipient email address',
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Email subject line',
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'Email body content',
                        ],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
            ],
            'create_hubspot_contact' => [
                'name' => 'create_hubspot_contact',
                'description' => 'Create a new contact in Hubspot CRM.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'Contact email address',
                        ],
                        'firstname' => [
                            'type' => 'string',
                            'description' => 'First name',
                        ],
                        'lastname' => [
                            'type' => 'string',
                            'description' => 'Last name',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Phone number',
                        ],
                        'company' => [
                            'type' => 'string',
                            'description' => 'Company name',
                        ],
                    ],
                    'required' => ['email'],
                ],
            ],
            'add_contact_note' => [
                'name' => 'add_contact_note',
                'description' => 'Add a note to a Hubspot contact.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_email' => [
                            'type' => 'string',
                            'description' => 'Email of the contact to add note to',
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'The note content to add',
                        ],
                    ],
                    'required' => ['contact_email', 'note'],
                ],
            ],
        ];
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

    /**
     * Main chat method (Gemini version)
     */
    public function chat(string $userMessage): array
    {
        // Store user message
        Message::create([
            'user_id' => $this->user->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Get conversation history
        $history = Message::where('user_id', $this->user->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->toArray();

        // Build conversation for Gemini
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        // Call Gemini with function calling
        $response = $this->callGemini($contents);

        return $response;
    }

    /**
     * Call Gemini with function calling
     */
    protected function callGemini(array $contents, int $maxIterations = 5): array
    {
        $iterations = 0;
        $toolResults = [];
        $tools = $this->getTools();

        while ($iterations < $maxIterations) {
            $iterations++;

            try {
                $chat = Gemini::geminiPro()
                    ->startChat()
                    ->withTools(array_values($tools));

                // Send all history
                foreach ($contents as $content) {
                    $chat = $chat->sendMessage($content['parts'][0]['text']);
                }

                $result = $chat->getResponse();
                $text = $result->text();
                
                // Check for function calls
                $functionCalls = $result->functionCalls();

                if (empty($functionCalls)) {
                    // No function calls, we have final answer
                    Message::create([
                        'user_id' => $this->user->id,
                        'role' => 'assistant',
                        'content' => $text,
                        'metadata' => [
                            'tool_calls' => $toolResults,
                            'model' => $this->model,
                        ],
                    ]);

                    return [
                        'content' => $text,
                        'tool_calls' => $toolResults,
                    ];
                }

                // Execute function calls
                foreach ($functionCalls as $call) {
                    $toolName = $call->name;
                    $toolArgs = (array) $call->args;

                    $toolResult = $this->executeTool($toolName, $toolArgs);
                    $toolResults[] = [
                        'tool' => $toolName,
                        'result' => $toolResult,
                    ];

                    // Send function response back to Gemini
                    $chat->sendMessage([
                        'functionResponse' => [
                            'name' => $toolName,
                            'response' => $toolResult,
                        ],
                    ]);
                }

                // Get final response after function calls
                $finalResult = $chat->getResponse();
                $finalText = $finalResult->text();

                Message::create([
                    'user_id' => $this->user->id,
                    'role' => 'assistant',
                    'content' => $finalText,
                    'metadata' => [
                        'tool_calls' => $toolResults,
                        'model' => $this->model,
                    ],
                ]);

                return [
                    'content' => $finalText,
                    'tool_calls' => $toolResults,
                ];

            } catch (\Exception $e) {
                Log::error('Gemini API error: ' . $e->getMessage());
                
                return [
                    'content' => 'Sorry, I encountered an error: ' . $e->getMessage(),
                    'error' => true,
                ];
            }
        }

        return [
            'content' => 'I apologize, but I reached the maximum number of iterations.',
            'error' => true,
        ];
    }

    protected function getSystemPrompt(): string
    {
        return "You are an AI assistant for a financial advisor named {$this->user->name}.

You have access to their emails and Hubspot CRM contacts through various tools.

When answering questions:
1. Use search_emails or search_contacts to find information
2. Provide helpful, concise answers
3. If asked to perform actions, use the appropriate tools

Be proactive and use tools without asking for permission.";
    }
}