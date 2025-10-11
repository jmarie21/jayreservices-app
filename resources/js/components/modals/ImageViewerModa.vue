<script setup lang="ts">
import { defineEmits, defineProps, onMounted } from 'vue';

const props = defineProps<{ src: string }>();
const emit = defineEmits(['close']);
const visible = true;

function emitClose() {
    emit('close');
}

// Close on Escape key
onMounted(() => {
    const handle = (e: KeyboardEvent) => {
        if (e.key === 'Escape') emitClose();
    };
    window.addEventListener('keydown', handle);
    return () => window.removeEventListener('keydown', handle);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="visible" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 backdrop-blur-sm" @click.self="emitClose">
                <button
                    @click="emitClose"
                    class="absolute top-4 right-4 z-[10000] rounded-full bg-white/10 p-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                >
                    ✕
                </button>
                <img :src="src" class="max-h-[90vh] max-w-[90vw] object-contain" />
            </div>
        </Transition>
    </Teleport>
</template>
