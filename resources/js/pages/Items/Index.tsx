import { Head, Link, router } from '@inertiajs/react';
import {
    FilePen,
    Plus,
    Search,
    Package
} from 'lucide-react';
import { useState } from 'react';
import FilterItem from '@/components/Partial/Filter/FilterItem';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    code?: string;
    name?: string;
    alias?: string;
    desc?: string;
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

    const isAsset =
        filters.type == '2' ||
        (typeof window !== 'undefined' &&
            window.location.pathname.includes('assetlancar'));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isAsset ? 'Asset Lancar' : 'Item List'} />

            {/* Matching standard root classes from Transactions/Index.tsx */}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header Section */}
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="mb-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            {isAsset ? 'Asset List' : 'Item List'}
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Manage your {isAsset ? 'asset' : 'product'}{' '}
                            inventory efficiently
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="mr-2 flex items-center space-x-2 rounded-md border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <Switch
                                id="show-image"
                                checked={showImage}
                                onCheckedChange={setShowImage}
                            />
                            <Label
                                htmlFor="show-image"
                                className="cursor-pointer text-sm text-zinc-600 dark:text-zinc-300"
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
                            <Button className="h-9 border-0 bg-blue-600 text-white hover:bg-blue-700">
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

                {/* Matching Data Table standard from Transactions/Index.tsx */}
                <div className="overflow-hidden border bg-white text-[13px] shadow-sm dark:bg-zinc-900">
                    <Table
                        wrapperClassName="max-h-[60vh] md:max-h-[calc(100vh-280px)] overflow-auto"
                        className="border-separate border-spacing-0"
                    >
                        <TableHeader className="bg-zinc-50 dark:bg-zinc-900">
                            <TableRow>
                                {showImage && (
                                    <TableHead className="sticky top-0 z-30 left-0 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                        Image
                                    </TableHead>
                                )}
                                <TableHead className={cn("sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900", !showImage && "left-0 z-30")}>
                                    Barcode
                                </TableHead>
                                {isAsset ? (
                                    <>
                                        <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                            Name
                                        </TableHead>
                                        <TableHead className="sticky top-0 z-20 w-[100px] border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-normal dark:bg-zinc-900">
                                            SKU
                                        </TableHead>
                                    </>
                                ) : (
                                    <>
                                        <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                            SKU
                                        </TableHead>
                                        <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                            Kode Produksi
                                        </TableHead>
                                        <TableHead className="sticky top-0 z-20 min-w-[120px] border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 dark:bg-zinc-900">
                                            Alias
                                        </TableHead>
                                    </>
                                )}
                                <TableHead className="sticky top-0 z-20 w-[180px] border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-normal dark:bg-zinc-900">
                                    Description
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                    Price
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 min-w-[150px] border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 dark:bg-zinc-900">
                                    NB
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                    Qty
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] font-bold uppercase tracking-wider text-zinc-500 whitespace-nowrap dark:bg-zinc-900">
                                    Jubelio
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 dark:bg-zinc-900"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length > 0 ? (
                                items.data.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className="group cursor-pointer transition-colors hover:bg-zinc-100/50 dark:hover:bg-zinc-800/50"
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
                                            <TableCell className="sticky left-0 z-10 bg-white py-3 px-2 text-[13px] transition-colors dark:bg-zinc-900 group-hover:bg-zinc-100/50 dark:group-hover:bg-zinc-800/50">
                                                <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-white dark:border-zinc-700">
                                                    {item.image_url ? (
                                                        <img
                                                            src={item.image_url}
                                                            alt={item.name}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <Package className="h-8 w-8 text-zinc-500" />
                                                    )}
                                                </div>
                                            </TableCell>
                                        )}
                                        <TableCell className={cn("py-3 px-2 text-[13px] font-medium whitespace-nowrap transition-colors", !showImage && "sticky left-0 z-10 bg-white dark:bg-zinc-900 group-hover:bg-zinc-100/50 dark:group-hover:bg-zinc-800/50")}>
                                            <Link
                                                href={
                                                    isAsset
                                                        ? `/assetlancar/${item.id}`
                                                        : itemRoutes.show.url({
                                                              item: item.id,
                                                          })
                                                }
                                                className="text-blue-600 hover:text-blue-500 hover:underline dark:text-blue-500"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {item.id}
                                            </Link>
                                        </TableCell>

                                        {isAsset ? (
                                            <>
                                                <TableCell className="min-w-[120px] py-3 px-2 text-[13px] italic text-zinc-700 dark:text-zinc-300">
                                                    {item.group?.alias || item.name || '-'}
                                                </TableCell>
                                                <TableCell className="w-[100px] whitespace-normal break-words py-3 px-2 text-[13px] text-zinc-500 dark:text-zinc-400">
                                                    {item.code || '-'}
                                                </TableCell>
                                            </>
                                        ) : (
                                            <>
                                                <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] text-zinc-500 dark:text-zinc-400">
                                                    {item.code || '-'}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] text-zinc-500 dark:text-zinc-400">
                                                    {item.pcode || '-'}
                                                </TableCell>
                                                <TableCell className="min-w-[120px] py-3 px-2 text-[13px] italic text-zinc-700 dark:text-zinc-300">
                                                    {item.group?.alias || item.name || '-'}
                                                </TableCell>
                                            </>
                                        )}

                                        <TableCell className="w-[180px] whitespace-normal break-words py-3 px-2 text-[13px] leading-tight text-zinc-700 dark:text-zinc-300">
                                            {item.group?.description || item.description || '-'}
                                        </TableCell>

                                        <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] font-bold text-zinc-900 tabular-nums dark:text-zinc-100">
                                            {new Intl.NumberFormat('id-ID', {
                                                style: 'currency',
                                                currency: 'IDR',
                                                maximumFractionDigits: 0,
                                            }).format(item.price)}
                                        </TableCell>

                                        <TableCell className="min-w-[150px] py-3 px-2 text-[13px] text-zinc-500 leading-tight">
                                            {item.group?.description2 || item.description2 || '--'}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] font-bold text-emerald-600 dark:text-green-500 tabular-nums">
                                            {item.qty}
                                        </TableCell>

                                        <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] text-zinc-400">
                                            {item.jubelio_item_id ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-blue-200 bg-blue-100 px-1 py-0 text-[13px] text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-500"
                                                >
                                                    {item.jubelio_item_id}
                                                </Badge>
                                            ) : (
                                                <span className="text-[13px] text-zinc-500">no sync</span>
                                            )}
                                        </TableCell>

                                        <TableCell className="py-3 px-2 text-right" onClick={(e) => e.stopPropagation()}>
                                            <div className="flex justify-end pr-1">
                                                <Link
                                                    href={
                                                        isAsset
                                                            ? `/assetlancar/${item.id}/edit`
                                                            : itemRoutes.edit.url({
                                                                  item: item.id,
                                                              })
                                                    }
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-500 hover:bg-zinc-200 hover:text-blue-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-blue-400"
                                                    >
                                                        <FilePen className="h-4 w-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={10} className="h-32 px-3 py-8 text-center text-zinc-500">
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                <Search className="h-4 w-4 text-zinc-400" />
                                            </div>
                                            <p>No items found matching your filters.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                    {/* Pagination matching Transactions standard */}
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
