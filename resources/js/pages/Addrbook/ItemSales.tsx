import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import { ArrowLeft, Calendar, Search, Download, X } from 'lucide-react';
import Pagination from '@/components/pagination';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from "@/lib/utils";

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
}

interface Sale {
    id: number;
    tahun: number;
    bulan: number;
    type: number;
    sum_qty: string;
    sum_total: string;
    group: ItemGroup | null;
}

interface PaginatedSales {
    data: Sale[];
    links: any[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    addrbook: Addrbook;
    sales: PaginatedSales;
    transactionTypes: { id: number, name: string }[];
    filters: {
        bulan?: number | null;
        tahun: number;
        search?: string;
        type?: number | null;
    };
    years: number[];
}

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

export default function AddrbookItemSales({ addrbook, sales, transactionTypes, filters, years }: Props) {
    const [bulan, setBulan] = useState(filters.bulan?.toString() || '');
    const [tahun, setTahun] = useState(filters.tahun?.toString() || '');
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type?.toString() || '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Item Sales', href: '#' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(`/${addrbook.type_slug}/${addrbook.id}/item-sales`, { 
            bulan, 
            tahun,
            search,
            type
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setBulan('');
        setTahun('');
        setSearch('');
        setType('');
        router.get(`/${addrbook.type_slug}/${addrbook.id}/item-sales`);
    };

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value) || 0);
    };

    const getTransactionTypeLabel = (typeId: number) => {
        return transactionTypes.find(t => t.id === typeId)?.name || 'Other';
    };

    const getTransactionTypeColor = (typeId: number) => {
        switch (typeId) {
            case 2: return 'bg-blue-500/10 text-blue-500 border-blue-500/20'; // Sell
            case 15: return 'bg-purple-500/10 text-purple-500 border-purple-500/20'; // Return
            default: return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Item Sales: ${addrbook.name}`} />

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
                            <h1 className="text-2xl font-bold text-white mb-1">Monthly Item Sales</h1>
                            <p className="text-gray-500 text-sm">
                                Sales performance for <span className="text-blue-400">{addrbook.name}</span>
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
                        {addrbook.type === 2 && ( // TYPE_WAREHOUSE
                            <Link
                                href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                                className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                            >
                                Items
                            </Link>
                        )}
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white whitespace-nowrap"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 text-blue-500 border-blue-500 whitespace-nowrap"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters */}
                    <div className="bg-[#111] p-6 rounded-xl border border-gray-800 mb-8">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4 items-end">
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Search Item Group</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
                                    <input
                                        type="text"
                                        placeholder="Name or Description..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 pl-10 pr-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Month</label>
                                <select
                                    value={bulan}
                                    onChange={(e) => setBulan(e.target.value)}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All Months</option>
                                    {MONTHS.map((m, i) => (
                                        <option key={i + 1} value={i + 1}>{m}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Year</label>
                                <select
                                    value={tahun}
                                    onChange={(e) => setTahun(e.target.value)}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y.toString()}>{y}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Type</label>
                                <select
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All Types</option>
                                    <option value="2">Sell</option>
                                    <option value="15">Return</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" className="flex-1 bg-blue-600 hover:bg-blue-500 text-white border-0">
                                    <Search className="w-4 h-4 mr-2" />
                                    Search
                                </Button>
                                <Button type="button" onClick={clearFilters} variant="outline" className="border-gray-800 text-gray-400 hover:text-white">
                                    <X className="w-4 h-4" />
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Sales Table */}
                    <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-[#161616] border-b border-gray-800">
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Period</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Type</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Item Group</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Qty</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {sales.data.length > 0 ? (
                                        sales.data.map((sale) => (
                                            <tr key={sale.id} className="hover:bg-white/[0.02] transition-colors group">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-200">
                                                        {MONTHS[sale.bulan - 1]} {sale.tahun}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge className={cn("px-2 py-0.5 text-[10px] uppercase font-bold border", getTransactionTypeColor(sale.type))}>
                                                        {getTransactionTypeLabel(sale.type)}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-200">
                                                            {sale.group?.description || sale.group?.name || 'Unknown Group'}
                                                        </span>
                                                        <span className="text-[10px] text-zinc-600 font-mono">
                                                            {sale.group?.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className={cn(
                                                        "text-sm font-bold font-mono",
                                                        sale.type === 2 ? "text-emerald-400" : "text-rose-400"
                                                    )}>
                                                        {parseFloat(sale.sum_qty).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-semibold text-gray-300">
                                                        {formatCurrency(sale.sum_total)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-12 text-center text-gray-500 italic">
                                                No sales data found for this period.
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
                                    Showing <span className="text-white">{sales.data.length}</span> of <span className="text-white">{sales.total}</span> records
                                </p>
                                <Pagination links={sales.links} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
