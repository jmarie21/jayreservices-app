import { NavItem } from '@/types';
import { ClipboardList, FolderOpenDot, LayoutGrid, UserCog } from 'lucide-vue-next';

export const allNavItems: { [key: string]: NavItem[] } = {
    admin: [
        {
            title: 'Dashboard',
            href: '/dashboard',
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
    // editor: [
    //     {
    //         title: 'Edit Projects',
    //         href: '/editor/projects',
    //         icon: LayoutGrid,
    //     },
    // ],
};
