// composables/useNotifications.ts
import { useEcho } from '@laravel/echo-vue';
import { ref } from 'vue';

interface Notification {
    message: string;
    project_id: number;
    project_name: string;
    client_name: string;
    timestamp: string;
}

// Make notifications reactive and persistent across component mounts
const notifications = ref<Notification[]>([]);
let echoChannel: any = null;
let isInitialized = false;

export function useNotifications() {
    const initializeEcho = () => {
        if (isInitialized) {
            console.log('Echo already initialized, skipping...');
            return;
        }

        try {
            console.log('Initializing Echo notifications...');
            const echo = useEcho();

            if (!echo) {
                console.error('Echo instance not available');
                return;
            }

            // Subscribe to the private channel
            echoChannel = echo.private('admin-notifications');

            // Listen for ProjectRevisionEvent
            echoChannel.listen('ProjectRevisionEvent', (event: any) => {
                console.log('🔔 Project revision event received:', event);
                console.log('🔔 Event data:', {
                    message: event.message,
                    project_id: event.project_id,
                    project_name: event.project_name,
                    client_name: event.client_name,
                    timestamp: event.timestamp,
                });

                notifications.value.unshift({
                    message: event.message,
                    project_id: event.project_id,
                    project_name: event.project_name,
                    client_name: event.client_name,
                    timestamp: event.timestamp,
                });

                console.log('✅ Notification added. Total notifications:', notifications.value.length);
            });

            // Also listen with dot prefix as fallback
            echoChannel.listen('.ProjectRevisionEvent', (event: any) => {
                console.log('🔔 Project revision event received (with dot):', event);

                notifications.value.unshift({
                    message: event.message,
                    project_id: event.project_id,
                    project_name: event.project_name,
                    client_name: event.client_name,
                    timestamp: event.timestamp,
                });
            });

            isInitialized = true;
            console.log('✅ Echo notifications initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing Echo notifications:', error);
        }
    };

    const removeNotification = (index: number) => {
        notifications.value.splice(index, 1);
    };

    const clearAllNotifications = () => {
        notifications.value = [];
    };

    return {
        notifications,
        initializeEcho,
        removeNotification,
        clearAllNotifications,
    };
}
