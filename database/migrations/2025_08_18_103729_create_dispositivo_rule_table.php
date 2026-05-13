<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispositivoRuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dispositivo_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('rules')->onDelete('cascade');
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->onDelete('cascade');
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
        Schema::dropIfExists('dispositivo_rule');
    }
}
