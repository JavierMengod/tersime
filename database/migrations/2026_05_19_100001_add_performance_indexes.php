<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('rules', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('rules', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
