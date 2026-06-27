<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    editors: { id: number; name: string }[];
    selectedIds: number[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', editorIds: number[]): void;
}>();

const selected = ref<number[]>([...props.selectedIds]);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selected.value = [...props.selectedIds];
        }
    },
);

function toggle(editorId: number, checked: boolean) {
    if (checked) {
        selected.value.push(editorId);
    } else {
        selected.value = selected.value.filter((id) => id !== editorId);
    }
}

function submit() {
    emit('submit', selected.value);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <div class="max-h-[320px] space-y-2 overflow-y-auto">
                <div v-if="editors.length === 0" class="text-sm text-slate-500">No editors found.</div>

                <label
                    v-for="editor in editors"
                    :key="editor.id"
                    class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50"
                >
                    <Checkbox
                        :model-value="selected.includes(editor.id)"
                        @update:model-value="(checked) => toggle(editor.id, checked === true)"
                    />
                    <span class="text-sm font-medium text-slate-900">{{ editor.name }}</span>
                </label>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button @click="submit">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
