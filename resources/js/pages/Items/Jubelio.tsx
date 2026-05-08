import { Head, Link, useForm } from '@inertiajs/react';
import {
    Image as ImageIcon,
    Search,
    RefreshCw,
    ExternalLink,
    Box,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Items', href: '/items' },
    { title: 'Item Details', href: '#' },
    { title: 'Jubelio Sync', href: '#' },
];

interface Item {
    id: number;
    code: string;
    name: string;
    jubelio_item_id: number | null;
    image_url?: string;
}

interface Props {
    item: Item;
    dataJubelio: any;
    message: string;
}

export default function ItemsJubelio({ item, dataJubelio, message }: Props) {
    const activeTab = 'Jubelio';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Jubelio: ${item.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Detail Item #{item.code}
                            </h1>
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800 text-nowrap">
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

                    <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                        {/* Left: Item Image */}
                        <div>
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

                        {/* Right: Jubelio Data */}
                        <div className="space-y-6">
                            <Card className="overflow-hidden rounded-2xl border-gray-800 bg-[#111] text-gray-300 shadow-xl">
                                <CardHeader className="border-b border-gray-800 bg-[#161616]">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="text-white">
                                                Jubelio Sync
                                            </CardTitle>
                                            <CardDescription className="text-gray-500">
                                                Real-time data from Jubelio API
                                            </CardDescription>
                                        </div>
                                        <Link
                                            href={`/items/${item.id}/jubelio-search`}
                                        >
                                            <Button className="rounded-xl bg-green-600 text-white hover:bg-green-700">
                                                <Search className="mr-2 h-4 w-4" />{' '}
                                                Link Item
                                            </Button>
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y divide-gray-800 p-0">
                                    <div className="grid grid-cols-2 p-4">
                                        <span className="font-bold text-gray-400">
                                            Jubelio Item ID
                                        </span>
                                        <span className="font-mono text-white">
                                            {item.jubelio_item_id ||
                                                'Not Linked'}
                                        </span>
                                    </div>

                                    {message !== 'ok' ? (
                                        <div className="bg-yellow-500/5 p-8 text-center text-sm text-yellow-500/80 italic">
                                            {message}
                                        </div>
                                    ) : (
                                        <>
                                            <div className="grid grid-cols-2 p-4">
                                                <span className="font-bold text-gray-400">
                                                    Jubelio Item Name
                                                </span>
                                                <span className="text-white">
                                                    {dataJubelio?.item_name ||
                                                        '-'}
                                                </span>
                                            </div>

                                            <div className="border-y border-gray-800 bg-[#161616]/50 p-4">
                                                <span className="flex items-center gap-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                                    <Box className="h-3 w-3" />{' '}
                                                    Inventory Status
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-2 items-center p-4">
                                                <span className="ml-4 text-gray-400">
                                                    On Hand
                                                </span>
                                                <span className="text-xl font-bold text-blue-400">
                                                    {dataJubelio?.total_stocks
                                                        ?.on_hand ?? 0}
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-2 items-center p-4">
                                                <span className="ml-4 text-gray-400">
                                                    On Order
                                                </span>
                                                <span className="text-xl font-bold text-orange-400">
                                                    {dataJubelio?.total_stocks
                                                        ?.on_order ?? 0}
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-2 items-center p-4">
                                                <span className="ml-4 text-gray-400">
                                                    Available
                                                </span>
                                                <span className="text-xl font-bold text-green-400">
                                                    {dataJubelio?.total_stocks
                                                        ?.available ?? 0}
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {item.jubelio_item_id && (
                                <div className="flex justify-end">
                                    <Button
                                        variant="ghost"
                                        onClick={() => window.location.reload()}
                                        className="text-gray-500 hover:text-white"
                                    >
                                        <RefreshCw className="mr-2 h-4 w-4" />{' '}
                                        Refresh Data
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
