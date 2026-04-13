<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import Sidebar from '@/components/ui/sidebar/Sidebar.vue';
import SidebarContent from '@/components/ui/sidebar/SidebarContent.vue';
import SidebarFooter from '@/components/ui/sidebar/SidebarFooter.vue';
import SidebarHeader from '@/components/ui/sidebar/SidebarHeader.vue';
import SidebarMenu from '@/components/ui/sidebar/SidebarMenu.vue';
import SidebarMenuButton from '@/components/ui/sidebar/SidebarMenuButton.vue';
import SidebarMenuItem from '@/components/ui/sidebar/SidebarMenuItem.vue';

import { allNavItems } from '@/config/nav-items';
import type { AppPageProps, Client, Editor, NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import NavMain from './NavMain.vue';
import NavUser from './NavUser.vue';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user);
const role = computed(() => user.value?.role);
const clients = computed<Client[]>(() => (page.props.clients as Client[]) ?? []);
const editors = computed<Editor[]>(() => (page.props.editors as Editor[]) ?? []);

const supportUnreadCount = ref<number>((page.props.supportUnreadCount as number) ?? 0);

// Sync with server value on Inertia page visits
watch(() => page.props.supportUnreadCount, (newVal) => {
    supportUnreadCount.value = (newVal as number) ?? 0;
});

// Listen for real-time messages on the admin inbox channel
onMounted(() => {
    if (role.value !== 'admin' || !window.Echo) return;

    window.Echo.private('support.admin.inbox').listen('.support.message.sent', (payload: any) => {
        // Only increment if the message was sent by a client (not by the admin themselves)
        if (payload.message?.sender_role !== 'admin') {
            supportUnreadCount.value++;
        }
    });

    // Reset count when admin navigates to the Messages page (they'll read messages there)
    router.on('navigate', (event) => {
        if (event.detail.page.url.startsWith('/messages')) {
            supportUnreadCount.value = 0;
        }
    });
});

onUnmounted(() => {
    if (role.value !== 'admin' || !window.Echo) return;
    window.Echo.leave('private-support.admin.inbox');
});

const mainNavItems = computed<NavItem[]>(() => {
    let navItems = [...(allNavItems[role.value] ?? [])];

    if (role.value === 'admin') {
        // Sort alphabetically by name
        const sortedClients = [...clients.value].sort((a, b) => a.name.localeCompare(b.name));
        const sortedEditors = [...editors.value].sort((a, b) => a.name.localeCompare(b.name));

        navItems = navItems.map((item) => {
            // Project Management section
            if (item.title === 'Project Management') {
                return {
                    ...item,
                    children: sortedClients.map((client) => ({
                        title: client.name,
                        href: `/project-mgmt/${client.id}`,
                    })),
                };
            }

            // Editor Management section
            if (item.title === 'Editor Management') {
                return {
                    ...item,
                    children: sortedEditors.map((editor) => ({
                        title: editor.name,
                        href: `/editor-mgmt/${editor.id}`,
                    })),
                };
            }

            // Messages badge
            if (item.title === 'Messages') {
                return {
                    ...item,
                    badge: supportUnreadCount.value,
                };
            }

            return item;
        });
    }

    return navItems;
});
</script>
<template>
    <Sidebar collapsible="icon" variant="inset" v-if="role">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <div>
                            <AppLogo />
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
