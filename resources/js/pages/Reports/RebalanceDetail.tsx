import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Warehouse, Calendar, Package, TrendingUp, RefreshCw, ArrowRight, CheckCircle2 } from 'lucide-react';

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

export default function RebalanceDetail({ item, sourceWarehouse, warehouseStocks, recommendation }: Props) {
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
                    <Button variant="outline" size="icon" onClick={() => window.history.back()}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{item.name}</h1>
                        <p className="text-zinc-500 font-mono text-sm">{item.code}</p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: Warehouse List (2/3 width) */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Warehouse className="h-5 w-5 text-zinc-400" />
                                    Stok & Performa di Seluruh Gudang
                                </CardTitle>
                                <CardDescription>
                                    Membandingkan jumlah stok dan aktivitas penjualan 30 hari terakhir.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                            <TableHead>Nama Gudang</TableHead>
                                            <TableHead className="text-center">Stok Saat Ini</TableHead>
                                            <TableHead className="text-center">Terakhir Laku</TableHead>
                                            <TableHead className="text-center">Laku (30 Hari)</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {warehouseStocks.map((ws) => (
                                            <TableRow 
                                                key={ws.warehouse_id} 
                                                className={ws.warehouse_id === sourceWarehouse.id ? "bg-amber-50/50 dark:bg-amber-900/10" : ""}
                                            >
                                                <TableCell>
                                                    <div className="font-bold text-sm flex items-center gap-2">
                                                        {ws.warehouse_name}
                                                        {ws.warehouse_id === sourceWarehouse.id && (
                                                            <span className="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold uppercase">Sumber</span>
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
                                                        <span className="text-xs font-medium">{ws.last_sale_date || '-'}</span>
                                                        <span className="text-[9px] text-zinc-400 uppercase">Last sold</span>
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
                        <Card className="border-blue-200 dark:border-blue-900/50 shadow-lg">
                            <CardHeader className="bg-blue-50/50 dark:bg-blue-900/10 border-b border-blue-100 dark:border-blue-900/50">
                                <CardTitle className="text-lg flex items-center gap-2 text-blue-700 dark:text-blue-400">
                                    <RefreshCw className="h-5 w-5" />
                                    Smart Recommendation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6">
                                {recommendation ? (
                                    <div className="space-y-6">
                                        <div className="flex items-center justify-center gap-4 py-4">
                                            <div className="text-center">
                                                <div className="text-[10px] text-zinc-400 uppercase font-bold mb-1">Dari</div>
                                                <div className="p-3 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-dashed border-zinc-300 dark:border-zinc-700">
                                                    <Warehouse className="h-6 w-6 text-zinc-500" />
                                                </div>
                                                <div className="mt-2 text-xs font-bold truncate max-w-[80px]">{recommendation.from_name}</div>
                                            </div>
                                            <ArrowRight className="h-6 w-6 text-blue-500 animate-pulse" />
                                            <div className="text-center">
                                                <div className="text-[10px] text-zinc-400 uppercase font-bold mb-1">Ke</div>
                                                <div className="p-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30 border-2 border-emerald-500">
                                                    <Warehouse className="h-6 w-6 text-emerald-600" />
                                                </div>
                                                <div className="mt-2 text-xs font-bold truncate max-w-[80px]">{recommendation.to_name}</div>
                                            </div>
                                        </div>

                                        <div className="bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                            <div className="flex justify-between items-center mb-4">
                                                <span className="text-sm font-medium">Qty Rekomendasi</span>
                                                <span className="text-2xl font-black text-blue-600">{recommendation.suggested_qty} <small className="text-xs text-zinc-400">Pcs</small></span>
                                            </div>
                                            <div className="space-y-2 text-xs text-zinc-500 italic leading-relaxed">
                                                <p className="flex gap-2">
                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                                                    Item ini sangat laku di <b>{recommendation.to_name}</b> ({recommendation.demand_30d} unit dalam 30 hari).
                                                </p>
                                                <p className="flex gap-2">
                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                                                    Memindahkan stok akan memaksimalkan perputaran uang dan mengurangi risiko deadstock.
                                                </p>
                                            </div>
                                        </div>

                                        <Button 
                                            className="w-full bg-blue-600 hover:bg-blue-700 py-6 text-base font-bold shadow-blue-500/20 shadow-xl"
                                            onClick={() => router.get('/transactions/move/create', {
                                                item_id: item.id,
                                                from_id: recommendation.from_id,
                                                to_id: recommendation.to_id,
                                                quantity: recommendation.suggested_qty
                                            })}
                                        >
                                            Buat Transaksi Pemindahan
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="text-center py-12 px-4">
                                        <div className="bg-zinc-50 dark:bg-zinc-900 p-4 rounded-full inline-block mb-4">
                                            <RefreshCw className="h-8 w-8 text-zinc-300" />
                                        </div>
                                        <p className="text-sm text-zinc-500 font-medium">Tidak ada gudang tujuan potensial.</p>
                                        <p className="text-xs text-zinc-400 mt-2">Semua gudang lain juga memiliki penjualan rendah untuk item ini.</p>
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
