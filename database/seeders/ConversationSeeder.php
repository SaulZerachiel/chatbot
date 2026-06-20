<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\CustomInstruction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
	public function run(): void
	{
		$users = User::all();

		foreach ($users as $user) {
			// Créer les instructions personnalisées pour chaque user
			CustomInstruction::create([
				'user_id' => $user->id,
				'about_user' => 'Etudiant en developpement web, 2eme annee.',
				'behavior' => 'Repondre avec des exemples de code PHP/Laravel.',
			]);

			// Créer 2 conversations de test par utilisateur
			for ($i = 1; $i <= 2; $i++) {
				$conversation = Conversation::create([
					'user_id' => $user->id,
					'title' => "Conversation de test $i",
					'model' => 'openai/gpt-4o-mini',
				]);

				Message::create([
					'conversation_id' => $conversation->id,
					'role' => 'user',
					'content' => 'Camarade Gandalf, explique Laravel !',
				]);

				Message::create([
					'conversation_id' => $conversation->id,
					'role' => 'assistant',
					'content' => 'Camarades Hobbits ! Laravel est le framework PHP qui vous liberera des architectures archaiques ! Vous ne passerez pas par les routes tortueuses du PHP brut !',
				]);

				Message::create([
					'conversation_id' => $conversation->id,
					'role' => 'user',
					'content' => 'Et Inertia.js, c\'est quoi ?',
				]);

				Message::create([
					'conversation_id' => $conversation->id,
					'role' => 'assistant',
					'content' => 'Inertia.js, mes camarades, c\'est le pont entre le Comte de Laravel et les Terres de Vue.js ! Une victoire pour le Peuple des Developpeurs !',
				]);
			}
		}
	}
}
