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
        // Convert existing string links to JSON arrays
        \Illuminate\Support\Facades\DB::table('projects')
            ->whereNotNull('output_link')
            ->get()
            ->each(function ($project) {
                // Check if it's already JSON (starts with [)
                if (!str_starts_with($project->output_link, '[')) {
                    \Illuminate\Support\Facades\DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['output_link' => json_encode([$project->output_link])]);
                }
            });

        Schema::table('projects', function (Blueprint $table) {
            $table->json('output_link')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('output_link')->nullable()->change();
        });
    }
};
