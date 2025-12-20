<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gmail_id')->unique(); // Gmail's message ID
            $table->string('thread_id')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->timestamp('email_date');
            $table->json('labels')->nullable(); // Gmail labels
            
            // For pgvector - we'll add embeddings here
            $table->vector('embedding', 1536)->nullable(); // OpenAI embeddings are 1536 dimensions
            
            $table->timestamps();
            
            $table->index(['user_id', 'email_date']);
            $table->index('gmail_id');
        });

        DB::statement('CREATE INDEX emails_embedding_idx ON emails USING ivfflat (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
