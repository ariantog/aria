import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Command, Plus, Search, X, Edit, Trash2, Box, Store, MapPin } from 'lucide-react';
import Pagination from '@/components/Pagination';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jubelio Sync Mapping',
        href: '/jubelio-sync',
    },
];

interface SyncMapping {
    id: number;
    jubelio_store_name: string;
    jubelio_location_name: string;
    warehouse_id: number;
    customer_id: number;
    bin_id: number;
    warehouse?: {
        name: string;
    };
    customer?: {
        name: string;
    };
}

interface Props {
    dataList: {
        data: SyncMapping[];
        links: any[];
    };
    filters: {
        name?: string;
    };
}

export default function SyncIndex({ dataList, filters }: Props) {
    const [search, setSearch] = useState(filters.name || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/jubelio-sync', { name: search }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        router.get('/jubelio-sync');
    };

    const deleteMapping = (id: number) => {
        if (confirm('Are you sure you want to delete this mapping?')) {
            router.delete(`/jubelio-sync/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jubelio Sync Mapping" />
            
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold flex items-center gap-2">
                        <Command className="h-6 w-6" />
                        Jubelio Sync Mapping
                    </h1>

                    <div className="flex items-center gap-2">
                        <form onSubmit={handleSearch} className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-sidebar-foreground/50" />
                                <Input 
                                    placeholder="Search location..." 
                                    className="pl-9 w-64 h-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <Button type="submit" size="sm">Search</Button>
                            {filters.name && (
                                <Button type="button" variant="ghost" size="sm" onClick={clearFilters}>
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </form>
                        <Button asChild size="sm" className="gap-2">
                            <Link href="/jubelio-sync/create">
                                <Plus className="h-4 w-4" /> Create Mapping
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-sidebar-accent/50 text-sidebar-foreground uppercase font-semibold text-[10px]">
                                <tr>
                                    <th className="px-6 py-4">Store Name</th>
                                    <th className="px-6 py-4">Location</th>
                                    <th className="px-6 py-4">Warehouse</th>
                                    <th className="px-6 py-4">Customer</th>
                                    <th className="px-6 py-4">Bin ID</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border">
                                {dataList.data.map((item) => (
                                    <tr key={item.id} className="hover:bg-sidebar-accent/30 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <Store className="h-3.5 w-3.5 text-blue-500" />
                                                <span className="font-medium">{item.jubelio_store_name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2 text-xs">
                                                <MapPin className="h-3.5 w-3.5 text-red-500" />
                                                {item.jubelio_location_name}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <Badge variant="outline" className="font-medium text-blue-500 border-blue-500/20">
                                                {item.warehouse?.name || 'Unknown'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4">
                                            {item.customer ? (
                                                <Badge variant="outline" className="font-medium text-green-500 border-green-500/20">
                                                    {item.customer.name}
                                                </Badge>
                                            ) : (
                                                <span className="text-sidebar-foreground/30 italic text-xs">Not Set</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <Box className="h-3.5 w-3.5 text-yellow-500" />
                                                <code className="text-xs">{item.bin_id || '-'}</code>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="outline" size="xs" asChild className="h-7 text-[10px]">
                                                    <Link href={`/jubelio-sync/${item.id}/bin`}>Set Bin</Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild className="h-8 w-8">
                                                    <Link href={`/jubelio-sync/${item.id}/edit`}>
                                                        <Edit className="h-4 w-4 text-blue-500" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => deleteMapping(item.id)} className="h-8 w-8">
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {dataList.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-sidebar-foreground/50 italic">
                                            No sync mappings found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4">
                    <Pagination links={dataList.links} />
                </div>
            </div>
        </AppLayout>
    );
}
