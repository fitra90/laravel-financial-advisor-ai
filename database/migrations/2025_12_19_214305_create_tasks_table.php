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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // e.g., 'schedule_meeting', 'send_email'
            $table->enum('status', ['pending', 'in_progress', 'waiting', 'completed', 'failed'])->default('pending');
            $table->text('description');
            $table->json('context'); // Store all context needed to continue the task
            $table->json('steps')->nullable(); // Track what steps have been completed
            $table->timestamp('last_attempted_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
