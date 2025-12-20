<?php

namespace App\Services;

use App\Models\User;
use App\Models\Email;
use Google\Client;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GmailService
{
    protected $user;
    protected $client;
    protected $service;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->initializeClient();
    }

    /**
     * Initialize Google Client
     */
    protected function initializeClient()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessType('offline');
        
        // Set access token
        $this->client->setAccessToken([
            'access_token' => $this->user->google_token,
            'refresh_token' => $this->user->google_refresh_token,
            'expires_in' => $this->user->google_token_expires_at 
                ? $this->user->google_token_expires_at->diffInSeconds(now())
                : 3600,
        ]);

        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken();
        }

        $this->service = new Gmail($this->client);
    }

    /**
     * Refresh access token
     */
    protected function refreshToken()
    {
        try {
            $this->client->fetchAccessTokenWithRefreshToken($this->user->google_refresh_token);
            $newToken = $this->client->getAccessToken();

            $this->user->update([
                'google_token' => $newToken['access_token'],
                'google_token_expires_at' => isset($newToken['expires_in'])
                    ? Carbon::now()->addSeconds($newToken['expires_in'])
                    : null,
            ]);

            Log::info("Refreshed Google token for user {$this->user->id}");
            
        } catch (\Exception $e) {
            Log::error('Failed to refresh Google token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of messages
     */
    public function listMessages($maxResults = 100, $pageToken = null)
    {
        try {
            $params = [
                'maxResults' => $maxResults,
                'userId' => 'me',
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $results = $this->service->users_messages->listUsersMessages('me', $params);
            
            return [
                'messages' => $results->getMessages() ?? [],
                'nextPageToken' => $results->getNextPageToken(),
            ];
            
        } catch (\Exception $e) {
            Log::error('Gmail listMessages error: ' . $e->getMessage());
            return ['messages' => [], 'nextPageToken' => null];
        }
    }

    /**
     * Get a single message by ID
     */
    public function getMessage($messageId)
    {
        try {
            return $this->service->users_messages->get('me', $messageId, ['format' => 'full']);
        } catch (\Exception $e) {
            Log::error("Gmail getMessage error for {$messageId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse message headers
     */
    protected function getHeader($message, $headerName)
    {
        $headers = $message->getPayload()->getHeaders();
        
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($headerName)) {
                return $header->getValue();
            }
        }
        
        return null;
    }

    /**
     * Extract email body
     */
    protected function getBody($message)
    {
        $payload = $message->getPayload();
        $body = '';

        // Check if body is in the main payload
        if ($payload->getBody()->getData()) {
            return base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        }

        // Check parts
        $parts = $payload->getParts();
        
        if ($parts) {
            foreach ($parts as $part) {
                if ($part->getMimeType() === 'text/plain' || $part->getMimeType() === 'text/html') {
                    if ($part->getBody()->getData()) {
                        $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                        break;
                    }
                }
                
                // Check nested parts
                if ($part->getParts()) {
                    foreach ($part->getParts() as $subPart) {
                        if ($subPart->getMimeType() === 'text/plain' || $subPart->getMimeType() === 'text/html') {
                            if ($subPart->getBody()->getData()) {
                                $body = base64_decode(strtr($subPart->getBody()->getData(), '-_', '+/'));
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return $body;
    }

    /**
     * Send an email
     */
    public function sendEmail($to, $subject, $body)
    {
        try {
            $message = new \Google\Service\Gmail\Message();
            
            $rawMessage = "From: {$this->user->email}\r\n";
            $rawMessage .= "To: {$to}\r\n";
            $rawMessage .= "Subject: {$subject}\r\n";
            $rawMessage .= "\r\n{$body}";
            
            $message->setRaw(base64_encode($rawMessage));
            
            $result = $this->service->users_messages->send('me', $message);
            
            Log::info("Email sent to {$to} by user {$this->user->id}");
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Gmail sendEmail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch and store emails in database
     */
    public function syncEmails($maxEmails = 100)
    {
        try {
            $messageList = $this->listMessages($maxEmails);
            $messages = $messageList['messages'];
            $synced = 0;

            foreach ($messages as $messageInfo) {
                try {
                    // Check if already synced
                    if (Email::where('gmail_id', $messageInfo->getId())->exists()) {
                        continue;
                    }

                    // Get full message
                    $message = $this->getMessage($messageInfo->getId());
                    
                    if (!$message) {
                        continue;
                    }

                    // Extract data
                    $from = $this->getHeader($message, 'From');
                    $to = $this->getHeader($message, 'To');
                    $subject = $this->getHeader($message, 'Subject');
                    $date = $this->getHeader($message, 'Date');
                    $body = $this->getBody($message);

                    // Parse from email
                    preg_match('/<(.+?)>/', $from, $matches);
                    $fromEmail = $matches[1] ?? $from;
                    
                    // Parse from name
                    $fromName = trim(str_replace(['<', '>', $fromEmail], '', $from));

                    // Store in database
                    Email::create([
                        'user_id' => $this->user->id,
                        'gmail_id' => $message->getId(),
                        'thread_id' => $message->getThreadId(),
                        'from_email' => $fromEmail,
                        'from_name' => $fromName ?: null,
                        'to_email' => $to,
                        'subject' => $subject,
                        'body_text' => strip_tags($body),
                        'body_html' => $body,
                        'email_date' => $date ? Carbon::parse($date) : now(),
                        'labels' => $message->getLabelIds(),
                    ]);

                    $synced++;
                    
                } catch (\Exception $e) {
                    Log::error("Failed to sync message {$messageInfo->getId()}: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("Synced {$synced} emails for user {$this->user->id}");
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Gmail syncEmails error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Watch for new emails (setup push notifications)
     */
    public function watchMailbox($topicName = 'projects/your-project/topics/gmail')
    {
        try {
            $watchRequest = new \Google\Service\Gmail\WatchRequest();
            $watchRequest->setTopicName($topicName);
            $watchRequest->setLabelIds(['INBOX']);

            $watchResponse = $this->service->users->watch('me', $watchRequest);
            
            Log::info("Gmail watch set up for user {$this->user->id}");
            return $watchResponse;
            
        } catch (\Exception $e) {
            Log::error('Gmail watch error: ' . $e->getMessage());
            return null;
        }
    }
}