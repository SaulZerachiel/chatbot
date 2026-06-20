<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskStreamService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
	public function __construct(private SimpleAskStreamService $streamService)
	{
	}

	/** Page d'accueil : liste des conversations. */
	public function index()
	{
		$conversations = auth()->user()
			->conversations()
			->orderByDesc('updated_at') // Tri : plus récente d'abord
			->get(['id', 'title', 'model', 'updated_at']);

		return Inertia::render('Chat/Index', [
			'conversations' => $conversations,
			'currentConversation' => null,
			'messages' => [],
			'models' => $this->streamService->getModelsLight(),
			'selectedModel' => auth()->user()->preferred_model
				?? SimpleAskStreamService::DEFAULT_MODEL,
		]);
	}

	/** Affiche une conversation spécifique avec ses messages. */
	public function show(Conversation $conversation)
	{
		// Sécurité : seul le propriétaire peut voir sa conversation
		if ($conversation->user_id !== auth()->id()) {
			abort(403);
		}

		$conversations = auth()->user()
			->conversations()
			->orderByDesc('updated_at')
			->get(['id', 'title', 'model', 'updated_at']);

		return Inertia::render('Chat/Index', [
			'conversations' => $conversations,
			'currentConversation' => $conversation,
			// Eager loading : charge les messages en 1 requête (évite N+1)
			'messages' => $conversation->messages,
			'models' => $this->streamService->getModelsLight(),
			'selectedModel' => $conversation->model
				?? auth()->user()->preferred_model
				?? SimpleAskStreamService::DEFAULT_MODEL,
		]);
	}

	/** Endpoint de streaming : reçoit message, stream réponse, sauvegarde tout. */
	public function stream(Request $request): StreamedResponse
	{
		$validated = $request->validate([
			'message' => 'required|string|max:100000',
			'model' => 'required|string',
			'conversation_id' => 'nullable|integer|exists:conversations,id',
		]);

		$user = auth()->user();

		// Mémoriser le modèle préféré de l'utilisateur
		if ($user->preferred_model !== $validated['model']) {
			$user->update(['preferred_model' => $validated['model']]);
		}

		// Obtenir ou créer la conversation
		if ($validated['conversation_id']) {
			$conversation = Conversation::find($validated['conversation_id']);

			if ($conversation->model !== $validated['model']) {
				$conversation->update(['model' => $validated['model']]);
			}
		} else {
			$conversation = Conversation::create([
				'user_id' => $user->id,
				'title' => null,
				'model' => $validated['model'],
			]);
		}

		// Sauvegarder le message de l'utilisateur
		$conversation->messages()->create([
			'role' => 'user',
			'content' => $validated['message'],
		]);

		// Construire l'historique complet pour l'API
		$history = $conversation->messages
			->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
			->toArray();

		$convId = $conversation->id;

		return response()->stream(
			function () use ($history, $validated, $conversation, $convId): void {
				$collected = '';

				// 1. Envoyer l'ID de conversation en PREMIER (pour le frontend)
				echo "[CONV_ID:{$convId}]";
				flush();

				// 2. Streamer la réponse token par token
				foreach ($this->streamService->streamChunks($history, $validated['model']) as $chunk) {
					echo $chunk;
					$collected .= $chunk;

					if (ob_get_level() > 0) {
						ob_flush();
					}

					flush();
				}

				// 3. Après le stream, sauvegarder la réponse complète en BDD
				$cleanContent = preg_replace('/\[REASONING\][\s\S]*?\[\/REASONING\]/', '', $collected);
				$cleanContent = trim(str_replace("[CONV_ID:{$convId}]", '', $cleanContent));

				if ($cleanContent) {
					$conversation->messages()->create([
						'role' => 'assistant',
						'content' => $cleanContent,
					]);
				}

				// 4. Générer le titre si c'est la 1ère réponse
				if (!$conversation->title) {
					$firstMsg = $conversation->messages()
						->where('role', 'user')
						->first();

					if ($firstMsg) {
						$conversation->update(['title' => mb_substr($firstMsg->content, 0, 60)]);
					}
				}

				$conversation->touch(); // Met à jour updated_at pour le tri
			},
			headers: [
				'Content-Type'      => 'text/event-stream; charset=utf-8',
				'Cache-Control'     => 'no-cache, no-store',
				'X-Accel-Buffering' => 'no',
			]
		);
	}
}
