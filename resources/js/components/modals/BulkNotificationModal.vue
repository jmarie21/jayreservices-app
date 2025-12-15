<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const subject = ref('');
const message = ref('');
const loading = ref(false);
const stats = ref<{ clientsCount: number; totalEmails: number } | null>(null);
const loadingStats = ref(false);
const selectedTemplate = ref<string | null>(null);

// Fetch stats when modal opens
watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        loadingStats.value = true;
        try {
            const response = await fetch(route('bulk-notification.stats'));
            stats.value = await response.json();
        } catch (error) {
            console.error('Failed to fetch stats:', error);
        } finally {
            loadingStats.value = false;
        }
    } else {
        // Reset form when closing
        subject.value = '';
        message.value = '';
        stats.value = null;
        selectedTemplate.value = null;
    }
});

const sendNotification = () => {
    if (!subject.value.trim() || !message.value.trim()) {
        toast.error('Please fill in both subject and message.', { position: 'top-right' });
        return;
    }

    loading.value = true;

    router.post(
        route('bulk-notification.send'),
        {
            subject: subject.value,
            message: message.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Announcement sent to all clients!', { position: 'top-right' });
                emit('close');
                subject.value = '';
                message.value = '';
            },
            onError: (errors) => {
                toast.error('Failed to send announcement. Please try again.', { position: 'top-right' });
                console.error(errors);
            },
            onFinish: () => {
                loading.value = false;
            },
        }
    );
};

const closeModal = () => {
    emit('close');
};

// Quick templates for common scenarios
const templates = [
    {
        name: 'Typhoon Warning',
        subject: '⚠️ Important: Typhoon Advisory - Service Delay Notice',
        message: `Due to an approaching typhoon, we wanted to inform you that our services may experience some delays.

Our team's safety is our priority, and we may need to temporarily suspend operations during severe weather conditions.

We will keep you updated on the status of your projects and resume normal operations as soon as it is safe to do so.

Please stay safe and take necessary precautions.`,
    },
    {
        name: 'Power Outage',
        subject: '🔌 Notice: Scheduled Power Interruption',
        message: `We would like to inform you that our area will be experiencing a scheduled power interruption.

During this time, our operations may be temporarily affected, and there might be delays in responding to your inquiries.

We apologize for any inconvenience this may cause and appreciate your understanding.

Normal operations will resume once power is restored.`,
    },
    {
        name: 'Holiday Notice',
        subject: '🎉 Holiday Schedule Notice',
        message: `We hope this message finds you well!

Please be informed that our office will be closed for the upcoming holiday.

During this period, response times may be longer than usual. Rest assured, we will attend to all inquiries and continue work on your projects once we return.

Thank you for your understanding and continued support.

Wishing you a wonderful holiday!`,
    },
];

const applyTemplate = (template: typeof templates[0]) => {
    subject.value = template.subject;
    message.value = template.message;
    selectedTemplate.value = template.name;
};
</script>

<template>
    <Dialog :open="open" @update:open="closeModal">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <span>📢</span>
                    Send Bulk Announcement
                </DialogTitle>
                <DialogDescription>
                    Send an email notification to all clients including their additional email addresses.
                </DialogDescription>
            </DialogHeader>

            <!-- Stats Display -->
            <div v-if="loadingStats" class="text-sm text-gray-500">
                Loading recipient information...
            </div>
            <div v-else-if="stats" class="rounded-lg bg-blue-50 p-3 text-sm">
                <p class="font-medium text-blue-800">
                    📧 This will send to <strong>{{ stats.totalEmails }}</strong> email addresses
                    across <strong>{{ stats.clientsCount }}</strong> clients.
                </p>
            </div>

            <!-- Quick Templates -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">Quick Templates:</Label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="template in templates"
                        :key="template.name"
                        :variant="selectedTemplate === template.name ? 'default' : 'outline'"
                        size="sm"
                        @click="applyTemplate(template)"
                    >
                        {{ template.name }}
                    </Button>
                </div>
            </div>

            <!-- Form Fields -->
            <div class="space-y-4">
                <div class="space-y-2">
                    <Label for="subject">Subject</Label>
                    <Input
                        id="subject"
                        v-model="subject"
                        placeholder="Enter email subject..."
                        :disabled="loading"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="message">Message</Label>
                    <Textarea
                        id="message"
                        v-model="message"
                        placeholder="Enter your announcement message..."
                        :disabled="loading"
                        class="min-h-[200px]"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeModal" :disabled="loading">
                    Cancel
                </Button>
                <Button @click="sendNotification" :disabled="loading || !subject.trim() || !message.trim()">
                    <span v-if="loading">Sending...</span>
                    <span v-else>Send to All Clients</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
