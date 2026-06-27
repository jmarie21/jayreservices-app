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
        Schema::create('client_dedicated_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->foreignId('editor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        DB::table('users')
            ->whereNotNull('dedicated_editor_id')
            ->get(['id', 'dedicated_editor_id'])
            ->each(function (object $user): void {
                DB::table('client_dedicated_editors')->insert([
                    'client_id' => $user->id,
                    'service_id' => null,
                    'editor_id' => $user->dedicated_editor_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dedicated_editor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dedicated_editor_id')->nullable()->after('recommended_editor_level')->constrained('users')->nullOnDelete();
        });

        DB::table('client_dedicated_editors')
            ->whereNull('service_id')
            ->select('client_id', DB::raw('MIN(editor_id) as editor_id'))
            ->groupBy('client_id')
            ->get()
            ->each(function (object $row): void {
                DB::table('users')->where('id', $row->client_id)->update(['dedicated_editor_id' => $row->editor_id]);
            });

        Schema::dropIfExists('client_dedicated_editors');
    }
};
