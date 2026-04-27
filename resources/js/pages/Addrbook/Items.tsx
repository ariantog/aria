import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import { ArrowLeft, Package, Search, Download, X, Eye, EyeOff, ImageIcon, FilePen } from 'lucide-react';
import Pagination from '@/components/pagination';
import { cn } from "@/lib/utils";
import { useState } from 'react';

interface Addrbook {
    id: number;
    name: string;
    type: number;
    type_slug: string;
}

interface ItemGroup {
    id: number;
    name: string;
    description: string | null;
    description2: string | null;
    image_url: string;
}

interface Item {
    id: number;
    name: string;
    code: string;
    image_url: string;
    price: string;
    description: string | null;
    group?: ItemGroup;
    pivot: {
        quantity: string;
    };
}

interface PaginatedItems {
    data: Item[];
    links: any[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    addrbook: Addrbook;
    items: PaginatedItems;
    filters: {
        name?: string;
        sort?: string;
        show0?: boolean;
    };
}

export default function AddrbookItems({ addrbook, items, filters }: Props) {
    const [name, setName] = useState(filters.name || '');
    const [sort, setSort] = useState(filters.sort || 'qtydesc');
    const [show0, setShow0] = useState(filters.show0 || false);
    
    const [showImage, setShowImage] = useState(false);
    const [isOnlineName, setIsOnlineName] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Items', href: '#' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(`/${addrbook.type_slug}/${addrbook.id}/items`, { 
            name, 
            sort, 
            show0: show0 ? 'show' : '' 
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setName('');
        setSort('qtydesc');
        setShow0(false);
        router.get(`/${addrbook.type_slug}/${addrbook.id}/items`);
    };

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value) || 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Items: ${addrbook.name}`} />

            <div className="flex-1 flex flex-col h-full bg-[#0A0A0A] min-h-screen text-gray-300 font-sans antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <Link href={`/addrbook/${addrbook.id}`} className="text-gray-500 hover:text-white transition-colors">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="text-zinc-600 font-mono text-sm">#{addrbook.id}</span>
                            </div>
                            <h1 className="text-2xl font-bold text-white mb-1">Warehouse Stock</h1>
                            <p className="text-gray-500 text-sm">
                                Available inventory for <span className="text-blue-400">{addrbook.name}</span>
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <Button className="bg-emerald-600 hover:bg-emerald-500 text-white border-0">
                                <Download className="w-4 h-4 mr-2" />
                                Download CSV
                            </Button>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="flex border-b border-gray-800 mb-8 overflow-x-auto">
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/transactions`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 text-blue-500 border-blue-500 whitespace-nowrap"
                        >
                            Items
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters & Toggles */}
                    <div className="bg-[#111] p-6 rounded-xl border border-gray-800 mb-8 space-y-6">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end pb-6 border-b border-gray-800/50">
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Item Name / Code</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
                                    <input
                                        type="text"
                                        placeholder="Search..."
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 pl-10 pr-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Sort By</label>
                                <select
                                    value={sort}
                                    onChange={(e) => setSort(e.target.value)}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="qtydesc">Quantity (High to Low)</option>
                                    <option value="qtyasc">Quantity (Low to High)</option>
                                    <option value="namedesc">Name (Z-A)</option>
                                    <option value="nameasc">Name (A-Z)</option>
                                    <option value="codedesc">Code (Z-A)</option>
                                    <option value="codeasc">Code (A-Z)</option>
                                </select>
                            </div>
                            <div className="flex items-center gap-3 py-2.5">
                                <label className="flex items-center cursor-pointer">
                                    <div className="relative">
                                        <input 
                                            type="checkbox" 
                                            className="sr-only" 
                                            checked={show0}
                                            onChange={(e) => setShow0(e.target.checked)}
                                        />
                                        <div className={cn(
                                            "w-10 h-5 rounded-full transition-colors",
                                            show0 ? "bg-blue-600" : "bg-gray-700"
                                        )}></div>
                                        <div className={cn(
                                            "absolute left-1 top-1 bg-white w-3 h-3 rounded-full transition-transform",
                                            show0 ? "translate-x-5" : "translate-x-0"
                                        )}></div>
                                    </div>
                                    <span className="ml-3 text-sm font-medium text-gray-400">Show Zero Stock</span>
                                </label>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" className="flex-1 bg-blue-600 hover:bg-blue-500 text-white border-0">
                                    Apply Filter
                                </Button>
                                <Button type="button" onClick={clearFilters} variant="outline" className="border-gray-800 text-gray-400 hover:text-white">
                                    <X className="w-4 h-4" />
                                </Button>
                            </div>
                        </form>

                        <div className="flex flex-wrap items-center gap-8">
                            <div className="flex items-center gap-3">
                                <span className="text-[10px] uppercase font-bold text-gray-500">Display Options:</span>
                                <Button 
                                    size="sm" 
                                    variant="outline" 
                                    onClick={() => setShowImage(!showImage)}
                                    className={cn(
                                        "h-8 border-gray-800 px-3",
                                        showImage ? "bg-blue-600/10 text-blue-400 border-blue-500/20" : "text-gray-500"
                                    )}
                                >
                                    <ImageIcon className="w-3.5 h-3.5 mr-2" />
                                    {showImage ? "Hide Image" : "Show Image"}
                                </Button>
                                
                                <div className="flex bg-[#161616] border border-gray-800 rounded-lg p-1">
                                    <button
                                        onClick={() => setIsOnlineName(false)}
                                        className={cn(
                                            "px-3 py-1 text-[10px] uppercase font-bold rounded-md transition-all",
                                            !isOnlineName ? "bg-gray-800 text-white" : "text-gray-600 hover:text-gray-400"
                                        )}
                                    >
                                        Normal Name
                                    </button>
                                    <button
                                        onClick={() => setIsOnlineName(true)}
                                        className={cn(
                                            "px-3 py-1 text-[10px] uppercase font-bold rounded-md transition-all",
                                            isOnlineName ? "bg-gray-800 text-white" : "text-gray-600 hover:text-gray-400"
                                        )}
                                    >
                                        Online Name
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Items Table */}
                    <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-[#161616] border-b border-gray-800">
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">ID</th>
                                        {showImage && (
                                            <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Image</th>
                                        )}
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">
                                            {isOnlineName ? "Online Product Name" : "Item Name"}
                                        </th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Code</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Description</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Price</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Stock</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {items.data.length > 0 ? (
                                        items.data.map((item) => (
                                            <tr key={item.id} className="hover:bg-white/[0.02] transition-colors group">
                                                <td className="px-6 py-4 whitespace-nowrap text-xs font-mono text-zinc-600">
                                                    #{item.id}
                                                </td>
                                                {showImage && (
                                                    <td className="px-6 py-4 whitespace-nowrap text-center">
                                                        <div className="inline-block p-1 bg-white/5 rounded-lg border border-gray-800">
                                                            <img 
                                                                src={item.image_url} 
                                                                alt={item.name}
                                                                className="w-10 h-10 object-cover rounded-md"
                                                                onError={(e) => {
                                                                    (e.target as HTMLImageElement).src = '/images/default-item.png';
                                                                }}
                                                            />
                                                        </div>
                                                    </td>
                                                )}
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-200">
                                                            {isOnlineName 
                                                                ? (item.group?.description2 || item.group?.description || item.name)
                                                                : (item.group?.description || item.name)
                                                            }
                                                        </span>
                                                        <span className="text-[10px] text-zinc-600 font-mono">
                                                            {item.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-xs font-mono text-blue-400">
                                                    {item.code}
                                                </td>
                                                <td className="px-6 py-4 min-w-[200px]">
                                                    <p className="text-xs text-gray-500 whitespace-normal">
                                                        {item.description || item.group?.description || '-'}
                                                    </p>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-semibold text-gray-300">
                                                        {formatCurrency(item.price)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className={cn(
                                                        "text-sm font-bold font-mono",
                                                        parseFloat(item.pivot.quantity) > 0 ? "text-emerald-400" : "text-zinc-600"
                                                    )}>
                                                        {parseFloat(item.pivot.quantity).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Link href={`/items/${item.id}/edit`}>
                                                        <Button size="icon" variant="ghost" className="h-8 w-8 text-gray-500 hover:text-blue-400 hover:bg-blue-400/10">
                                                            <FilePen className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={showImage ? 8 : 7} className="px-6 py-12 text-center text-gray-500 italic">
                                                No items found in this warehouse.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="px-6 py-4 bg-[#161616] border-t border-gray-800">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-gray-500">
                                    Showing <span className="text-white">{items.data.length}</span> of <span className="text-white">{items.total}</span> items
                                </p>
                                <Pagination links={items.links} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
