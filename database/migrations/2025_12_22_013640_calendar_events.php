<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_id')->index(); // Google Calendar event ID
            $table->string('calendar_id')->nullable(); // Google Calendar ID
            $table->string('summary'); // Event title
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->json('attendees')->nullable(); // Array of attendee emails
            $table->string('status')->default('confirmed'); // confirmed, tentative, cancelled
            $table->string('organizer_email')->nullable();
            $table->string('organizer_name')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_event_id')->nullable();
            $table->text('html_link')->nullable(); // Link to event in Google Calendar
            $table->timestamps();

            // Unique constraint on user_id and event_id
            $table->unique(['user_id', 'event_id']);
            
            // Index for date range queries
            $table->index(['user_id', 'start_datetime']);
            $table->index(['user_id', 'end_datetime']);
        });

        // Add vector column for embeddings (pgvector extension required)
        DB::statement('ALTER TABLE calendar_events ADD COLUMN embedding vector(1536)');
        
        // Create index for vector similarity search
        DB::statement('CREATE INDEX calendar_events_embedding_idx ON calendar_events USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};