<script setup lang="ts">
import { ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../ui/dialog';

const props = defineProps<{
    open: boolean;
    onClose: () => void;
}>();

const formData = ref({
    videoLink: '',
    notes: '',
});

const handleSubmit = () => {
    console.log('Basic form submitted:', formData.value);
    props.onClose();
};
</script>
\

<template>
    <Dialog
        :open="open"
        @update:open="
            (v: any) => {
                if (!v) onClose();
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Order: Deluxe Style</DialogTitle>
            </DialogHeader>

            <input type="text" placeholder="Video Link" v-model="formData.videoLink" class="mt-2 w-full rounded border px-3 py-2" />

            <textarea placeholder="Notes" v-model="formData.notes" class="mt-2 w-full rounded border px-3 py-2" />

            <button @click="handleSubmit" class="mt-4 w-full rounded bg-black py-2 text-white">Submit</button>
        </DialogContent>
    </Dialog>
</template>
