import { NavItem } from '@/types';
import { Brush, ClipboardList, FolderOpenDot, HandCoins, LayoutGrid, UserCog } from 'lucide-vue-next';

export const allNavItems: { [key: string]: NavItem[] } = {
    admin: [
        {
            title: 'Admin Dashboard',
            href: '/admin-dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'User Management',
            href: '/user-mgmt',
            icon: UserCog,
        },
        {
            title: 'Project Management',
            href: '/project-mgmt',
            icon: FolderOpenDot,
        },
        {
            title: 'Editor Management',
            href: '/editor-mgmt',
            icon: Brush,
        },
        {
            title: 'Invoice Management',
            href: '/invoice-mgmt',
            icon: HandCoins,
        },
    ],
    client: [
        {
            title: 'Services',
            href: '/services',
            icon: ClipboardList,
        },
        {
            title: 'Projects',
            href: '/projects',
            icon: FolderOpenDot,
        },
    ],
    editor: [
        {
            title: 'Editor Dashboard',
            href: '/editor-dashboard',
            icon: LayoutGrid,
        },

        {
            title: 'Editor Projects',
            href: '/editor-projects',
            icon: FolderOpenDot,
        },
    ],
};
