<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing CSV values to JSON before changing column type
        DB::table('alert_logs')->whereNotNull('channels')->orderBy('id')->each(function ($row) {
            $csv = trim($row->channels);
            if ($csv === '') {
                DB::table('alert_logs')->where('id', $row->id)->update(['channels' => null]);
                return;
            }
            $array = array_values(array_filter(array_map('trim', explode(',', $csv))));
            DB::table('alert_logs')->where('id', $row->id)->update(['channels' => json_encode($array)]);
        });

        Schema::table('alert_logs', function (Blueprint $table) {
            $table->json('channels')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Convert JSON back to CSV
        DB::table('alert_logs')->whereNotNull('channels')->orderBy('id')->each(function ($row) {
            $array = json_decode($row->channels, true) ?? [];
            $csv   = implode(',', array_filter(array_map('trim', $array)));
            DB::table('alert_logs')->where('id', $row->id)->update(['channels' => $csv ?: null]);
        });

        Schema::table('alert_logs', function (Blueprint $table) {
            $table->string('channels', 100)->nullable()->change();
        });
    }
};
