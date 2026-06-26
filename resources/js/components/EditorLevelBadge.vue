<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { editorLevelBadgeClasses, editorLevelLabels } from '@/lib/editor-level';
import type { EditorLevel } from '@/types/app-page-prop';
import { computed } from 'vue';

const props = defineProps<{
    level?: EditorLevel | null;
}>();

const label = computed(() => (props.level ? editorLevelLabels[props.level] : 'Unassigned'));
const classes = computed(() => (props.level ? editorLevelBadgeClasses[props.level] : 'bg-slate-200 text-slate-700'));
</script>

<template>
    <Badge :class="classes">{{ label }}</Badge>
</template>
