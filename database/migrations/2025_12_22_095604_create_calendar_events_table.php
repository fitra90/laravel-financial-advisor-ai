<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('calendar_events');
        
        Schema::create('calendar_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('event_id')->unique();
                $table->string('calendar_id')->default('primary');
                $table->string('summary');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->dateTime('start_datetime');
                $table->dateTime('end_datetime');
                $table->json('attendees')->nullable();
                $table->string('status')->default('confirmed');
                $table->string('organizer_email')->nullable();
                $table->string('organizer_name')->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('recurring_event_id')->nullable();
                $table->string('html_link')->nullable();
                $table->vector('embedding', 768); // For pgvector
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('start_datetime');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
