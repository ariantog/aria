import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import { ArrowLeft, Printer } from 'lucide-react';

export default function Show({ borongan, details }: any) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Borongan', href: '/borongan' },
        { title: `Detail #${borongan.id}`, href: `/borongan/${borongan.id}` },
    ];

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    };

    const subTotalItem = details.reduce((acc: number, curr: any) => acc + (parseFloat(curr.total) || 0), 0);
    const totalQty = details.reduce((acc: number, curr: any) => acc + (parseInt(curr.quantity) || 0), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Detail Borongan #${borongan.id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 w-full">
                <div className="flex items-center justify-between mb-2 hover-none-print">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/borongan">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold tracking-tight">Detail Borongan #{borongan.id}</h1>
                    </div>
                    <Button variant="secondary" onClick={() => window.print()}>
                        <Printer className="mr-2 h-4 w-4" /> Cetak
                    </Button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Informasi Borongan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="grid grid-cols-2 gap-1 text-sm">
                                <span className="text-muted-foreground">ID Transaksi</span>
                                <span className="font-medium">#{borongan.id}</span>

                                <span className="text-muted-foreground">Tanggal Buat</span>
                                <span className="font-medium">{borongan.date ? format(new Date(borongan.date), 'dd MMMM yyyy', { locale: id }) : '-'}</span>

                                <span className="text-muted-foreground">Periode</span>
                                <span className="font-medium">
                                    {borongan.from ? format(new Date(borongan.from), 'dd/MM/yyyy') : '-'}
                                    {' - '}
                                    {borongan.to ? format(new Date(borongan.to), 'dd/MM/yyyy') : '-'}
                                </span>

                                <div className="col-span-2 border-t mt-2 pt-2 grid grid-cols-2 gap-1">
                                    <span className="text-muted-foreground">Total Qty</span>
                                    <span className="font-medium">{totalQty} Pcs</span>

                                    <span className="text-muted-foreground">Tres</span>
                                    <span className="font-medium">{formatCurrency(borongan.tres)}</span>

                                    <span className="text-muted-foreground">Permak</span>
                                    <span className="font-medium">{formatCurrency(borongan.permak)}</span>

                                    <span className="text-muted-foreground">Lainnya</span>
                                    <span className="font-medium">{formatCurrency(borongan.lain2)}</span>

                                    <span className="text-muted-foreground font-bold">Total</span>
                                    <span className="font-bold text-primary">{formatCurrency(borongan.total)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Informasi Pihak</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="grid grid-cols-2 gap-1 text-sm">
                                <span className="text-muted-foreground">Penjahit</span>
                                <span className="font-semibold text-primary text-base">{borongan.jahit?.name || '-'}</span>

                                <span className="text-muted-foreground">Dibuat Oleh</span>
                                <span className="font-medium">{borongan.user?.name || '-'}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="mt-2">
                    <CardHeader className="py-4">
                        <CardTitle className="text-lg">Rincian Item Gudang</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="relative w-full overflow-auto">
                            <table className="w-full caption-bottom text-sm">
                                <thead className="bg-gray-100 dark:bg-gray-800">
                                    <tr className="border-b transition-colors hover:bg-muted/50">
                                        <th className="h-10 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase">Kitir</th>
                                        <th className="h-10 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase">Items</th>
                                        <th className="h-10 px-4 text-right align-middle font-medium text-muted-foreground text-xs uppercase">Price</th>
                                        <th className="h-10 px-4 text-right align-middle font-medium text-muted-foreground text-xs uppercase">Quantity</th>
                                        <th className="h-10 px-4 text-right align-middle font-medium text-muted-foreground text-xs uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="[&_tr:last-child]:border-0 border-b">
                                    {details.length > 0 ? (
                                        details.map((d: any, idx: number) => {
                                            const code = d.item ? (d.item.name || d.item.id) : (d.produksi?.temp_name || '-');
                                            const serial = d.produksi?.serial || '-';
                                            return (
                                                <tr key={d.id} className="transition-colors hover:bg-muted/50">
                                                    <td className="p-3 px-4 align-middle font-medium">{serial}</td>
                                                    <td className="p-3 px-4 align-middle">{code}</td>
                                                    <td className="p-3 px-4 align-middle text-right">{formatCurrency(d.ongkos)}</td>
                                                    <td className="p-3 px-4 align-middle text-right">{d.quantity}</td>
                                                    <td className="p-3 px-4 align-middle text-right font-semibold">{formatCurrency(d.total)}</td>
                                                </tr>
                                            )
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="p-4 text-center text-muted-foreground">
                                                Tidak ada item
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot className="border-t bg-muted/20">
                                    <tr>
                                        <td colSpan={3} className="px-4 py-3 font-semibold text-right">Subtotal Jumlah:</td>
                                        <td className="px-4 py-3 text-right font-bold">{totalQty} pcs</td>
                                        <td className="px-4 py-3 text-right font-bold text-primary">{formatCurrency(subTotalItem)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Print Styles */}
            <style dangerouslySetInnerHTML={{
                __html: `
                @media print {
                    .hover-none-print { display: none !important; }
                    body { background: white !important; }
                    .bg-muted\\/20, .bg-muted\\/40 { background: transparent !important; }
                    .border-primary\\/20 { border-color: #e2e8f0 !important; }
                    main { padding: 0 !important; }
                    nav, header, aside { display: none !important; }
                    .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
                    .rounded-xl { border-radius: 0 !important; }
                    .border-b { border-bottom: 1px solid #e2e8f0 !important; }
                }
            `}} />
        </AppLayout>
    );
}
