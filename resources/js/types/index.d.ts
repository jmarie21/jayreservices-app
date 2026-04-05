import type { Config } from 'ziggy-js';
import type { ServicePricingData } from './services';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: any;
    children?: NavItem[];
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
    additional_emails: string | null;
}

type Client = {
    id: number;
    name: string;
};

type Editor = {
    id: number;
    name: string;
};

type Status = 'todo' | 'in_progress' | 'for_qa' | 'done_qa' | 'sent_to_client' | 'revision' | 'revision_completed' | 'backlog' | 'cancelled';

export interface Services {
    id: number;
    name: string;
    features: string[];
    price: number;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
    video_link?: string;
}

export interface Projects {
    id: number;
    client_id: number;
    editor_id: number | null;
    service_id: number;
    service_sub_style_id?: number | null;
    service: {
        id?: number;
        name?: string;
        price?: number;
        pricing_data?: ServicePricingData;
        [key: string]: any;
    };
    editor: Record<string>;
    client: Record<string>;

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
    editor_price: number;
    output_link?: { name: string; link: string }[];
    with_agent?: boolean;
    per_property?: boolean;
    per_property_count?: number;
    rush?: boolean;

    in_progress_since: string | null;
    revision_since: string | null;
    status: 'todo' | 'in_progress' | 'for_qa' | 'done_qa' | 'sent_to_client' | 'revision' | 'revision_completed' | 'backlog' | 'cancelled';
    priority: 'urgent' | 'high' | 'normal' | 'low';
    extra_fields?: Record<string, any>;

    created_at: string;
    updated_at: string;

    comments?: Comment[];
}

export interface Comment {
    id: number;
    body: string | null;
    created_at: string;
    user_id: number;
    user: {
        id: number;
        name: string;
        role: string;
    };
    image_url?: string | null;
    attachments: CommentAttachment[];
}

export interface CommentAttachment {
    id: number;
    url: string;
    mime_type?: string | null;
    original_name?: string | null;
    size_bytes?: number | null;
    position: number;
    is_legacy?: boolean;
}

// Invoice interface
export interface Invoice {
    id: number;
    client_id: number;
    client: Client;
    projects: Projects[];
    invoice_number: number;

    paypal_link: string;
    date_from?: string;
    date_to?: string;
    total_amount: number;

    status: 'pending' | 'sent' | 'paid' | 'cancelled';
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
