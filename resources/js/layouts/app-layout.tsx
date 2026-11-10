import { usePage } from '@inertiajs/react';
import AppHeaderLayout from '@/layouts/app/app-header-layout';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Super Admin dan HR memakai sidebar; kandidat memakai navigasi atas yang sederhana.
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;
    const Layout =
        auth.user.role === 'candidate' ? AppHeaderLayout : AppSidebarLayout;

    return <Layout breadcrumbs={breadcrumbs}>{children}</Layout>;
}
