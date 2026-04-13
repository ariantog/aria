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
    Factory,
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
        return auth.permissions.includes(permission) || auth.permissions.includes('*');
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
                ...(hasPermission('items-list') || isSuperAdmin ? [{
                    title: 'Group',
                    href: '/items-group',
                    isActive: isRouteActive('/items-group'),
                }] : []),
                ...(hasPermission('tags-list') || isSuperAdmin || true ? [{
                    title: 'Tags',
                    href: '/tags',
                    isActive: isRouteActive('/tags'),
                }] : []),
            ].filter(Boolean) as NavItem[],
        },
        // Journal Group
        {
            title: 'Journal',
            href: '#',
            icon: BookOpen,
            isActive: isRouteActive('/journals'),
            items: [
                ...(hasPermission('operations-list') || isSuperAdmin || true ? [{
                    title: 'Operations',
                    href: '/journals/operations',
                    isActive: isRouteActive('/journals/operations'),
                }] : []),
                ...(hasPermission('account-list-list') || isSuperAdmin || true ? [{
                    title: 'Account List',
                    href: '/journals/account-list',
                    isActive: isRouteActive('/journals/account-list'),
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
                ...(hasPermission('transactions-type-buy') || isSuperAdmin ? [{
                    title: 'Buy',
                    href: '/transactions/buy/create',
                    isActive: isRouteActive('/transactions/buy/create'),
                }] : []),
                ...(hasPermission('transactions-type-sell') || isSuperAdmin ? [{
                    title: 'Sell',
                    href: '/transactions/sell/create',
                    isActive: isRouteActive('/transactions/sell/create'),
                }] : []),
                ...(hasPermission('transactions-type-move') || isSuperAdmin ? [{
                    title: 'Move',
                    href: '/transactions/move/create',
                    isActive: isRouteActive('/transactions/move/create'),
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
        ...(hasPermission('karyawan-list') || isSuperAdmin ? [{
            title: 'Karyawan',
            href: '#',
            icon: Users,
            isActive: isRouteActive('/karyawan') || isRouteActive('/gaji'),
            items: [
                {
                    title: 'Daftar Karyawan',
                    href: '/karyawan',
                    isActive: isRouteActive('/karyawan'),
                },
                {
                    title: 'Gaji Bulanan',
                    href: '/gaji',
                    isActive: isRouteActive('/gaji'),
                }
            ],
        }] : []),
        ...(hasPermission('borongan-list') || isSuperAdmin ? [{
            title: 'Borongan',
            href: '/borongan',
            createUrl: '/borongan/create',
            icon: Receipt,
            isActive: isRouteActive('/borongan'),
        }] : []),
        ...(hasPermission('reports-nett-cash') || isSuperAdmin ? [{
            title: 'Reports',
            href: '#',
            icon: PieChart,
            isActive: isRouteActive('/reports'),
            items: [
                {
                    title: 'Nett Cash',
                    href: '/reports/nett-cash-sby',
                    isActive: isRouteActive('/reports/nett-cash-sby'),
                },
                ...(hasPermission('reports-cash-flow') || isSuperAdmin ? [{
                    title: 'Cash Flow',
                    href: '/reports/cash-flow',
                    isActive: isRouteActive('/reports/cash-flow'),
                }] : []),
                ...(hasPermission('reports-compare') || isSuperAdmin ? [{
                    title: 'Compare',
                    href: '/reports/compare',
                    isActive: isRouteActive('/reports/compare'),
                }] : []),
                ...(hasPermission('reports-inventory-health') || isSuperAdmin ? [{
                    title: 'Inventory Health',
                    href: '/reports/inventory-health',
                    isActive: isRouteActive('/reports/inventory-health'),
                }] : []),
            ],
        }] : []),
        {
            title: 'Produksi',
            href: '#',
            icon: Factory,
            isActive: isRouteActive('/produksi'),
            items: [
                ...(hasPermission('production-list') || isSuperAdmin ? [{
                    title: 'Produksi',
                    href: '/produksi',
                    createUrl: '/produksi/create',
                    isActive: isRouteActive('/produksi') && !isRouteActive('/produksi/potong') && !isRouteActive('/produksi/jahit') && !isRouteActive('/produksi/qc') && !isRouteActive('/produksi/setoran'),
                }] : []),
                ...(hasPermission('production-setoran-list') || isSuperAdmin ? [{
                    title: 'Setoran',
                    href: '/produksi/setoran',
                    isActive: isRouteActive('/produksi/setoran'),
                }] : []),
                ...(hasPermission('production-worker-list') || isSuperAdmin ? [
                    {
                        title: 'Potong',
                        href: '/produksi/potong/list',
                        isActive: isRouteActive('/produksi/potong/list'),
                    },
                    {
                        title: 'Jahit',
                        href: '/produksi/jahit/list',
                        isActive: isRouteActive('/produksi/jahit/list'),
                    },
                    {
                        title: 'QC',
                        href: '/produksi/qc/list',
                        isActive: isRouteActive('/produksi/qc/list'),
                    },
                ] : []),
            ].filter(Boolean) as NavItem[],
        },
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
                            <Link href={dashboard()}>
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
