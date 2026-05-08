import { Head, Link, router } from '@inertiajs/react';
import {
    Printer,
    RotateCcw,
    ArrowLeft,
    FileText,
    Calendar,
    User as UserIcon,
    Tag,
    Info,
    Package,
    ArrowRight,
    ExternalLink,
    AlertCircle,
    Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import addrbook from '@/routes/addrbook';
import items from '@/routes/items';
import transactions from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';


interface Props {
    transaction: any;
    config: {
        sender_label: string;
        receiver_label: string;
        type_slug: string;
    };
    can: {
        restore: boolean;
    };
}

export default function DeletedShow({ transaction, config, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Transaksi',
            href: transactions.index.url(),
        },
        {
            title: 'Transaksi Terhapus',
            href: '/transactions/deleted',
        },
        {
            title: `Invoice #${transaction.invoice_number}`,
            href: `/transactions/deleted/${transaction.id}`,
        },
    ];

    // Column Visibility States
    const [showImage, setShowImage] = useState(true);
    const [showBarcode, setShowBarcode] = useState(true);
    const [showSku, setShowSku] = useState(false);

    const handleRestore = () => {
        router.post(`/transactions/deleted/${transaction.id}/restore`);
    };

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat('id-ID').format(num);
    };

    const formatDate = (date: string) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    const formatDateTime = (dateString: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    // Helper to determine status badge
    const getStatusBadge = () => {
        const statuses: Record<number, { label: string; color: string }> = {
            1: { label: 'Pending', color: 'bg-yellow-100 text-yellow-800' },
            2: { label: 'Selesai', color: 'bg-green-100 text-green-800' },
            3: { label: 'Dibatalkan', color: 'bg-red-100 text-red-800' },
        };
        const status = statuses[transaction.status] || {
            label: 'Tidak Diketahui',
            color: 'bg-gray-100 text-gray-800',
        };
        return (
            <Badge className={`${status.color} border-none px-3 font-semibold`}>
                {status.label}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Detail Transaksi Terhapus #${transaction.invoice_number}`}
            />

            <div className="space-y-6 p-6">
                {/* Top Action Bar */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-3">
                        <Link href="/transactions/deleted">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-9 w-9"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight">
                                    Detail Transaksi Terhapus
                                </h1>
                                <Badge
                                    variant="destructive"
                                    className="uppercase"
                                >
                                    Terhapus
                                </Badge>
                            </div>
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <FileText className="h-3.5 w-3.5 text-blue-500" />
                                Invoice #{transaction.invoice_number}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                            className="gap-2"
                        >
                            <Printer className="h-4 w-4" />
                            Cetak
                        </Button>

                        {can.restore && (
                            <ConfirmDialog
                                onConfirm={handleRestore}
                                title="Pulihkan Transaksi"
                                description="Apakah Anda yakin ingin memulihkan transaksi ini? Transaksi akan dikembalikan ke daftar aktif dan dampaknya terhadap stok serta saldo akan dihitung ulang."
                                confirmText="Pulihkan"
                                destructive={false}
                                trigger={
                                    <Button
                                        variant="outline"
                                        className="gap-2 border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-400"
                                    >
                                        <RotateCcw className="h-4 w-4" />
                                        Pulihkan Transaksi
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </div>

                {/* Deleted Info Banner */}
                <div className="flex items-center gap-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-400">
                    <Trash2 className="h-5 w-5 shrink-0" />
                    <div>
                        <p className="text-sm font-bold">
                            Transaksi ini dihapus pada{' '}
                            {formatDateTime(transaction.deleted_at)}
                        </p>
                        <p className="text-xs opacity-80">
                            Penghapusan dapat dibatalkan. Memulihkan akan
                            menghitung ulang inventaris dan saldo terkait.
                        </p>
                    </div>
                </div>

                {/* Primary Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Summary Card */}
                    <Card className="overflow-hidden border-zinc-200 shadow-sm dark:border-zinc-800">
                        <div className="h-2 w-full bg-zinc-500" />
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                Total Akhir
                            </CardTitle>
                            <div className="mt-1 flex items-baseline gap-1">
                                <span className="text-3xl font-black text-zinc-500">
                                    IDR
                                </span>
                                <span className="text-4xl font-black tracking-tighter text-zinc-900 tabular-nums dark:text-zinc-100">
                                    {formatNumber(
                                        Math.abs(transaction.grand_total),
                                    )}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="flex items-center justify-between rounded-lg border border-dashed bg-muted/50 p-3">
                                <div className="text-sm font-medium">
                                    Status
                                </div>
                                {getStatusBadge()}
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1.5 text-muted-foreground">
                                        <Calendar className="h-3.5 w-3.5" />{' '}
                                        Tanggal
                                    </span>
                                    <span className="font-semibold">
                                        {formatDate(transaction.date)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1.5 text-muted-foreground">
                                        <Info className="h-3.5 w-3.5" /> Tipe
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        className="capitalize"
                                    >
                                        {config.type_slug}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sender Info */}
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20 pb-3">
                            <div>
                                <CardTitle className="text-sm font-bold tracking-widest text-muted-foreground uppercase">
                                    {config.sender_label} (Dari)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Asal barang-barang ini
                                </CardDescription>
                            </div>
                            <Package className="h-4 w-4 text-zinc-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.sender ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-bold text-zinc-600 dark:bg-zinc-800">
                                            {transaction.sender.name.charAt(0)}
                                        </div>
                                        <div>
                                            <p className="text-lg leading-tight font-bold">
                                                {transaction.sender.name}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                ID: {transaction.sender.id}
                                            </p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded bg-muted/30 p-2 text-center">
                                            <p className="mb-0.5 text-[10px] font-bold text-muted-foreground uppercase">
                                                Tipe
                                            </p>
                                            <p className="text-xs font-semibold">
                                                {transaction.sender.type_name}
                                            </p>
                                        </div>
                                        <Link
                                            href={addrbook.type.show.url({
                                                type: transaction.sender
                                                    .type_slug,
                                                addrbook: transaction.sender.id,
                                            })}
                                            className="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/20"
                                        >
                                            <p className="mb-0.5 w-full text-center text-[10px] font-bold text-muted-foreground uppercase group-hover:text-zinc-900 dark:group-hover:text-zinc-100">
                                                Lihat Detail
                                            </p>
                                            <ExternalLink className="h-3 w-3 translate-y-0 transition-transform group-hover:-translate-y-0.5" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="mb-2 h-8 w-8 opacity-20" />
                                    <p className="text-sm font-medium italic">
                                        Tidak ada info pengirim
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Receiver Info */}
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20 pb-3">
                            <div>
                                <CardTitle className="text-sm font-bold tracking-widest text-muted-foreground uppercase">
                                    {config.receiver_label} (Ke)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Tujuan barang-barang ini
                                </CardDescription>
                            </div>
                            <ArrowRight className="h-4 w-4 text-zinc-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.receiver ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-bold text-zinc-600 dark:bg-zinc-800">
                                            {transaction.receiver.name.charAt(
                                                0,
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-lg leading-tight font-bold">
                                                {transaction.receiver.name}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                ID: {transaction.receiver.id}
                                            </p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded bg-muted/30 p-2 text-center">
                                            <p className="mb-0.5 text-[10px] font-bold text-muted-foreground uppercase">
                                                Tipe
                                            </p>
                                            <p className="text-xs font-semibold">
                                                {transaction.receiver.type_name}
                                            </p>
                                        </div>
                                        <Link
                                            href={addrbook.type.show.url({
                                                type: transaction.receiver
                                                    .type_slug,
                                                addrbook:
                                                    transaction.receiver.id,
                                            })}
                                            className="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/20"
                                        >
                                            <p className="mb-0.5 w-full text-center text-[10px] font-bold text-muted-foreground uppercase group-hover:text-zinc-900 dark:group-hover:text-zinc-100">
                                                Lihat Detail
                                            </p>
                                            <ExternalLink className="h-3 w-3 translate-y-0 transition-transform group-hover:-translate-y-0.5" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="mb-2 h-8 w-8 opacity-20" />
                                    <p className="text-sm font-medium italic">
                                        Tidak ada info penerima
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Items Section */}
                <Card className="border-none shadow-md print:shadow-none">
                    <CardHeader className="flex flex-col justify-between gap-4 pb-4 md:flex-row md:items-center">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Package className="h-5 w-5 text-zinc-600" />
                                Daftar Barang
                            </CardTitle>
                            <CardDescription>
                                Barang yang diminta dalam transaksi ini (
                                {transaction.total_items})
                            </CardDescription>
                        </div>

                        {/* Column Controls */}
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-full border border-dashed bg-muted/40 px-4 py-2 print:hidden">
                            <span className="mr-[-8px] text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                Tampilan:
                            </span>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="img"
                                    checked={showImage}
                                    onCheckedChange={() =>
                                        setShowImage(!showImage)
                                    }
                                />
                                <label
                                    htmlFor="img"
                                    className="cursor-pointer text-xs leading-none font-bold peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Gambar
                                </label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="bc"
                                    checked={showBarcode}
                                    onCheckedChange={() =>
                                        setShowBarcode(!showBarcode)
                                    }
                                />
                                <label
                                    htmlFor="bc"
                                    className="cursor-pointer text-xs leading-none font-bold peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Barcode
                                </label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="sku"
                                    checked={showSku}
                                    onCheckedChange={() => setShowSku(!showSku)}
                                />
                                <label
                                    htmlFor="sku"
                                    className="cursor-pointer text-xs leading-none font-bold peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    SKU
                                </label>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left text-sm">
                                <thead className="border-y bg-muted/50 text-xs text-muted-foreground uppercase tabular-nums">
                                    <tr>
                                        {showImage && (
                                            <th className="w-20 px-4 py-3 text-center font-black">
                                                Gbr
                                            </th>
                                        )}
                                        {showBarcode && (
                                            <th className="px-4 py-3 font-black">
                                                Barcode
                                            </th>
                                        )}
                                        {showSku && (
                                            <th className="px-4 py-3 font-black">
                                                SKU
                                            </th>
                                        )}
                                        <th className="px-4 py-3 font-black">
                                            Nama Barang
                                        </th>
                                        <th className="px-4 py-3 text-center font-black">
                                            Qty
                                        </th>
                                        <th className="px-4 py-3 text-right font-black">
                                            Harga
                                        </th>
                                        <th className="px-4 py-3 text-center font-black">
                                            Diskon(%)
                                        </th>
                                        <th className="px-4 py-3 text-right font-black">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transaction.details.map(
                                        (detail: any, idx: number) => (
                                            <tr
                                                key={idx}
                                                className="group border-b tabular-nums transition-colors hover:bg-muted/20"
                                            >
                                                {showImage && (
                                                    <td className="px-4 py-3 text-center">
                                                        <div className="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                                                            {detail.item
                                                                ?.image_url ? (
                                                                <img
                                                                    src={
                                                                        detail
                                                                            .item
                                                                            .image_url
                                                                    }
                                                                    alt={
                                                                        detail
                                                                            .item
                                                                            ?.name
                                                                    }
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            ) : (
                                                                <Package className="h-6 w-6 text-muted-foreground/30" />
                                                            )}
                                                        </div>
                                                    </td>
                                                )}
                                                {showBarcode && (
                                                    <td className="px-4 py-3 font-mono text-xs">
                                                        <Link
                                                            href={items.show.url(
                                                                {
                                                                    item: detail
                                                                        .item
                                                                        ?.id,
                                                                },
                                                            )}
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            {detail.item?.id}
                                                        </Link>
                                                    </td>
                                                )}
                                                {showSku && (
                                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground italic">
                                                        {detail.item?.code ||
                                                            '-'}
                                                    </td>
                                                )}
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col">
                                                        <span className="font-bold text-zinc-900 dark:text-zinc-100">
                                                            {detail.item?.name}
                                                        </span>
                                                        <span className="mt-0.5 line-clamp-1 text-[10px] leading-tight text-muted-foreground italic">
                                                            {detail.notes ||
                                                                detail.item
                                                                    ?.description}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="inline-flex items-center justify-center rounded bg-muted px-2 py-1 text-xs font-black">
                                                        {detail.quantity}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium">
                                                    {formatNumber(detail.price)}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {detail.discount > 0 ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="h-5 border-dashed border-red-300 bg-red-50 py-0 text-[10px] font-bold text-red-600"
                                                        >
                                                            -
                                                            {formatNumber(
                                                                detail.discount,
                                                            )}
                                                        </Badge>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <span className="font-black text-zinc-900 dark:text-zinc-100">
                                                        {formatNumber(
                                                            detail.total,
                                                        )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Footer Totals & Summary */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {/* Notes/Discription Card */}
                    <Card className="flex h-full flex-col shadow-sm">
                        <CardHeader className="border-b bg-muted/10 py-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-bold">
                                <FileText className="h-4 w-4 opacity-50" />
                                Catatan Internal
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex-1 pt-4">
                            {transaction.notes ? (
                                <p className="border-l-2 border-zinc-200 py-1 pl-4 text-sm leading-relaxed whitespace-pre-line text-muted-foreground italic">
                                    "{transaction.notes}"
                                </p>
                            ) : (
                                <div className="flex h-full flex-col items-center justify-center py-4 text-muted-foreground opacity-30">
                                    <AlertCircle className="mb-1 h-6 w-6" />
                                    <p className="text-xs italic">
                                        Tidak ada catatan
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Financial Summary Card */}
                    <Card className="border-zinc-200 bg-zinc-50/20 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/10">
                        <CardContent className="space-y-3 p-6 tabular-nums">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Subtotal
                                </span>
                                <span className="font-bold">
                                    {formatNumber(transaction.total)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Diskon Invoice (
                                    {transaction.discount_percent || 0}%)
                                </span>
                                <span className="font-bold text-red-600">
                                    -{formatNumber(transaction.discount)}
                                </span>
                            </div>

                            <Separator className="border-dashed" />

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground italic underline decoration-dotted">
                                    Penyesuaian
                                </span>
                                <span
                                    className={`font-bold ${transaction.adjustment < 0 ? 'text-red-500' : 'text-green-500'}`}
                                >
                                    {transaction.adjustment > 0 ? '+' : ''}
                                    {formatNumber(transaction.adjustment)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    PPN / Pajak
                                </span>
                                <span className="font-bold">
                                    {formatNumber(transaction.tax_amount)}
                                </span>
                            </div>

                            <div className="pt-2">
                                <div className="flex items-center justify-between rounded-lg bg-zinc-800 p-4 text-white shadow-lg dark:bg-zinc-700">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] font-black tracking-widest text-zinc-300 uppercase">
                                            Total Akhir
                                        </span>
                                        <span className="text-xs font-medium text-zinc-400 italic">
                                            Jumlah Bersih
                                        </span>
                                    </div>
                                    <span className="text-2xl font-black">
                                        IDR{' '}
                                        {formatNumber(
                                            Math.abs(transaction.grand_total),
                                        )}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <style>{`
                @media print {
                    body { background: white !important; }
                    .print\\:hidden { display: none !important; }
                    .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                    .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
                    .border-zinc-100, .border-zinc-200 { border-color: #e5e7eb !important; }
                    .bg-zinc-800 { background-color: #000 !important; color: white !important; -webkit-print-color-adjust: exact; }
                    .p-6 { padding: 0 !important; }
                    .rounded-lg, .rounded-full { border-radius: 0 !important; }
                    table { border: 1px solid #000 !important; }
                    th, td { border: 0.5px solid #eee !important; }
                    .tabular-nums { font-variant-numeric: tabular-nums; }
                    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                }
            `}</style>
        </AppLayout>
    );
}
