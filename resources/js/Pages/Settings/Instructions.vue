<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    instruction: { type: Object, default: null },
})

const aboutUser = ref(props.instruction?.about_user ?? '')
const behavior = ref(props.instruction?.behavior ?? '')
const saved = ref(false)

function save() {
    router.post(route('instructions.update'), {
        about_user: aboutUser.value,
        behavior: behavior.value,
    }, {
        onSuccess: () => {
            saved.value = true
            setTimeout(() => {
                saved.value = false
            }, 3000)
        },
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-10">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-purple-400">Instructions personnalisées</h1>
                <p class="mt-2 text-sm text-gray-400">
                    Gandalf Mélenchon adaptera ses réponses à vos préférences.
                </p>
            </div>

            <div class="space-y-6 rounded-2xl border border-gray-800 bg-gray-900 p-6 shadow-xl">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">Qui êtes-vous ?</label>
                    <textarea
                        v-model="aboutUser"
                        rows="5"
                        class="w-full rounded-xl border border-gray-700 bg-gray-800 px-4 py-3 text-sm text-gray-100 placeholder-gray-500 focus:border-purple-500 focus:outline-none"
                        placeholder="Ex: Étudiant en développement web, 2e année..."
                    ></textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-300">Comment doit répondre Gandalf Mélenchon ?</label>
                    <textarea
                        v-model="behavior"
                        rows="5"
                        class="w-full rounded-xl border border-gray-700 bg-gray-800 px-4 py-3 text-sm text-gray-100 placeholder-gray-500 focus:border-purple-500 focus:outline-none"
                        placeholder="Ex: Répondre avec des exemples de code PHP/Laravel..."
                    ></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        class="rounded-xl bg-purple-700 px-5 py-3 text-sm font-semibold transition hover:bg-purple-600"
                        @click="save"
                    >
                        Sauvegarder
                    </button>

                    <a
                        :href="route('chat.index')"
                        class="rounded-xl border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-300 transition hover:bg-gray-800"
                        @click.prevent="router.visit(route('chat.index'))"
                    >
                        Retour au chat
                    </a>

                    <span v-if="saved" class="text-sm text-emerald-400">Instructions sauvegardées !</span>
                </div>
            </div>
        </div>
    </div>
</template>
