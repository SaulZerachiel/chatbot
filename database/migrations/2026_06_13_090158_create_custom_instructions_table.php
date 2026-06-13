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
        Schema::create('custom_instructions', function (Blueprint $table) {
            $table->id();
            // Un seul enregistrement par user (relation 1-1 — UNIQUE)
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            // Ce que l'IA doit savoir sur l'utilisateur
            $table->text('about_user')->nullable();
            // Comment l'IA doit se comporter
            $table->text('behavior')->nullable();
            $table->timestamps();
        });
    }

    /**php artisan migrate:fresh
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_instructions');
    }
};
