<script setup lang="ts">
import { editorLevelDotClasses, editorLevelLabels, editorLevelOrder } from '@/lib/editor-level';
</script>

<template>
    <div class="flex items-center gap-3 text-sm text-slate-500">
        <span v-for="level in editorLevelOrder" :key="level" class="flex items-center gap-1.5">
            <span class="size-2.5 rounded-full" :class="editorLevelDotClasses[level]" />
            {{ editorLevelLabels[level] }}
        </span>
    </div>
</template>
