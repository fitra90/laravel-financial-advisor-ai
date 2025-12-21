<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\AgentService;

class TestScenarios extends Command
{
    protected $signature = 'test:scenarios {user_id?}';
    protected $description = 'Test real user scenarios';

    public function handle()
    {
        $userId = $this->argument('user_id') ?? 1;
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return 1;
        }

        $agent = new AgentService($user);

        $this->info("🎬 Testing User Scenarios\n");

        // Scenario 1: General question
        $this->info("Scenario 1: General Question");
        $this->line("User: What is compound interest?");
        
        $response = $agent->chat('What is compound interest?');
        $this->info("AI: " . substr($response['content'], 0, 200) . "...");
        $this->newLine();

        sleep(2);

        // Scenario 2: Search emails
        $this->info("Scenario 2: Search Emails");
        $this->line("User: Search my emails for any mentions of meetings");
        
        $response = $agent->chat('Search my emails for any mentions of meetings');
        $this->info("AI: " . substr($response['content'], 0, 200) . "...");
        
        if (!empty($response['tool_calls'])) {
            $this->warn("Tools used: " . json_encode(array_column($response['tool_calls'], 'tool')));
        }
        $this->newLine();

        sleep(2);

        // Scenario 3: Search contacts
        $this->info("Scenario 3: Search Contacts");
        $this->line("User: Who are my contacts?");
        
        $response = $agent->chat('Who are my contacts?');
        $this->info("AI: " . substr($response['content'], 0, 200) . "...");
        
        if (!empty($response['tool_calls'])) {
            $this->warn("Tools used: " . json_encode(array_column($response['tool_calls'], 'tool')));
        }
        $this->newLine();

        sleep(2);

        // Scenario 4: Specific search
        $this->info("Scenario 4: Specific Search");
        $this->line("User: Find emails from last week");
        
        $response = $agent->chat('Find emails from last week');
        $this->info("AI: " . substr($response['content'], 0, 200) . "...");
        $this->newLine();

        $this->info("✓ All scenarios tested!");

        return 0;
    }
}