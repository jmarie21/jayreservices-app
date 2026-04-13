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
        Schema::create('service_format_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_sub_style_id')->constrained('service_sub_styles')->cascadeOnDelete();
            $table->string('format_name');
            $table->string('format_label');
            $table->decimal('client_price', 10, 2)->default(0);
            $table->decimal('editor_price', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_format_pricing');
    }
};
