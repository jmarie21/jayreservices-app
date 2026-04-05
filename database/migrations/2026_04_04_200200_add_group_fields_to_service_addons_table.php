<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_addons', function (Blueprint $table) {
            $table->foreignId('service_addon_group_id')
                ->nullable()
                ->after('id')
                ->constrained('service_addon_groups')
                ->nullOnDelete();
            $table->boolean('is_rush_option')->default(false)->after('has_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('service_addons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_addon_group_id');
            $table->dropColumn('is_rush_option');
        });
    }
};
