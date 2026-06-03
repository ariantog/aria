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

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 min-h-screen bg-black text-zinc-100">
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
                <div className="overflow-hidden border bg-white text-[11px] shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="max-h-[60vh] overflow-auto md:max-h-[calc(100vh-280px)]">
                        <table className="w-full border-separate border-spacing-0 text-left">
                            <thead className="bg-zinc-50 dark:bg-zinc-900">
                                <tr>
                                    {showImage && (
                                        <th className="sticky top-0 left-0 z-30 border-b border-r bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                            Image
                                        </th>
                                    )}
                                    <th className={`sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900 ${!showImage && 'left-0 z-30 border-r'}`}>
                                        Barcode
                                    </th>
                                    {isAsset ? (
                                        <>
                                            <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                                Name
                                            </th>
                                            <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                                SKU
                                            </th>
                                        </>
                                    ) : (
                                        <>
                                            <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                                SKU
                                            </th>
                                            <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                                Kode Produksi
                                            </th>
                                            <th className="sticky top-0 z-20 min-w-[120px] border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase dark:bg-zinc-900">
                                                Alias
                                            </th>
                                        </>
                                    )}
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Description
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Price
                                    </th>
                                    <th className="sticky top-0 z-20 min-w-[150px] border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase dark:bg-zinc-900">
                                        NB
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Qty
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Jubelio
                                    </th>
                                    <th className="sticky top-0 z-20 border-b border-r bg-zinc-50 px-2 py-3 text-right text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/50">
                                {items.data.length > 0 ? (
                                    items.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="group cursor-pointer transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50"
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
                                                <td className="sticky left-0 z-10 border-r bg-white px-2 py-1 dark:bg-zinc-900">
                                                    <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-white dark:border-zinc-700">
                                                        {item.image_url ? (
                                                            <img
                                                                src={
                                                                    item.image_url
                                                                }
                                                                alt={item.name}
                                                                className="h-full w-full object-cover"
                                                            />
                                                        ) : (
                                                            <Package className="h-8 w-8 text-zinc-500" />
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                            <td className={`px-2 py-1 font-medium whitespace-nowrap ${!showImage && 'sticky left-0 z-10 border-r bg-white dark:bg-zinc-900'}`}>
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
                                                    className="text-blue-600 hover:text-blue-500 hover:underline dark:text-blue-500"
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
                                                    <td className="min-w-[120px] px-2 py-1 italic text-zinc-700 dark:text-zinc-300">
                                                        {item.group?.alias ||
                                                            item.name ||
                                                            '-'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-2 py-1 text-zinc-500 dark:text-zinc-400">
                                                        {item.code || '-'}
                                                    </td>
                                                </>
                                            ) : (
                                                <>
                                                    <td className="whitespace-nowrap px-2 py-1 text-zinc-500 dark:text-zinc-400">
                                                        {item.code || '-'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-2 py-1 text-zinc-500 dark:text-zinc-400">
                                                        {item.pcode || '-'}
                                                    </td>
                                                    <td className="min-w-[120px] px-2 py-1 italic text-zinc-700 dark:text-zinc-300">
                                                        {item.group?.alias ||
                                                            item.name ||
                                                            '-'}
                                                    </td>
                                                </>
                                            )}

                                            <td className="max-w-[200px] truncate px-2 py-1 leading-tight">
                                                <span className="text-zinc-700 dark:text-zinc-300">
                                                    {item.group?.description ||
                                                        item.description ||
                                                        '-'}
                                                </span>
                                            </td>

                                            <td className="whitespace-nowrap px-2 py-1 font-bold text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {new Intl.NumberFormat(
                                                    'id-ID',
                                                    {
                                                        style: 'currency',
                                                        currency: 'IDR',
                                                        maximumFractionDigits: 0,
                                                    },
                                                ).format(item.price)}
                                            </td>

                                            <td className="min-w-[150px] px-2 py-1 text-zinc-500 leading-tight">
                                                {item.group?.description2 ||
                                                    item.description2 ||
                                                    '--'}
                                            </td>
                                            <td className="whitespace-nowrap px-2 py-1 font-bold text-emerald-600 dark:text-green-500 tabular-nums">
                                                {item.qty}
                                            </td>

                                            <td className="whitespace-nowrap px-2 py-1 text-zinc-400">
                                                {item.jubelio_item_id ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-blue-200 bg-blue-100 px-1 py-0 text-[9px] text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-500"
                                                    >
                                                        {item.jubelio_item_id}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-[9px] text-zinc-500">
                                                        no sync
                                                    </span>
                                                )}
                                            </td>

                                            <td
                                                className="border-r px-2 py-1 text-right"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <div className="flex justify-end gap-1">
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
                                                        className="inline-flex h-6 w-6 items-center justify-center rounded-md border border-zinc-200 bg-white text-[11px] font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                                                    >
                                                        <FilePen className="h-3 w-3" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="h-32 px-3 py-8 text-center text-zinc-500"
                                        >
                                            <div className="flex flex-col items-center gap-2">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                    <Search className="h-4 w-4 text-zinc-400" />
                                                </div>
                                                <p>No items found matching your filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    <div className="border-t bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
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
