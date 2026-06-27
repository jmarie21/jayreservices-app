<script setup lang="ts">
import EditorLevelBadge from '@/components/EditorLevelBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { EditorLevel, User } from '@/types/app-page-prop';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import { toast } from 'vue-sonner';

const props = defineProps<{
    users: User[];
    levelField: 'editor_level' | 'recommended_editor_level';
    assignRouteName: string;
    entityNoun: string;
    editors?: { id: number; name: string }[];
    dedicatedEditorRouteName?: string;
}>();

type ColumnKey = EditorLevel | 'unassigned';

const columnDefinitions: { key: ColumnKey; title: string }[] = [
    { key: 'senior', title: 'Senior Level' },
    { key: 'mid', title: 'Mid Level' },
    { key: 'junior', title: 'Junior Level' },
    { key: 'unassigned', title: 'Unassigned' },
];

const columns = reactive<Record<ColumnKey, User[]>>({
    senior: [],
    mid: [],
    junior: [],
    unassigned: [],
});

function syncColumns() {
    columns.senior = [];
    columns.mid = [];
    columns.junior = [];
    columns.unassigned = [];

    for (const user of props.users) {
        const level = user[props.levelField] ?? 'unassigned';
        columns[level].push(user);
    }
}

watch(() => props.users, syncColumns, { immediate: true, deep: true });

function assign(level: ColumnKey, userIds: number[]) {
    router.patch(
        route(props.assignRouteName),
        {
            level: level === 'unassigned' ? null : level,
            user_ids: userIds,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                toast.error('Something went wrong', {
                    description: `Could not update the ${props.entityNoun}'s level. Please try again.`,
                });
                syncColumns();
            },
        },
    );
}

function onChange(level: ColumnKey, event: { added?: { element: User } }) {
    if (event.added) {
        assign(level, [event.added.element.id]);
    }
}

function updateDedicatedEditor(user: User, editorId: number | null) {
    if (!props.dedicatedEditorRouteName) {
        return;
    }

    const previousEditorId = user.dedicated_editor_id ?? null;
    user.dedicated_editor_id = editorId;

    router.patch(
        route(props.dedicatedEditorRouteName, user.id),
        { editor_id: editorId },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                user.dedicated_editor_id = previousEditorId;
                toast.error('Something went wrong', {
                    description: `Could not update ${user.name}'s dedicated editor. Please try again.`,
                });
            },
        },
    );
}

const addModalOpen = ref(false);
const addModalLevel = ref<ColumnKey>('unassigned');
const selectedUserIds = ref<number[]>([]);

function openAddModal(level: ColumnKey) {
    addModalLevel.value = level;
    selectedUserIds.value = [];
    addModalOpen.value = true;
}

function toggleSelected(userId: number, checked: boolean) {
    if (checked) {
        selectedUserIds.value.push(userId);
    } else {
        selectedUserIds.value = selectedUserIds.value.filter((id) => id !== userId);
    }
}

function submitAddModal() {
    if (selectedUserIds.value.length === 0) {
        addModalOpen.value = false;
        return;
    }

    assign(addModalLevel.value, selectedUserIds.value);
    addModalOpen.value = false;
}

const addModalTitle = computed(() => {
    const definition = columnDefinitions.find((column) => column.key === addModalLevel.value);

    return `Add ${props.entityNoun}s to ${definition?.title ?? ''}`;
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div v-for="column in columnDefinitions" :key="column.key" class="rounded-xl border border-slate-200 bg-slate-50">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div class="space-y-0.5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ column.title }}</h2>
                    <p class="text-xs text-slate-500">{{ columns[column.key].length }} {{ props.entityNoun }}(s)</p>
                </div>
                <Button size="sm" variant="outline" @click="openAddModal(column.key)">+ Add</Button>
            </div>

            <draggable
                :list="columns[column.key]"
                group="levels"
                item-key="id"
                class="flex max-h-[60vh] min-h-[120px] flex-col gap-2 overflow-y-auto p-3"
                ghost-class="opacity-50"
                filter=".level-board-no-drag"
                :prevent-on-filter="false"
                @change="(event: any) => onChange(column.key, event)"
            >
                <template #item="{ element }: { element: User }">
                    <div class="cursor-grab rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm active:cursor-grabbing">
                        <p class="text-sm font-medium text-slate-900">{{ element.name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ element.email }}</p>

                        <div v-if="props.editors" class="level-board-no-drag mt-2 cursor-default">
                            <Label class="text-xs text-slate-500">Dedicated editor</Label>
                            <Select
                                :modelValue="element.dedicated_editor_id ?? null"
                                @update:modelValue="(value) => updateDedicatedEditor(element, value as number | null)"
                            >
                                <SelectTrigger class="h-8 w-full text-xs">
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">None</SelectItem>
                                    <SelectItem v-for="editor in props.editors" :key="editor.id" :value="editor.id">
                                        {{ editor.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </template>
            </draggable>
        </div>
    </div>

    <Dialog :open="addModalOpen" @update:open="(value) => (addModalOpen = value)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ addModalTitle }}</DialogTitle>
            </DialogHeader>

            <div class="max-h-[320px] space-y-2 overflow-y-auto">
                <div v-if="props.users.length === 0" class="text-sm text-slate-500">No {{ props.entityNoun }}s found.</div>

                <label
                    v-for="user in props.users"
                    :key="user.id"
                    class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50"
                >
                    <Checkbox
                        :model-value="selectedUserIds.includes(user.id)"
                        @update:model-value="(checked) => toggleSelected(user.id, checked === true)"
                    />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-900">{{ user.name }}</p>
                        <p class="text-xs text-slate-500">{{ user.email }}</p>
                    </div>
                    <EditorLevelBadge :level="(user[props.levelField] ?? null) as EditorLevel | null" />
                </label>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="addModalOpen = false">Cancel</Button>
                <Button :disabled="selectedUserIds.length === 0" @click="submitAddModal">Add</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
