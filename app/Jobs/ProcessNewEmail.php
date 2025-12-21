<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Email;
use App\Models\Instruction;
use App\Services\AgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNewEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $email;

    public function __construct(User $user, Email $email)
    {
        $this->user = $user;
        $this->email = $email;
    }

    public function handle()
    {
        try {
            Log::info("Processing new email {$this->email->id} for user {$this->user->id}");

            // Get active instructions for this trigger
            $instructions = Instruction::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->whereJsonContains('triggers', 'new_email')
                ->get();

            if ($instructions->isEmpty()) {
                Log::info("No active instructions for new_email trigger");
                return;
            }

            // Build context about the email
            $emailContext = "New email received:\n";
            $emailContext .= "From: {$this->email->from_name} <{$this->email->from_email}>\n";
            $emailContext .= "Subject: {$this->email->subject}\n";
            $emailContext .= "Date: {$this->email->email_date}\n";
            $emailContext .= "Preview: " . mb_substr($this->email->body_text, 0, 300) . "\n\n";

            // Build instruction context
            $emailContext .= "Your ongoing instructions:\n";
            foreach ($instructions as $instruction) {
                $emailContext .= "- {$instruction->instruction}\n";
            }

            $emailContext .= "\nBased on the email and your instructions, should you take any action? If yes, take the action. If no, just acknowledge you received the email.";

            // Let the agent process it
            $agent = new AgentService($this->user);
            $response = $agent->chat($emailContext);

            Log::info("Proactive response: " . $response['content']);

            if (!empty($response['tool_calls'])) {
                Log::info("Tools used: " . json_encode($response['tool_calls']));
            }

        } catch (\Exception $e) {
            Log::error("Error processing email {$this->email->id}: " . $e->getMessage());
        }
    }
}