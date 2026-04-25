import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

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
                    <h1 className="text-2xl font-bold tracking-tight">Item Gudang</h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Total {totalWarehouse} Gudang
                    </p>
                </div>

                {/* Warehouse List Table */}
                <div className="space-y-4">
                    <h3 className="text-lg font-bold px-1 text-zinc-900 dark:text-zinc-100">Warehouse Stock</h3>
                    <div className="rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                    <TableHead className="text-zinc-900 dark:text-zinc-100">Gudang</TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">Item</TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">Qty</TableHead>
                                    <TableHead className="text-right text-zinc-900 dark:text-zinc-100">Cost</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.map((row) => (
                                    <TableRow key={row.id} className="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <TableCell className="font-medium">
                                            <Link 
                                                href={`/warehouse/${row.id}`} 
                                                className="text-blue-600 dark:text-blue-400 hover:underline"
                                            >
                                                {row.nama_gudang}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(row.total_item)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatNumber(row.total_qty)}</TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">{formatCurrency(row.total_cost)}</TableCell>
                                    </TableRow>
                                ))}
                                <TableRow className="bg-zinc-100/50 dark:bg-zinc-800/50 font-bold">
                                    <TableCell>TOTAL</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatNumber(grandTotalItem)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatNumber(grandTotalQty)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatCurrency(grandTotalCost)}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                {/* Summary Cards at the bottom */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500 mb-1">Total Item (SKU)</p>
                            <p className="text-2xl font-bold text-right tabular-nums">{formatNumber(grandTotalItem)}</p>
                        </CardContent>
                    </Card>
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500 mb-1">Total Qty</p>
                            <p className="text-2xl font-bold text-right tabular-nums">{formatNumber(grandTotalQty)}</p>
                        </CardContent>
                    </Card>
                    <Card className="border-zinc-200 dark:border-zinc-800">
                        <CardContent className="p-4">
                            <p className="text-sm text-zinc-500 mb-1">Total Asset (Cost)</p>
                            <p className="text-2xl font-bold text-right text-emerald-600 dark:text-emerald-400 tabular-nums">
                                {formatCurrency(grandTotalCost)}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
