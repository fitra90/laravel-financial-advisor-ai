<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Email;
use App\Models\Contact;
use App\Models\Instruction;
use App\Services\GmailService;
use App\Services\HubspotService;
use App\Services\EmbeddingService;
use App\Services\AgentService;

class TestSystem extends Command
{
    protected $signature = 'test:system {user_id?}';
    protected $description = 'Test all system components';

    public function handle()
    {
        $userId = $this->argument('user_id') ?? 1;
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return 1;
        }

        $this->info("🧪 Testing Financial Advisor AI System");
        $this->info("User: {$user->name} ({$user->email})");
        $this->newLine();

        $passed = 0;
        $failed = 0;

        // Test 1: Database
        $this->info('Test 1: Database Connection');
        try {
            $emailCount = Email::where('user_id', $userId)->count();
            $contactCount = Contact::where('user_id', $userId)->count();
            $this->info("✓ Emails: {$emailCount}");
            $this->info("✓ Contacts: {$contactCount}");
            $passed++;
        } catch (\Exception $e) {
            $this->error("✗ Database error: " . $e->getMessage());
            $failed++;
        }
        $this->newLine();

        // Test 2: Google OAuth
        $this->info('Test 2: Google OAuth');
        if ($user->google_token) {
            $this->info("✓ Google connected");
            
            if ($user->google_token_expires_at) {
                $expired = $user->google_token_expires_at->isPast();
                if ($expired) {
                    $this->warn("⚠ Token expired: {$user->google_token_expires_at}");
                } else {
                    $this->info("✓ Token valid until: {$user->google_token_expires_at}");
                }
            }
            $passed++;
        } else {
            $this->error("✗ Google not connected");
            $failed++;
        }
        $this->newLine();

        // Test 3: Hubspot OAuth
        $this->info('Test 3: Hubspot OAuth');
        if ($user->hubspot_token) {
            $this->info("✓ Hubspot connected");
            $passed++;
        } else {
            $this->error("✗ Hubspot not connected");
            $failed++;
        }
        $this->newLine();

        // Test 4: Gmail Service
        $this->info('Test 4: Gmail Service');
        if ($user->google_token) {
            try {
                $gmail = new GmailService($user);
                $list = $gmail->listMessages(1);
                $this->info("✓ Gmail API working");
                $this->info("✓ Available messages: " . count($list['messages']));
                $passed++;
            } catch (\Exception $e) {
                $this->error("✗ Gmail error: " . $e->getMessage());
                $failed++;
            }
        } else {
            $this->warn("⊘ Skipping (no token)");
        }
        $this->newLine();

        // Test 5: Hubspot Service
        $this->info('Test 5: Hubspot Service');
        if ($user->hubspot_token) {
            try {
                $hubspot = new HubspotService($user);
                $contacts = $hubspot->getContacts(5);
                $this->info("✓ Hubspot API working");
                $this->info("✓ Contacts available: " . count($contacts));
                $passed++;
            } catch (\Exception $e) {
                $this->error("✗ Hubspot error: " . $e->getMessage());
                $failed++;
            }
        } else {
            $this->warn("⊘ Skipping (no token)");
        }
        $this->newLine();

        // Test 6: Embeddings
        $this->info('Test 6: Embeddings');
        try {
            $embedding = new EmbeddingService();
            $testVector = $embedding->generateEmbedding('Test sentence');
            
            if ($testVector && count($testVector) === 768) {
                $this->info("✓ Embedding generation working");
                $this->info("✓ Vector dimensions: " . count($testVector));
                $passed++;
            } else {
                $this->error("✗ Invalid embedding dimensions");
                $failed++;
            }
        } catch (\Exception $e) {
            $this->error("✗ Embedding error: " . $e->getMessage());
            $failed++;
        }
        $this->newLine();

        // Test 7: Vector Search
        $this->info('Test 7: Semantic Search');
        $embeddedEmails = Email::where('user_id', $userId)->whereNotNull('embedding')->count();
        $embeddedContacts = Contact::where('user_id', $userId)->whereNotNull('embedding')->count();
        
        $this->info("Embedded emails: {$embeddedEmails}");
        $this->info("Embedded contacts: {$embeddedContacts}");
        
        if ($embeddedEmails > 0) {
            try {
                $embedding = new EmbeddingService();
                $results = $embedding->searchSimilarEmails('test', $userId, 3);
                $this->info("✓ Email search working: " . count($results) . " results");
                $passed++;
            } catch (\Exception $e) {
                $this->error("✗ Search error: " . $e->getMessage());
                $failed++;
            }
        } else {
            $this->warn("⚠ No embeddings found. Run: php artisan embeddings:generate {$userId}");
        }
        $this->newLine();

        // Test 8: AI Agent
        $this->info('Test 8: AI Agent Chat');
        try {
            $agent = new AgentService($user);
            $response = $agent->chat('Hello! Just testing.');
            
            if (!empty($response['content'])) {
                $preview = substr($response['content'], 0, 100);
                $this->info("✓ Agent responding");
                $this->info("Response: {$preview}...");
                $passed++;
            } else {
                $this->error("✗ Empty response");
                $failed++;
            }
        } catch (\Exception $e) {
            $this->error("✗ Agent error: " . $e->getMessage());
            $failed++;
        }
        $this->newLine();

        // Test 9: Instructions
        $this->info('Test 9: Ongoing Instructions');
        $activeInstructions = Instruction::where('user_id', $userId)
            ->where('is_active', true)
            ->count();
        $this->info("Active instructions: {$activeInstructions}");
        if ($activeInstructions > 0) {
            $this->info("✓ Instructions system working");
            $passed++;
        } else {
            $this->warn("⚠ No active instructions");
        }
        $this->newLine();

        // Summary
        $total = $passed + $failed;
        $this->info("=== SUMMARY ===");
        $this->info("Total tests: {$total}");
        $this->info("Passed: {$passed}");
        
        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        } else {
            $this->info("Failed: {$failed}");
        }
        
        $this->newLine();
        
        if ($failed === 0) {
            $this->info("🎉 All tests passed! System is ready.");
            return 0;
        } else {
            $this->warn("⚠ Some tests failed. Review errors above.");
            return 1;
        }
    }
}