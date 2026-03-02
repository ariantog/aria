import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Label } from "@/components/ui/label";
import { ArrowLeft, BarChart2, Filter, X, Package } from 'lucide-react';
import { useState } from 'react';

interface StatEntry {
    transaction_type: number;
    showdate: string;
    bulan: string;
    tahun: string;
    total_qty: string | number;
}

interface Item {
    id: number;
    name: string;
    code: string;
    type: number;
    group?: { id: number; name: string };
}

interface Props {
    item: Item;
    data: StatEntry[];
    filters: {
        from: string;
        to: string;
    };
}

export default function ItemStats({ item, data, filters }: Props) {
    const [searchParams, setSearchParams] = useState(filters);

    const getBaseUrl = (type: number) => {
        return type === 2 ? '/assetlancar' : '/items';
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: item.type === 2 ? 'Assets' : 'Items', href: getBaseUrl(item.type) },
        { title: item.name, href: `${getBaseUrl(item.type)}/${item.id}` },
        { title: 'Stats', href: '#' },
    ];

    // Pivot data by date
    const pivotData = () => {
        const months: Record<string, any> = {};

        data.forEach(entry => {
            if (!months[entry.showdate]) {
                months[entry.showdate] = {
                    date: entry.showdate,
                    sell: 0,
                    move: 0,
                    return: 0,
                    production: 0
                };
            }

            const qty = Number(entry.total_qty);
            switch (entry.transaction_type) {
                case 2: months[entry.showdate].sell += qty; break;
                case 3: months[entry.showdate].move += qty; break;
                case 15: months[entry.showdate].return += qty; break;
                case 16: months[entry.showdate].production += qty; break;
            }
        });

        return Object.values(months);
    };

    const pivotedRows = pivotData();

    // Totals
    const totals = pivotedRows.reduce((acc, row) => ({
        sell: acc.sell + row.sell,
        move: acc.move + row.move,
        return: acc.return + row.return,
        production: acc.production + row.production
    }), { sell: 0, move: 0, return: 0, production: 0 });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`${getBaseUrl(item.type)}/${item.id}/stats`, searchParams, { preserveState: true });
    };

    const clearFilters = () => {
        const defaultFrom = new Date();
        defaultFrom.setMonth(defaultFrom.getMonth() - 11);
        defaultFrom.setDate(1);

        const reset = {
            from: defaultFrom.toISOString().split('T')[0],
            to: new Date().toISOString().split('T')[0]
        };
        setSearchParams(reset);
        router.get(`${getBaseUrl(item.type)}/${item.id}/stats`, reset);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stats: ${item.name}`} />

            <div className="p-4 sm:p-6 lg:p-8 bg-black min-h-screen text-zinc-100">
                {/* Header */}
                <div className="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            className="border-zinc-800 bg-zinc-900/50 hover:bg-zinc-800 text-zinc-400 h-10 w-10"
                            onClick={() => (item.group ? router.get(`/items-group/${item.group.id}`) : router.get(`${getBaseUrl(item.type)}/${item.id}`))}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <h1 className="text-2xl font-bold tracking-tight text-white">{item.code} - {item.name}</h1>
                                {item.group && <Badge variant="secondary" className="bg-zinc-800 text-zinc-400 hover:bg-zinc-700">{item.group.name}</Badge>}
                            </div>
                            <p className="text-zinc-500 flex items-center gap-2">
                                <BarChart2 className="h-4 w-4" /> Performance Statistics
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="mb-8 p-4 bg-zinc-900/50 border border-zinc-800 rounded-xl shadow-sm">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div className="space-y-2">
                            <Label htmlFor="from" className="text-xs font-semibold uppercase text-zinc-500">From</Label>
                            <Input
                                id="from"
                                type="date"
                                value={searchParams.from}
                                onChange={e => setSearchParams({ ...searchParams, from: e.target.value })}
                                className="bg-zinc-950 border-zinc-800 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="to" className="text-xs font-semibold uppercase text-zinc-500">To</Label>
                            <Input
                                id="to"
                                type="date"
                                value={searchParams.to}
                                onChange={e => setSearchParams({ ...searchParams, to: e.target.value })}
                                className="bg-zinc-950 border-zinc-800 text-zinc-200"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" className="flex-1 bg-zinc-100 text-zinc-900 hover:bg-zinc-200">
                                <Filter className="mr-2 h-4 w-4" /> Filter
                            </Button>
                            <Button type="button" variant="outline" onClick={clearFilters} className="border-zinc-800 text-zinc-400 hover:bg-zinc-800/50">
                                <X className="h-4 w-4" /> Clear
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-hidden shadow-sm">
                    <Table>
                        <TableHeader className="bg-zinc-900/80 border-b border-zinc-800">
                            <TableRow className="hover:bg-transparent border-zinc-800">
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 py-4">Month</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 py-4 text-right">Sell</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 py-4 text-right text-rose-400">Return</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 py-4 text-right text-amber-400">Move</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 py-4 text-right text-indigo-400">Production</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-zinc-800/50 uppercase">
                            {pivotedRows.length > 0 ? (
                                <>
                                    {pivotedRows.map((row, idx) => (
                                        <TableRow key={idx} className="hover:bg-zinc-800/40 border-zinc-800/50 transition-colors">
                                            <TableCell className="px-6 py-4 text-white font-medium">{row.date}</TableCell>
                                            <TableCell className="px-6 py-4 text-right text-zinc-300 font-mono">{row.sell || '-'}</TableCell>
                                            <TableCell className="px-6 py-4 text-right text-rose-500/80 font-mono">{row.return || '-'}</TableCell>
                                            <TableCell className="px-6 py-4 text-right text-amber-500/80 font-mono">{row.move || '-'}</TableCell>
                                            <TableCell className="px-6 py-4 text-right text-indigo-500/80 font-mono">{row.production || '-'}</TableCell>
                                        </TableRow>
                                    ))}
                                    {/* Footer Totals */}
                                    <TableRow className="bg-zinc-900/80 hover:bg-zinc-900/80 font-bold border-t-2 border-zinc-700">
                                        <TableCell className="px-6 py-4 text-white uppercase tracking-wider">Total</TableCell>
                                        <TableCell className="px-6 py-4 text-right text-green-400 font-mono">{totals.sell}</TableCell>
                                        <TableCell className="px-6 py-4 text-right text-rose-400 font-mono">{totals.return}</TableCell>
                                        <TableCell className="px-6 py-4 text-right text-amber-400 font-mono">{totals.move}</TableCell>
                                        <TableCell className="px-6 py-4 text-right text-indigo-400 font-mono">{totals.production}</TableCell>
                                    </TableRow>
                                </>
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={5} className="h-64 text-center">
                                        <div className="flex flex-col items-center gap-2 text-zinc-600">
                                            <Package className="h-10 w-10 opacity-20" />
                                            <p>No statistical data found for this period.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
