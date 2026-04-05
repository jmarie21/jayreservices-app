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
        Schema::create('service_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('addon_type')->default('boolean');
            $table->decimal('client_price', 10, 2)->default(0);
            $table->decimal('editor_price', 10, 2)->default(0);
            $table->boolean('has_quantity')->default(false);
            $table->string('sample_link')->nullable();
            $table->string('group')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_addons');
    }
};
