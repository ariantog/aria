import { Head, Link, router } from '@inertiajs/react';
import {
    Package,
    BarChart2,
    History,
    FileEdit,
    ArrowLeft,
    Info,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Warehouse {
    id: number;
    name: string;
    type: number;
}

interface WarehouseItem {
    id: number;
    quantity: number;
    warehouse: Warehouse;
}

interface Item {
    id: number;
    code: string;
    pcode: string;
    name: string;
    type: number;
    price: number;
    cost: number;
    warehouse_items: WarehouseItem[];
}

interface Group {
    id: number;
    name: string;
    alias: string;
    description: string;
    master: string;
    variant: string;
    image_url: string;
    items: Item[];
}

interface Props {
    group: Group;
}

export default function GroupShow({ group }: Props) {
    const [showZeroStock, setShowZeroStock] = useState(false);

    const isAssetGroup = group.items.some((item) => item.type === 2);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: isAssetGroup ? 'Assets' : 'Items',
            href: isAssetGroup ? '/assetlancar' : '/items',
        },
        { title: 'Groups', href: '/items-group' },
        { title: group.name, href: '#' },
    ];

    const getBaseUrl = (type: number) => {
        return type === 2 ? '/assetlancar' : '/items';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Group Detail: ${group.name}`} />

            <div className="min-h-screen bg-black p-4 text-zinc-100 sm:p-6 lg:p-8">
                {/* Header Actions */}
                <div className="mb-6 flex items-center justify-between">
                    <Button
                        variant="ghost"
                        className="text-zinc-400 hover:text-white"
                        onClick={() => router.get('/items-group')}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" /> Back to Group
                        List
                    </Button>
                </div>

                {/* Group Info Section */}
                <div className="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Image Card */}
                    <Card className="overflow-hidden border-zinc-800 bg-zinc-900 lg:col-span-1">
                        <CardContent className="flex min-h-[300px] items-center justify-center bg-white p-0">
                            {group.image_url ? (
                                <img
                                    src={group.image_url}
                                    alt={group.name}
                                    className="max-h-[400px] w-auto object-contain"
                                />
                            ) : (
                                <Package className="h-20 w-20 text-zinc-300" />
                            )}
                        </CardContent>
                    </Card>

                    {/* Details Card */}
                    <Card className="border-zinc-800 bg-zinc-900 lg:col-span-2">
                        <CardHeader className="border-b border-zinc-800">
                            <CardTitle className="flex items-center gap-2 text-2xl font-bold">
                                <Info className="h-6 w-6 text-blue-500" /> Group
                                Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-6">
                            <div className="grid grid-cols-1 gap-x-12 gap-y-6 md:grid-cols-2">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold tracking-wider text-zinc-500 uppercase">
                                        Group Name / PCode
                                    </p>
                                    <p className="text-xl font-medium text-white">
                                        {group.name}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold tracking-wider text-zinc-500 uppercase">
                                        Alias
                                    </p>
                                    <p className="text-xl font-medium text-blue-400 italic">
                                        {group.alias || '-'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold tracking-wider text-zinc-500 uppercase">
                                        Master
                                    </p>
                                    <p className="text-zinc-300">
                                        {group.master || '-'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold tracking-wider text-zinc-500 uppercase">
                                        Variant
                                    </p>
                                    <p className="text-zinc-300">
                                        {group.variant || '-'}
                                    </p>
                                </div>
                                <div className="col-span-1 space-y-1 border-t border-zinc-800 pt-4 md:col-span-2">
                                    <p className="text-sm font-semibold tracking-wider text-zinc-500 uppercase">
                                        Description
                                    </p>
                                    <p className="leading-relaxed text-zinc-200">
                                        {group.description ||
                                            'No description available for this group.'}
                                    </p>
                                </div>
                                <div className="col-span-1 flex items-center gap-4 pt-4 md:col-span-2">
                                    <div className="flex items-center space-x-2 rounded-md border border-zinc-800 bg-zinc-950 p-2">
                                        <Switch
                                            id="show-zero"
                                            checked={showZeroStock}
                                            onCheckedChange={setShowZeroStock}
                                        />
                                        <Label
                                            htmlFor="show-zero"
                                            className="cursor-pointer text-sm text-zinc-400"
                                        >
                                            Show 0 Quantity
                                        </Label>
                                    </div>
                                    <Button
                                        variant="outline"
                                        className="border-green-800 text-green-500 hover:bg-green-900/20"
                                    >
                                        <BarChart2 className="mr-2 h-4 w-4" />{' '}
                                        Group Stats
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Variants (Items) Section */}
                <h2 className="mb-4 flex items-center gap-2 text-xl font-bold text-white">
                    <Package className="h-5 w-5 text-zinc-400" /> Items /
                    Variants in Group
                </h2>

                <div className="space-y-6">
                    {group.items.map((item) => {
                        const totalQty = item.warehouse_items.reduce(
                            (sum, wh) => sum + Number(wh.quantity),
                            0,
                        );
                        const filteredWH = showZeroStock
                            ? item.warehouse_items
                            : item.warehouse_items.filter(
                                  (wh) => Number(wh.quantity) > 0,
                              );

                        return (
                            <div
                                key={item.id}
                                className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm"
                            >
                                {/* Item Header */}
                                <div className="flex flex-col items-start justify-between gap-4 border-b border-zinc-800 bg-zinc-950 px-6 py-4 md:flex-row md:items-center">
                                    <div className="flex flex-col">
                                        <div className="mb-1 flex items-center gap-3">
                                            <Badge
                                                variant="outline"
                                                className="border-zinc-700 bg-zinc-900 text-zinc-400"
                                            >
                                                {item.id}
                                            </Badge>
                                            <h3 className="text-lg font-bold text-white">
                                                {item.code} -{' '}
                                                <Link
                                                    href={`${getBaseUrl(item.type)}/${item.id}`}
                                                    className="text-blue-500 hover:underline"
                                                >
                                                    {item.name}
                                                </Link>
                                            </h3>
                                        </div>
                                        <p className="font-mono text-xs text-zinc-500">
                                            {item.pcode}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-zinc-400 hover:text-white"
                                            onClick={() =>
                                                router.get(
                                                    `${getBaseUrl(item.type)}/${item.id}/transactions`,
                                                )
                                            }
                                        >
                                            <History className="mr-2 h-4 w-4" />{' '}
                                            Transactions
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-zinc-400 hover:text-white"
                                            onClick={() =>
                                                router.get(
                                                    `${getBaseUrl(item.type)}/${item.id}/stats`,
                                                )
                                            }
                                        >
                                            <BarChart2 className="mr-2 h-4 w-4" />{' '}
                                            Stats
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-zinc-400 hover:text-white"
                                            onClick={() =>
                                                router.get(
                                                    `${getBaseUrl(item.type)}/${item.id}/edit`,
                                                )
                                            }
                                        >
                                            <FileEdit className="mr-2 h-4 w-4" />{' '}
                                            Edit
                                        </Button>
                                    </div>
                                </div>

                                {/* Warehouse Stock */}
                                <CardContent className="p-6">
                                    <div className="max-w-2xl divide-y divide-zinc-800/50">
                                        {filteredWH.length > 0 ? (
                                            filteredWH.map((wh) => (
                                                <div
                                                    key={wh.id}
                                                    className="flex justify-between py-3"
                                                >
                                                    <span className="font-medium text-zinc-300">
                                                        {wh.warehouse?.name ||
                                                            'Unknown Warehouse'}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'font-mono font-bold',
                                                            Number(
                                                                wh.quantity,
                                                            ) > 0
                                                                ? 'text-green-500'
                                                                : 'text-zinc-600',
                                                        )}
                                                    >
                                                        {wh.quantity}
                                                    </span>
                                                </div>
                                            ))
                                        ) : (
                                            <div className="py-4 text-zinc-600 italic">
                                                No stock found in warehouses.
                                            </div>
                                        )}
                                        <div className="mt-2 flex justify-between border-t border-zinc-700 py-4 text-lg font-bold">
                                            <span className="text-white">
                                                Total Quantity
                                            </span>
                                            <span className="font-mono text-green-400">
                                                {totalQty}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
