import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableFooter } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Filter, X, TrendingUp, TrendingDown, Wallet, ShoppingCart, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface ReportData {
    cashIn: Record<number, number>;
    cashOut: Record<number, number>;
    sell: Record<number, number>;
    return: Record<number, number>;
    nettCash: number;
    nettSell: number;
}

interface Addrbook {
    id: number;
    name: string;
    type: number;
    type_slug: string;
}

interface Props {
    customerList: Addrbook[];
    resellerList: Addrbook[];
    customerReport: ReportData;
    resellerReport: ReportData;
    filters: {
        month: number;
        year: number;
    };
    yearList: number[];
    datesNow: {
        month: number;
        year: number;
    };
}

export default function NettCashSby({ customerList, resellerList, customerReport, resellerReport, filters, yearList, datesNow }: Props) {
    const [month, setMonth] = useState(filters.month.toString());
    const [year, setYear] = useState(filters.year.toString());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Nett Cash', href: '/reports/nett-cash-sby' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/nett-cash-sby', { month, year }, { preserveState: true });
    };

    const handleClear = () => {
        setMonth(datesNow.month.toString());
        setYear(datesNow.year.toString());
        router.get('/reports/nett-cash-sby');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(amount));
    };

    const sumValues = (obj: Record<number, number>) => Object.values(obj).reduce((a, b) => a + b, 0);

    const ReportTable = ({ title, list, report }: { title: string, list: Addrbook[], report: ReportData }) => (
        <div className="space-y-4">
            <h3 className="text-lg font-bold px-1">{title}</h3>
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-zinc-50 dark:bg-zinc-900">
                            <TableHead className="w-[300px]">{title}</TableHead>
                            <TableHead className="text-right">Cash In</TableHead>
                            <TableHead className="text-right">Cash Out</TableHead>
                            <TableHead className="text-right">Sell</TableHead>
                            <TableHead className="text-right">Return</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {list.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center">Data Empty</TableCell>
                            </TableRow>
                        ) : (
                            list.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-medium">
                                        <Link href={`/${item.type_slug}/${item.id}`} className="text-blue-600 dark:text-blue-400 hover:underline">
                                            {item.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-right">{formatCurrency(report.cashIn[item.id] || 0)}</TableCell>
                                    <TableCell className="text-right">{formatCurrency(report.cashOut[item.id] || 0)}</TableCell>
                                    <TableCell className="text-right">{formatCurrency(report.sell[item.id] || 0)}</TableCell>
                                    <TableCell className="text-right">{formatCurrency(report.return[item.id] || 0)}</TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                    <TableFooter>
                        <TableRow className="font-semibold bg-zinc-50 dark:bg-zinc-900">
                            <TableCell>Total</TableCell>
                            <TableCell className="text-right">{formatCurrency(sumValues(report.cashIn))}</TableCell>
                            <TableCell className="text-right">{formatCurrency(sumValues(report.cashOut))}</TableCell>
                            <TableCell className="text-right">{formatCurrency(sumValues(report.sell))}</TableCell>
                            <TableCell className="text-right">{formatCurrency(sumValues(report.return))}</TableCell>
                        </TableRow>
                        <TableRow className="font-bold border-t-2">
                            <TableCell>Nett</TableCell>
                            <TableCell className="text-right text-emerald-600 dark:text-emerald-400">{formatCurrency(report.nettCash)}</TableCell>
                            <TableCell></TableCell>
                            <TableCell className="text-right text-blue-600 dark:text-blue-400">{formatCurrency(report.nettSell)}</TableCell>
                            <TableCell></TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </div>
    );

    const totalCashIn = sumValues(customerReport.cashIn) + sumValues(resellerReport.cashIn);
    const totalCashOut = sumValues(customerReport.cashOut) + sumValues(resellerReport.cashOut);
    const totalSell = sumValues(customerReport.sell) + sumValues(resellerReport.sell);
    const totalReturn = sumValues(customerReport.return) + sumValues(resellerReport.return);
    const totalNettCash = customerReport.nettCash + resellerReport.nettCash;
    const totalNettSell = customerReport.nettSell + resellerReport.nettSell;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nett Cash" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Nett Cash</h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Data for {filters.month} - {filters.year}
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader className="pb-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-1.5 w-[180px]">
                                <Label htmlFor="month">Month</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Select month" />
                                    </SelectTrigger>
                                    <SelectContent>
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

                <div className="space-y-10">
                    <ReportTable title="Customer" list={customerList} report={customerReport} />
                    <ReportTable title="Reseller" list={resellerList} report={resellerReport} />

                    <div className="space-y-6">
                        <h3 className="text-lg font-bold px-1">Global Summary</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Cash In</CardTitle>
                                    <TrendingUp className="h-4 w-4 text-emerald-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-xl font-bold">{formatCurrency(totalCashIn)}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Cash Out</CardTitle>
                                    <TrendingDown className="h-4 w-4 text-rose-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-xl font-bold">{formatCurrency(totalCashOut)}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Sell</CardTitle>
                                    <ShoppingCart className="h-4 w-4 text-blue-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-xl font-bold text-blue-600">{formatCurrency(totalSell)}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Return</CardTitle>
                                    <RefreshCw className="h-4 w-4 text-rose-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-xl font-bold text-rose-600">{formatCurrency(totalReturn)}</div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Card className="bg-emerald-50/50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Global Nett Cash</CardTitle>
                                    <Wallet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                        {formatCurrency(totalNettCash)}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card className="bg-blue-50/50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/20">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Global Nett Sell</CardTitle>
                                    <ShoppingCart className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-blue-700 dark:text-blue-300">
                                        {formatCurrency(totalNettSell)}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
