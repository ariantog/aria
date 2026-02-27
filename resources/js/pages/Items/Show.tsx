
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import itemRoutes from '@/routes/items';
import { FilePen, Package, Image as ImageIcon, Printer, PenSquare, Box, ChevronRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { useState } from 'react';
import { cn } from "@/lib/utils";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Items', href: '/items' },
    { title: 'Item Details', href: '#' },
];

interface WarehouseItem {
    id: number;
    warehouse: {
        id: number;
        name: string;
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
        { name: 'Transaction', href: '#', current: activeTab === 'Transaction' },
        { name: 'Stats', href: '#', current: activeTab === 'Stats' },
        { name: 'Jubelio', href: '#', current: activeTab === 'Jubelio' },
    ];

    // Calculate total stock
    const totalStock = item.warehouse_items?.reduce((sum, wh) => sum + wh.quantity, 0) || 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Item: ${item.name}`} />

            <div className="flex-1 flex flex-col h-full bg-[#0A0A0A] min-h-screen text-gray-300 font-sans antialiased">

                {/* Header / Breadcrumbs - using AppLayout's built-in breadcrumbs usually, but adding the specific header from design if needed inside content */}

                {/* Scrollable Area */}
                <div className="flex-1 p-8">
                    {/* Page Title & Header Actions */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <h1 className="text-2xl font-bold text-white mb-1">Detail Item #{item.code}</h1>
                            <p className="text-gray-500 text-sm">
                                Last updated {item.updated_at || 'recently'} by {item.updated_by || 'System Admin'}
                            </p>
                        </div>
                        <div className="flex gap-3">
                            <Button variant="outline" className="bg-gray-800 hover:bg-gray-700 text-white border-gray-700 hover:text-white">
                                <Printer className="mr-2 h-4 w-4" /> Print Label
                            </Button>
                            <Link href={itemRoutes.edit.url({ item: item.id })}>
                                <Button className="bg-green-600 hover:bg-green-500 text-white shadow-lg shadow-green-900/20 border-0">
                                    <PenSquare className="mr-2 h-4 w-4" /> Edit Details
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="flex border-b border-gray-800 mb-8 overflow-x-auto">
                        {tabs.map((tab) => (
                            <button
                                key={tab.name}
                                onClick={() => setActiveTab(tab.name)}
                                className={cn(
                                    "px-6 py-4 text-sm font-medium transition-all border-b-2",
                                    activeTab === tab.name
                                        ? "text-blue-500 border-blue-500"
                                        : "text-gray-500 hover:text-white border-transparent hover:border-gray-700"
                                )}
                            >
                                {tab.name}
                            </button>
                        ))}
                    </div>

                    {/* Detail Content Grid */}
                    <div className="grid grid-cols-1 xl:grid-cols-12 gap-8">
                        {/* Image Section */}
                        <div className="xl:col-span-5">
                            <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden p-4 shadow-xl">
                                {item.image_url ? (
                                    <img
                                        src={item.image_url}
                                        alt="Item Main Image"
                                        className="w-full h-auto rounded-xl object-cover aspect-[4/3]"
                                    />
                                ) : (
                                    <div className="w-full h-full aspect-[4/3] bg-[#161616] rounded-xl flex items-center justify-center text-gray-700">
                                        <ImageIcon className="h-24 w-24" />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Attributes Section */}
                        <div className="xl:col-span-7">
                            <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                                <div className="px-6 py-5 border-b border-gray-800 bg-[#161616] flex justify-between items-center">
                                    <div className="flex items-center gap-3">
                                        <div className="w-2 h-6 bg-blue-500 rounded-full"></div>
                                        <h3 className="text-sm font-semibold text-white uppercase tracking-widest">Item Specifications</h3>
                                    </div>
                                </div>

                                <div className="p-6 space-y-6">
                                    {/* Group 1: Identity */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 bg-[#161616] rounded-xl border border-gray-800">
                                        <div>
                                            <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-1.5">Product Name</p>
                                            <p className="text-lg font-semibold text-white">{item.name}</p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-1.5">SKU Reference</p>
                                            <span className="font-mono text-blue-400 text-sm bg-blue-500/5 px-2 py-1 rounded border border-blue-500/10 inline-block">
                                                {item.code}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Group 2: Technical Details */}
                                    <div className="grid grid-cols-1 gap-8 px-2">
                                        <div>
                                            <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-2">Barcode / Alias</p>
                                            <div className="flex items-center gap-3">
                                                <div className="min-w-[80px]">
                                                    <span className="text-white font-medium">{item.id}</span>
                                                </div>
                                                <span className="h-4 w-px bg-gray-800 flex-shrink-0"></span>
                                                <div className="flex-1 bg-[#161616] rounded-md px-3 py-1.5 border border-gray-800/50">
                                                    <span className="text-gray-400 italic">
                                                        {item.group ? item.group.alias : item.alias || '-'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Group: Description & Notes */}
                                    <div className="grid grid-cols-1 gap-6 px-2 pt-4 border-t border-gray-800/50">
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-1">Description / NB</p>
                                                <div className="space-y-4">
                                                    <div>
                                                        <p className="text-[10px] uppercase font-bold text-gray-600 tracking-tight mb-1">Description</p>
                                                        <p className={cn("text-xs text-gray-400 leading-relaxed", !expandedDesc && "line-clamp-3")}>
                                                            {item.group ? item.group.description : item.description || '-'}
                                                        </p>
                                                        {(item.group?.description || item.description || '').length > 100 && (
                                                            <button
                                                                onClick={() => setExpandedDesc(!expandedDesc)}
                                                                className="text-[10px] font-bold text-blue-500 hover:text-blue-400 mt-2 uppercase tracking-wider transition-colors"
                                                            >
                                                                {expandedDesc ? 'Read Less' : 'Read More'}
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] uppercase font-bold text-gray-600 tracking-tight mb-1">NB</p>
                                                        <p className={cn("text-xs text-gray-400 leading-relaxed", !expandedNB && "line-clamp-3")}>
                                                            {item.group?.description2 || item.description2 || '-'}
                                                        </p>
                                                        {(item.group?.description2 || item.description2 || '').length > 100 && (
                                                            <button
                                                                onClick={() => setExpandedNB(!expandedNB)}
                                                                className="text-[10px] font-bold text-blue-500 hover:text-blue-400 mt-2 uppercase tracking-wider transition-colors"
                                                            >
                                                                {expandedNB ? 'Read Less' : 'Read More'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Group 3: Financials & Tags */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 px-2 pt-4 border-t border-gray-800/50">
                                        <div>
                                            <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-2">Pricing Strategy</p>
                                            <div className="flex flex-col">
                                                <p className="text-2xl font-black text-white tracking-tight">
                                                    {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.price)}
                                                </p>
                                                <p className="text-[10px] text-gray-500 mt-1">
                                                    Base Cost: <span className="text-gray-400">{new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.cost)}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-[10px] uppercase font-bold text-gray-500 tracking-widest mb-2">Group & Tags</p>
                                            <div className="flex flex-wrap gap-2">
                                                {item.group && (
                                                    <span className="text-blue-400 underline decoration-blue-900/50 underline-offset-4 text-xs font-medium mr-2">
                                                        {item.group.name}
                                                    </span>
                                                )}

                                                {item.tags?.map((tag) => (
                                                    <span key={tag.id} className="bg-blue-500/10 text-blue-400 text-[9px] px-2 py-1 rounded border border-blue-500/20 uppercase font-bold tracking-tighter">
                                                        {tag.name}
                                                    </span>
                                                ))}

                                                {!item.tags?.length && !item.group && <span className="text-gray-600 text-[10px]">No tags</span>}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Warehouse Section */}
                    <div className="mt-8">
                        <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                            <div className="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-white uppercase tracking-wider">Warehouse Availability</h3>
                                <div className="flex items-center gap-6">
                                    <div className="flex items-center gap-3">
                                        <span className="text-[10px] uppercase font-bold text-gray-500 tracking-widest">Show empty warehouses</span>
                                        <Switch
                                            checked={showZero}
                                            onCheckedChange={setShowZero}
                                            className="data-[state=checked]:bg-green-600 bg-gray-700 border-transparent"
                                        />
                                    </div>
                                    <div className="text-xs text-gray-500 font-medium">Total Stock: <span className="text-white">{totalStock} Units</span></div>
                                </div>
                            </div>

                            <div className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {item.warehouse_items && item.warehouse_items.length > 0 ? (
                                        item.warehouse_items.map((whItem) => (
                                            <div
                                                key={whItem.id}
                                                className={cn(
                                                    "bg-[#161616] border border-gray-800 p-4 rounded-xl flex justify-between items-center transition-all",
                                                    !showZero && whItem.quantity < 1 ? "hidden" : "",
                                                    whItem.quantity < 1 ? "opacity-50" : ""
                                                )}
                                            >
                                                <div>
                                                    <p className="text-white font-medium">{whItem.warehouse?.name || 'Unknown'}</p>
                                                    <p className="text-[10px] text-gray-500 uppercase">Warehouse ID: {whItem.warehouse?.id}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className={cn("text-lg font-bold", whItem.quantity > 0 ? "text-blue-400" : "text-gray-500")}>
                                                        {whItem.quantity}
                                                    </p>
                                                    <p className="text-[10px] text-gray-600 uppercase font-bold">
                                                        {whItem.quantity > 0 ? 'Units' : 'Out of Stock'}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="col-span-full py-8 text-center text-gray-500">
                                            No warehouse data available.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
