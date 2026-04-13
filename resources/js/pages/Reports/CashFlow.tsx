import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Filter, X } from 'lucide-react';
import { useState } from 'react';

interface CashFlowItem {
    type_id: number;
    type_name: string;
    cash_in_total: number;
    cash_out_total: number;
    sell_total: number;
    return_total: number;
    buy_total: number;
    return_supplier: number;
}

interface Props {
    groupBySender: CashFlowItem[];
    groupByReceiver: CashFlowItem[];
    filters: {
        month: number | null;
        year: number;
    };
    yearList: number[];
    datesNow: {
        month: number;
        year: number;
    };
}

export default function CashFlow({ groupBySender, groupByReceiver, filters, yearList, datesNow }: Props) {
    const [month, setMonth] = useState(filters.month?.toString() || '0');
    const [year, setYear] = useState(filters.year.toString());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Cash Flow', href: '/reports/cash-flow' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params: any = { year };
        if (month !== '0') params.month = month;
        router.get('/reports/cash-flow', params, { preserveState: true });
    };

    const handleClear = () => {
        setMonth('0');
        setYear(datesNow.year.toString());
        router.get('/reports/cash-flow');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(amount));
    };

    const CashFlowTable = ({ title, data }: { title: string, data: CashFlowItem[] }) => (
        <div className="space-y-4">
            <h3 className="text-lg font-bold px-1">{title}</h3>
            <div className="rounded-md border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                            <TableHead className="w-[200px]">Type</TableHead>
                            <TableHead className="text-right">Cash In</TableHead>
                            <TableHead className="text-right">Cash Out</TableHead>
                            <TableHead className="text-right">Sell</TableHead>
                            <TableHead className="text-right">Return</TableHead>
                            <TableHead className="text-right">Buy</TableHead>
                            <TableHead className="text-right">Return Supplier</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="h-24 text-center">Data Empty</TableCell>
                            </TableRow>
                        ) : (
                            data.map((item, index) => (
                                <TableRow key={index} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors">
                                    <TableCell className="font-bold">{item.type_name}</TableCell>
                                    <TableCell className="text-right text-emerald-600 dark:text-emerald-400 font-medium">{formatCurrency(item.cash_in_total)}</TableCell>
                                    <TableCell className="text-right text-rose-600 dark:text-rose-400 font-medium">{formatCurrency(item.cash_out_total)}</TableCell>
                                    <TableCell className="text-right font-medium">{formatCurrency(item.sell_total)}</TableCell>
                                    <TableCell className="text-right text-rose-500 font-medium">{formatCurrency(item.return_total)}</TableCell>
                                    <TableCell className="text-right font-medium">{formatCurrency(item.buy_total)}</TableCell>
                                    <TableCell className="text-right text-emerald-500 font-medium">{formatCurrency(item.return_supplier)}</TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cash Flow" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Cash Flow</h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Summary of transactions by sender and receiver type.
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader className="p-4 sm:p-6 pb-4 sm:pb-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-1.5 w-[180px]">
                                <Label htmlFor="month">Month</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Select month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">All Months</SelectItem>
                                        {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                            <SelectItem key={m} value={m.toString()}>{m}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5 w-[180px]">
                                <Label htmlFor="year">Year</Label>
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder="Select year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearList.map((y) => (
                                            <SelectItem key={y} value={y.toString()}>{y}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button type="button" variant="outline" onClick={handleClear}>
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                </Card>

                <div className="space-y-12">
                    <CashFlowTable title="By Sender" data={groupBySender} />
                    <CashFlowTable title="By Receiver" data={groupByReceiver} />
                </div>
            </div>
        </AppLayout>
    );
}
