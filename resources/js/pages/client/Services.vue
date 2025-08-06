<script setup lang="ts">
import BasicStyleForm from '@/components/forms/BasicStyleForm.vue';
import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, Services } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const { services } = usePage<AppPageProps<{ services: Services[] }>>().props;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services',
        href: '/services',
    },
];

const selectedService = ref<Services | null>(null);

const isStyle = (style: string): style is 'Basic Style' | 'Deluxe Style' | 'Premium Style' | 'Luxury Style' => {
    return ['Basic Style', 'Deluxe Style', 'Premium Style', 'Luxury Style'].includes(style);
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
        <Head title="Services" />
        <div class="grid gap-4 p-4 md:grid-cols-4">
            <div
                v-for="service in services"
                :key="service.id"
                class="flex h-full min-h-[350px] flex-col justify-between rounded-xl border bg-white p-4 shadow"
            >
                <div>
                    <h3 class="mb-2 text-lg font-semibold">{{ service.name }}</h3>

                    <ul class="list-inside list-disc text-sm text-muted-foreground">
                        <li v-for="(feature, index) in service.features" :key="index">
                            {{ feature }}
                        </li>
                    </ul>
                </div>

                <div class="mt-4">
                    <p class="mb-2 font-bold text-primary">₱{{ service.price }}</p>
                    <Button class="w-full" @click="openModal(service)">Select Style</Button>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <BasicStyleForm
            v-if="selectedService?.name === 'Basic Style'"
            :open="true"
            :base-price="selectedService.price"
            @close="closeModal"
            :service-id="selectedService.id"
        />
        <DeluxeStyleForm
            v-if="selectedService?.name === 'Deluxe Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />
        <PremiumStyleForm v-if="selectedService?.name === 'Premium Style'" :open="true" @close="closeModal" />
        <LuxuryStyleForm v-if="selectedService?.name === 'Luxury Style'" :open="true" @close="closeModal" />
    </AppLayout>
</template>
