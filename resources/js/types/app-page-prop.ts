export type UserRole = 'admin' | 'client' | 'editor';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
};

export type Services = {
    id: number;
    name: string;
    description: string | null;
    features: string[];
    price: number;
    created_at: string;
    updated_at: string;
};

export type AppPageProps = {
    auth?: {
        user?: User;
    };
    services?: Services[];
};

export type BasicForm = {
    id?: number;
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
    file_link?: string;
    notes?: string;
    total_price: number;
    with_agent?: boolean;
};

export type DeluxeForm = {
    id?: number;
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
    file_link?: string;
    notes?: string;
    total_price: number;
    with_agent?: boolean;
};
