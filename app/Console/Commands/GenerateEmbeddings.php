<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmbeddingService;

class GenerateEmbeddings extends Command
{
    protected $signature = 'embeddings:generate {user_id? : The ID of the user} {--all : Generate for all users}';
    protected $description = 'Generate embeddings for emails and contacts';

    public function handle()
    {
        $embeddingService = new EmbeddingService();

        if ($this->option('all')) {
            $this->generateForAllUsers($embeddingService);
        } elseif ($userId = $this->argument('user_id')) {
            $this->generateForUser($userId, $embeddingService);
        } else {
            $this->error('Please provide a user ID or use --all flag');
            return 1;
        }

        return 0;
    }

    protected function generateForUser($userId, $embeddingService)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return;
        }

        $this->info("Generating embeddings for {$user->email}...");

        // Emails
        $this->info('Processing emails...');
        $emailCount = $embeddingService->embedAllEmails($userId);
        $this->info("✓ Embedded {$emailCount} emails");

        // Contacts
        $this->info('Processing contacts...');
        $contactCount = $embeddingService->embedAllContacts($userId);
        $this->info("✓ Embedded {$contactCount} contacts");

        $this->info("Done! Total: {$emailCount} emails + {$contactCount} contacts");
    }

    protected function generateForAllUsers($embeddingService)
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->warn('No users found');
            return;
        }

        $this->info("Generating embeddings for {$users->count()} user(s)...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $embeddingService->embedAllEmails($user->id);
            $embeddingService->embedAllContacts($user->id);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✓ All embeddings generated!");
    }
}