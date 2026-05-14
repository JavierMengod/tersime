<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlertLogsTable extends Migration
{
    public function up()
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->foreign('rule_id')->references('id')->on('rules')->nullOnDelete();
            $table->string('rule_name');
            $table->unsignedBigInteger('dispositivo_id')->nullable();
            $table->foreign('dispositivo_id')->references('id')->on('dispositivos')->nullOnDelete();
            $table->string('device_name');
            $table->string('type', 20);       // 'firing' | 'resolution'
            $table->string('channels', 100)->nullable(); // 'telegram,email,discord'
            $table->text('message');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'device_name']);
            $table->index(['user_id', 'rule_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('alert_logs');
    }
}
