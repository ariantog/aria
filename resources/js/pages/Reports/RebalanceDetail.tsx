import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Warehouse,
    Calendar,
    Package,
    TrendingUp,
    RefreshCw,
    ArrowRight,
    CheckCircle2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
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

interface WarehouseStock {
    warehouse_id: number;
    warehouse_name: string;
    current_stock: number | string;
    last_sale_date: string | null;
    sold_30d: number | string;
}

interface Props {
    item: {
        id: number;
        name: string;
        code: string;
    };
    sourceWarehouse: {
        id: number;
        name: string;
    };
    warehouseStocks: WarehouseStock[];
    recommendation: {
        from_id: number;
        from_name: string;
        to_id: number;
        to_name: string;
        demand_30d: number;
        suggested_qty: number;
    } | null;
}

export default function RebalanceDetail({
    item,
    sourceWarehouse,
    warehouseStocks,
    recommendation,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Stock Intelligence', href: '/reports/stock-intelligence' },
        { title: 'Rebalance Detail', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Rebalance: ${item.name}`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex items-center gap-4">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {item.name}
                        </h1>
                        <p className="font-mono text-sm text-zinc-500">
                            {item.code}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: Warehouse List (2/3 width) */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Warehouse className="h-5 w-5 text-zinc-400" />
                                    Stok & Performa di Seluruh Gudang
                                </CardTitle>
                                <CardDescription>
                                    Membandingkan jumlah stok dan aktivitas
                                    penjualan 30 hari terakhir.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                            <TableHead>Nama Gudang</TableHead>
                                            <TableHead className="text-center">
                                                Stok Saat Ini
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Terakhir Laku
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Laku (30 Hari)
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {warehouseStocks.map((ws) => (
                                            <TableRow
                                                key={ws.warehouse_id}
                                                className={
                                                    ws.warehouse_id ===
                                                    sourceWarehouse.id
                                                        ? 'bg-amber-50/50 dark:bg-amber-900/10'
                                                        : ''
                                                }
                                            >
                                                <TableCell>
                                                    <div className="flex items-center gap-2 text-sm font-bold">
                                                        {ws.warehouse_name}
                                                        {ws.warehouse_id ===
                                                            sourceWarehouse.id && (
                                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 uppercase">
                                                                Sumber
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <div className="flex items-center justify-center gap-1.5 font-mono font-bold text-blue-600">
                                                        <Package className="h-3.5 w-3.5" />
                                                        {ws.current_stock}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <div className="flex flex-col items-center">
                                                        <span className="text-xs font-medium">
                                                            {ws.last_sale_date ||
                                                                '-'}
                                                        </span>
                                                        <span className="text-[9px] text-zinc-400 uppercase">
                                                            Last sold
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <div className="flex items-center justify-center gap-1.5 font-mono font-bold text-emerald-600">
                                                        <TrendingUp className="h-3.5 w-3.5" />
                                                        {ws.sold_30d || 0}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right: Smart Suggestion Card */}
                    <div className="space-y-6">
                        <Card className="border-blue-200 shadow-lg dark:border-blue-900/50">
                            <CardHeader className="border-b border-blue-100 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/10">
                                <CardTitle className="flex items-center gap-2 text-lg text-blue-700 dark:text-blue-400">
                                    <RefreshCw className="h-5 w-5" />
                                    Smart Recommendation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6">
                                {recommendation ? (
                                    <div className="space-y-6">
                                        <div className="flex items-center justify-center gap-4 py-4">
                                            <div className="text-center">
                                                <div className="mb-1 text-[10px] font-bold text-zinc-400 uppercase">
                                                    Dari
                                                </div>
                                                <div className="rounded-full border-2 border-dashed border-zinc-300 bg-zinc-100 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                                    <Warehouse className="h-6 w-6 text-zinc-500" />
                                                </div>
                                                <div className="mt-2 max-w-[80px] truncate text-xs font-bold">
                                                    {recommendation.from_name}
                                                </div>
                                            </div>
                                            <ArrowRight className="h-6 w-6 animate-pulse text-blue-500" />
                                            <div className="text-center">
                                                <div className="mb-1 text-[10px] font-bold text-zinc-400 uppercase">
                                                    Ke
                                                </div>
                                                <div className="rounded-full border-2 border-emerald-500 bg-emerald-100 p-3 dark:bg-emerald-900/30">
                                                    <Warehouse className="h-6 w-6 text-emerald-600" />
                                                </div>
                                                <div className="mt-2 max-w-[80px] truncate text-xs font-bold">
                                                    {recommendation.to_name}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                            <div className="mb-4 flex items-center justify-between">
                                                <span className="text-sm font-medium">
                                                    Qty Rekomendasi
                                                </span>
                                                <span className="text-2xl font-black text-blue-600">
                                                    {
                                                        recommendation.suggested_qty
                                                    }{' '}
                                                    <small className="text-xs text-zinc-400">
                                                        Pcs
                                                    </small>
                                                </span>
                                            </div>
                                            <div className="space-y-2 text-xs leading-relaxed text-zinc-500 italic">
                                                <p className="flex gap-2">
                                                    <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                                    Item ini sangat laku di{' '}
                                                    <b>
                                                        {recommendation.to_name}
                                                    </b>{' '}
                                                    ({recommendation.demand_30d}{' '}
                                                    unit dalam 30 hari).
                                                </p>
                                                <p className="flex gap-2">
                                                    <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                                    Memindahkan stok akan
                                                    memaksimalkan perputaran
                                                    uang dan mengurangi risiko
                                                    deadstock.
                                                </p>
                                            </div>
                                        </div>

                                        <Button
                                            className="w-full bg-blue-600 py-6 text-base font-bold shadow-xl shadow-blue-500/20 hover:bg-blue-700"
                                            onClick={() =>
                                                router.get(
                                                    '/transactions/move/create',
                                                    {
                                                        item_id: item.id,
                                                        from_id:
                                                            recommendation.from_id,
                                                        to_id: recommendation.to_id,
                                                        quantity:
                                                            recommendation.suggested_qty,
                                                    },
                                                )
                                            }
                                        >
                                            Buat Transaksi Pemindahan
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="px-4 py-12 text-center">
                                        <div className="mb-4 inline-block rounded-full bg-zinc-50 p-4 dark:bg-zinc-900">
                                            <RefreshCw className="h-8 w-8 text-zinc-300" />
                                        </div>
                                        <p className="text-sm font-medium text-zinc-500">
                                            Tidak ada gudang tujuan potensial.
                                        </p>
                                        <p className="mt-2 text-xs text-zinc-400">
                                            Semua gudang lain juga memiliki
                                            penjualan rendah untuk item ini.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
