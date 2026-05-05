import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import {
    ArrowLeft,
    BarChart3,
    Calendar,
    Search,
    Download,
    X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
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

export default function AddrbookStats({
    addrbook,
    dataStat,
    filters,
    years,
}: Props) {
    const [month, setMonth] = useState(filters.month || '');
    const [year, setYear] = useState(filters.year);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Stats', href: '#' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            `/${addrbook.type_slug}/${addrbook.id}/stats`,
            { month, year },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        const now = new Date();
        setMonth('');
        setYear(now.getFullYear());
        router.get(`/${addrbook.type_slug}/${addrbook.id}/stats`);
    };

    const formatNumber = (value: number) => {
        if (value === 0) return '-';
        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0,
        }).format(value);
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
                                Activity Summary
                            </h1>
                            <p className="text-sm text-gray-500">
                                Categorized transaction statistics for{' '}
                                <span className="text-blue-400">
                                    {addrbook.name}
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="scrollbar-hide mb-8 flex overflow-x-auto border-b border-gray-800">
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
                            className="border-b-2 border-blue-500 px-6 py-4 text-sm font-medium whitespace-nowrap text-blue-500 transition-all"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters */}
                    <div className="mb-8 rounded-xl border border-gray-800 bg-[#111] p-4">
                        <form
                            onSubmit={handleFilter}
                            className="grid grid-cols-1 items-end gap-4 md:grid-cols-3"
                        >
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Month
                                </label>
                                <select
                                    value={month}
                                    onChange={(e) => setMonth(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Full Year (All Months)
                                    </option>
                                    {[...Array(12)].map((_, i) => (
                                        <option key={i + 1} value={i + 1}>
                                            {new Date(0, i).toLocaleString(
                                                'id-ID',
                                                { month: 'long' },
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Year
                                </label>
                                <select
                                    value={year}
                                    onChange={(e) =>
                                        setYear(parseInt(e.target.value))
                                    }
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y}>
                                            {y}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 border-0 bg-blue-600 text-white hover:bg-blue-500"
                                >
                                    Apply Filter
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

                    {/* Stats Table */}
                    <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                        <div className="border-b border-gray-800 bg-[#161616] p-6">
                            <h3 className="text-sm font-bold tracking-widest text-gray-400 uppercase">
                                Summary for{' '}
                                {month
                                    ? new Date(
                                          0,
                                          Number(month) - 1,
                                      ).toLocaleString('id-ID', {
                                          month: 'long',
                                      })
                                    : 'All Year'}{' '}
                                {year}
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-gray-800 bg-[#161616]">
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Type
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Cash In
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Cash Out
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Sell
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Return
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {categories.map((cat) => (
                                        <tr
                                            key={cat.key}
                                            className="transition-colors hover:bg-white/[0.02]"
                                        >
                                            <td className="px-6 py-4">
                                                <span className="text-sm font-medium text-gray-300">
                                                    {cat.label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="font-mono text-sm text-blue-400">
                                                    {formatNumber(
                                                        dataStat.cash_in[
                                                            cat.key
                                                        ] as number,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="font-mono text-sm text-orange-400">
                                                    {formatNumber(
                                                        dataStat.cash_out[
                                                            cat.key
                                                        ] as number,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="font-mono text-sm text-emerald-400">
                                                    {formatNumber(
                                                        dataStat.sell[
                                                            cat.key
                                                        ] as number,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="font-mono text-sm text-purple-400">
                                                    {formatNumber(
                                                        dataStat.return[
                                                            cat.key
                                                        ] as number,
                                                    )}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t border-gray-800 bg-[#161616] font-bold">
                                    <tr>
                                        <td className="px-6 py-4 text-[10px] tracking-widest text-gray-500 uppercase">
                                            Total
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-sm text-blue-400">
                                            {formatNumber(
                                                dataStat.cash_in.total,
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-sm text-orange-400">
                                            {formatNumber(
                                                dataStat.cash_out.total,
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-sm text-emerald-400">
                                            {formatNumber(dataStat.sell.total)}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-sm text-purple-400">
                                            {formatNumber(
                                                dataStat.return.total,
                                            )}
                                        </td>
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
