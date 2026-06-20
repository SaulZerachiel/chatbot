<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useStream } from '@laravel/stream-vue'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'

const props = defineProps({
	conversations: { type: Array, default: () => [] },
	currentConversation: { type: Object, default: null },
	messages: { type: Array, default: () => [] },
	models: { type: Array, default: () => [] },
	selectedModel: { type: String, default: 'google/gemini-flash-1.5' },
})

const userMessage = ref('')
const isStreaming = ref(false)
const rawStreamedContent = ref('')
const currentConvId = ref(props.currentConversation?.id ?? null)
const chosenModel = ref(props.selectedModel)
const messagesEl = ref(null)
const localMessages = ref([...props.messages])

// Sync localMessages quand Inertia recharge les props
watch(() => props.messages, (newMessages) => {
    // Ne pas écraser si on est en train de streamer
    if (!isStreaming.value) {
        localMessages.value = [...newMessages]
    }
}, { deep: true })

// Toggle sombre/clair
const isDark = ref(true)
function toggleTheme() { isDark.value = !isDark.value }

// Recherche dans les conversations
const search = ref('')
const filteredConversations = computed(() =>
	props.conversations.filter(c =>
		(c.title ?? '').toLowerCase().includes(search.value.toLowerCase())
	)
)

// Menu 3 points
const openMenuId = ref(null)
const renamingId = ref(null)
const renameValue = ref('')

function toggleMenu(convId, e) {
	e.stopPropagation()
	e.preventDefault()
	openMenuId.value = openMenuId.value === convId ? null : convId
}

function startRename(conv, e) {
	e.stopPropagation()
	renamingId.value = conv.id
	renameValue.value = conv.title ?? ''
	openMenuId.value = null
}

function confirmRename(conv) {
	if (!renameValue.value.trim()) return
	router.patch(route('chat.rename', conv.id), { title: renameValue.value.trim() }, {
		preserveScroll: true,
		onSuccess: () => { renamingId.value = null }
	})
}

function deleteConversation(conv, e) {
	e.stopPropagation()
	openMenuId.value = null
	router.delete(route('chat.destroy', conv.id))
}

function closeMenu() { openMenuId.value = null }

const md = new MarkdownIt({
	highlight(str, lang) {
		if (lang && hljs.getLanguage(lang)) {
			try {
				return '<pre class="hljs"><code>'
					+ hljs.highlight(str, { language: lang }).value
					+ '</code></pre>'
			} catch (_) {}
		}
		return '<pre class="hljs"><code>'
			+ md.utils.escapeHtml(str)
			+ '</code></pre>'
	},
})

const { send } = useStream(route('chat.stream'), {
	onData(event) {
		const text = typeof event === 'string' ? event : (event.value ?? event.data ?? '')
		
		// Extraire les données après "data: " et avant "\n\n"
		const lines = text.split('\n\n')
		for (const line of lines) {
			if (!line.startsWith('data: ')) continue
			const content = line.slice(6) // Enlève "data: "
			
			const convMatch = content.match(/\[CONV_ID:(\d+)\]/)
			if (convMatch) {
				currentConvId.value = parseInt(convMatch[1], 10)
				continue
			}
			
			// Ignorer le reasoning
			if (content.startsWith('[REASONING]')) continue
			
			rawStreamedContent.value += content
		}
	},
	onFinish() {
		isStreaming.value = false
		if (currentConvId.value) {
			// Si on est déjà sur la bonne conversation, juste recharger les données
			if (props.currentConversation?.id === currentConvId.value) {
				router.reload({
					only: ['messages', 'conversations'],
					preserveScroll: true,
				})
			} else {
				// Nouvelle conversation : naviguer vers elle
				router.visit(route('chat.show', currentConvId.value), {
					preserveScroll: true,
				})
			}
		}
	},
	onError() {
		isStreaming.value = false
		rawStreamedContent.value += '\n\n*[Erreur — Vous ne passerez pas !]*'
	},
})

const streamedContent = computed(() => rawStreamedContent.value.trim())

