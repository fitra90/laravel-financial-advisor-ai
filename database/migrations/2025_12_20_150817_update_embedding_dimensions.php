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
        DB::statement('ALTER TABLE emails DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE emails ADD COLUMN embedding vector(768)');
        DB::statement('DROP INDEX IF EXISTS emails_embedding_idx');
        DB::statement('CREATE INDEX emails_embedding_idx ON emails USING ivfflat (embedding vector_cosine_ops)');

        // Update contacts table
        DB::statement('ALTER TABLE contacts DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE contacts ADD COLUMN embedding vector(768)');
        DB::statement('DROP INDEX IF EXISTS contacts_embedding_idx');
        DB::statement('CREATE INDEX contacts_embedding_idx ON contacts USING ivfflat (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE emails DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE emails ADD COLUMN embedding vector(1536)');
        
        DB::statement('ALTER TABLE contacts DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE contacts ADD COLUMN embedding vector(1536)');
    }
};
