import { Head, Link, router } from '@inertiajs/react';
import {
    Printer,
    Search,
    Trash2,
    Calendar,
    Wallet,
    CreditCard,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Gaji {
    id: number;
    karyawan_id: number;
    bulan: number;
    tahun: number;
    bulanan: number;
    harian: number;
    premi: number;
    total_potongan: number;
    sanksi: number;
    total_gaji: number;
    karyawan?: {
        id: number;
        nama: string;
    };
    bank_single?: {
        name: string;
    };
}

interface GajiPerBank {
    bank_id: number | null;
    total_gaji: number;
    bank?: {
        name: string;
    };
}

interface Props {
    gajiList: {
        data: Gaji[];
        links: any[];
        from: number;
        to: number;
        total: number;
        last_page: number;
    };
    gajiPerBank: GajiPerBank[];
    bulanSelect: number;
    yearSelect: number;
    filters: {
        karyawan?: string;
        bulan?: string;
        tahun?: string;
    };
    auth: {
        user: any;
        roles: string[];
    };
}

export default function Index({
    gajiList,
    gajiPerBank,
    bulanSelect,
    yearSelect,
    filters,
    auth,
}: Props) {
    const [search, setSearch] = useState(filters.karyawan || '');
    const [bulan, setBulan] = useState(bulanSelect.toString());
    const [tahun, setTahun] = useState(yearSelect.toString());

    const isSuperAdmin = auth?.roles?.includes('superadmin');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Payroll', href: '/gaji' },
        { title: 'Monthly Salary', href: '/gaji' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/gaji',
            { bulan, tahun, karyawan: search },
            { preserveState: true, replace: true },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this payroll record?')) {
            router.delete(`/gaji/${id}`);
        }
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'decimal',
            minimumFractionDigits: 0,
        }).format(value);
    };

    // Calculate Grand Total for all banks
    const grandTotalBank = gajiPerBank.reduce(
        (sum: number, item: any) => sum + Number(item.total_gaji),
        0,
    );

    const monthNames = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Management" />
            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Monthly Salary Management
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Track and manage employee salary disbursements for{' '}
                            {monthNames[parseInt(bulan) - 1]} {tahun}.
                        </p>
                    </div>
                </div>

                {/* Statistics Cards */}
                {isSuperAdmin && (
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card className="border-blue-100 bg-blue-50/30 shadow-sm dark:border-blue-900/20 dark:bg-blue-900/10">
                            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                                <CardTitle className="text-xs font-bold text-blue-600 uppercase dark:text-blue-400">
                                    Grand Total Payroll
                                </CardTitle>
                                <Wallet className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-700 dark:text-blue-300">
                                    IDR {formatCurrency(grandTotalBank)}
                                </div>
                                <p className="text-[10px] text-blue-600/60 dark:text-blue-400/60">
                                    Combined across all banks
                                </p>
                            </CardContent>
                        </Card>

                        {gajiPerBank.map((bank: any, idx) => (
                            <Card key={idx} className="shadow-sm">
                                <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                                    <CardTitle className="text-xs font-bold text-zinc-500 uppercase">
                                        {bank.bank?.name || 'Kas Tunai'}
                                    </CardTitle>
                                    <CreditCard className="h-4 w-4 text-zinc-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                        IDR {formatCurrency(bank.total_gaji)}
                                    </div>
                                    <p className="text-[10px] text-zinc-400">
                                        Total disbursement
                                    </p>
                                </CardContent>
                            </Card>
                        ))}

                        <Card className="shadow-sm">
                            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                                <CardTitle className="text-xs font-bold text-zinc-500 uppercase">
                                    Total Employees
                                </CardTitle>
                                <Users className="h-4 w-4 text-zinc-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                                    {gajiList.total}
                                </div>
                                <p className="text-[10px] text-zinc-400">
                                    Processed this month
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Filters */}
                <div className="mb-6 flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm md:flex-row md:items-center dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <Select
                                value={bulan}
                                onValueChange={(v) => {
                                    setBulan(v);
                                    router.get(
                                        '/gaji',
                                        { bulan: v, tahun, karyawan: search },
                                        { preserveState: true },
                                    );
                                }}
                            >
                                <SelectTrigger className="w-[160px] pl-9">
                                    <SelectValue placeholder="Bulan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {monthNames.map((m, i) => (
                                        <SelectItem
                                            key={i}
                                            value={(i + 1).toString()}
                                        >
                                            {m}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Input
                            type="number"
                            className="w-[100px]"
                            value={tahun}
                            onChange={(e) => setTahun(e.target.value)}
                            onBlur={() => handleFilter()}
                        />
                    </div>

                    <div className="hidden h-8 w-px bg-zinc-200 md:block dark:bg-zinc-800" />

                    <form
                        onSubmit={handleFilter}
                        className="relative flex flex-1 items-center"
                    >
                        <Search className="absolute left-3 h-4 w-4 text-zinc-400" />
                        <Input
                            placeholder="Search employee name..."
                            className="pl-9"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button type="submit" className="ml-2 hidden sm:flex">
                            Search
                        </Button>
                    </form>
                </div>

                {/* Data Table */}
                <div className="overflow-hidden rounded-xl border bg-white text-sm shadow-sm dark:bg-zinc-900">
                    <Table>
                        <TableHeader className="bg-zinc-50/50 dark:bg-zinc-900/50">
                            <TableRow>
                                <TableHead className="font-bold uppercase tracking-wider">
                                    Periode
                                </TableHead>
                                <TableHead className="font-bold uppercase tracking-wider">
                                    Karyawan
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Bulanan
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Total Harian
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Premi
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Potongan Cuti
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Bonus
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Sanksi
                                </TableHead>
                                <TableHead className="text-right font-bold uppercase tracking-wider">
                                    Total Gaji
                                </TableHead>
                                <TableHead className="font-bold uppercase tracking-wider">
                                    Account Bank
                                </TableHead>
                                {isSuperAdmin && (
                                    <TableHead className="w-[100px] text-center font-bold uppercase tracking-wider">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {gajiList?.data?.length > 0 ? (
                                gajiList.data.map((item: any) => (
                                    <TableRow
                                        key={item.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40"
                                    >
                                        <TableCell className="whitespace-nowrap">
                                            {item.bulan}/{item.tahun}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={`/karyawan/${item.karyawan_id}`}
                                                className="font-semibold text-blue-600 hover:underline"
                                            >
                                                {item.karyawan?.nama}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums text-zinc-700 dark:text-zinc-300">
                                            {formatCurrency(item.bulanan)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums text-zinc-700 dark:text-zinc-300">
                                            {formatCurrency(item.harian)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums text-zinc-700 dark:text-zinc-300">
                                            {item.potongan_cuti_premi > 0
                                                ? '0'
                                                : formatCurrency(item.premi)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums font-medium text-rose-600">
                                            {formatCurrency(
                                                item.potongan_cuti_bulanan,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums text-emerald-600">
                                            {formatCurrency(item.bonus || 0)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums font-medium text-rose-600">
                                            {formatCurrency(item.sanksi || 0)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums font-bold text-emerald-600 dark:text-emerald-500">
                                            {formatCurrency(item.total_gaji)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'font-normal whitespace-nowrap',
                                                    !item.bank_single?.name &&
                                                        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/30 dark:bg-amber-900/10 dark:text-amber-400',
                                                )}
                                            >
                                                {item.bank_single?.name ||
                                                    'Kas Tunai'}
                                            </Badge>
                                        </TableCell>
                                        {isSuperAdmin && (
                                            <TableCell>
                                                <div className="flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-50"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/gaji/cetak/${item.id}`}
                                                            target="_blank"
                                                            title="Print Slip"
                                                        >
                                                            <Printer className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-zinc-400 hover:text-rose-600"
                                                        onClick={() =>
                                                            handleDelete(
                                                                item.id,
                                                            )
                                                        }
                                                        title="Delete Record"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={isSuperAdmin ? 7 : 6}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Search className="h-8 w-8 text-zinc-200" />
                                            <p>
                                                No payroll records found for
                                                this period.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    <div className="border-t bg-zinc-50/50 p-4 dark:bg-zinc-900/50">
                        <Pagination
                            links={gajiList.links}
                            from={gajiList.from}
                            to={gajiList.to}
                            total={gajiList.total}
                            label="payroll records"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