async function sendMessage() {
	const msg = userMessage.value.trim()
	if (!msg || isStreaming.value) return

	// Afficher immédiatement le message de l'utilisateur
	localMessages.value.push({ id: Date.now(), role: 'user', content: msg })

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
	localMessages.value = []
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

function exportMarkdown() {
	const lines = localMessages.value.map(m =>
		`**${m.role === 'user' ? 'Moi' : 'Gandalf Mélenchon'}**\n\n${m.content}`
	).join('\n\n---\n\n')
	const blob = new Blob([lines], { type: 'text/markdown' })
	const a = document.createElement('a')
	a.href = URL.createObjectURL(blob)
	a.download = `conversation-${currentConvId.value}.md`
	a.click()
}
</script>

<template>
	<div :class="isDark ? 'bg-gray-950 text-gray-100' : 'bg-white text-gray-900'" class="flex h-screen" @click="closeMenu">

		<!-- SIDEBAR -->
		<aside :class="isDark ? 'bg-gray-900 border-gray-800' : 'bg-gray-100 border-gray-200'" class="flex w-64 shrink-0 flex-col border-r">

			<div class="p-3 space-y-1">
				<button
					class="w-full rounded-lg bg-purple-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-purple-600"
					@click="newConversation"
				>
					Nouvelle conversation
				</button>
				<button
					v-if="localMessages.length > 0"
					@click="exportMarkdown"
					:class="isDark ? 'border-gray-700 text-gray-400 hover:text-purple-400' : 'border-gray-300 text-gray-500 hover:text-purple-600'"
					class="w-full rounded-lg border px-3 py-2 text-xs transition"
				>
					Exporter en Markdown
				</button>
			</div>

			<div class="px-3 pb-2">
				<label :class="isDark ? 'text-gray-400' : 'text-gray-500'" class="mb-1 block text-xs">Modèle IA</label>
				<select
					v-model="chosenModel"
					:class="isDark ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-800'"
					class="w-full rounded border px-2 py-1 text-xs focus:outline-none"
				>
					<option v-for="m in models" :key="m.id" :value="m.id">
						{{ m.name }}
					</option>
				</select>
			</div>

			<!-- Barre de recherche -->
			<div class="px-3 pb-2">
				<input
					v-model="search"
					type="text"
					placeholder="Rechercher..."
					:class="isDark ? 'bg-gray-800 border-gray-700 text-gray-200 placeholder-gray-500' : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400'"
					class="w-full rounded border px-2 py-1 text-xs focus:outline-none"
				/>
			</div>

			<!-- Liste des conversations -->
			<nav class="flex-1 space-y-1 overflow-y-auto px-2">
				<div
					v-for="conv in filteredConversations"
					:key="conv.id"
					class="group relative"
				>
					<!-- Mode renommage -->
					<div v-if="renamingId === conv.id" class="flex items-center gap-1 px-2 py-1">
						<input
							v-model="renameValue"
							@keydown.enter="confirmRename(conv)"
							@keydown.escape="renamingId = null"
							class="flex-1 rounded border border-purple-500 bg-gray-800 px-2 py-1 text-xs text-gray-100 focus:outline-none"
							autofocus
						/>
						<button @click="confirmRename(conv)" class="text-green-400 hover:text-green-300 text-xs">✓</button>
						<button @click="renamingId = null" class="text-gray-500 hover:text-gray-300 text-xs">✕</button>
					</div>

					<!-- Mode normal -->
					<div
						v-else
						:class="currentConversation?.id === conv.id
							? 'bg-gray-700 text-white'
							: isDark ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-600 hover:bg-gray-200'"
						class="flex cursor-pointer items-center rounded-lg px-3 py-2 text-sm transition"
						@click="router.visit(route('chat.show', conv.id))"
					>
						<span class="flex-1 truncate">{{ conv.title ?? 'Nouvelle conversation' }}</span>

						<!-- Bouton 3 points -->
						<button
							@click="toggleMenu(conv.id, $event)"
							class="ml-1 shrink-0 rounded p-0.5 text-gray-500 opacity-0 transition hover:text-gray-300 group-hover:opacity-100"
							:class="openMenuId === conv.id ? 'opacity-100' : ''"
						>
							⋯
						</button>
					</div>

					<!-- Menu dropdown -->
					<div
						v-if="openMenuId === conv.id"
						:class="isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'"
						class="absolute left-2 right-2 top-full z-10 rounded-lg border shadow-lg"
						@click.stop
					>
						<button
							@click="startRename(conv, $event)"
							:class="isDark ? 'text-gray-200 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-100'"
							class="flex w-full items-center gap-2 px-3 py-2 text-xs transition"
						>
							Renommer
						</button>
						<button
							@click="deleteConversation(conv, $event)"
							class="flex w-full items-center gap-2 px-3 py-2 text-xs text-red-400 transition hover:bg-red-900/20"
						>
							Supprimer
						</button>
					</div>
				</div>
			</nav>

			<!-- Bas de sidebar -->
			<div :class="isDark ? 'border-gray-800' : 'border-gray-200'" class="border-t p-3 space-y-2">
				<a
					:href="route('instructions.index')"
					:class="isDark ? 'text-gray-500 hover:text-purple-400' : 'text-gray-400 hover:text-purple-600'"
					class="block text-xs transition"
					@click.prevent="router.visit(route('instructions.index'))"
				>
					Instructions personnalisées
				</a>
				<button
					@click="toggleTheme"
					:class="isDark ? 'text-gray-500 hover:text-purple-400' : 'text-gray-400 hover:text-purple-600'"
					class="block text-xs transition"
				>
					{{ isDark ? '☀️ Mode clair' : '🌙 Mode sombre' }}
				</button>
			</div>
		</aside>

		<!-- ZONE PRINCIPALE -->
		<main class="flex flex-1 flex-col overflow-hidden">
			<div ref="messagesEl" class="flex-1 space-y-4 overflow-y-auto p-6">

				<!-- Message de bienvenue -->
				<div
					v-if="localMessages.length === 0 && !isStreaming"
					class="flex h-full flex-col items-center justify-center text-center"
				>
					<div class="mb-4 text-6xl">🧙‍♂️</div>
					<h2 class="text-xl font-bold text-purple-400">Gandalf Mélenchon</h2>
					<p :class="isDark ? 'text-gray-500' : 'text-gray-400'" class="mt-2 text-sm">
						Parlez, Camarades Hobbits ! Je suis prêt à vous éclairer<br>
						de la flamme d'Udûn et de la conscience populaire !
					</p>
				</div>

				<!-- Messages -->
				<div v-for="msg in localMessages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
					<div
						class="max-w-2xl rounded-2xl px-4 py-3 text-sm"
						:class="msg.role === 'user'
							? 'bg-purple-700 text-white'
							: isDark ? 'bg-gray-800 text-gray-100' : 'bg-gray-100 text-gray-800'"
					>
						<div
							v-if="msg.role === 'assistant'"
							class="prose prose-sm max-w-none"
							:class="isDark ? 'prose-invert' : ''"
							v-html="renderMarkdown(msg.content)"
						></div>
						<div v-else>{{ msg.content }}</div>
					</div>
				</div>

				<!-- Réponse en streaming -->
				<div v-if="isStreaming" class="flex justify-start">
					<div :class="isDark ? 'bg-gray-800 text-gray-100' : 'bg-gray-100 text-gray-800'" class="max-w-2xl rounded-2xl px-4 py-3 text-sm">
						<div
							v-if="streamedContent"
							class="prose prose-sm max-w-none"
							:class="isDark ? 'prose-invert' : ''"
							v-html="renderMarkdown(streamedContent)"
						></div>
						<div v-else class="flex items-center gap-2 text-gray-400">
							<span class="animate-pulse">🧙‍♂️</span>
							<span class="text-xs">Gandalf Mélenchon réfléchit...</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Zone de saisie -->
			<div :class="isDark ? 'border-gray-800 bg-gray-900' : 'border-gray-200 bg-gray-50'" class="border-t p-4">
				<div class="flex items-end gap-3">
					<textarea
						v-model="userMessage"
						:disabled="isStreaming"
						:class="isDark
							? 'border-gray-700 bg-gray-800 text-gray-100 placeholder-gray-500 focus:border-purple-500'
							: 'border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-purple-400'"
						class="flex-1 resize-none rounded-xl border px-4 py-3 text-sm focus:outline-none disabled:opacity-50"
						:rows="2"
						placeholder="Interrogez Gandalf Mélenchon... (Entrée pour envoyer)"
						@keydown="onKeydown"
					></textarea>
					<button
						:disabled="isStreaming || !userMessage.trim()"
						class="rounded-xl bg-purple-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-purple-600 disabled:cursor-not-allowed disabled:opacity-40"
						@click="sendMessage"
					>
						{{ isStreaming ? '⏳' : 'Envoyer' }}
					</button>
				</div>
				<p :class="isDark ? 'text-gray-600' : 'text-gray-400'" class="mt-1 text-right text-xs">
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