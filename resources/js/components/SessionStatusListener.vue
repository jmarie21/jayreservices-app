<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';

const page = usePage<AppPageProps>();
const userId = computed(() => page.props.auth?.user?.id ?? null);

let activeChannelName: string | null = null;

const leaveActiveChannel = () => {
    if (!activeChannelName || !window.Echo) {
        activeChannelName = null;
        return;
    }

    window.Echo.leave(`private-${activeChannelName}`);
    activeChannelName = null;
};

const subscribeToDeactivation = (nextUserId: number | null) => {
    leaveActiveChannel();

    if (!nextUserId || !window.Echo) {
        return;
    }

    activeChannelName = `App.Models.User.${nextUserId}`;

    window.Echo.private(activeChannelName).listen('.user.deactivated', (payload: { login_url?: string }) => {
        window.location.assign(payload?.login_url ?? '/login?inactive=1');
    });
};

watch(userId, (nextUserId) => {
    subscribeToDeactivation(nextUserId);
}, { immediate: true });

onBeforeUnmount(() => {
    leaveActiveChannel();
});
</script>

<template />
