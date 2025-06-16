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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('encryption_key'); // Clave única para cifrado de PDFs

            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_code')->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        /*
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        */
    }
};
