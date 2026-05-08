import { Head, Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
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

interface WarehouseData {
    id: number;
    nama_gudang: string;
    total_item: number;
    total_qty: number;
    total_cost: number;
}

interface Props {
    data: WarehouseData[];
    totalWarehouse: number;
}

export default function WarehouseItemReport({ data, totalWarehouse }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Item Gudang', href: '/reports/warehouse-item' },
    ];

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

    const grandTotalItem = data.reduce((a, b) => a + Number(b.total_item), 0);
    const grandTotalQty = data.reduce((a, b) => a + Number(b.total_qty), 0);
    const grandTotalCost = data.reduce((a, b) => a + Number(b.total_cost), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Item Gudang" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Item Gudang
                    </h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Total {totalWarehouse} Gudang
                    </p>
                </div>

                {/* Warehouse List Table */}
                <div className="space-y-4">
                    <h3 className="px-1 text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        Warehouse Stock
                    </h3>
                    <div className="overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                    <TableHead className="text-zinc-900 dark:text-zinc-100">
                                        Gudang
                                    </TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">
                                        Item
                                    </TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">
                                        Cost
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <TableCell className="font-medium">
                                            <Link
                                                href={`/warehouse/${row.id}`}
                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                {row.nama_gudang}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(row.total_item)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(row.total_qty)}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {formatCurrency(row.total_cost)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-zinc-100/50 font-bold dark:bg-zinc-800/50">
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(grandTotalItem)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(grandTotalQty)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(grandTotalCost)}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                {/* Summary Cards at the bottom */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="mb-1 text-sm text-zinc-500">
                                Total Item (SKU)
                            </p>
                            <p className="text-right text-2xl font-bold tabular-nums">
                                {formatNumber(grandTotalItem)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="mb-1 text-sm text-zinc-500">
                                Total Qty
                            </p>
                            <p className="text-right text-2xl font-bold tabular-nums">
                                {formatNumber(grandTotalQty)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="mb-1 text-sm text-zinc-500">
                                Total Asset (Cost)
                            </p>
                            <p className="text-right text-2xl font-bold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {formatCurrency(grandTotalCost)}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
