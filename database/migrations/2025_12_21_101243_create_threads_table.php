<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('context')->default('all'); // 'all', 'emails', 'contacts', 'calendar'
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'last_message_at']);
        });

        // Add thread_id to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('thread_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['thread_id']);
            $table->dropColumn('thread_id');
        });
        
        Schema::dropIfExists('threads');
    }
};