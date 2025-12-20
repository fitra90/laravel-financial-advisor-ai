<?php

namespace App\Services;

use App\Models\User;
use App\Models\Message;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\Parts\TextPart;
use Gemini\Resources\Parts\FunctionCallPart;
use Gemini\Resources\Parts\FunctionResponsePart;
use Gemini\Data\Tool;  
use Gemini\Data\FunctionDeclaration;
use Gemini\Data\Schema;
use Gemini\Data\ToolConfig;
use Gemini\Enums\DataType;
use Illuminate\Support\Facades\Log;

class AgentService
{
    protected $user;
    protected $ragService;
    protected $gmailService;
    protected $hubspotService;
    protected $model = 'gemini-2.5-flash-lite'; // Fast and free!

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
            ]
        );

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
        $response = $this->callGemini($contents, $userMessage);

        return $response;
    }

    /**
     * Call Gemini with function calling
     */
    protected function callGemini(array $contents, string $userMessage, int $maxIterations = 5): array
    {
        $iterations = 0;
        $toolResults = [];

        while ($iterations < $maxIterations) {
            $iterations++;

            try {
            
                $model = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')
                            ->withTool($this->getTools());
                            
                $chat = $model->startChat();

                //    Send all history (the way you're sending history looks slightly unusual, 
                //    but assuming $contents is structured correctly for historical messages
                //    it will work, though typically you'd send one final message).
                foreach ($contents as $content) {
                    $chat->sendMessage($content['parts'][0]['text']);
                }

               // Get last user message
                $lastMessage = end($contents);
                $userText = $lastMessage['parts'][0]['text'] ?? '';

                if (empty($userText)) {
                    throw new \Exception('No user message found');
                }

                $result = $chat->sendMessage($userText);
                $text = $result->text();
                
                // // Check for function calls

                $parts = $result->parts();

                $functionCall = collect($parts)->firstWhere('functionCall');

                if ($functionCall) {
                    $name = $functionCall->functionCall->name;
                    $args = (array) $functionCall->functionCall->args;

                    // Now execute your tool
                    $toolResult = $this->executeTool($name, $args);

                    // Send back function response
                    $chat->sendMessage([
                        'role' => 'tool',
                        'parts' => [[
                            'functionResponse' => [
                                'name' => $name,
                                'response' => $toolResult
                            ]
                        ]]
                    ]);

                    // Get final text response
                    $finalResponse = $chat->sendMessage(''); // or 'Continue'
                    $finalText = $finalResponse->text();
                } else {
                    // No function call — direct text response
                    // $text = $result->text();
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
               
                foreach ($functionCall as $call) {
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
                $finalResult = $chat->sendMessage($userText);
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