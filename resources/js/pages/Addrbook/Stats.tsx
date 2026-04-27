import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import { ArrowLeft, BarChart3, Calendar, Search, Download, X } from 'lucide-react';
import { cn } from "@/lib/utils";
import { useState } from 'react';

interface Addrbook {
    id: number;
    name: string;
    type: number;
    type_slug: string;
}

interface StatMetric {
    customer: number;
    reseller: number;
    journal: number;
    bank: number;
    warehouse: number;
    other: number;
    total: number;
}

interface DataStat {
    cash_in: StatMetric;
    cash_out: StatMetric;
    sell: StatMetric;
    return: StatMetric;
}

interface Props {
    addrbook: Addrbook;
    dataStat: DataStat;
    filters: {
        month?: number | string;
        year: number;
    };
    years: number[];
}

export default function AddrbookStats({ addrbook, dataStat, filters, years }: Props) {
    const [month, setMonth] = useState(filters.month || '');
    const [year, setYear] = useState(filters.year);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Stats', href: '#' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(`/${addrbook.type_slug}/${addrbook.id}/stats`, { month, year }, { preserveState: true });
    };

    const clearFilters = () => {
        const now = new Date();
        setMonth('');
        setYear(now.getFullYear());
        router.get(`/${addrbook.type_slug}/${addrbook.id}/stats`);
    };

    const formatNumber = (value: number) => {
        if (value === 0) return '-';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
    };

    const categories = [
        { key: 'customer' as keyof StatMetric, label: 'Customer' },
        { key: 'reseller' as keyof StatMetric, label: 'Reseller' },
        { key: 'journal' as keyof StatMetric, label: 'Journal' },
        { key: 'bank' as keyof StatMetric, label: 'Bank' },
        { key: 'warehouse' as keyof StatMetric, label: 'Warehouse' },
        { key: 'other' as keyof StatMetric, label: 'Lainnya' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stats: ${addrbook.name}`} />

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
                            <h1 className="text-2xl font-bold text-white mb-1">Activity Summary</h1>
                            <p className="text-gray-500 text-sm">
                                Categorized transaction statistics for <span className="text-blue-400">{addrbook.name}</span>
                            </p>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="flex border-b border-gray-800 mb-8 overflow-x-auto scrollbar-hide">
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
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 text-blue-500 border-blue-500 whitespace-nowrap"
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

                    {/* Filters */}
                    <div className="bg-[#111] p-4 rounded-xl border border-gray-800 mb-8">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Month</label>
                                <select
                                    value={month}
                                    onChange={(e) => setMonth(e.target.value)}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Full Year (All Months)</option>
                                    {[...Array(12)].map((_, i) => (
                                        <option key={i + 1} value={i + 1}>
                                            {new Date(0, i).toLocaleString('id-ID', { month: 'long' })}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] uppercase font-bold text-gray-500 mb-2">Year</label>
                                <select
                                    value={year}
                                    onChange={(e) => setYear(parseInt(e.target.value))}
                                    className="w-full bg-[#161616] border border-gray-800 rounded-lg py-2 px-4 text-sm text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
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
                    </div>

                    {/* Stats Table */}
                    <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                        <div className="p-6 border-b border-gray-800 bg-[#161616]">
                            <h3 className="text-sm font-bold text-gray-400 uppercase tracking-widest">
                                Summary for {month ? new Date(0, Number(month) - 1).toLocaleString('id-ID', { month: 'long' }) : 'All Year'} {year}
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-[#161616] border-b border-gray-800">
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Type</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Cash In</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Cash Out</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Sell</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Return</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {categories.map((cat) => (
                                        <tr key={cat.key} className="hover:bg-white/[0.02] transition-colors">
                                            <td className="px-6 py-4">
                                                <span className="text-sm font-medium text-gray-300">{cat.label}</span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="text-sm font-mono text-blue-400">
                                                    {formatNumber(dataStat.cash_in[cat.key] as number)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="text-sm font-mono text-orange-400">
                                                    {formatNumber(dataStat.cash_out[cat.key] as number)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="text-sm font-mono text-emerald-400">
                                                    {formatNumber(dataStat.sell[cat.key] as number)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="text-sm font-mono text-purple-400">
                                                    {formatNumber(dataStat.return[cat.key] as number)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="bg-[#161616] border-t border-gray-800 font-bold">
                                    <tr>
                                        <td className="px-6 py-4 text-[10px] uppercase text-gray-500 tracking-widest">Total</td>
                                        <td className="px-6 py-4 text-right text-sm font-mono text-blue-400">{formatNumber(dataStat.cash_in.total)}</td>
                                        <td className="px-6 py-4 text-right text-sm font-mono text-orange-400">{formatNumber(dataStat.cash_out.total)}</td>
                                        <td className="px-6 py-4 text-right text-sm font-mono text-emerald-400">{formatNumber(dataStat.sell.total)}</td>
                                        <td className="px-6 py-4 text-right text-sm font-mono text-purple-400">{formatNumber(dataStat.return.total)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
