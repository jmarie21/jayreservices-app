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
        if (DB::getDriverName() === 'pgsql') {
            // Drop the old check/enum constraint in PostgreSQL
            DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_check;');
        }

        Schema::table('projects', function (Blueprint $table) {
            // Change status from enum to string
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Rollback: convert back to enum
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'todo',
                'backlog',
                'for_qa',
                'done_qa',
                'sent_to_client',
                'revision',
                'revision_completed'
            ])->default('pending')->change();
        });
    }
};
