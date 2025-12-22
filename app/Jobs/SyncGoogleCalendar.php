<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $user;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $options = [])
    {
        $this->user = $user;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting calendar sync', ['user_id' => $this->user->id]);

        $service = new GoogleCalendarService($this->user);
        $result = $service->syncEvents($this->options);

        if ($result['success']) {
            $this->user->update([
                'calendar_last_sync_at' => now(),
            ]);

            Log::info('Calendar sync completed', [
                'user_id' => $this->user->id,
                'synced' => $result['synced'],
                'errors' => $result['errors'],
            ]);
        } else {
            Log::error('Calendar sync failed', [
                'user_id' => $this->user->id,
                'message' => $result['message'],
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Calendar sync job failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}