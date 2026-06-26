import type { EditorLevel } from '@/types/app-page-prop';

export const editorLevelOrder: EditorLevel[] = ['senior', 'mid', 'junior'];

export const editorLevelLabels: Record<EditorLevel, string> = {
    senior: 'Senior',
    mid: 'Mid',
    junior: 'Junior',
};

export const editorLevelBadgeClasses: Record<EditorLevel, string> = {
    senior: 'bg-blue-100 text-blue-700',
    mid: 'bg-amber-100 text-amber-700',
    junior: 'bg-emerald-100 text-emerald-700',
};

export const editorLevelTextClasses: Record<EditorLevel, string> = {
    senior: 'text-blue-700',
    mid: 'text-amber-700',
    junior: 'text-emerald-700',
};

export const editorLevelDotClasses: Record<EditorLevel, string> = {
    senior: 'bg-blue-500',
    mid: 'bg-amber-500',
    junior: 'bg-emerald-500',
};
