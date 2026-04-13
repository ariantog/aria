import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Filter, X } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';

interface MonthlyItemSale {
    id: number;
    year: number;
    month: number;
    group_id: number;
    customer_id: number;
    qty_net: string | number;
    amount_net: string | number;
    group?: {
        id: number;
        name: string;
    };
    customer?: {
        id: number;
        name: string;
    };
}

interface Props {
    dataList: {
        data: MonthlyItemSale[];
        links: any[];
    };
    filters: {
        month: number | string | null;
        year: number | string | null;
    };
    yearList: number[];
}

export default function ItemSales({ dataList, filters, yearList }: Props) {
    const [month, setMonth] = useState(filters.month?.toString() || '0');
    const [year, setYear] = useState(filters.year?.toString() || new Date().getFullYear().toString());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Item Sales', href: '/reports/item-sales' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params: any = { year };
        if (month !== '0') params.month = month;
        router.get('/reports/item-sales', params, { preserveState: true });
    };

    const handleClear = () => {
        setMonth('0');
        setYear(new Date().getFullYear().toString());
        router.get('/reports/item-sales');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(amount));
    };

    const formatNumber = (num: number | string) => {
        return new Intl.NumberFormat('id-ID').format(Number(num));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Item Sales Report" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Item Sales (Net)</h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Laporan penjualan bersih per kategori dan customer (Net = Sell - Return).
                    </p>
                </div>

                <Card>
                    <CardHeader className="p-4 sm:p-6 pb-4 sm:pb-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-1.5 w-[150px]">
                                <Label htmlFor="month">Bulan</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Semua Bulan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Semua Bulan</SelectItem>
                                        {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                            <SelectItem key={m} value={m.toString()}>{m}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5 w-[150px]">
                                <Label htmlFor="year">Tahun</Label>
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder="Pilih Tahun" />
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
                                    Bersihkan
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                </Card>

                <div className="rounded-md border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableHead className="w-[120px]">Periode</TableHead>
                                <TableHead>Grup Item</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead className="text-right w-[120px]">Qty Jual (Net)</TableHead>
                                <TableHead className="text-right w-[180px]">Nominal (Net)</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {dataList.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="h-24 text-center">Data Kosong</TableCell>
                                </TableRow>
                            ) : (
                                dataList.data.map((item) => (
                                    <TableRow key={item.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors">
                                        <TableCell className="font-medium">{item.month}/{item.year}</TableCell>
                                        <TableCell>
                                            {item.group ? (
                                                <Link 
                                                    href={`/items-group/${item.group.id}`}
                                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                                >
                                                    {item.group.name}
                                                </Link>
                                            ) : '-'}
                                        </TableCell>
                                        <TableCell className="font-semibold text-zinc-700 dark:text-zinc-300">
                                            {item.customer?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-bold text-emerald-600">
                                            {formatNumber(item.qty_net)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-bold">
                                            {formatCurrency(item.amount_net)}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
                
                <div className="mt-4">
                    <Pagination links={dataList.links} />
                </div>
            </div>
        </AppLayout>
    );
}
