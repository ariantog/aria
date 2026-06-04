import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Search, Download, X } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

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
    transactionTypes: { id: number; name: string }[];
    filters: {
        bulan?: number | null;
        tahun: number;
        search?: string;
        type?: number | null;
    };
    years: number[];
    can: {
        bank_hidden_balance?: boolean;
    };
}

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export default function AddrbookItemSales({
    addrbook,
    sales,
    transactionTypes,
    filters,
    years,
}: Props) {
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
        router.get(
            `/${addrbook.type_slug}/${addrbook.id}/item-sales`,
            {
                bulan,
                tahun,
                search,
                type,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setBulan('');
        setTahun('');
        setSearch('');
        setType('');
        router.get(`/${addrbook.type_slug}/${addrbook.id}/item-sales`);
    };

    const formatCurrency = (value: string | number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(value) || 0);
    };

    const getTransactionTypeLabel = (typeId: number) => {
        return transactionTypes.find((t) => t.id === typeId)?.name || 'Other';
    };

    const getTransactionTypeColor = (typeId: number) => {
        switch (typeId) {
            case 2:
                return 'bg-blue-500/10 text-blue-500 border-blue-500/20'; // Sell
            case 15:
                return 'bg-purple-500/10 text-purple-500 border-purple-500/20'; // Return
            default:
                return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Item Sales: ${addrbook.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <div className="mb-2 flex items-center gap-2">
                                <Link
                                    href={`/addrbook/${addrbook.id}`}
                                    className="text-gray-500 transition-colors hover:text-white"
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="font-mono text-sm text-zinc-600">
                                    #{addrbook.id}
                                </span>
                            </div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Monthly Item Sales
                            </h1>
                            <p className="text-sm text-gray-500">
                                Sales performance for{' '}
                                <span className="text-blue-400">
                                    {addrbook.name}
                                </span>
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <Button className="border-0 bg-emerald-600 text-white hover:bg-emerald-500">
                                <Download className="mr-2 h-4 w-4" />
                                Download CSV
                            </Button>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800">
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/transactions`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Transaction
                        </Link>
                        {addrbook.type === 2 && ( // TYPE_WAREHOUSE
                            <Link
                                href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                                className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                            >
                                Items
                            </Link>
                        )}
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="border-b-2 border-blue-500 px-6 py-4 text-sm font-medium whitespace-nowrap text-blue-500 transition-all"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters */}
                    <div className="mb-8 rounded-xl border border-gray-800 bg-[#111] p-6">
                        <form
                            onSubmit={handleFilter}
                            className="grid grid-cols-1 items-end gap-4 md:grid-cols-4 lg:grid-cols-5"
                        >
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Search Item Group
                                </label>
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                    <input
                                        type="text"
                                        placeholder="Name or Description..."
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-800 bg-[#161616] py-2 pr-4 pl-10 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Month
                                </label>
                                <select
                                    value={bulan}
                                    onChange={(e) => setBulan(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">All Months</option>
                                    {MONTHS.map((m, i) => (
                                        <option key={i + 1} value={i + 1}>
                                            {m}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Year
                                </label>
                                <select
                                    value={tahun}
                                    onChange={(e) => setTahun(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y.toString()}>
                                            {y}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Type
                                </label>
                                <select
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">All Types</option>
                                    <option value="2">Sell</option>
                                    <option value="15">Return</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 border-0 bg-blue-600 text-white hover:bg-blue-500"
                                >
                                    <Search className="mr-2 h-4 w-4" />
                                    Search
                                </Button>
                                <Button
                                    type="button"
                                    onClick={clearFilters}
                                    variant="outline"
                                    className="border-gray-800 text-gray-400 hover:text-white"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Sales Table */}
                    <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-gray-800 bg-[#161616]">
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Period
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Type
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Item Group
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Qty
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Total Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {sales.data.length > 0 ? (
                                        sales.data.map((sale) => (
                                            <tr
                                                key={sale.id}
                                                className="group transition-colors hover:bg-white/[0.02]"
                                            >
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-200">
                                                        {MONTHS[sale.bulan - 1]}{' '}
                                                        {sale.tahun}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge
                                                        className={cn(
                                                            'border px-2 py-0.5 text-[10px] font-bold uppercase',
                                                            getTransactionTypeColor(
                                                                sale.type,
                                                            ),
                                                        )}
                                                    >
                                                        {getTransactionTypeLabel(
                                                            sale.type,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-200">
                                                            {sale.group
                                                                ?.description ||
                                                                sale.group
                                                                    ?.name ||
                                                                'Unknown Group'}
                                                        </span>
                                                        <span className="font-mono text-[10px] text-zinc-600">
                                                            {sale.group?.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-sm font-bold',
                                                            sale.type === 2
                                                                ? 'text-emerald-400'
                                                                : 'text-rose-400',
                                                        )}
                                                    >
                                                        {parseFloat(
                                                            sale.sum_qty,
                                                        ).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-semibold text-gray-300">
                                                        {formatCurrency(
                                                            sale.sum_total,
                                                        )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-6 py-12 text-center text-gray-500 italic"
                                            >
                                                No sales data found for this
                                                period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-t border-gray-800 bg-[#161616] px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-gray-500">
                                    Showing{' '}
                                    <span className="text-white">
                                        {sales.data.length}
                                    </span>{' '}
                                    of{' '}
                                    <span className="text-white">
                                        {sales.total}
                                    </span>{' '}
                                    records
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
