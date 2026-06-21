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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Clé étrangère vers users — si user supprimé, ses convs aussi
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->index();
            // Titre généré automatiquement (null au début)
            $table->string('title')->nullable();
            // Le modèle IA utilisé pour cette conversation
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
