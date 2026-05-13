<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)->unique()->nullable(false);

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('operator', ['>', '<', '==', '!=', '>=', '<='])->default('==');

            $table->integer('time_range');

            $table->string('comparison_value');

            $table->boolean('is_active')->default(true);

            $table->boolean('email_enabled')->default(false);
            $table->boolean('telegram_enabled')->default(false);
            $table->boolean('discord_enabled')->default(false);
            $table->string('recipient_email')->nullable();

            $table->text('template_telegram')->nullable();
            $table->text('template_email')->nullable();
            $table->text('template_discord')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rules');
    }
}
