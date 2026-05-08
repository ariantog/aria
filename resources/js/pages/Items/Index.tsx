import { Head, Link, router } from '@inertiajs/react';
import type {
    Filter} from 'lucide-react';
import {
    FilePen,
    Trash2,
    Plus,
    SlidersHorizontal,
    Search,
    Package,
    Image as ImageIcon,
    X
} from 'lucide-react';
import { useState, useEffect } from 'react';
import FilterItem from '@/components/Partial/Filter/FilterItem';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Items',
        href: '/items',
    },
];

interface Item {
    id: number;
    code: string;
    pcode: string;
    name: string;
    brand: string;
    type: string;
    price: number;
    qty: number;
    description: string;
    description2: string;
    image_url: string;
    jubelio_item_id?: number;
    group?: {
        alias: string;
        description: string;
        description2: string;
    };
    created_at: string;
}

interface Filter {
    search?: string;
    brand?: string;
    type?: string;
    jahit?: string[];
    size?: string[];
    warna?: string;
    item_type?: string;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    items: {
        data: Item[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: Filter;
    brands: Option[];
    types: Option[];
    tags: Record<number, any[]>;
}

export default function ItemsIndex({
    items,
    filters,
    brands,
    types,
    tags,
}: Props) {
    const [showImage, setShowImage] = useState(true);

    // Check if we are in Asset Asset Lancar mode based on route or filters
    // Using simple heuristic: if filters.type is '2', or url contains assetlancar
    const isAsset =
        filters.type == '2' ||
        (typeof window !== 'undefined' &&
            window.location.pathname.includes('assetlancar'));

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this item?')) {
            router.delete(itemRoutes.destroy.url({ item: id }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isAsset ? 'Asset Lancar' : 'Item List'} />

            <div className="min-h-screen bg-black p-4 text-zinc-100 sm:p-6 lg:p-8">
                {/* Header Section */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="mb-1 text-3xl font-bold tracking-tight text-white">
                            {isAsset ? 'Asset List' : 'Item List'}
                        </h1>
                        <p className="text-zinc-400">
                            Manage your {isAsset ? 'asset' : 'product'}{' '}
                            inventory efficiently
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="mr-2 flex items-center space-x-2 rounded-md border border-zinc-800 bg-zinc-900/50 p-2">
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

                        <Link
                            href={
                                isAsset
                                    ? '/assetlancar/create'
                                    : itemRoutes.create.url()
                            }
                        >
                            <Button className="border-0 bg-blue-600 text-white hover:bg-blue-700">
                                <Plus className="mr-2 h-4 w-4" /> Add{' '}
                                {isAsset ? 'Asset' : 'Item'}
                            </Button>
                        </Link>
                    </div>
                </div>
                <FilterItem
                    baseUrl={isAsset ? '/assetlancar' : '/items'}
                    filters={filters as any}
                    tags={tags}
                    isAsset={isAsset}
                />
                {/* Table Section */}
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
                                        Barcode
                                    </th>
                                    {isAsset ? (
                                        <>
                                            <th
                                                scope="col"
                                                className="px-6 py-3"
                                            >
                                                Name
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-6 py-3"
                                            >
                                                SKU
                                            </th>
                                        </>
                                    ) : (
                                        <>
                                            <th
                                                scope="col"
                                                className="px-6 py-3"
                                            >
                                                SKU
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-6 py-3"
                                            >
                                                Kode Produksi
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-6 py-3"
                                            >
                                                Alias
                                            </th>
                                        </>
                                    )}
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Description
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Price
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        NB
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Qty
                                    </th>
                                    <th className="px-6 py-4 font-bold tracking-wider">
                                        Jubelio
                                    </th>
                                    <th className="px-6 py-4 text-right font-bold tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/50">
                                {items.data.length > 0 ? (
                                    items.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="group cursor-pointer transition-colors hover:bg-zinc-800/40"
                                            onClick={() =>
                                                router.get(
                                                    isAsset
                                                        ? `/assetlancar/${item.id}`
                                                        : itemRoutes.show.url({
                                                              item: item.id,
                                                          }),
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
                                                    href={
                                                        isAsset
                                                            ? `/assetlancar/${item.id}`
                                                            : itemRoutes.show.url(
                                                                  {
                                                                      item: item.id,
                                                                  },
                                                              )
                                                    }
                                                    className="text-blue-500 hover:text-blue-400 hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {item.id}
                                                </Link>
                                            </td>

                                            {/* Column Logic:
                                                Item: SKU, Kode Produksi, Alias
                                                Asset: Name, SKU
                                            */}

                                            {isAsset ? (
                                                <>
                                                    <td className="px-6 py-4 text-zinc-300 italic">
                                                        {item.group?.alias ||
                                                            item.name ||
                                                            '-'}
                                                    </td>
                                                    <td className="px-6 py-4 text-zinc-400">
                                                        {item.code || '-'}
                                                    </td>
                                                </>
                                            ) : (
                                                <>
                                                    <td className="px-6 py-4 text-zinc-400">
                                                        {item.code || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 text-zinc-400">
                                                        {item.pcode || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 text-zinc-300 italic">
                                                        {item.group?.alias ||
                                                            item.name ||
                                                            '-'}
                                                    </td>
                                                </>
                                            )}

                                            <td className="max-w-xs truncate px-6 py-4">
                                                <span className="text-zinc-300">
                                                    {item.group?.description ||
                                                        item.description ||
                                                        '-'}
                                                </span>
                                            </td>

                                            <td className="px-6 py-4 font-bold text-white">
                                                {new Intl.NumberFormat(
                                                    'id-ID',
                                                    {
                                                        style: 'currency',
                                                        currency: 'IDR',
                                                        maximumFractionDigits: 0,
                                                    },
                                                ).format(item.price)}
                                            </td>

                                            <td className="px-6 py-4 text-zinc-500">
                                                {item.group?.description2 ||
                                                    item.description2 ||
                                                    '--'}
                                            </td>
                                            <td className="px-6 py-4 font-bold text-green-500">
                                                {item.qty}
                                            </td>

                                            <td className="px-6 py-4 text-zinc-400">
                                                {item.jubelio_item_id ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-blue-800 bg-blue-900/20 text-blue-500"
                                                    >
                                                        {item.jubelio_item_id}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-zinc-600">
                                                        no sync
                                                    </span>
                                                )}
                                            </td>

                                            <td
                                                className="px-6 py-4 text-right"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={
                                                            isAsset
                                                                ? `/assetlancar/${item.id}/edit`
                                                                : itemRoutes.edit.url(
                                                                      {
                                                                          item: item.id,
                                                                      },
                                                                  )
                                                        }
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-zinc-800 bg-zinc-900 text-sm font-medium text-zinc-400 ring-offset-background transition-colors hover:bg-zinc-800 hover:text-zinc-50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="px-6 py-12 text-center text-zinc-500"
                                        >
                                            No items found matching your
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
                            links={items.links}
                            from={items.from}
                            to={items.to}
                            total={items.total}
                            label="items"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
