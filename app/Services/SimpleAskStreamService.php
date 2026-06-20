<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Service de streaming IA avec OpenRouter.
 * Envoie la réponse token par token (comme ChatGPT).
 */
class SimpleAskStreamService
{
	public const DEFAULT_MODEL = 'openai/gpt-4o-mini';

	private string $apiKey;

	private string $baseUrl;

	public function __construct()
	{
		$this->apiKey = config('services.openrouter.api_key');
		$this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
	}

	/** Récupère la liste complète des modèles (cache 1h). */
	public function getModels(): array
	{
		return cache()->remember('openrouter.models', now()->addHour(), function (): array {
			$response = Http::withToken($this->apiKey)->get("{$this->baseUrl}/models");

			return collect($response->json('data', []))
				->sortBy('name')
				->map(fn (array $m): array => ['id' => $m['id'], 'name' => $m['name']])
				->values()
				->toArray();
		});
	}

	/** Version légère : uniquement id et name (pour le frontend). */
	public function getModelsLight(): array
	{
		return $this->getModels();
	}

	/**
	 * Génère les chunks du stream sous forme de Generator PHP.
	 * Utilisation: foreach ($service->streamChunks($messages, $model) as $chunk) { ... }
	 *
	 * @param array $messages Historique [['role'=>'user','content'=>'...']]
	 * @param string $model Identifiant du modèle OpenRouter
	 */
	public function streamChunks(array $messages, ?string $model = null): Generator
	{
		$response = $this->sendStreamRequest($messages, $model);

		if ($response->failed()) {
			yield '[ERROR] ' . $response->json('error.message', 'Erreur inconnue');

			return;
		}

		foreach ($this->parseSSEStream($response->toPsrResponse()->getBody()) as $event) {
			if ($event['type'] === 'error') {
				yield '[ERROR] ' . $event['data'];

				return;
			}

			if ($event['type'] === 'content' && $event['data']) {
				yield $event['data'];
			}

			if ($event['type'] === 'reasoning' && $event['data']) {
				yield '[REASONING]' . $event['data'] . '[/REASONING]';
			}
		}
	}

	// ─── MÉTHODES PRIVÉES ─────────────────────────────────────────────────────

	/** Envoie la requête HTTP streaming vers OpenRouter. */
	private function sendStreamRequest(array $messages, ?string $model): \Illuminate\Http\Client\Response
	{
		$allMessages = [$this->getSystemPrompt(), ...$messages];

		return Http::withToken($this->apiKey)
			->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')])
			->withOptions(['stream' => true])
			->timeout(120)
			->post("{$this->baseUrl}/chat/completions", [
				'model' => $model ?? self::DEFAULT_MODEL,
				'messages' => $allMessages,
				'stream' => true,
				'temperature' => 1.0,
			]);
	}

	/** Lit le stream SSE et yield les événements parsés. */
	private function parseSSEStream(StreamInterface $body): Generator
	{
		$buffer = '';

		while (!$body->eof()) {
			$buffer .= $body->read(1024);

			while (($pos = strpos($buffer, "\n")) !== false) {
				$line = trim(substr($buffer, 0, $pos));
				$buffer = substr($buffer, $pos + 1);

				if ($event = $this->parseSSELine($line)) {
					yield $event;
				}
			}
		}
	}

	/** Parse une ligne SSE. */
	private function parseSSELine(string $line): ?array
	{
		if ($line === '' || str_starts_with($line, ':')) {
			return null;
		}

		if (!str_starts_with($line, 'data: ')) {
			return null;
		}

		$data = substr($line, 6);

		if ($data === '[DONE]') {
			return ['type' => 'done', 'data' => null];
		}

		return $this->parseJSON($data);
	}

	/** Parse le JSON d'un chunk SSE. */
	private function parseJSON(string $json): ?array
	{
		try {
			$parsed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

			if (isset($parsed['error'])) {
				return ['type' => 'error', 'data' => $parsed['error']['message'] ?? 'Erreur'];
			}

			$delta = $parsed['choices'][0]['delta'] ?? [];

			if (!empty($delta['content'])) {
				return ['type' => 'content', 'data' => $delta['content']];
			}

			if (!empty($delta['reasoning'])) {
				return ['type' => 'reasoning', 'data' => $delta['reasoning']];
			}

			return null;
		} catch (\JsonException) {
			return null;
		}
	}

	/**
	 * Construit le system prompt avec personnalité Gandalf Mélenchon
	 * + instructions personnalisées de l'utilisateur connecté.
	 */
	private function getSystemPrompt(): array
	{
		$user = auth()->user();
		$now = now()->locale('fr')->format('l d F Y H:i');
		$customInstruction = $user?->customInstruction;

		return [
			'role' => 'system',
			'content' => view('prompts.system', [
				'now' => $now,
				'user' => $user?->name ?? 'un aventurier',
				'aboutUser' => $customInstruction?->about_user ?? '',
				'behavior' => $customInstruction?->behavior ?? '',
			])->render(),
		];
	}
}
