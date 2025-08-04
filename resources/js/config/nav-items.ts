import { NavItem } from '@/types';
import { ClipboardList, FolderOpenDot, LayoutGrid } from 'lucide-vue-next';

export const allNavItems: { [key: string]: NavItem[] } = {
    admin: [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
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
