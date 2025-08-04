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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained("users")->cascadeOnDelete();
            $table->foreignId("editor_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->foreignId("service_id")->constrained("services")->cascadeOnDelete();

            // Basic fields from the form
            $table->string("style");
            $table->string("company_name");
            $table->string("contact");
            $table->string("project_name");
            $table->string("format")->nullable();
            $table->string("camera")->nullable();
            $table->string("quality")->nullable();
            $table->string("music")->nullable();
            $table->string("music_link")->nullable();
            $table->string("file_link");
            $table->text("notes")->nullable();

            // Status, pricing, output
            $table->decimal("total_price", 10, 2);
            $table->string("output_link")->nullable();
            $table->enum("status", ["pending", "in_progress", "completed"])->default("pending");

            // Optional JSON for premium/luxury extras
            $table->json("extra_fields")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
