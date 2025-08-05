import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'admin' | 'client' | 'editor';
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Services {
    id: number;
    name: string;
    features: string[];
    price: number;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface Projects {
    id: number;
    client_id: number;
    editor_id: number | null;
    service_id: number;

    style: string;
    company_name: string;
    contact: string;
    project_name: string;
    format?: string;
    camera?: string;
    quality?: string;
    music?: string;
    music_link?: string;
    file_link: string;
    notes?: string;
    total_price: number;
    output_link?: string;

    status: 'pending' | 'in_progress' | 'completed';
    extra_fields?: Record<string, any>;

    created_at: string; // ISO date
    updated_at: string; // ISO date
}

export type BreadcrumbItemType = BreadcrumbItem;
