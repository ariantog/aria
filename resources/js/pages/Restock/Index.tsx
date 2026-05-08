import { Head, Link, router } from '@inertiajs/react';
import {
    Plus,
    Search,
    History,
    Edit,
    ArrowUpDown,
    ShoppingCart,
    FileSpreadsheet
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stuff', href: '#' },
    { title: 'Restock', href: '/restock' },
];

export default function RestockIndex({ restocks, cartCount, filters }: any) {
    const [search, setSearch] = useState(filters.code || '');
    const [sortCol, setSortCol] = useState(filters.kolom || '');
    const [sortOrder, setSortOrder] = useState(filters.order || 'desc');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/restock', { code: search, kolom: sortCol, order: sortOrder }, { preserveState: true });
    };

    const handleSort = (col: string) => {
        const newOrder = sortCol === col && sortOrder === 'desc' ? 'asc' : 'desc';
        setSortCol(col);
        setSortOrder(newOrder);
        router.get('/restock', { code: search, kolom: col, order: newOrder }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Restock Management" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Stat Cards - Dashboard Style */}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <ShoppingCart className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Restocks</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{restocks.total}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                                <History className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">In Production</p>
                                <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    {restocks.data.reduce((acc: number, item: any) => acc + item.in_production_quantity, 0)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                    <ShoppingCart className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">Received Cart</p>
                                    <p className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">{cartCount}</p>
                                </div>
                            </div>
                            <Link href="/restock/received">
                                <Button size="sm" variant="outline">View Cart</Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex flex-1 flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 className="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Restock Registry
                            </h2>
                            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                                Track items from restock to warehouse arrival.
                            </p>
                        </div>
                        <div className="flex flex-1 items-center justify-end gap-3 w-full sm:w-auto">
                            <form onSubmit={handleSearch} className="relative w-full max-w-xs">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                                <Input
                                    placeholder="Search items..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="bg-zinc-50 pl-9 dark:bg-zinc-900"
                                />
                            </form>
                            <Link href="/restock/create">
                                <Button size="sm" className="bg-blue-600 text-white hover:bg-blue-700">
                                    <Plus className="mr-2 h-4 w-4" /> New
                                </Button>
                            </Link>
                            <Link href="/restock/upload">
                                <Button size="sm" variant="secondary">
                                    <FileSpreadsheet className="mr-2 h-4 w-4" /> Import
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <div className="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-zinc-50/50 text-xs text-zinc-500 uppercase dark:bg-zinc-900/50">
                                    <tr>
                                        <th className="px-6 py-3 font-bold tracking-wider">Item</th>
                                        <th className="px-6 py-3 font-bold tracking-wider cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors text-center" onClick={() => handleSort('restock')}>
                                            Restock <ArrowUpDown className="inline h-3 w-3 ml-1" />
                                        </th>
                                        <th className="px-6 py-3 font-bold tracking-wider cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors text-center" onClick={() => handleSort('production')}>
                                            Prod <ArrowUpDown className="inline h-3 w-3 ml-1" />
                                        </th>
                                        <th className="px-6 py-3 font-bold tracking-wider cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors text-center" onClick={() => handleSort('shipped')}>
                                            Ship <ArrowUpDown className="inline h-3 w-3 ml-1" />
                                        </th>
                                        <th className="px-6 py-3 font-bold tracking-wider cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors text-center" onClick={() => handleSort('missing')}>
                                            Miss <ArrowUpDown className="inline h-3 w-3 ml-1" />
                                        </th>
                                        <th className="px-6 py-3 font-bold tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800/50">
                                    {restocks.data.length > 0 ? (
                                        restocks.data.map((item: any) => (
                                            <tr key={item.id} className="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="font-medium text-zinc-900 dark:text-zinc-100">{item.item?.name}</div>
                                                    <div className="text-xs text-zinc-500">{item.item?.code || `ID: ${item.item_id}`}</div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge variant="outline" className="font-mono">{item.restocked_quantity}</Badge>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge className="bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 font-mono">
                                                        {item.in_production_quantity}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge className="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-mono">
                                                        {item.shipped_quantity}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge className="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 font-mono">
                                                        {item.missing_quantity}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Link href={`/restock/${item.id}/update`}>
                                                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                <Edit className="h-4 w-4 text-zinc-500" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/restock/${item.id}/history`}>
                                                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                <History className="h-4 w-4 text-zinc-500" />
                                                            </Button>
                                                        </Link>
                                                        {item.shipped_quantity > 0 && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="icon"
                                                                className="h-8 w-8"
                                                                onClick={() => router.post(`/restock/${item.id}/add-to-gudang`, { quantity: item.shipped_quantity })}
                                                            >
                                                                <ShoppingCart className="h-4 w-4 text-emerald-600" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-8 text-center text-zinc-500">
                                                No restock records found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {restocks.total > 0 && (
                            <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                                <Pagination 
                                    links={restocks.links} 
                                    from={restocks.from} 
                                    to={restocks.to} 
                                    total={restocks.total} 
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
