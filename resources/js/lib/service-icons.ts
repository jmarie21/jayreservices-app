import { Church, Construction, Hotel, Laugh, PartyPopper, type LucideIcon } from 'lucide-vue-next';

const iconMap: Record<string, LucideIcon> = {
    Hotel,
    Church,
    PartyPopper,
    Construction,
    Laugh,
};

export const getServiceIcon = (iconName?: string | null): LucideIcon => {
    if (!iconName) {
        return Hotel;
    }

    return iconMap[iconName] ?? Hotel;
};
