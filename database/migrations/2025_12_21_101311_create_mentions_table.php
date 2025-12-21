<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'email', 'contact', 'calendar_attendee'
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};