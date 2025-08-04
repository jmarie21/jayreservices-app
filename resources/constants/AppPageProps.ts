import { Services } from '../../resources/js/types/index';

export type AppPageProps = {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
            role: 'admin' | 'client' | 'editor';
        };
    };

    services?: Services[];
};
