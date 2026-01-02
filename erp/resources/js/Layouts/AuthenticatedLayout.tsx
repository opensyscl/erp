import '../../css/app.css';
import { usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useEffect } from 'react';
import { PageProps } from '@/types';
import { NavTab } from '@/components/SectionNav';
import { usePreventScrollLockShift } from '@/Hooks/usePreventScrollLockShift';

import ModernShell from './shells/ModernShell';
import SidebarShell from './shells/SidebarShell';
import MinimalShell from './shells/MinimalShell';
import DarkShell from './shells/DarkShell';


const shells: Record<string, React.ComponentType<any>> = {
    modern: ModernShell,
    sidebar: SidebarShell,
    minimal: MinimalShell,
    dark: DarkShell,
};

export default function Authenticated({
    header,
    children,
    settings,
    sectionTabs,
    className = 'min-h-screen',
    isFullMain = false,
    absoluteNav = false
}: PropsWithChildren<{ header?: ReactNode, settings?: any, sectionTabs?: NavTab[], className?: string, isFullMain?: boolean, absoluteNav?: boolean }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const { props } = usePage<PageProps>();
    const tenant = props.tenant;

    // Prevent layout shift when dropdowns/selects open
    usePreventScrollLockShift();

    // Sync data-shell attribute on the HTML element
    useEffect(() => {
        const layoutTemplate = tenant?.layout_template || 'modern';
        document.documentElement.setAttribute('data-shell', layoutTemplate);
        localStorage.setItem('app-shell', layoutTemplate);
    }, [tenant?.layout_template]);

    // Select the appropriate shell based on tenant's layout_template
    const layoutTemplate = (tenant?.layout_template as string) || 'modern';
    const Shell = shells[layoutTemplate] || ModernShell;

    // Shared props for all shells
    const shellProps = {
        user,
        settings,
        sectionTabs,
        className,
        isFullMain,
        absoluteNav,
        showingNavigationDropdown,
        setShowingNavigationDropdown,
    };

    return (
        <Shell {...shellProps}>
            {children}
        </Shell>
    );
}
