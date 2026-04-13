export type ServiceFormatPrice = {
    id: number;
    format_name: string;
    format_label: string;
    client_price: number;
    editor_price?: number;
    sort_order: number;
};

export type ServiceSubStyle = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    format_pricing: ServiceFormatPrice[];
};

export type ServiceAddonGroupOption = {
    id: number;
    name: string;
    slug: string;
    client_price: number;
    editor_price?: number;
    sample_link?: string | null;
    sort_order: number;
    has_quantity: boolean;
    is_rush_option?: boolean;
    is_active?: boolean;
};

export type ServiceAddonGroup = {
    id: number;
    label: string;
    slug: string;
    input_type: 'dropdown' | 'checkbox_group';
    helper_text?: string | null;
    sort_order: number;
    is_required: boolean;
    is_active: boolean;
    options: ServiceAddonGroupOption[];
};

export type ServiceAddon = {
    id: number;
    assignment_id?: number;
    service_addon_group_id?: number | null;
    name: string;
    slug: string;
    addon_type: 'boolean' | 'quantity' | 'checkbox_group';
    client_price: number;
    editor_price?: number;
    has_quantity: boolean;
    sample_link?: string | null;
    group?: string | null;
    group_label?: string | null;
    group_input_type?: 'dropdown' | 'checkbox_group' | null;
    group_helper_text?: string | null;
    group_sort_order?: number;
    group_required?: boolean;
    sort_order: number;
    is_rush_option?: boolean;
    is_active?: boolean;
};

export type ServiceAddonAssignment = {
    id: number;
    addon_id: number | null;
    addon_name: string | null;
    addon_slug: string | null;
    addon_type: 'boolean' | 'quantity' | 'checkbox_group' | null;
    group: string | null;
    client_price: number;
    editor_price: number;
    client_price_override: number | null;
    editor_price_override: number | null;
};

export type ServicePricingData = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    features: string[];
    video_link?: string | null;
    thumbnail_url?: string | null;
    sort_order: number;
    is_active: boolean;
    category?: {
        id: number;
        name: string;
        slug: string;
        icon?: string | null;
    } | null;
    sub_styles: ServiceSubStyle[];
    addons: ServiceAddon[];
    addon_groups?: ServiceAddonGroup[];
};

export type ServiceCategory = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    video_link?: string | null;
    thumbnail_url?: string | null;
    icon?: string | null;
    sort_order: number;
    is_active: boolean;
    services: ServicePricingData[];
    addon_assignments?: ServiceAddonAssignment[];
};

export type ServiceManagementCategoryRow = {
    id: number;
    name: string;
    slug: string;
    video_link?: string | null;
    thumbnail_url?: string | null;
    sort_order: number;
    is_active: boolean;
    services_count: number;
    bullet_points_count: number;
};

export type ServiceManagementServiceRow = {
    id: number;
    name: string;
    slug: string;
    video_link?: string | null;
    thumbnail_url?: string | null;
    sort_order: number;
    is_active: boolean;
    styles_count: number;
    addon_groups_count: number;
    category?: {
        id: number;
        name: string;
    } | null;
};

export type ServiceEditorCategoryOption = {
    id: number;
    name: string;
};

export type ServiceEditorData = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    video_link?: string | null;
    thumbnail_url?: string | null;
    sort_order: number;
    is_active: boolean;
    features: string[];
    category?: {
        id: number;
        name: string;
    } | null;
    sub_styles: ServiceSubStyle[];
    addon_groups: ServiceAddonGroup[];
};

export type SelectedServiceAddon = {
    addon_id?: number | null;
    slug: string;
    name: string;
    quantity: number;
    group?: string | null;
};
