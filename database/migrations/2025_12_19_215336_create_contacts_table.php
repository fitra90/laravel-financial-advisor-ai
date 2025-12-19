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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('hubspot_id')->unique();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable(); // Concatenated notes from Hubspot
            $table->json('properties')->nullable(); // All other Hubspot properties
            
            // For pgvector
            $table->vector('embedding', 1536)->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'email']);
            $table->index('hubspot_id');
        });
        
        DB::statement('CREATE INDEX contacts_embedding_idx ON contacts USING ivfflat (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
