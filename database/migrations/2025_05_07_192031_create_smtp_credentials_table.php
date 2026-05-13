<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmtpCredentialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('smtp_credentials', function (Blueprint $table) {
            $table->id();
    
            // Relación con usuarios
            $table->foreignId('user_id')
                  ->constrained()            // asume tabla users, columna id
                  ->cascadeOnDelete();
    
            // Campos de configuración SMTP
            $table->string('host');
            $table->integer('port');
            $table->string('username');
            $table->string('password');
            $table->string('encryption')->default('tls');
            $table->boolean('active')->default(false);
    
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
        Schema::dropIfExists('smtp_credentials');
    }
}
