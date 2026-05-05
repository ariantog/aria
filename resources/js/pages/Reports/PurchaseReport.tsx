import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Filter, X } from 'lucide-react';
import { useState } from 'react';

interface SupplierItem {
    id: number;
    name: string;
}

interface SupplierReport {
    buy: Record<number, number>;
    returnSupplier: Record<number, number>;
    cashInSupplier: Record<number, number>;
    cashInAccount: Record<number, number>;
    cashOutSupplier: Record<number, number>;
    cashOutAccount: Record<number, number>;
    nettBuy: number;
}

interface Props {
    supplierList: SupplierItem[];
    supplierReport: SupplierReport;
    accountList: SupplierItem[];
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

export default function PurchaseReport({
    supplierList,
    supplierReport,
    accountList,
    filters,
    yearList,
    datesNow,
}: Props) {
    const [month, setMonth] = useState(filters.month?.toString() || '0');
    const [year, setYear] = useState(filters.year.toString());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Pembelian', href: '/reports/purchase' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params: any = { year };
        if (month !== '0') params.month = month;
        router.get('/reports/purchase', params, { preserveState: true });
    };

    const handleClear = () => {
        setMonth('0');
        setYear(datesNow.year.toString());
        router.get('/reports/purchase');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(amount));
    };

    const totalBuy = Object.values(supplierReport.buy).reduce(
        (a, b) => a + b,
        0,
    );
    const totalReturn = Object.values(supplierReport.returnSupplier).reduce(
        (a, b) => a + b,
        0,
    );
    const totalCashInSupplier = Object.values(
        supplierReport.cashInSupplier,
    ).reduce((a, b) => a + b, 0);
    const totalCashOutSupplier = Object.values(
        supplierReport.cashOutSupplier,
    ).reduce((a, b) => a + b, 0);
    const totalCashInAccount = Object.values(
        supplierReport.cashInAccount,
    ).reduce((a, b) => a + b, 0);
    const totalCashOutAccount = Object.values(
        supplierReport.cashOutAccount,
    ).reduce((a, b) => a + b, 0);

    const totalCashIn = totalCashInSupplier + totalCashInAccount;
    const totalCashOut = totalCashOutSupplier + totalCashOutAccount;
    const nettSupplierBuy = totalBuy - totalReturn;
    const nettCash = totalCashOut - totalCashIn;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Pembelian" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Laporan Pembelian
                        </h1>
                        <p className="text-zinc-500 dark:text-zinc-400">
                            Data for{' '}
                            {filters.month
                                ? `Bulan ${filters.month} - ${filters.year}`
                                : `Tahun ${filters.year}`}
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader className="p-4 pb-4 sm:p-6 sm:pb-4">
                        <form
                            onSubmit={handleFilter}
                            className="flex flex-wrap items-end gap-4"
                        >
                            <div className="grid w-[180px] gap-1.5">
                                <Label htmlFor="month">Month</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Select month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">
                                            All Months
                                        </SelectItem>
                                        {Array.from(
                                            { length: 12 },
                                            (_, i) => i + 1,
                                        ).map((m) => (
                                            <SelectItem
                                                key={m}
                                                value={m.toString()}
                                            >
                                                {m}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid w-[180px] gap-1.5">
                                <Label htmlFor="year">Year</Label>
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder="Select year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearList.map((y) => (
                                            <SelectItem
                                                key={y}
                                                value={y.toString()}
                                            >
                                                {y}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClear}
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                </Card>

                {/* Tables */}
                <div className="space-y-8">
                    {/* Supplier Buy Table */}
                    <div className="space-y-4">
                        <h3 className="px-1 text-lg font-bold">Supplier Buy</h3>
                        <div className="overflow-hidden rounded-md border bg-white shadow-sm dark:bg-zinc-900">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                        <TableHead>Supplier</TableHead>
                                        <TableHead className="text-right">
                                            Buy
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Return
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Nett
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {supplierList.map((item) => {
                                        const buy =
                                            supplierReport.buy[item.id] || 0;
                                        const ret =
                                            supplierReport.returnSupplier[
                                                item.id
                                            ] || 0;
                                        const nett = buy - ret;
                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCurrency(buy)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCurrency(ret)}
                                                </TableCell>
                                                <TableCell className="text-right font-bold">
                                                    {formatCurrency(nett)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(totalBuy)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(totalReturn)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(nettSupplierBuy)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    {/* Supplier Cash Table */}
                    <div className="space-y-4">
                        <h3 className="px-1 text-lg font-bold">
                            Supplier Cash
                        </h3>
                        <div className="overflow-hidden rounded-md border bg-white shadow-sm dark:bg-zinc-900">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                        <TableHead>Supplier</TableHead>
                                        <TableHead className="text-right">
                                            Cash In
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Cash Out
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Nett
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {supplierList.map((item) => {
                                        const cin =
                                            supplierReport.cashInSupplier[
                                                item.id
                                            ] || 0;
                                        const cout =
                                            supplierReport.cashOutSupplier[
                                                item.id
                                            ] || 0;
                                        const nett = cout - cin;
                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-600">
                                                    {formatCurrency(cin)}
                                                </TableCell>
                                                <TableCell className="text-right text-rose-600">
                                                    {formatCurrency(cout)}
                                                </TableCell>
                                                <TableCell className="text-right font-bold">
                                                    {formatCurrency(nett)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(
                                                totalCashInSupplier,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(
                                                totalCashOutSupplier,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(
                                                totalCashOutSupplier -
                                                    totalCashInSupplier,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    {/* Journal Cash Table */}
                    <div className="space-y-4">
                        <h3 className="px-1 text-lg font-bold">
                            Journal Cash (Account)
                        </h3>
                        <div className="overflow-hidden rounded-md border bg-white shadow-sm dark:bg-zinc-900">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                        <TableHead>Account</TableHead>
                                        <TableHead className="text-right">
                                            Cash In
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Cash Out
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Nett
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {accountList.map((item) => {
                                        const cin =
                                            supplierReport.cashInAccount[
                                                item.id
                                            ] || 0;
                                        const cout =
                                            supplierReport.cashOutAccount[
                                                item.id
                                            ] || 0;
                                        const nett = cout - cin;
                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">
                                                    {item.name}
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-600">
                                                    {formatCurrency(cin)}
                                                </TableCell>
                                                <TableCell className="text-right text-rose-600">
                                                    {formatCurrency(cout)}
                                                </TableCell>
                                                <TableCell className="text-right font-bold">
                                                    {formatCurrency(nett)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(totalCashInAccount)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(
                                                totalCashOutAccount,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(
                                                totalCashOutAccount -
                                                    totalCashInAccount,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">Total Buy</p>
                            <p className="text-right text-xl font-bold">
                                {formatCurrency(totalBuy)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Return
                            </p>
                            <p className="text-right text-xl font-bold">
                                {formatCurrency(totalReturn)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Cash In
                            </p>
                            <p className="text-right text-xl font-bold text-emerald-600">
                                {formatCurrency(totalCashIn)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Cash Out
                            </p>
                            <p className="text-right text-xl font-bold text-rose-600">
                                {formatCurrency(totalCashOut)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <Card className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/20">
                        <CardContent className="p-6">
                            <p className="text-sm font-medium text-amber-800 dark:text-amber-400">
                                Nett Supplier Buy
                            </p>
                            <p
                                className={`text-right text-3xl font-bold ${nettSupplierBuy >= 0 ? 'text-rose-600' : 'text-emerald-600'}`}
                            >
                                {formatCurrency(nettSupplierBuy)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <CardContent className="p-6">
                            <p className="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                                Nett Cash
                            </p>
                            <p
                                className={`text-right text-3xl font-bold ${nettCash >= 0 ? 'text-rose-600' : 'text-emerald-600'}`}
                            >
                                {formatCurrency(nettCash)}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
