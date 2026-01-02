import { ReactNode } from 'react';

export interface DashboardModule {
    name: string;
    description: string;
    icon: string | ReactNode;
    href?: string;
    color: string;
    soon?: boolean;
}

export interface DashboardProps {
    modules: DashboardModule[];
}

export type DashboardShellType = 'classic' | 'modern' | 'minimal' | 'dark';
