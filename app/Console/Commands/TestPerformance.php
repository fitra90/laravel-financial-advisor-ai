<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\AgentService;
use App\Services\EmbeddingService;

class TestPerformance extends Command
{
    protected $signature = 'test:performance {user_id?}';
    protected $description = 'Test system performance';

    public function handle()
    {
        $userId = $this->argument('user_id') ?? 1;
        $user = User::find($userId);

        $this->info("⚡ Performance Tests\n");

        // Test 1: Chat response time
        $this->info("Test 1: Chat Response Time");
        $agent = new AgentService($user);
        
        $start = microtime(true);
        $response = $agent->chat('Hello');
        $time = round((microtime(true) - $start) * 1000, 2);
        
        $this->info("Response time: {$time}ms");
        
        if ($time < 2000) {
            $this->info("✓ Fast response");
        } elseif ($time < 5000) {
            $this->warn("⚠ Acceptable response");
        } else {
            $this->error("✗ Slow response");
        }
        $this->newLine();

        // Test 2: Embedding generation
        $this->info("Test 2: Embedding Generation");
        $embedding = new EmbeddingService();
        
        $start = microtime(true);
        $vector = $embedding->generateEmbedding('Test text for embedding');
        $time = round((microtime(true) - $start) * 1000, 2);
        
        $this->info("Generation time: {$time}ms");
        
        if ($time < 1000) {
            $this->info("✓ Fast generation");
        } elseif ($time < 3000) {
            $this->warn("⚠ Acceptable");
        } else {
            $this->error("✗ Slow generation");
        }
        $this->newLine();

        // Test 3: Vector search
        $this->info("Test 3: Vector Search");
        
        $start = microtime(true);
        $results = $embedding->searchSimilarEmails('test query', $userId, 5);
        $time = round((microtime(true) - $start) * 1000, 2);
        
        $this->info("Search time: {$time}ms");
        $this->info("Results: " . count($results));
        
        if ($time < 500) {
            $this->info("✓ Fast search");
        } else {
            $this->warn("⚠ Could be faster");
        }
        $this->newLine();

        $this->info("✓ Performance tests complete!");

        return 0;
    }
}