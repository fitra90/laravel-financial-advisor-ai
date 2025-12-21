<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\User;
use App\Jobs\CheckNewEmails;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Check for new emails every 5 minutes
        $schedule->call(function () {
            $users = User::whereNotNull('google_token')->get();
            
            foreach ($users as $user) {
                CheckNewEmails::dispatch($user);
            }
        })->everyFiveMinutes()->name('check-new-emails');

        // Cleanup old messages (optional)
        $schedule->command('model:prune', ['--model' => 'App\\Models\\Message'])
            ->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}