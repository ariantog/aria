import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle, Package, Warehouse, Hash } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Discrepancy {
    id: number;
    item_id: number | null;
    jubelio_item_id: number;
    jubelio_location_id: number;
    jubelio_location_name: string | null;
    warehouse_id: number;
    aria_qty: number;
    jubelio_qty: number;
    item: {
        id: number;
        name: string;
        code: string;
    } | null;
    warehouse: {
        name: string;
    } | null;
}

interface StockCheck {
    id: number;
    page_tracking: number;
    status: string;
    created_at: string;
    discrepancies: Discrepancy[];
}

interface Props {
    stockCheck: StockCheck;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengecekan Stok',
        href: '/jubelio-stock-checks',
    },
    {
        title: 'Detail Pengecekan',
        href: '#',
    },
];

export default function Show({ stockCheck }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Detail Pengecekan #${stockCheck.id}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/jubelio-stock-checks">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">Pengecekan Stok #{stockCheck.id}</h1>
                        <p className="text-sm text-muted-foreground">
                            Status: <span className="font-bold uppercase">{stockCheck.status}</span> | 
                            Dibuat: {new Date(stockCheck.created_at).toLocaleString('id-ID')}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium">Halaman Terakhir</CardTitle>
                            <Hash className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stockCheck.page_tracking}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium">Total Ketidakcocokan</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">
                                {stockCheck.discrepancies.length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-medium">Batas Ketidakcocokan</CardTitle>
                            <Package className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">200</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Ketidakcocokan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted font-semibold uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Item (Aria)</th>
                                        <th className="px-4 py-3">Jubelio Item ID</th>
                                        <th className="px-4 py-3">Warehouse (Aria)</th>
                                        <th className="px-4 py-3">Location (Jubelio)</th>
                                        <th className="px-4 py-3 text-center">Qty Aria</th>
                                        <th className="px-4 py-3 text-center">Qty Jubelio</th>
                                        <th className="px-4 py-3 text-center">Selisih</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {stockCheck.discrepancies.map((item) => (
                                        <tr key={item.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3">
                                                {item.item ? (
                                                    <div className="flex flex-col">
                                                        <span className="font-bold">{item.item.name}</span>
                                                        <span className="text-xs text-muted-foreground font-mono">{item.item.code}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground italic">Item tidak ditemukan</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono">
                                                {item.jubelio_item_id}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Warehouse className="h-4 w-4 opacity-50" />
                                                    <span>{item.warehouse?.name || `ID: ${item.warehouse_id}`}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span>{item.jubelio_location_name || '-'}</span>
                                                    <span className="text-xs text-muted-foreground font-mono">ID: {item.jubelio_location_id}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center font-bold">
                                                {item.aria_qty}
                                            </td>
                                            <td className="px-4 py-3 text-center font-bold text-blue-600 dark:text-blue-400">
                                                {item.jubelio_qty}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant="destructive">
                                                    {(item.aria_qty - item.jubelio_qty).toFixed(2)}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                    {stockCheck.discrepancies.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground italic">
                                                Tidak ada ketidakcocokan ditemukan pada pengecekan ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
