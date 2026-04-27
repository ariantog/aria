import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import { Package, BarChart2, ArrowLeft, Calendar, User } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

interface StatRow {
    transaction_type: number;
    showdate: string;
    bulan: string;
    tahun: string;
    total_qty: string;
}

interface Item {
    id: number;
    pcode: string;
    code: string;
    name: string;
    group?: {
        id: number;
        name: string;
    };
}

interface Addrbook {
    id: number;
    name: string;
    type: number;
}

interface Props {
    item: Item;
    data: StatRow[];
    addrbooks: Addrbook[];
    filters: {
        from: string;
        to: string;
        addr?: string | number;
    };
}

export default function ItemStats({ item, data, addrbooks, filters }: Props) {
    const [fromDate, setFromDate] = useState(filters.from || '');
    const [toDate, setToDate] = useState(filters.to || '');
    const [addr, setAddr] = useState(filters.addr || '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Items', href: '/items' },
        { title: item.name, href: `/items/${item.id}` },
        { title: 'Stats', href: '#' },
    ];

    const handleFilter = () => {
        router.get(`/items/${item.id}/stats`, { 
            from: fromDate, 
            to: toDate,
            addr: addr
        }, { preserveState: true });
    };

    // Group data by date
    const groupedData = data.reduce((acc: any, curr) => {
        if (!acc[curr.showdate]) acc[curr.showdate] = {};
        acc[curr.showdate][curr.transaction_type] = curr.total_qty;
        return acc;
    }, {});

    const uniqueDates = Array.from(new Set(data.map(d => d.showdate)));

    // Calculate totals for each column
    const totals = data.reduce((acc: any, curr) => {
        const type = curr.transaction_type;
        acc[type] = (acc[type] || 0) + parseFloat(curr.total_qty);
        return acc;
    }, {});

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stats: ${item.name}`} />

            <div className="flex-1 flex flex-col h-full bg-[#0A0A0A] min-h-screen text-gray-300 font-sans antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <Link href={`/items/${item.id}`} className="text-gray-500 hover:text-white transition-colors">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="text-zinc-600 font-mono text-sm">#{item.code}</span>
                            </div>
                            <h1 className="text-2xl font-bold text-white mb-1">Item Statistics</h1>
                            <p className="text-gray-500 text-sm">
                                Monthly transaction volume for <span className="text-blue-400">{item.name}</span>
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-4 bg-[#111] p-4 rounded-xl border border-gray-800">
                            <div className="flex items-center gap-2">
                                <Calendar className="w-4 h-4 text-gray-500" />
                                <input
                                    type="date"
                                    value={fromDate}
                                    onChange={(e) => setFromDate(e.target.value)}
                                    className="bg-transparent border-0 text-sm text-white focus:ring-0 w-32"
                                />
                            </div>
                            <span className="text-gray-700">to</span>
                            <div className="flex items-center gap-2">
                                <Calendar className="w-4 h-4 text-gray-500" />
                                <input
                                    type="date"
                                    value={toDate}
                                    onChange={(e) => setToDate(e.target.value)}
                                    className="bg-transparent border-0 text-sm text-white focus:ring-0 w-32"
                                />
                            </div>
                            <div className="flex items-center gap-2 border-l border-gray-800 pl-4 ml-2">
                                <User className="w-4 h-4 text-gray-500" />
                                <select
                                    value={addr}
                                    onChange={(e) => setAddr(e.target.value)}
                                    className="bg-transparent border-0 text-sm text-white focus:ring-0 w-48 appearance-none"
                                >
                                    <option value="" className="bg-[#111]">All Address Books</option>
                                    {addrbooks.map((ab) => (
                                        <option key={ab.id} value={ab.id} className="bg-[#111]">
                                            {ab.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Button onClick={handleFilter} size="sm" className="bg-blue-600 hover:bg-blue-500 text-white border-0">
                                Apply Filter
                            </Button>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="flex border-b border-gray-800 mb-8 overflow-x-auto">
                        <Link
                            href={`/items/${item.id}`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/items/${item.id}/transactions`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white"
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/items/${item.id}/stats`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 text-blue-500 border-blue-500"
                        >
                            Stats
                        </Link>
                    </div>

                    {/* Stats Table */}
                    <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-[#161616] border-b border-gray-800">
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Month</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Sell</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Move</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Return</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-center">Production</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {uniqueDates.length > 0 ? (
                                        uniqueDates.map((date: any) => (
                                            <tr key={date} className="hover:bg-white/[0.02] transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-bold text-gray-200">{date}</span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="text-sm font-mono text-blue-400">
                                                        {groupedData[date][2] ? parseFloat(groupedData[date][2]).toLocaleString() : '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="text-sm font-mono text-amber-400">
                                                        {groupedData[date][3] ? parseFloat(groupedData[date][3]).toLocaleString() : '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="text-sm font-mono text-purple-400">
                                                        {groupedData[date][15] ? parseFloat(groupedData[date][15]).toLocaleString() : '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="text-sm font-mono text-indigo-400">
                                                        {groupedData[date][16] ? parseFloat(groupedData[date][16]).toLocaleString() : '-'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-12 text-center text-gray-500 italic">
                                                No statistical data available for this period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                {uniqueDates.length > 0 && (
                                    <tfoot className="bg-[#161616] border-t border-gray-800 font-bold">
                                        <tr>
                                            <td className="px-6 py-4 text-sm text-gray-300 uppercase tracking-wider">Total</td>
                                            <td className="px-6 py-4 text-center text-sm font-mono text-blue-400">
                                                {totals[2] ? totals[2].toLocaleString() : '0'}
                                            </td>
                                            <td className="px-6 py-4 text-center text-sm font-mono text-amber-400">
                                                {totals[3] ? totals[3].toLocaleString() : '0'}
                                            </td>
                                            <td className="px-6 py-4 text-center text-sm font-mono text-purple-400">
                                                {totals[15] ? totals[15].toLocaleString() : '0'}
                                            </td>
                                            <td className="px-6 py-4 text-center text-sm font-mono text-indigo-400">
                                                {totals[16] ? totals[16].toLocaleString() : '0'}
                                            </td>
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
