import { Head, Link, usePage } from '@inertiajs/react';
import {
    FilePen,
    Package,
    Image as ImageIcon,
    Printer,
    PenSquare,
    Box,
    ChevronRight,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Items', href: '/items' },
    { title: 'Item Details', href: '#' },
];

interface WarehouseItem {
    id: number;
    warehouse: {
        id: number;
        name: string;
        type: number;
    };
    quantity: number;
}

interface Item {
    id: number;
    pcode: string;
    code: string;
    name: string;
    brand: number; // Enum or string depending on backend
    type: number; // Enum
    price: number;
    cost: number;
    description: string;
    description2: string;
    alias?: string;
    tags?: { id: number; name: string }[];
    group?: {
        id: number;
        name: string;
        alias: string;
        description: string;
        description2: string;
    };
    warehouse_items: WarehouseItem[];
    image_url?: string;
    updated_at?: string;
    updated_by?: string;
}

interface Props {
    item: Item;
}

export default function ItemsShow({ item }: Props) {
    const [showZero, setShowZero] = useState(false);
    const [activeTab, setActiveTab] = useState('Detail');
    const [expandedDesc, setExpandedDesc] = useState(false);
    const [expandedNB, setExpandedNB] = useState(false);

    const tabs = [
        { name: 'Detail', href: '#', current: activeTab === 'Detail' },
        {
            name: 'Transaction',
            href: '#',
            current: activeTab === 'Transaction',
        },
        { name: 'Stats', href: '#', current: activeTab === 'Stats' },
        { name: 'Jubelio', href: '#', current: activeTab === 'Jubelio' },
    ];

    // Calculate total stock
    const totalStock =
        item.warehouse_items?.reduce(
            (sum, wh) => sum + Number(wh.quantity),
            0,
        ) || 0;

    // Filter lists
    const warehouseList = item.warehouse_items || [];

    const renderStockList = (list: WarehouseItem[]) => {
        const visibleList = showZero
            ? list
            : list.filter((wh) => Number(wh.quantity) > 0);

        if (visibleList.length === 0) {
            return (
                <div className="col-span-full py-8 text-center text-sm text-gray-500 italic">
                    No warehouse data available.
                </div>
            );
        }

        return (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {visibleList.map((whItem) => (
                    <div
                        key={whItem.id}
                        className={cn(
                            'flex items-center justify-between rounded-xl border border-gray-800 bg-[#161616] p-4 transition-all',
                            whItem.quantity < 1 ? 'opacity-50' : '',
                        )}
                    >
                        <div>
                            <p className="font-medium text-white">
                                {whItem.warehouse?.name || 'Unknown'}
                            </p>
                            <p className="text-[10px] text-gray-500 uppercase">
                                ID: {whItem.warehouse?.id}
                            </p>
                        </div>
                        <div className="text-right">
                            <p
                                className={cn(
                                    'text-lg font-bold',
                                    whItem.quantity > 0
                                        ? 'text-blue-400'
                                        : 'text-gray-500',
                                )}
                            >
                                {whItem.quantity}
                            </p>
                            <p className="text-[10px] font-bold text-gray-600 uppercase">
                                {whItem.quantity > 0 ? 'Units' : 'Out of Stock'}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Item: ${item.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                {/* Header / Breadcrumbs - using AppLayout's built-in breadcrumbs usually, but adding the specific header from design if needed inside content */}

                {/* Scrollable Area */}
                <div className="flex-1 p-8">
                    {/* Page Title & Header Actions */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Detail Item #{item.code}
                            </h1>
                            <p className="text-sm text-gray-500">
                                Last updated {item.updated_at || 'recently'} by{' '}
                                {item.updated_by || 'System Admin'}
                            </p>
                        </div>
                        <div className="flex gap-3">
                            <Button
                                variant="outline"
                                className="border-gray-700 bg-gray-800 text-white hover:bg-gray-700 hover:text-white"
                            >
                                <Printer className="mr-2 h-4 w-4" /> Print Label
                            </Button>
                            <Link href={itemRoutes.edit.url({ item: item.id })}>
                                <Button className="border-0 bg-green-600 text-white shadow-lg shadow-green-900/20 hover:bg-green-500">
                                    <PenSquare className="mr-2 h-4 w-4" /> Edit
                                    Details
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800">
                        <Link
                            href={`/items/${item.id}`}
                            className={cn(
                                'border-b-2 px-6 py-4 text-sm font-medium transition-all',
                                activeTab === 'Detail'
                                    ? 'border-blue-500 text-blue-500'
                                    : 'border-transparent text-gray-500 hover:border-gray-700 hover:text-white',
                            )}
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/items/${item.id}/transactions`}
                            className={cn(
                                'border-b-2 px-6 py-4 text-sm font-medium transition-all',
                                activeTab === 'Transaction'
                                    ? 'border-blue-500 text-blue-500'
                                    : 'border-transparent text-gray-500 hover:border-gray-700 hover:text-white',
                            )}
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/items/${item.id}/stats`}
                            className={cn(
                                'border-b-2 px-6 py-4 text-sm font-medium transition-all',
                                activeTab === 'Stats'
                                    ? 'border-blue-500 text-blue-500'
                                    : 'border-transparent text-gray-500 hover:border-gray-700 hover:text-white',
                            )}
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/items/${item.id}/jubelio`}
                            className={cn(
                                'border-b-2 px-6 py-4 text-sm font-medium transition-all',
                                activeTab === 'Jubelio'
                                    ? 'border-blue-500 text-blue-500'
                                    : 'border-transparent text-gray-500 hover:border-gray-700 hover:text-white',
                            )}
                        >
                            Jubelio
                        </Link>
                    </div>

                    {/* Detail Content Grid */}
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-12">
                        {/* Image Section */}
                        <div className="xl:col-span-5">
                            <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] p-4 shadow-xl">
                                {item.image_url ? (
                                    <img
                                        src={item.image_url}
                                        alt="Item Main Image"
                                        className="aspect-[4/3] h-auto w-full rounded-xl object-cover"
                                    />
                                ) : (
                                    <div className="flex aspect-[4/3] h-full w-full items-center justify-center rounded-xl bg-[#161616] text-gray-700">
                                        <ImageIcon className="h-24 w-24" />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Attributes Section */}
                        <div className="xl:col-span-7">
                            <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                                <div className="flex items-center justify-between border-b border-gray-800 bg-[#161616] px-6 py-5">
                                    <div className="flex items-center gap-3">
                                        <div className="h-6 w-2 rounded-full bg-blue-500"></div>
                                        <h3 className="text-sm font-semibold tracking-widest text-white uppercase">
                                            Item Specifications
                                        </h3>
                                    </div>
                                </div>

                                <div className="space-y-6 p-6">
                                    {/* Group 1: Identity */}
                                    <div className="grid grid-cols-1 gap-6 rounded-xl border border-gray-800 bg-[#161616] p-4 md:grid-cols-2">
                                        <div>
                                            <p className="mb-1.5 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                Product Name
                                            </p>
                                            <p className="text-lg font-semibold text-white">
                                                {item.name}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1.5 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                SKU Reference
                                            </p>
                                            <span className="inline-block rounded border border-blue-500/10 bg-blue-500/5 px-2 py-1 font-mono text-sm text-blue-400">
                                                {item.code}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Group 2: Technical Details */}
                                    <div className="grid grid-cols-1 gap-8 px-2">
                                        <div>
                                            <p className="mb-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                Barcode / Alias
                                            </p>
                                            <div className="flex items-center gap-3">
                                                <div className="min-w-[80px]">
                                                    <span className="font-medium text-white">
                                                        {item.id}
                                                    </span>
                                                </div>
                                                <span className="h-4 w-px flex-shrink-0 bg-gray-800"></span>
                                                <div className="flex-1 rounded-md border border-gray-800/50 bg-[#161616] px-3 py-1.5">
                                                    <span className="text-gray-400 italic">
                                                        {item.group
                                                            ? item.group.alias
                                                            : item.alias || '-'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Group: Description & Notes */}
                                    <div className="grid grid-cols-1 gap-6 border-t border-gray-800/50 px-2 pt-4">
                                        <div className="space-y-4">
                                            <div>
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                    Description / NB
                                                </p>
                                                <div className="space-y-4">
                                                    <div>
                                                        <p className="mb-1 text-[10px] font-bold tracking-tight text-gray-600 uppercase">
                                                            Description
                                                        </p>
                                                        <p
                                                            className={cn(
                                                                'text-xs leading-relaxed text-gray-400',
                                                                !expandedDesc &&
                                                                    'line-clamp-3',
                                                            )}
                                                        >
                                                            {item.group
                                                                ? item.group
                                                                      .description
                                                                : item.description ||
                                                                  '-'}
                                                        </p>
                                                        {(
                                                            item.group
                                                                ?.description ||
                                                            item.description ||
                                                            ''
                                                        ).length > 100 && (
                                                            <button
                                                                onClick={() =>
                                                                    setExpandedDesc(
                                                                        !expandedDesc,
                                                                    )
                                                                }
                                                                className="mt-2 text-[10px] font-bold tracking-wider text-blue-500 uppercase transition-colors hover:text-blue-400"
                                                            >
                                                                {expandedDesc
                                                                    ? 'Read Less'
                                                                    : 'Read More'}
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <p className="mb-1 text-[10px] font-bold tracking-tight text-gray-600 uppercase">
                                                            NB
                                                        </p>
                                                        <p
                                                            className={cn(
                                                                'text-xs leading-relaxed text-gray-400',
                                                                !expandedNB &&
                                                                    'line-clamp-3',
                                                            )}
                                                        >
                                                            {item.group
                                                                ?.description2 ||
                                                                item.description2 ||
                                                                '-'}
                                                        </p>
                                                        {(
                                                            item.group
                                                                ?.description2 ||
                                                            item.description2 ||
                                                            ''
                                                        ).length > 100 && (
                                                            <button
                                                                onClick={() =>
                                                                    setExpandedNB(
                                                                        !expandedNB,
                                                                    )
                                                                }
                                                                className="mt-2 text-[10px] font-bold tracking-wider text-blue-500 uppercase transition-colors hover:text-blue-400"
                                                            >
                                                                {expandedNB
                                                                    ? 'Read Less'
                                                                    : 'Read More'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Group 3: Financials & Tags */}
                                    <div className="grid grid-cols-1 gap-8 border-t border-gray-800/50 px-2 pt-4 md:grid-cols-2">
                                        <div>
                                            <p className="mb-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                Pricing Strategy
                                            </p>
                                            <div className="flex flex-col">
                                                <p className="text-2xl font-black tracking-tight text-white">
                                                    {new Intl.NumberFormat(
                                                        'id-ID',
                                                        {
                                                            style: 'currency',
                                                            currency: 'IDR',
                                                        },
                                                    ).format(item.price)}
                                                </p>
                                                <p className="mt-1 text-[10px] text-gray-500">
                                                    Base Cost:{' '}
                                                    <span className="text-gray-400">
                                                        {new Intl.NumberFormat(
                                                            'id-ID',
                                                            {
                                                                style: 'currency',
                                                                currency: 'IDR',
                                                            },
                                                        ).format(item.cost)}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="mb-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                Group & Tags
                                            </p>
                                            <div className="flex flex-wrap gap-2">
                                                {item.group && (
                                                    <span className="mr-2 text-xs font-medium text-blue-400 underline decoration-blue-900/50 underline-offset-4">
                                                        {item.group.name}
                                                    </span>
                                                )}

                                                {item.tags?.map((tag) => (
                                                    <span
                                                        key={tag.id}
                                                        className="rounded border border-blue-500/20 bg-blue-500/10 px-2 py-1 text-[9px] font-bold tracking-tighter text-blue-400 uppercase"
                                                    >
                                                        {tag.name}
                                                    </span>
                                                ))}

                                                {!item.tags?.length &&
                                                    !item.group && (
                                                        <span className="text-[10px] text-gray-600">
                                                            No tags
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Warehouse Section */}
                    <div className="mt-8">
                        <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                            <div className="flex items-center justify-between border-b border-gray-800 px-6 py-4">
                                <h3 className="text-sm font-semibold tracking-wider text-white uppercase">
                                    Warehouse Availability
                                </h3>
                                <div className="flex items-center gap-6">
                                    <div className="flex items-center gap-3">
                                        <span className="text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Show empty warehouses
                                        </span>
                                        <Switch
                                            checked={showZero}
                                            onCheckedChange={setShowZero}
                                            className="border-transparent bg-gray-700 data-[state=checked]:bg-green-600"
                                        />
                                    </div>
                                    <div className="text-xs font-medium text-gray-500">
                                        Total Stock:{' '}
                                        <span className="text-white">
                                            {totalStock} Units
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="p-6">
                                {renderStockList(warehouseList)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
