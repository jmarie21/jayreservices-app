<script setup lang="ts">
import DynamicOrderForm from '@/components/forms/DynamicOrderForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ServiceCategory, ServicePricingData } from '@/types/services';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<{ category: ServiceCategory; clients: { id: number; name: string }[] }>();
const category = computed(() => page.props.category);
const selectedService = ref<ServicePricingData | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Services Management',
        href: '/admin-services',
    },
    {
        title: category.value.name,
        href: `/admin-services/${category.value.slug}`,
    },
]);

const isImageLink = (link?: string | null) => {
    if (!link) return false;
    return link.startsWith('/images/') || /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i.test(link);
};

const normalizeVideoSrc = (link: string) => link.replace('watch?v=', 'embed/');
</script>

<template>
    <Head :title="`${category.name} Catalog`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div class="max-w-3xl space-y-2">
                    <h1 class="text-3xl font-bold">{{ category.name }}</h1>
                    <p class="text-muted-foreground">Create admin orders with the same dynamic pricing data used by the public catalog.</p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/admin-services">Back to Management</Link>
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card
                    v-for="service in category.services"
                    :key="service.id"
                    class="flex h-full min-h-0 flex-col overflow-hidden gap-0 border-0 py-0 shadow-sm ring-1 ring-black/5"
                >
                    <div v-if="service.video_link" class="h-64 w-full overflow-hidden bg-slate-100 sm:h-72 xl:h-56 2xl:h-64">
                        <img
                            v-if="isImageLink(service.video_link)"
                            :src="service.video_link"
                            :alt="service.name"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <iframe
                            v-else
                            class="h-full w-full"
                            :src="normalizeVideoSrc(service.video_link)"
                            :title="service.name"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>

                    <CardContent class="flex min-h-0 flex-1 flex-col gap-4 p-6">
                        <div class="space-y-2">
                            <h2 class="text-xl font-semibold">{{ service.name }}</h2>
                            <p v-if="service.description" class="text-sm text-muted-foreground">{{ service.description }}</p>
                        </div>

                        <ul
                            v-if="service.features.length"
                            class="max-h-[clamp(10rem,24dvh,18rem)] flex-1 overflow-y-auto pr-2 list-disc space-y-2 pl-5 text-sm leading-6 text-muted-foreground marker:text-slate-400"
                        >
                            <li v-for="feature in service.features" :key="feature" class="pl-1">
                                {{ feature }}
                            </li>
                        </ul>

                        <div class="mt-auto flex shrink-0 justify-end border-t pt-4">
                            <Button @click="selectedService = service">Create Order</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <DynamicOrderForm v-if="selectedService" :open="true" :service="selectedService" @close="selectedService = null" />
    </AppLayout>
</template>
