import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BarChart2, Filter, X, Package } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface StatEntry {
    transaction_type: number;
    showdate: string;
    bulan: string;
    tahun: string;
    total_qty: string | number;
}

interface Group {
    id: number;
    name: string;
    alias: string;
}

interface Props {
    group: Group;
    data: StatEntry[];
    filters: {
        from: string;
        to: string;
    };
}

export default function GroupStats({ group, data, filters }: Props) {
    const [searchParams, setSearchParams] = useState(filters);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Items', href: '/items' },
        { title: 'Groups', href: '/items-group' },
        { title: group.name, href: `/items-group/${group.id}` },
        { title: 'Stats', href: '#' },
    ];

    // Pivot data by date
    const pivotData = () => {
        const months: Record<string, any> = {};

        data.forEach((entry) => {
            if (!months[entry.showdate]) {
                months[entry.showdate] = {
                    date: entry.showdate,
                    sell: 0,
                    move: 0,
                    return: 0,
                    production: 0,
                };
            }

            const qty = Number(entry.total_qty);
            switch (entry.transaction_type) {
                case 2:
                    months[entry.showdate].sell += qty;
                    break;
                case 3:
                    months[entry.showdate].move += qty;
                    break;
                case 15:
                    months[entry.showdate].return += qty;
                    break;
                case 16:
                    months[entry.showdate].production += qty;
                    break;
            }
        });

        return Object.values(months);
    };

    const pivotedRows = pivotData();

    // Totals
    const totals = pivotedRows.reduce(
        (acc, row) => ({
            sell: acc.sell + row.sell,
            move: acc.move + row.move,
            return: acc.return + row.return,
            production: acc.production + row.production,
        }),
        { sell: 0, move: 0, return: 0, production: 0 },
    );

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/items-group/${group.id}/stats`, searchParams, {
            preserveState: true,
        });
    };

    const clearFilters = () => {
        const defaultFrom = new Date();
        defaultFrom.setMonth(defaultFrom.getMonth() - 11);
        defaultFrom.setDate(1);

        const reset = {
            from: defaultFrom.toISOString().split('T')[0],
            to: new Date().toISOString().split('T')[0],
        };
        setSearchParams(reset);
        router.get(`/items-group/${group.id}/stats`, reset);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Group Stats: ${group.name}`} />

            <div className="min-h-screen bg-black p-4 text-zinc-100 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-10 w-10 border-zinc-800 bg-zinc-900/50 text-zinc-400 hover:bg-zinc-800"
                            onClick={() =>
                                router.get(`/items-group/${group.id}`)
                            }
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <div className="mb-1 flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">
                                    Group: {group.name}
                                </h1>
                                {group.alias && (
                                    <Badge
                                        variant="secondary"
                                        className="bg-zinc-800 text-zinc-400 italic hover:bg-zinc-700"
                                    >
                                        {group.alias}
                                    </Badge>
                                )}
                            </div>
                            <p className="flex items-center gap-2 text-zinc-500">
                                <BarChart2 className="h-4 w-4" /> Collective
                                Group Performance
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="mb-8 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 shadow-sm">
                    <form
                        onSubmit={handleFilter}
                        className="grid grid-cols-1 items-end gap-4 md:grid-cols-3"
                    >
                        <div className="space-y-2">
                            <Label
                                htmlFor="from"
                                className="text-xs font-semibold text-zinc-500 uppercase"
                            >
                                From
                            </Label>
                            <Input
                                id="from"
                                type="date"
                                value={searchParams.from}
                                onChange={(e) =>
                                    setSearchParams({
                                        ...searchParams,
                                        from: e.target.value,
                                    })
                                }
                                className="border-zinc-800 bg-zinc-950 text-zinc-200"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label
                                htmlFor="to"
                                className="text-xs font-semibold text-zinc-500 uppercase"
                            >
                                To
                            </Label>
                            <Input
                                id="to"
                                type="date"
                                value={searchParams.to}
                                onChange={(e) =>
                                    setSearchParams({
                                        ...searchParams,
                                        to: e.target.value,
                                    })
                                }
                                className="border-zinc-800 bg-zinc-950 text-zinc-200"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                className="flex-1 bg-zinc-100 text-zinc-900 hover:bg-zinc-200"
                            >
                                <Filter className="mr-2 h-4 w-4" /> Filter
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                                className="border-zinc-800 text-zinc-400 hover:bg-zinc-800/50"
                            >
                                <X className="h-4 w-4" /> Clear
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900/50 shadow-sm">
                    <Table>
                        <TableHeader className="border-b border-zinc-800 bg-zinc-900/80">
                            <TableRow className="border-zinc-800 hover:bg-transparent">
                                <TableHead className="px-6 py-4 text-[10px] font-bold tracking-wider text-zinc-500 uppercase">
                                    Month
                                </TableHead>
                                <TableHead className="px-6 py-4 text-right text-[10px] font-bold tracking-wider text-zinc-500 uppercase">
                                    Sell
                                </TableHead>
                                <TableHead className="px-6 py-4 text-right text-[10px] font-bold tracking-wider text-rose-400 text-zinc-500 uppercase">
                                    Return
                                </TableHead>
                                <TableHead className="px-6 py-4 text-right text-[10px] font-bold tracking-wider text-amber-400 text-zinc-500 uppercase">
                                    Move
                                </TableHead>
                                <TableHead className="px-6 py-4 text-right text-[10px] font-bold tracking-wider text-indigo-400 text-zinc-500 uppercase">
                                    Production
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-zinc-800/50 uppercase">
                            {pivotedRows.length > 0 ? (
                                <>
                                    {pivotedRows.map((row, idx) => (
                                        <TableRow
                                            key={idx}
                                            className="border-zinc-800/50 transition-colors hover:bg-zinc-800/40"
                                        >
                                            <TableCell className="px-6 py-4 font-medium text-white">
                                                {row.date}
                                            </TableCell>
                                            <TableCell className="px-6 py-4 text-right font-mono text-zinc-300">
                                                {row.sell || '-'}
                                            </TableCell>
                                            <TableCell className="px-6 py-4 text-right font-mono text-rose-500/80">
                                                {row.return || '-'}
                                            </TableCell>
                                            <TableCell className="px-6 py-4 text-right font-mono text-amber-500/80">
                                                {row.move || '-'}
                                            </TableCell>
                                            <TableCell className="px-6 py-4 text-right font-mono text-indigo-500/80">
                                                {row.production || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {/* Footer Totals */}
                                    <TableRow className="border-t-2 border-zinc-700 bg-zinc-900/80 font-bold hover:bg-zinc-900/80">
                                        <TableCell className="px-6 py-4 tracking-wider text-white uppercase">
                                            Total
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-mono text-green-400">
                                            {totals.sell}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-mono text-rose-400">
                                            {totals.return}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-mono text-amber-400">
                                            {totals.move}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-mono text-indigo-400">
                                            {totals.production}
                                        </TableCell>
                                    </TableRow>
                                </>
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-64 text-center"
                                    >
                                        <div className="flex flex-col items-center gap-2 text-zinc-600">
                                            <Package className="h-10 w-10 opacity-20" />
                                            <p>
                                                No statistical data found for
                                                this period.
                                            </p>
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
