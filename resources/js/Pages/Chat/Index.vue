<script setup>
import { computed, nextTick, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useStream } from '@laravel/stream-vue'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'

const props = defineProps({
	conversations: { type: Array, default: () => [] },
	currentConversation: { type: Object, default: null },
	messages: { type: Array, default: () => [] },
	models: { type: Array, default: () => [] },
	selectedModel: { type: String, default: 'openai/gpt-4o-mini' },
})

const userMessage = ref('')
const isStreaming = ref(false)
const rawStreamedContent = ref('')
const currentConvId = ref(props.currentConversation?.id ?? null)
const chosenModel = ref(props.selectedModel)
const messagesEl = ref(null)

const md = new MarkdownIt({
	highlight(str, lang) {
		if (lang && hljs.getLanguage(lang)) {
			try {
				return '<pre class="hljs"><code>'
					+ hljs.highlight(str, { language: lang }).value
					+ '</code></pre>'
			} catch (_) {
			}
		}

		return '<pre class="hljs"><code>'
			+ md.utils.escapeHtml(str)
			+ '</code></pre>'
	},
})

const { send } = useStream(route('chat.stream'), {
	onData(event) {
		const text = event.value ?? ''
		const convMatch = text.match(/\[CONV_ID:(\d+)\]/)

		if (convMatch) {
			currentConvId.value = parseInt(convMatch[1], 10)
		}

		rawStreamedContent.value += text
	},
	onFinish() {
		isStreaming.value = false

		if (currentConvId.value) {
			router.visit(route('chat.show', currentConvId.value))
		}
	},
	onError() {
		isStreaming.value = false
		rawStreamedContent.value += '\n\n*[Erreur — Vous ne passerez pas !]*'
	},
})

const streamedContent = computed(() =>
	rawStreamedContent.value
		.replace(/\[CONV_ID:\d+\]/g, '')
		.replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '')
		.trim(),
)

async function sendMessage() {
	const msg = userMessage.value.trim()

	if (!msg || isStreaming.value) return

	isStreaming.value = true
	rawStreamedContent.value = ''
	userMessage.value = ''

	send({
		message: msg,
		model: chosenModel.value,
		conversation_id: currentConvId.value,
	})

	await nextTick()
	messagesEl.value?.scrollTo({ top: messagesEl.value.scrollHeight, behavior: 'smooth' })
}

function newConversation() {
	currentConvId.value = null
	router.visit(route('chat.index'))
}

function renderMarkdown(text) {
	return md.render(text ?? '')
}

function onKeydown(e) {
	if (e.key === 'Enter' && !e.shiftKey) {
		e.preventDefault()
		sendMessage()
	}
}
</script>

<template>
	<div class="flex h-screen bg-gray-950 text-gray-100">
		<aside class="flex w-64 shrink-0 flex-col border-r border-gray-800 bg-gray-900">
			<div class="p-3">
				<button
					class="w-full rounded-lg bg-purple-700 px-3 py-2 text-sm font-semibold transition hover:bg-purple-600"
					@click="newConversation"
				>
					Nouvelle conversation
				</button>
			</div>

			<div class="px-3 pb-3">
				<label class="mb-1 block text-xs text-gray-400">Modèle IA</label>
				<select
					v-model="chosenModel"
					class="w-full rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-200 focus:outline-none"
				>
					<option v-for="m in models" :key="m.id" :value="m.id">
						{{ m.name }}
					</option>
				</select>
			</div>

			<nav class="flex-1 space-y-1 overflow-y-auto px-2">
				<a
					v-for="conv in conversations"
					:key="conv.id"
					:class="currentConversation?.id === conv.id ? 'bg-gray-700 text-white' : 'text-gray-400'"
					:href="route('chat.show', conv.id)"
					class="block cursor-pointer truncate rounded-lg px-3 py-2 text-sm transition hover:bg-gray-800"
					@click.prevent="router.visit(route('chat.show', conv.id))"
				>
					{{ conv.title ?? 'Nouvelle conversation' }}
				</a>
			</nav>

			<div class="border-t border-gray-800 p-3">
				<a
					:href="route('instructions.index')"
					class="block text-xs text-gray-500 transition hover:text-purple-400"
					@click.prevent="router.visit(route('instructions.index'))"
				>
					Instructions personnalisées
				</a>
			</div>
		</aside>

		<main class="flex flex-1 flex-col overflow-hidden">
			<div ref="messagesEl" class="flex-1 space-y-4 overflow-y-auto p-6">
				<div
					v-if="messages.length === 0 && !isStreaming"
					class="flex h-full flex-col items-center justify-center text-center"
				>
					<div class="mb-4 text-6xl"></div>
					<h2 class="text-xl font-bold text-purple-400">Gandalf Mélenchon</h2>
					<p class="mt-2 text-sm text-gray-500">
						Parlez, Camarades Hobbits ! Je suis prêt à vous éclairer<br>
						de la flamme d'Udûn et de la conscience populaire !
					</p>
				</div>

				<div v-for="msg in messages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
					<div
						class="max-w-2xl rounded-2xl px-4 py-3 text-sm"
						:class="msg.role === 'user' ? 'bg-purple-700 text-white' : 'bg-gray-800 text-gray-100'"
					>
						<div
							v-if="msg.role === 'assistant'"
							class="prose prose-invert prose-sm max-w-none"
							v-html="renderMarkdown(msg.content)"
						></div>
						<div v-else>{{ msg.content }}</div>
					</div>
				</div>

				<div v-if="isStreaming" class="flex justify-start">
					<div class="max-w-2xl rounded-2xl bg-gray-800 px-4 py-3 text-sm text-gray-100">
						<div
							v-if="streamedContent"
							class="prose prose-invert prose-sm max-w-none"
							v-html="renderMarkdown(streamedContent)"
						></div>
						<div v-else class="flex items-center gap-2 text-gray-400">
							<span class="animate-pulse"></span>
							<span class="text-xs">Gandalf Mélenchon réfléchit...</span>
						</div>
					</div>
				</div>
			</div>

			<div class="border-t border-gray-800 bg-gray-900 p-4">
				<div class="flex items-end gap-3">
					<textarea
						v-model="userMessage"
						:disabled="isStreaming"
						class="flex-1 resize-none rounded-xl border border-gray-700 bg-gray-800 px-4 py-3 text-sm text-gray-100 placeholder-gray-500 focus:border-purple-500 focus:outline-none disabled:opacity-50"
						:rows="2"
						placeholder="Interrogez Gandalf Mélenchon... (Entrée pour envoyer)"
						@keydown="onKeydown"
					></textarea>
					<button
						:disabled="isStreaming || !userMessage.trim()"
						class="rounded-xl bg-purple-700 px-5 py-3 text-sm font-semibold transition hover:bg-purple-600 disabled:cursor-not-allowed disabled:opacity-40"
						@click="sendMessage"
					>
						{{ isStreaming ? ' ' : 'Envoyer' }}
					</button>
				</div>

				<p class="mt-1 text-right text-xs text-gray-600">
					Shift+Entrée = saut de ligne
				</p>
			</div>
		</main>
	</div>
</template>

<style>
.hljs {
	background: #1e1e2e;
	border-radius: 6px;
	padding: 12px;
	overflow-x: auto;
}

.prose pre {
	background: transparent !important;
	padding: 0 !important;
}

.prose code {
	background: rgba(139, 92, 246, 0.15);
	border-radius: 4px;
	font-size: 0.85em;
	padding: 2px 5px;
}
</style>
