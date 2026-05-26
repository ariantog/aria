import { Head, Link, router } from '@inertiajs/react';
import {
    Package,
    Image as ImageIcon,
    Search as SearchIcon,
    X,
    Filter,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

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

            <div className="min-h-screen bg-black p-4 text-zinc-100 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="mb-1 text-3xl font-bold tracking-tight text-white">
                            Group List
                        </h1>
                        <p className="text-zinc-400">
                            View and manage item groups and their collective
                            stock
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center space-x-2 rounded-md border border-zinc-800 bg-zinc-900/50 p-2">
                            <Switch
                                id="show-image"
                                checked={showImage}
                                onCheckedChange={setShowImage}
                            />
                            <Label
                                htmlFor="show-image"
                                className="cursor-pointer text-sm text-zinc-300"
                            >
                                Show Images
                            </Label>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="mb-8 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 shadow-sm">
                    <form
                        onSubmit={handleSearch}
                        className="grid grid-cols-1 items-end gap-4 md:grid-cols-4"
                    >
                        <div className="space-y-2">
                            <Label
                                htmlFor="kode"
                                className="text-xs font-semibold text-zinc-500 uppercase"
                            >
                                Kode
                            </Label>
                            <Input
                                id="kode"
                                value={searchParams.kode || ''}
                                onChange={(e) =>
                                    setSearchParams({
                                        ...searchParams,
                                        kode: e.target.value,
                                    })
                                }
                                placeholder="Filter Kode..."
                                className="border-zinc-800 bg-zinc-950 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label
                                htmlFor="alias"
                                className="text-xs font-semibold text-zinc-500 uppercase"
                            >
                                Alias
                            </Label>
                            <Input
                                id="alias"
                                value={searchParams.alias || ''}
                                onChange={(e) =>
                                    setSearchParams({
                                        ...searchParams,
                                        alias: e.target.value,
                                    })
                                }
                                placeholder="Filter Alias..."
                                className="border-zinc-800 bg-zinc-950 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label
                                htmlFor="desc"
                                className="text-xs font-semibold text-zinc-500 uppercase"
                            >
                                Description
                            </Label>
                            <Input
                                id="desc"
                                value={searchParams.desc || ''}
                                onChange={(e) =>
                                    setSearchParams({
                                        ...searchParams,
                                        desc: e.target.value,
                                    })
                                }
                                placeholder="Filter Description..."
                                className="border-zinc-800 bg-zinc-950 text-zinc-200"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                className="flex-1 bg-zinc-100 text-zinc-900 hover:bg-zinc-200"
                            >
                                <Filter className="mr-2 h-4 w-4" /> Filter
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                                className="border-zinc-800 text-zinc-400 hover:bg-zinc-800/50"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900/50 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-800 bg-zinc-900/50 text-xs text-zinc-500 uppercase">
                                <tr>
                                    {showImage && (
                                        <th className="px-6 py-4 font-bold tracking-wider">
                                            Image
                                        </th>
                                    )}
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Kode
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Master
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Alias
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Variant
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Description
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        In Warehouse
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/50">
                                {groups.data.length > 0 ? (
                                    groups.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="group cursor-pointer transition-colors hover:bg-zinc-800/40"
                                            onClick={() =>
                                                router.get(
                                                    `/items-group/${item.id}`,
                                                )
                                            }
                                        >
                                            {showImage && (
                                                <td className="px-6 py-4">
                                                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border border-zinc-700 bg-white">
                                                        {item.image_url ? (
                                                            <img
                                                                src={
                                                                    item.image_url
                                                                }
                                                                alt={item.name}
                                                                className="h-full w-full object-cover"
                                                            />
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
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {item.name}
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 text-zinc-400">
                                                {item.master || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-zinc-300 italic">
                                                {item.alias || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-zinc-400">
                                                {item.variant || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-zinc-300">
                                                {item.description || '-'}
                                            </td>
                                            <td className="px-6 py-4 font-bold text-green-500">
                                                {item.in_warehouse_qty}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-blue-800 text-blue-500 hover:bg-blue-900/20"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        router.post('/restock/add-item', { code: String(item.id), qty: 1 }, { preserveScroll: true });
                                                    }}
                                                >
                                                    <ShoppingCart className="mr-2 h-4 w-4" /> Restock
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={showImage ? 8 : 7}
                                            className="px-6 py-12 text-center text-zinc-500"
                                        >
                                            No groups found matching your
                                            filters.
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
