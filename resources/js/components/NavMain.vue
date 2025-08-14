<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const expanded = ref<Record<string, boolean>>({}); // track open/close state per menu

const toggleExpand = (title: string) => {
    expanded.value[title] = !expanded.value[title];
};

const isActive = (href?: string) => {
    return href ? page.url.startsWith(href) : false;
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Dropdown Parent -->
                <SidebarMenuItem v-if="item.children && item.children.length">
                    <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title" @click="toggleExpand(item.title)">
                        <div class="flex w-full cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-2">
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                            </div>
                            <component :is="expanded[item.title] ? ChevronDown : ChevronRight" class="h-4 w-4" />
                        </div>
                    </SidebarMenuButton>

                    <!-- Children (collapsible) -->
                    <SidebarMenu v-show="expanded[item.title]" class="pl-6">
                        <SidebarMenuItem v-for="child in item.children" :key="child.title">
                            <SidebarMenuButton as-child :is-active="isActive(child.href)" :tooltip="child.title">
                                <Link :href="child.href || '#'">
                                    <span>{{ child.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarMenuItem>

                <!-- Normal Menu Items -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                        <Link :href="item.href || '#'">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
