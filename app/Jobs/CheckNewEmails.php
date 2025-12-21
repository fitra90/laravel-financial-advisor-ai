<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Email;
use App\Services\GmailService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckNewEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    public $tries = 2;
    public $timeout = 180; // 3 minutes

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        // Reload user from database (in case it changed)
        $this->user->refresh();

        if (!$this->user->google_token) {
            Log::info("User {$this->user->id} has no Google token");
            return;
        }

        try {
            Log::info("Checking new emails for user {$this->user->id}");

            $gmail = new GmailService($this->user);
            
            // Get latest email from DB
            // $latestEmail = Email::where('user_id', $this->user->id)
            //     ->orderBy('email_date', 'desc')
            //     ->first();

            // Sync recent emails (last 10)
            $synced = $gmail->syncEmails(10);

            if ($synced > 0) {
                Log::info("Found {$synced} new emails for user {$this->user->id}");

                // Generate embeddings for new emails
                $embeddingService = new EmbeddingService();
                $embeddingService->embedAllEmails($this->user->id);

                // Process each new email with proactive agent
                $newEmails = Email::where('user_id', $this->user->id)
                    ->whereNull('processed_at')
                    ->orderBy('email_date', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($newEmails as $email) {
                    ProcessNewEmail::dispatch($this->user, $email);
                    $email->update(['processed_at' => now()]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Error checking emails for user {$this->user->id}: " . $e->getMessage());
        }
    }
}