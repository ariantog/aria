import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Filter, X, Search } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Input } from '@/components/ui/input';

interface MonthlyItemSale {
    id: string;
    year: number;
    month: number;
    group_id: number;
    customer_id: number;
    type: number;
    type_name: string;
    sum_qty: number;
    sum_total: number;
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
        bulan: number | string | null;
        tahun: number | string | null;
        type: number | string | null;
        search_group: string | null;
    };
    yearList: number[];
}

export default function ItemSales({ dataList, filters, yearList }: Props) {
    const [bulan, setBulan] = useState(filters.bulan?.toString() || '0');
    const [tahun, setTahun] = useState(filters.tahun?.toString() || new Date().getFullYear().toString());
    const [type, setType] = useState(filters.type?.toString() || '0');
    const [searchGroup, setSearchGroup] = useState(filters.search_group || '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Item Sales', href: '/reports/item-sales' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params: any = {};
        if (bulan !== '0') params.bulan = bulan;
        if (tahun !== '0') params.tahun = tahun;
        if (type !== '0') params.type = type;
        if (searchGroup) params.search_group = searchGroup;
        router.get('/reports/item-sales', params, { preserveState: true });
    };

    const handleClear = () => {
        setBulan('0');
        setTahun(new Date().getFullYear().toString());
        setType('0');
        setSearchGroup('');
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
                    <h1 className="text-2xl font-bold tracking-tight">Item Sales</h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Laporan penjualan per kategori dan customer.
                    </p>
                </div>

                <Card>
                    <CardHeader className="p-4 sm:p-6 pb-4 sm:pb-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-1.5 w-[120px]">
                                <Label htmlFor="month">Bulan</Label>
                                <Select value={bulan} onValueChange={setBulan}>
                                    <SelectTrigger id="month">
                                        <SelectValue placeholder="Semua" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Semua</SelectItem>
                                        {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                            <SelectItem key={m} value={m.toString()}>{m}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5 w-[120px]">
                                <Label htmlFor="year">Tahun</Label>
                                <Select value={tahun} onValueChange={setTahun}>
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder="Tahun" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearList.map((y) => (
                                            <SelectItem key={y} value={y.toString()}>{y}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5 w-[250px]">
                                <Label htmlFor="search_group">Cari Grup Item</Label>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" />
                                    <Input
                                        id="search_group"
                                        type="text"
                                        placeholder="Ketik nama grup..."
                                        className="pl-9"
                                        value={searchGroup}
                                        onChange={(e) => setSearchGroup(e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-1.5 w-[120px]">
                                <Label htmlFor="type">Tipe</Label>
                                <Select value={type} onValueChange={setType}>
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder="Semua" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Semua</SelectItem>
                                        <SelectItem value="2">Sell</SelectItem>
                                        <SelectItem value="15">Return</SelectItem>
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
                                <TableHead className="w-[100px]">Periode</TableHead>
                                <TableHead>Grup Item</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead className="w-[100px]">Tipe</TableHead>
                                <TableHead className="text-right w-[100px]">Total Qty</TableHead>
                                <TableHead className="text-right w-[160px]">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {dataList.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="h-24 text-center">Data Kosong</TableCell>
                                </TableRow>
                            ) : (
                                dataList.data.map((item) => (
                                    <TableRow key={item.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors">
                                        <TableCell className="font-medium text-xs">{item.month}/{item.year}</TableCell>
                                        <TableCell className="text-xs">
                                            {item.group ? (
                                                <Link 
                                                    href={`/items-group/${item.group.id}`}
                                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                                >
                                                    {item.group.name}
                                                </Link>
                                            ) : '-'}
                                        </TableCell>
                                        <TableCell className="font-semibold text-zinc-700 dark:text-zinc-300 text-xs">
                                            {item.customer?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <span className={`px-2 py-0.5 rounded text-[10px] uppercase font-bold ${
                                                item.type === 2 
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' 
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'
                                            }`}>
                                                {item.type_name}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-bold text-xs">
                                            {formatNumber(item.sum_qty)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-bold text-xs">
                                            {formatCurrency(item.sum_total)}
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
