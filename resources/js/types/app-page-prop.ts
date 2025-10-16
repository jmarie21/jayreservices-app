export type UserRole = 'admin' | 'client' | 'editor';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    additional_emails?: string | null;
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
    client_id?: number | null;
    service_id: number;
    style: string;
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
    per_property?: boolean;
    rush?: boolean;
};

export type TalkingHeadsForm = {
    id?: number;
    client_id?: number | null;
    service_id: number;
    style: string;
    project_name: string;
    format?: string;
    camera?: string;
    quality?: string;
    music?: string;
    music_link?: string;
    file_link?: string;
    notes?: string;
    total_price: number;
    rush?: boolean;
};

export type DeluxeForm = {
    id?: number;
    client_id?: number | null;
    service_id: number;
    style: string;
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
    extra_fields?: {
        effects: string[]; // e.g., ["Ken Burns"]
        captions: string[]; // e.g., ["3D Text behind the Agent Talking"]
    };
    per_property?: boolean;
    rush?: boolean;
};

export type PremiumForm = {
    id?: number;
    client_id?: number | null;
    service_id: number;
    style: string;
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
    extra_fields?: {
        effects: string[]; // e.g., ["Ken Burns"]
        captions: string[]; // e.g., ["3D Text behind the Agent Talking"]
    };
    per_property?: boolean;
    rush?: boolean;
};

export type LuxuryForm = {
    id?: number;
    client_id?: number | null;
    service_id: number;
    style: string;
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
    extra_fields?: {
        effects: string[]; // e.g., ["Ken Burns"]
        captions: string[]; // e.g., ["3D Text behind the Agent Talking"]
    };
    per_property?: boolean;
    rush?: boolean;
};

// ✅ Generic pagination type
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};
