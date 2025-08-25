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
