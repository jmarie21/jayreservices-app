<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const isOpen = ref(false);
const notifications = ref(page.props.notifications?.recent || []);
const unreadCount = ref(page.props.notifications?.unread_count || 0);

const user = page.props.auth.user; // assuming you share auth user globally

// Check if we're in a browser environment
const isBrowser = typeof window !== 'undefined';

onMounted(() => {
    if (!isBrowser || !user?.id) return;

    const audio = new Audio(`${window.location.origin}/sounds/notification.mp3`);
    audio.preload = 'auto';

    // Unlock audio after first user click
    document.addEventListener(
        'click',
        () => {
            audio
                .play()
                .then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                })
                .catch(() => {});
        },
        { once: true },
    );

    // Check if Echo exists before using it
    if (window.Echo) {
        const channel = window.Echo.private(`App.Models.User.${user.id}`);

        channel.notification((notification) => {
            console.log('🔔 New notification received:', notification);

            // 🔊 Play sound
            audio.currentTime = 0;
            audio.play().catch(() => {});

            // 📨 Add notification
            notifications.value.unshift({
                id: notification.id ?? Date.now(),
                data: notification,
                created_at: new Date().toISOString(),
                read_at: null,
            });

            unreadCount.value++;
        });
    }
});

onUnmounted(() => {
    if (isBrowser && user?.id && window.Echo) {
        window.Echo.leave(`private-App.Models.User.${user.id}`);
    }
});

// const reloadInterval = null;

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        // Reload data when opening dropdown
        router.reload({ only: ['notifications'] });
    }
};

const markAllAsRead = () => {
    router.post(
        '/notifications/read-all',
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                // Instantly update frontend state
                notifications.value = notifications.value.map((n) => ({
                    ...n,
                    read_at: new Date().toISOString(),
                }));
                unreadCount.value = 0;
            },
        },
    );
};

const handleNotificationClick = (notification) => {
    // Mark as read if unread
    if (!notification.read_at) {
        router.post(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['notifications'],
                onSuccess: () => {
                    // Navigate using route name and params
                    if (notification.data.route_name) {
                        isOpen.value = false;
                        router.visit(route(notification.data.route_name, notification.data.route_params || {}));
                    }
                },
            },
        );
    } else {
        // Already read, just navigate
        if (notification.data.route_name) {
            isOpen.value = false;
            router.visit(route(notification.data.route_name, notification.data.route_params || {}));
        }
    }
};

const deleteNotification = (id) => {
    router.delete(`/notifications/${id}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['notifications'],
    });
};

// ✅ NEW: Delete all notifications
const deleteAllNotifications = () => {
    router.delete('/notifications/delete-all', {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = [];
            unreadCount.value = 0;
        },
    });
};

const formatDate = (date) => {
    const now = new Date();
    const notificationDate = new Date(date);
    const diffInSeconds = Math.floor((now - notificationDate) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;

    return notificationDate.toLocaleDateString();
};
</script>

<template>
    <div class="relative">
        <!-- Notification Bell Button -->
        <button
            @click="toggleDropdown"
            type="button"
            class="relative rounded-full border border-gray-300 bg-white p-2 text-gray-600 transition hover:border-indigo-400 hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
        >
            <!-- Bell Icon -->
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>

            <!-- Unread Badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-flex translate-x-1/2 -translate-y-1/2 transform items-center justify-center rounded-full bg-red-600 px-2 py-1 text-xs leading-none font-bold text-white"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown Menu -->
        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="ring-opacity-5 absolute right-0 z-50 mt-2 w-[28rem] max-w-[95vw] overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black sm:max-w-[24rem]"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                    <button v-if="notifications.length > 0" @click="markAllAsRead" class="text-xs text-indigo-600 hover:text-indigo-800">
                        Mark all as read
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <div v-if="notifications.length === 0" class="p-8 text-center text-gray-500">
                        <svg class="mx-auto mb-2 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                        <p class="text-sm">No notifications</p>
                    </div>

                    <div v-else>
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            :class="[
                                'cursor-pointer border-b border-gray-100 px-4 py-3 transition-colors hover:bg-gray-50',
                                notification.read_at ? 'bg-white' : 'bg-blue-50',
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div @click="handleNotificationClick(notification)" class="flex-1">
                                    <p class="text-sm break-words text-gray-900" v-html="notification.data.message"></p>
                                    <p class="mt-1 text-xs text-gray-500">{{ formatDate(notification.created_at) }}</p>
                                </div>
                                <button @click.stop="deleteNotification(notification.id)" class="ml-2 text-gray-400 hover:text-red-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="notifications.length > 0" class="border-t border-gray-200 px-4 py-3 text-center">
                    <button @click="deleteAllNotifications" class="text-sm font-medium text-red-600 hover:text-red-800">
                        Clear all notifications
                    </button>
                </div>
            </div>
        </transition>

        <!-- Click outside to close -->
        <div v-if="isOpen" @click="isOpen = false" class="fixed inset-0 z-40"></div>
    </div>
</template>
