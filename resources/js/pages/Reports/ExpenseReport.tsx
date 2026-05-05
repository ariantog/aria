import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router, Link } from '@inertiajs/react';
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

interface ListItem {
    id: number;
    name: string;
}

interface ReportData {
    cashIn: Record<number, number>;
    cashOut: Record<number, number>;
}

interface Props {
    accountList: ListItem[];
    accountReport: ReportData;
    bankList: ListItem[];
    bankReport: ReportData;
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

export default function ExpenseReport({
    accountList,
    accountReport,
    bankList,
    bankReport,
    filters,
    yearList,
    datesNow,
}: Props) {
    const [month, setMonth] = useState(filters.month?.toString() || '0');
    const [year, setYear] = useState(filters.year.toString());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Laporan Biaya', href: '/reports/expense' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params: any = { year };
        if (month !== '0') params.month = month;
        router.get('/reports/expense', params, { preserveState: true });
    };

    const handleClear = () => {
        setMonth('0');
        setYear(datesNow.year.toString());
        router.get('/reports/expense');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(amount));
    };

    const totalAccountIn = Object.values(accountReport.cashIn).reduce(
        (a, b) => a + b,
        0,
    );
    const totalAccountOut = Object.values(accountReport.cashOut).reduce(
        (a, b) => a + b,
        0,
    );
    const totalAccountNett = totalAccountIn + totalAccountOut;

    const totalBankIn = Object.values(bankReport.cashIn).reduce(
        (a, b) => a + b,
        0,
    );
    const totalBankOut = Object.values(bankReport.cashOut).reduce(
        (a, b) => a + b,
        0,
    );
    const totalBankNett = totalBankIn + totalBankOut;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Biaya" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Laporan Biaya Jurnal & Bank
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

                <div className="space-y-12">
                    {/* Biaya Jurnal (Account) */}
                    <div className="space-y-4">
                        <h3 className="px-1 text-lg font-bold">
                            Biaya Jurnal (Account)
                        </h3>
                        <div className="overflow-hidden rounded-md border bg-white shadow-sm dark:bg-zinc-900">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                        <TableHead>Nama Jurnal</TableHead>
                                        <TableHead className="text-right">
                                            Cash In (Dari Bank)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Cash Out (Ke Bank)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Nett
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {accountList.map((item) => {
                                        const cin =
                                            accountReport.cashIn[item.id] || 0;
                                        const cout =
                                            accountReport.cashOut[item.id] || 0;
                                        const nett = cin + cout;
                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">
                                                    <Link
                                                        href={`/addrbook/${item.id}`}
                                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-600">
                                                    {formatCurrency(cin)}
                                                </TableCell>
                                                <TableCell className="text-right text-rose-600">
                                                    {formatCurrency(cout)}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-bold ${nett < 0 ? 'text-rose-600' : ''}`}
                                                >
                                                    {formatCurrency(nett)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                        <TableCell>Total Jurnal</TableCell>
                                        <TableCell className="text-right text-emerald-600">
                                            {formatCurrency(totalAccountIn)}
                                        </TableCell>
                                        <TableCell className="text-right text-rose-600">
                                            {formatCurrency(totalAccountOut)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(totalAccountNett)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    {/* Biaya Bank */}
                    <div className="space-y-4">
                        <h3 className="px-1 text-lg font-bold">Biaya Bank</h3>
                        <div className="overflow-hidden rounded-md border bg-white shadow-sm dark:bg-zinc-900">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                        <TableHead>Nama Bank</TableHead>
                                        <TableHead className="text-right">
                                            Cash In (Dari Jurnal)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Cash Out (Ke Jurnal)
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Nett
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bankList.map((item) => {
                                        const cin =
                                            bankReport.cashIn[item.id] || 0;
                                        const cout =
                                            bankReport.cashOut[item.id] || 0;
                                        const nett = cin + cout;
                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">
                                                    <Link
                                                        href={`/addrbook/${item.id}`}
                                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-600">
                                                    {formatCurrency(cin)}
                                                </TableCell>
                                                <TableCell className="text-right text-rose-600">
                                                    {formatCurrency(cout)}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-bold ${nett < 0 ? 'text-rose-600' : ''}`}
                                                >
                                                    {formatCurrency(nett)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                        <TableCell>Total Bank</TableCell>
                                        <TableCell className="text-right text-emerald-600">
                                            {formatCurrency(totalBankIn)}
                                        </TableCell>
                                        <TableCell className="text-right text-rose-600">
                                            {formatCurrency(totalBankOut)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(totalBankNett)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="border-l-4 border-l-emerald-500">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Mutasi Masuk (Cash In)
                            </p>
                            <p className="text-right text-2xl font-bold text-emerald-600">
                                {formatCurrency(totalAccountIn)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-rose-500">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Mutasi Keluar (Cash Out)
                            </p>
                            <p className="text-right text-2xl font-bold text-rose-600">
                                {formatCurrency(totalAccountOut)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-blue-500">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500">
                                Total Selisih (Nett)
                            </p>
                            <p className="text-right text-2xl font-bold text-blue-600">
                                {formatCurrency(totalAccountNett)}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
