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

        // Sync all calendars every 6 hours
        $schedule->command('calendar:sync --all')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // ================= DAILY CLEANUP =================
        // Cleanup old jobs daily at 3 AM
        $schedule->command('queue:prune-batches --hours=48')->dailyAt('03:00');
        $schedule->command('queue:prune-failed-jobs --hours=72')->dailyAt('03:30');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}