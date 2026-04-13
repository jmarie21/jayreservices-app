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
        Schema::create('service_addon_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_addon_id')->constrained('service_addons')->cascadeOnDelete();
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->decimal('client_price_override', 10, 2)->nullable();
            $table->decimal('editor_price_override', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['assignable_type', 'assignable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_addon_assignments');
    }
};
