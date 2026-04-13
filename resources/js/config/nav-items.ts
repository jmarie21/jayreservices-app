import { NavItem } from '@/types';
import {
    Brush,
    ClipboardList,
    FolderOpenDot,
    Folders,
    HandCoins,
    LayoutGrid,
    MessageSquareText,
    Settings,
    SquareGanttChart,
    UserCog,
    UsersRound,
} from 'lucide-vue-next';

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
            title: 'All Projects',
            href: '/all-projects',
            icon: SquareGanttChart,
        },
        {
            title: 'Editors Projects',
            href: '/editors-projects',
            icon: UsersRound,
        },
        {
            title: 'Project Management',
            href: undefined,
            icon: Folders,
        },
        {
            title: 'Editor Management',
            href: undefined,
            icon: Brush,
        },
        {
            title: 'Invoice Management',
            href: '/invoice-mgmt',
            icon: HandCoins,
        },
        {
            title: 'Messages',
            href: '/messages',
            icon: MessageSquareText,
        },
        {
            title: 'Services',
            href: '/admin-service-catalog',
            icon: ClipboardList,
        },
        {
            title: 'Services Management',
            href: '/admin-services',
            icon: Settings,
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
        // {
        //     title: 'Editor Dashboard',
        //     href: '/editor-dashboard',
        //     icon: LayoutGrid,
        // },

        {
            title: 'Editor Projects',
            href: '/editor-projects',
            icon: FolderOpenDot,
        },
    ],
};
