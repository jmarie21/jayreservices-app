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
