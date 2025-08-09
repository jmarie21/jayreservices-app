<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Projects } from '@/types';

defineProps<{
    open: boolean;
    project: Projects;
}>();

defineEmits(['close']);
</script>

<template>
    <Dialog :open="open" @close="$emit('close')">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Project Details</DialogTitle>
            </DialogHeader>
            <div class="space-y-2">
                <p><strong>Service:</strong> {{ project.service?.name }}</p>
                <p><strong>Project Name:</strong> {{ project.project_name }}</p>
                <p><strong>Style:</strong> {{ project.style }}</p>
                <p><strong>Company:</strong> {{ project.company_name }}</p>
                <p><strong>Contact:</strong> {{ project.contact }}</p>
                <p><strong>Status:</strong> {{ project.status }}</p>
                <p><strong>Total Price:</strong> ${{ project.total_price }}</p>
                <p>
                    <strong>File Link:</strong>
                    <a :href="project.file_link" target="_blank" class="text-blue-500 underline underline-offset-3">Raw Files Link</a>
                </p>
                <p>
                    <strong>Output Link:</strong>
                    <a :href="project.output_link" target="_blank" class="text-blue-500 underline underline-offset-3"
                        ><span v-if="project.output_link">Finished Output Link</span></a
                    >
                </p>
                <p><strong>Notes:</strong> {{ project.notes || 'N/A' }}</p>
            </div>
            <div class="mt-4 flex justify-end">
                <Button @click="$emit('close')">Close</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
