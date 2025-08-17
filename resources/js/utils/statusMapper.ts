// utils/statusMapper.ts
export const mapStatusForClient = (status: string): 'pending' | 'in_progress' | 'completed' => {
    switch (status) {
        case 'todo':
        case 'backlog':
            return 'pending';
        case 'in_progress':
        case 'for_qa':
        case 'done_qa':
        case 'revision':
            return 'in_progress';
        case 'revision_completed':
        case 'sent_to_client':
            return 'completed';
        default:
            return 'pending'; // fallback safe default
    }
};
