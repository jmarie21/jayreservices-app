<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const mainNavItems = computed<NavItem[]>(() => {
    let navItems = [...(allNavItems[role.value] ?? [])];

    if (role.value === 'admin') {
        navItems = navItems.map((item) => {
            // Project Management (already done for clients)
            if (item.title === 'Project Management') {
                return {
                    ...item,
                    children: clients.value.map((client) => ({
                        title: client.name,
                        href: `/project-mgmt/${client.id}`,
                    })),
                };
            }

            // Editor Management (new)
            if (item.title === 'Editor Management') {
                return {
                    ...item,
                    children: editors.value.map((editor: any) => ({
                        title: editor.name,
                        href: `/editor-mgmt/${editor.id}`,
                    })),
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
