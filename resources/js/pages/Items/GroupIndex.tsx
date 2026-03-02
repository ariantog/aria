import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Package, Image as ImageIcon, Search as SearchIcon, X, Filter } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import Pagination from '@/components/Partial/Pagination';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Items', href: '/items' },
    { title: 'Groups', href: '/items-group' },
];

interface Group {
    id: number;
    name: string;
    alias: string;
    description: string;
    master: string;
    variant: string;
    image_url: string;
    in_warehouse_qty: number;
}

interface Props {
    groups: {
        data: Group[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: {
        kode?: string;
        alias?: string;
        desc?: string;
    };
}

export default function GroupIndex({ groups, filters }: Props) {
    const [showImage, setShowImage] = useState(true);
    const [searchParams, setSearchParams] = useState(filters);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/items-group', searchParams, { preserveState: true });
    };

    const clearFilters = () => {
        setSearchParams({});
        router.get('/items-group');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Item Groups" />

            <div className="p-4 sm:p-6 lg:p-8 bg-black min-h-screen text-zinc-100">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-white mb-1">Group List</h1>
                        <p className="text-zinc-400">View and manage item groups and their collective stock</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center space-x-2 bg-zinc-900/50 p-2 rounded-md border border-zinc-800">
                            <Switch id="show-image" checked={showImage} onCheckedChange={setShowImage} />
                            <Label htmlFor="show-image" className="text-sm text-zinc-300 cursor-pointer">Show Images</Label>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="mb-8 p-4 bg-zinc-900/50 border border-zinc-800 rounded-xl shadow-sm">
                    <form onSubmit={handleSearch} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div className="space-y-2">
                            <Label htmlFor="kode" className="text-xs font-semibold uppercase text-zinc-500">Kode</Label>
                            <Input
                                id="kode"
                                value={searchParams.kode || ''}
                                onChange={e => setSearchParams({ ...searchParams, kode: e.target.value })}
                                placeholder="Filter Kode..."
                                className="bg-zinc-950 border-zinc-800 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="alias" className="text-xs font-semibold uppercase text-zinc-500">Alias</Label>
                            <Input
                                id="alias"
                                value={searchParams.alias || ''}
                                onChange={e => setSearchParams({ ...searchParams, alias: e.target.value })}
                                placeholder="Filter Alias..."
                                className="bg-zinc-950 border-zinc-800 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="desc" className="text-xs font-semibold uppercase text-zinc-500">Description</Label>
                            <Input
                                id="desc"
                                value={searchParams.desc || ''}
                                onChange={e => setSearchParams({ ...searchParams, desc: e.target.value })}
                                placeholder="Filter Description..."
                                className="bg-zinc-950 border-zinc-800 text-zinc-200"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" className="flex-1 bg-zinc-100 text-zinc-900 hover:bg-zinc-200">
                                <Filter className="mr-2 h-4 w-4" /> Filter
                            </Button>
                            <Button type="button" variant="outline" onClick={clearFilters} className="border-zinc-800 text-zinc-400 hover:bg-zinc-800/50">
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="text-xs text-zinc-500 uppercase bg-zinc-900/50 border-b border-zinc-800">
                                <tr>
                                    {showImage && <th className="px-6 py-4 font-bold tracking-wider">Image</th>}
                                    <th className="px-6 py-4 font-bold tracking-wider">Kode</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Master</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Alias</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Variant</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">Description</th>
                                    <th className="px-6 py-4 font-bold tracking-wider">In Warehouse</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/50">
                                {groups.data.length > 0 ? (
                                    groups.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="group hover:bg-zinc-800/40 transition-colors cursor-pointer"
                                            onClick={() => router.get(`/items-group/${item.id}`)}
                                        >
                                            {showImage && (
                                                <td className="px-6 py-4">
                                                    <div className="h-10 w-10 rounded-md bg-white flex items-center justify-center overflow-hidden border border-zinc-700">
                                                        {item.image_url ? (
                                                            <img src={item.image_url} alt={item.name} className="h-full w-full object-cover" />
                                                        ) : (
                                                            <Package className="h-5 w-5 text-zinc-500" />
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                            <td className="px-6 py-4 font-medium">
                                                <Link
                                                    href={`/items-group/${item.id}`}
                                                    className="text-blue-500 hover:text-blue-400 hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {item.name}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 text-zinc-400">{item.master || '-'}</td>
                                            <td className="px-6 py-4 text-zinc-300 italic">{item.alias || '-'}</td>
                                            <td className="px-6 py-4 text-zinc-400">{item.variant || '-'}</td>
                                            <td className="px-6 py-4 text-zinc-300">{item.description || '-'}</td>
                                            <td className="px-6 py-4 text-green-500 font-bold">{item.in_warehouse_qty}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={showImage ? 7 : 6} className="px-6 py-12 text-center text-zinc-500">
                                            No groups found matching your filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="border-t border-zinc-800 px-6 py-4">
                        <Pagination
                            links={groups.links}
                            from={groups.from}
                            to={groups.to}
                            total={groups.total}
                            label="groups"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
