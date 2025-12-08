import { NavItem } from '@/types';
import { BookHeart, Brush, Church, ClipboardList, FolderOpenDot, HandCoins, Hotel, House, LayoutGrid, PartyPopper, SquareGanttChart, UserCog } from 'lucide-vue-next';

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
            title: 'Project Management',
            href: undefined,
            icon: FolderOpenDot,
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
            title: 'Real Estate Services',
            href: '/admin-realestate-services',
            icon: Hotel,
        },
        {
            title: 'Wedding Services',
            href: '/admin-wedding-services',
            icon: Church,
        },
        {
            title: 'Event Services',
            href: '/admin-event-services',
            icon: PartyPopper,
        },
    ],
    client: [
        {
            title: 'Real Estate Services',
            href: '/realestate-services',
            icon: ClipboardList,
        },
        {
            title: 'Wedding Services',
            href: '/wedding-services',
            icon: BookHeart,
        },
        {
            title: 'Event Services',
            href: '/event-services',
            icon: PartyPopper,
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
