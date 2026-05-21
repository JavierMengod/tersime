<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('coste_kwh', 8, 4)->default(0.15)->after('theme');
        });

        // Seed existing users with the current global value
        $global = DB::table('ajustes')->where('key', 'coste_kwh')->value('value') ?? '0.15';
        DB::table('users')->update(['coste_kwh' => (float) $global]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('coste_kwh');
        });
    }
};
