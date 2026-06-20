<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Créer 3 utilisateurs de test aléatoires
        User::factory(3)->create();

        // Créer l'utilisateur principal (pour se connecter facilement)
        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);

        // Lancer le seeder de conversations
        $this->call([
            ConversationSeeder::class,
        ]);
    }
}
