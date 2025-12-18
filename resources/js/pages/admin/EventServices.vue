<script setup lang="ts">

// import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
// import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import EventBasicForm from '@/components/forms/EventBasicForm.vue';
import EventPremiumForm from '@/components/forms/EventPremiumForm.vue';
import EventLuxuryForm from '@/components/forms/EventLuxuryForm.vue';
// import TalkingHeadsForm from '@/components/forms/TalkingHeadsForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, Services } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const { services } = usePage<AppPageProps<{ services: Services[] }>>().props;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Event Services',
        href: '/event-services',
    },
];

const selectedService = ref<Services | null>(null);

const isImageLink = (link?: string | null) => {
    if (!link) return false;
    return link.startsWith('/images/') || /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i.test(link);
};

const normalizeVideoSrc = (link: string) => link.replace('watch?v=', 'embed/');

const isStyle = (style: string): style is 'Event Basic Style' | 'Event Deluxe Style' | 'Event Premium Style' | 'Event Luxury Style' | 'Event Talking Heads' => {
    return ['Event Basic Style', 'Event Deluxe Style', 'Event Premium Style', 'Event Luxury Style', 'Event Talking Heads'].includes(style);
};

function openModal(service: Services) {
    if (isStyle(service.name)) {
        selectedService.value = service;
    }
}

function closeModal() {
    selectedService.value = null;
}
</script>

<template>
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Event Services" />
        <div class="grid gap-4 p-4 md:grid-cols-3">
            <div v-for="service in services" :key="service.id" class="flex h-full min-h-[450px] flex-col rounded-xl border bg-white shadow">
                <!-- Image/Video at top (no padding) -->
                <div v-if="service.video_link" class="h-[320px] w-full flex-shrink-0">
                    <img
                        v-if="isImageLink(service.video_link)"
                        class="h-full w-full rounded-t-xl object-cover"
                        :src="service.video_link"
                        :alt="service.name"
                        loading="lazy"
                    />
                    <iframe
                        v-else
                        class="h-full w-full rounded-t-xl"
                        :src="normalizeVideoSrc(service.video_link)"
                        :title="service.name"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>

                <!-- Card content with padding -->
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="mb-2 text-lg font-semibold">{{ service.name }}</h3>

                    <ul class="list-inside list-disc text-sm text-muted-foreground">
                        <li v-for="(feature, index) in service.features" :key="index">
                            {{ feature }}
                        </li>
                    </ul>

                    <div class="mt-auto">
                        <!-- <p class="mb-2 font-bold text-primary">${{ service.price }}</p> -->
                        <Button class="w-full" @click="openModal(service)">Select Style</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <EventBasicForm
            v-if="selectedService?.name === 'Event Basic Style'"
            :open="true"
            :base-price="selectedService.price"
            @close="closeModal"
            :service-id="selectedService.id"
        />

        <EventPremiumForm
            v-if="selectedService?.name === 'Event Premium Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />

        <EventLuxuryForm
            v-if="selectedService?.name === 'Event Luxury Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />
    </AppLayout>
</template>
