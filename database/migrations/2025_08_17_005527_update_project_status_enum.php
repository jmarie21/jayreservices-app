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
            // SQLite doesn't support "alter enum" directly
            // so we redefine the column with new values
            $table->enum("status", [
                "pending",
                "in_progress",
                "completed",
                "todo",
                "backlog",
                "for_qa",
                "done_qa",
                "sent_to_client",
                "revision",
                "revision_completed"
            ])->default("pending")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('projects', function (Blueprint $table) {
            $table->enum("status", ["pending", "in_progress", "completed"])
                  ->default("pending")
                  ->change();
        });
    }
};
