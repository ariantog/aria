import { Link, usePage } from '@inertiajs/react';
import {
    AudioWaveform,
    BookOpen,
    Command,
    FileText,
    Folder,
    Frame,
    GalleryVerticalEnd,
    LayoutGrid,
    Map,
    Package,
    PieChart,
    Settings2,
    SquareTerminal,
    Users,
    Receipt,
    Settings,
} from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';



const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props as any;
    const { url } = usePage();

    const isRouteActive = (path: string) => url.startsWith(path);

    const hasPermission = (permission: string) => {
        if (!auth?.permissions) return false;
        return auth.permissions.includes(permission);
    };

    const hasRole = (role: string) => {
        if (!auth?.roles) return false;
        return auth.roles.includes(role);
    };

    const isSuperAdmin = hasRole('superadmin');

    const { addrbook_types } = usePage().props as any;

    // Build Address Book menu items
    const addrbookItems = addrbook_types?.map((type: any) => ({
        title: type.name,
        href: '/' + type.slug,
        createUrl: '/' + type.slug + '/create',
        icon: null,
        isActive: isRouteActive('/' + type.slug),
        permission: `${type.slug}-addrbook-list`
    })).filter((item: any) => hasPermission(item.permission) || isSuperAdmin) || [];

    const navItems: NavItem[] = [
        ...(hasPermission('dashboard-view') || true ? [{ // Dashboard usually everyone can see, or check a specific permission
            title: 'Dashboard',
            href: dashboard.url(),
            icon: LayoutGrid,
            isActive: isRouteActive(dashboard.url()),
        }] : []),
        // User Management Group
        {
            title: 'User Management',
            href: '#',
            icon: Users,
            items: [
                ...(hasPermission('users-list') || isSuperAdmin ? [{
                    title: 'Users',
                    href: '/users',
                    isActive: isRouteActive('/users'),
                }] : []),
                ...(hasPermission('roles-list') || isSuperAdmin ? [{
                    title: 'Roles',
                    href: '/roles',
                    isActive: isRouteActive('/roles'),
                }] : []),
                ...(hasPermission('permissions-list') || isSuperAdmin ? [{
                    title: 'Permissions',
                    href: '/permissions',
                    isActive: isRouteActive('/permissions'),
                }] : []),
                ...(hasPermission('locations-list') || isSuperAdmin ? [{
                    title: 'Locations',
                    href: '/locations',
                    isActive: isRouteActive('/locations'),
                }] : []),
            ].filter(Boolean) as NavItem[],
        },
        // Stuff Group
        {
            title: 'Stuff',
            href: '#',
            icon: Package,
            items: [
                ...(hasPermission('items-list') || isSuperAdmin ? [{
                    title: 'Item',
                    href: '/items',
                    isActive: isRouteActive('/items'),
                }] : []),
                ...(hasPermission('asset-lancar-list') || isSuperAdmin ? [{
                    title: 'Asset Lancar',
                    href: '/assetlancar',
                    isActive: isRouteActive('/assetlancar'),
                }] : []),
            ].filter(Boolean) as NavItem[],
        },
        {
            title: 'Transactions',
            href: '/transactions',
            icon: Receipt,
            isActive: isRouteActive('/transactions'),
            items: [
                ...(hasPermission('transactions-list') || isSuperAdmin ? [{
                    title: 'List All',
                    href: '/transactions',
                    isActive: url === '/transactions',
                }] : []),
                ...(hasPermission('transactions-type-cash-in') || isSuperAdmin ? [{
                    title: 'Cash In',
                    href: '/transactions/cash-in',
                    isActive: isRouteActive('/transactions/cash-in'),
                }] : []),
                ...(hasPermission('transactions-type-cash-out') || isSuperAdmin ? [{
                    title: 'Cash Out',
                    href: '/transactions/cash-out',
                    isActive: isRouteActive('/transactions/cash-out'),
                }] : []),
                ...(hasPermission('transactions-type-transfer') || isSuperAdmin ? [{
                    title: 'Transfer',
                    href: '/transactions/transfer',
                    isActive: isRouteActive('/transactions/transfer'),
                }] : []),
                ...(hasPermission('transactions-type-adjust') || isSuperAdmin ? [{
                    title: 'Adjust',
                    href: '/transactions/adjust',
                    isActive: isRouteActive('/transactions/adjust'),
                }] : []),
                ...(hasPermission('transactions-type-return') || isSuperAdmin ? [{
                    title: 'Return',
                    href: '/transactions/return/create',
                    isActive: isRouteActive('/transactions/return/create'),
                }] : []),
                ...(hasPermission('transactions-type-return-supplier') || isSuperAdmin ? [{
                    title: 'Return Supplier',
                    href: '/transactions/return-supplier/create',
                    isActive: isRouteActive('/transactions/return-supplier/create'),
                }] : []),
            ].filter(Boolean) as NavItem[],
        },
        // Address Book Group
        {
            title: 'Address Book',
            href: '/addrbook',
            icon: BookOpen,
            isActive: isRouteActive('/addrbook') || addrbookItems.some((item: any) => item.isActive),
            items: [
                ...(hasPermission('addrbook-list') || isSuperAdmin ? [{
                    title: 'All Contacts',
                    href: '/addrbook',
                    isActive: url === '/addrbook',
                }] : []),
                ...addrbookItems
            ],
        },
        ...(hasPermission('setting-system-list') || isSuperAdmin ? [{
            title: 'System Settings',
            href: '/system-settings',
            icon: Settings,
            isActive: isRouteActive('/system-settings'),
        }] : []),
        ...(hasPermission('posts-list') || isSuperAdmin ? [{
            title: 'Posts',
            href: '/posts',
            icon: FileText,
            isActive: isRouteActive('/posts'),
        }] : []),
    ].filter(item => {
        if (item.items) {
            return item.items.length > 0;
        }
        return true;
    });

    const filteredNavItems = navItems;


    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
