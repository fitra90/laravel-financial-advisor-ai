<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\GmailService;

class SyncGmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature =  'gmail:sync {user_id? : The ID of the user to sync} {--all : Sync all users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Gmail emails for user(s)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->syncAllUsers();
        } elseif ($userId = $this->argument('user_id')) {
            $this->syncUser($userId);
        } else {
            $this->error('Please provide a user ID or use --all flag');
            return 1;
        }

        return 0;
    }

    protected function syncUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return;
        }

        if (!$user->google_token) {
            $this->warn("User {$userId} ({$user->email}) has no Google token");
            return;
        }

        $this->info("Syncing emails for {$user->email}...");

        try {
            $gmail = new GmailService($user);
            $synced = $gmail->syncEmails(100);
            
            $this->info("✓ Synced {$synced} emails for {$user->email}");
        } catch (\Exception $e) {
            $this->error("✗ Failed to sync for {$user->email}: " . $e->getMessage());
        }
    }

    protected function syncAllUsers()
    {
        $users = User::whereNotNull('google_token')->get();

        if ($users->isEmpty()) {
            $this->warn('No users with Google tokens found');
            return;
        }

        $this->info("Syncing emails for {$users->count()} user(s)...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                $gmail = new GmailService($user);
                $gmail->syncEmails(100);
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed to sync for {$user->email}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->info("\n✓ Sync complete!");
    }
}
