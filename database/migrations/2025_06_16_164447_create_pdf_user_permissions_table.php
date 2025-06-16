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
        Schema::create('pdf_user_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pdf_id')->constrained('pdfs')->onDelete('cascade');
            $table->foreignId('shared_with_user_id')->constrained('users')->onDelete('cascade');

            $table->boolean('can_view')->default(false);
            $table->boolean('can_download')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_user_permissions');
    }
};
