import { Head, Link, useForm } from '@inertiajs/react';
import {
    Printer,
    Trash2,
    ArrowLeft,
    FileText,
    Calendar,
    User as UserIcon,
    Tag,
    Info,
    ChevronDown,
    Package,
    ArrowRight,
    ExternalLink,
    AlertCircle,
    CheckCircle2,
    Eye,
    EyeOff,
    MoreVertical,
    X,
    MousePointer2,
    RefreshCw,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    auth: any;
    can: {
        delete_transaction: boolean;
        edit_transaction: boolean;
    };
}

export default function Show({ transaction, config, auth, can }: Props) {
    const { delete: destroy } = useForm();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Transactions',
            href: transactions.index.url(),
        },
        {
            title: `Invoice #${transaction.invoice_number}`,
            href: transactions.show.url({ transaction: transaction.id }),
        },
    ];

    // Column Visibility States
    const [showImage, setShowImage] = useState(true);
    const [showBarcode, setShowBarcode] = useState(true);
    const [showSku, setShowSku] = useState(false);

    const handleDelete = () => {
        destroy(transactions.destroy.url({ transaction: transaction.id }));
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

    // Helper to determine status badge
    const getStatusBadge = () => {
        const statuses: Record<number, { label: string; color: string }> = {
            1: { label: 'Pending', color: 'bg-yellow-100 text-yellow-800' },
            2: { label: 'Completed', color: 'bg-green-100 text-green-800' },
            3: { label: 'Cancelled', color: 'bg-red-100 text-red-800' },
        };
        const status = statuses[transaction.status] || {
            label: 'Unknown',
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
            <Head title={`Transaction #${transaction.invoice_number}`} />

            <div className="space-y-6 p-6">
                {/* Top Action Bar */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-3">
                        <Link href={transactions.index.url()}>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-9 w-9"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Detail Transaction
                            </h1>
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
                            Print
                        </Button>

                        {can.delete_transaction && (
                            <ConfirmDialog
                                onConfirm={handleDelete}
                                title="Hapus Transaksi"
                                description="Apakah Anda yakin ingin menghapus transaksi ini? Transaksi akan dipindahkan ke daftar hapus dan dampak stok/saldo akan dibatalkan."
                                trigger={
                                    <Button
                                        variant="destructive"
                                        className="gap-2"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Hapus
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </div>

                {/* Primary Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Summary Card */}
                    <Card className="overflow-hidden border-blue-100 shadow-sm dark:border-blue-900">
                        <div className="h-2 w-full bg-blue-600" />
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                Grand Total
                            </CardTitle>
                            <div className="mt-1 flex items-baseline gap-1">
                                <span className="text-3xl font-black text-blue-600 dark:text-blue-400">
                                    IDR
                                </span>
                                <span className="text-4xl font-black tracking-tighter tabular-nums">
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
                                        Date
                                    </span>
                                    <span className="font-semibold">
                                        {formatDate(transaction.date)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1.5 text-muted-foreground">
                                        <Info className="h-3.5 w-3.5" /> Type
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        className="capitalize"
                                    >
                                        {config.type_slug}
                                    </Badge>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="flex items-center gap-1.5 text-muted-foreground">
                                        <Tag className="h-3.5 w-3.5" /> Submit
                                        Source
                                    </span>
                                    {transaction.submit_type === 2 ? (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-amber-500/20 bg-amber-500/10 font-bold text-amber-500"
                                        >
                                            <RefreshCw className="h-3 w-3" />{' '}
                                            cron jubelio
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-blue-500/20 bg-blue-500/10 font-bold text-blue-500"
                                        >
                                            <MousePointer2 className="h-3 w-3" />{' '}
                                            aria submit
                                        </Badge>
                                    )}
                                </div>
                                {transaction.user && (
                                    <div className="flex justify-between text-sm">
                                        <span className="flex items-center gap-1.5 text-muted-foreground">
                                            <UserIcon className="h-3.5 w-3.5" />{' '}
                                            Created By
                                        </span>
                                        <span className="font-medium underline decoration-blue-500/30">
                                            {transaction.user.name}
                                        </span>
                                    </div>
                                )}
                                {transaction.sync_cek && (
                                    <div className="flex justify-between pt-2 text-sm">
                                        <span className="flex items-center gap-1.5 font-bold text-blue-600">
                                            <RefreshCw className="h-3.5 w-3.5" />{' '}
                                            Sinkron Jubelio
                                        </span>
                                        <Link
                                            href={`/jubelio-transaction/${transaction.id}/detail-sync`}
                                        >
                                            <Badge
                                                variant="outline"
                                                className="cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                            >
                                                Kelola Sinkron{' '}
                                                <ExternalLink className="ml-1 h-3 w-3" />
                                            </Badge>
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sender Info */}
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b bg-muted/20 pb-3">
                            <div>
                                <CardTitle className="text-sm font-bold tracking-widest text-muted-foreground uppercase">
                                    {config.sender_label} (From)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Origin of these items
                                </CardDescription>
                            </div>
                            <Package className="h-4 w-4 text-blue-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.sender ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600 dark:bg-blue-900/30">
                                            {transaction.sender.name.charAt(0)}
                                        </div>
                                        <div>
                                            <p className="text-lg leading-tight font-bold">
                                                {transaction.sender.name}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                ID: {transaction.sender.id}
                                            </p>
                                            <SideStatus
                                                submitted={transaction.a_synced}
                                                needsSync={
                                                    transaction.sync_cek ===
                                                        'S' ||
                                                    transaction.sync_cek === 'B'
                                                }
                                                jubelioLocation={
                                                    transaction.jubelio_a
                                                }
                                                isFromJubelio={
                                                    transaction.is_from_jubelio
                                                }
                                                role="sender"
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded bg-muted/30 p-2 text-center">
                                            <p className="mb-0.5 text-[10px] font-bold text-muted-foreground uppercase">
                                                Type
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
                                            className="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10"
                                        >
                                            <p className="mb-0.5 w-full text-center text-[10px] font-bold text-muted-foreground uppercase group-hover:text-blue-500">
                                                View Details
                                            </p>
                                            <ExternalLink className="h-3 w-3 translate-y-0 transition-transform group-hover:-translate-y-0.5 group-hover:text-blue-500" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="mb-2 h-8 w-8 opacity-20" />
                                    <p className="text-sm font-medium italic">
                                        No sender info
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
                                    {config.receiver_label} (To)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Destination of these items
                                </CardDescription>
                            </div>
                            <ArrowRight className="h-4 w-4 text-green-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.receiver ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 font-bold text-green-600 dark:bg-green-900/30">
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
                                            <SideStatus
                                                submitted={transaction.b_synced}
                                                needsSync={
                                                    transaction.sync_cek ===
                                                        'R' ||
                                                    transaction.sync_cek === 'B'
                                                }
                                                jubelioLocation={
                                                    transaction.jubelio_b
                                                }
                                                isFromJubelio={
                                                    transaction.is_from_jubelio
                                                }
                                                role="receiver"
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded bg-muted/30 p-2 text-center">
                                            <p className="mb-0.5 text-[10px] font-bold text-muted-foreground uppercase">
                                                Type
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
                                            className="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-green-400 hover:bg-green-50 dark:hover:bg-green-900/10"
                                        >
                                            <p className="mb-0.5 w-full text-center text-[10px] font-bold text-muted-foreground uppercase group-hover:text-green-500">
                                                View Details
                                            </p>
                                            <ExternalLink className="h-3 w-3 translate-y-0 transition-transform group-hover:-translate-y-0.5 group-hover:text-green-500" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="mb-2 h-8 w-8 opacity-20" />
                                    <p className="text-sm font-medium italic">
                                        No receiver info
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
                                <Package className="h-5 w-5 text-blue-600" />
                                Items List
                            </CardTitle>
                            <CardDescription>
                                Requested items in this transaction (
                                {transaction.total_items})
                            </CardDescription>
                        </div>

                        {/* Column Controls */}
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-full border border-dashed bg-muted/40 px-4 py-2 print:hidden">
                            <span className="mr-[-8px] text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                View:
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
                                    Image
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
                        <div className="flex flex-col print:block">
                            {/* Header - Hidden on Mobile */}
                            <div className="hidden grid-cols-12 gap-4 border-y bg-muted/50 p-3 text-[10px] font-bold tracking-wider text-muted-foreground uppercase sm:grid print:grid">
                                {showImage && (
                                    <div className="col-span-1 text-center font-black">
                                        Img
                                    </div>
                                )}
                                {showBarcode && (
                                    <div className="col-span-1 font-black">
                                        Barcode
                                    </div>
                                )}
                                {showSku && (
                                    <div className="col-span-1 font-black">
                                        SKU
                                    </div>
                                )}
                                <div
                                    className={cn(
                                        'font-black',
                                        showImage && showBarcode && showSku
                                            ? 'col-span-3'
                                            : 'col-span-6', // Simplistic adjustment
                                    )}
                                >
                                    Item Name
                                </div>
                                <div className="col-span-1 text-center font-black">
                                    Qty
                                </div>
                                <div className="col-span-2 text-right font-black">
                                    Price
                                </div>
                                <div className="col-span-1 text-center font-black">
                                    Disc(%)
                                </div>
                                <div className="col-span-2 text-right font-black">
                                    Subtotal
                                </div>
                            </div>

                            {/* Rows - Cards on Mobile, Grid on Desktop */}
                            <div className="divide-y print:block print:divide-y">
                                {transaction.details.map(
                                    (detail: any, idx: number) => (
                                        <div
                                            key={idx}
                                            className="group flex flex-col gap-4 p-4 transition-colors hover:bg-muted/20 sm:grid sm:grid-cols-12 sm:items-center sm:gap-4 sm:p-3 sm:text-sm print:grid print:grid-cols-12 print:items-center print:gap-4 print:p-3"
                                        >
                                            {/* Mobile: Image & Name Header */}
                                            <div className="flex items-start gap-3 sm:hidden">
                                                {showImage && (
                                                    <div className="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-white shadow-sm">
                                                        {detail.item
                                                            ?.image_url ? (
                                                            <img
                                                                src={
                                                                    detail.item
                                                                        .image_url
                                                                }
                                                                alt={
                                                                    detail.item
                                                                        ?.name
                                                                }
                                                                className="h-full w-full object-cover"
                                                            />
                                                        ) : (
                                                            <Package className="h-7 w-7 text-muted-foreground/30" />
                                                        )}
                                                    </div>
                                                )}
                                                <div className="flex flex-col">
                                                    <div className="font-bold text-gray-900 dark:text-gray-100">
                                                        {detail.item?.name}
                                                    </div>
                                                    <div className="flex flex-wrap gap-2 pt-1">
                                                        {showBarcode && (
                                                            <span className="font-mono text-[10px] font-medium text-blue-600 dark:text-blue-400">
                                                                #{detail.item?.id}
                                                            </span>
                                                        )}
                                                        {showSku &&
                                                            detail.item
                                                                ?.code && (
                                                                <span className="font-mono text-[10px] text-muted-foreground italic">
                                                                    SKU:{' '}
                                                                    {
                                                                        detail
                                                                            .item
                                                                            ?.code
                                                                    }
                                                                </span>
                                                            )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Desktop: Image */}
                                            {showImage && (
                                                <div className="hidden col-span-1 text-center sm:block print:block">
                                                    <div className="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                                                        {detail.item
                                                            ?.image_url ? (
                                                            <img
                                                                src={
                                                                    detail.item
                                                                        .image_url
                                                                }
                                                                alt={
                                                                    detail.item
                                                                        ?.name
                                                                }
                                                                className="h-full w-full object-cover"
                                                            />
                                                        ) : (
                                                            <Package className="h-6 w-6 text-muted-foreground/30" />
                                                        )}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Desktop: Barcode */}
                                            {showBarcode && (
                                                <div className="hidden col-span-1 font-mono text-xs sm:block print:block">
                                                    <Link
                                                        href={items.show.url({
                                                            item: detail.item
                                                                ?.id,
                                                        })}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        {detail.item?.id}
                                                    </Link>
                                                </div>
                                            )}

                                            {/* Desktop: SKU */}
                                            {showSku && (
                                                <div className="hidden col-span-1 font-mono text-xs text-muted-foreground italic sm:block print:block">
                                                    {detail.item?.code || '-'}
                                                </div>
                                            )}

                                            {/* Desktop: Name */}
                                            <div
                                                className={cn(
                                                    'hidden sm:flex flex-col print:flex',
                                                    showImage &&
                                                        showBarcode &&
                                                        showSku
                                                        ? 'col-span-3'
                                                        : 'col-span-6',
                                                )}
                                            >
                                                <span className="font-bold text-gray-900 dark:text-gray-100">
                                                    {detail.item?.name}
                                                </span>
                                                <span className="mt-0.5 line-clamp-1 text-[10px] leading-tight text-muted-foreground italic">
                                                    {detail.notes ||
                                                        detail.item
                                                            ?.description}
                                                </span>
                                            </div>

                                            {/* Mobile fields / Desktop cells */}
                                            <div className="flex items-center justify-between sm:col-span-1 sm:block sm:text-center print:block print:text-center">
                                                <span className="text-[10px] font-bold text-muted-foreground uppercase sm:hidden print:hidden">
                                                    Qty
                                                </span>
                                                <span className="inline-flex items-center justify-center rounded bg-muted px-2 py-1 text-xs font-black sm:bg-transparent sm:p-0">
                                                    {detail.quantity}
                                                </span>
                                            </div>

                                            <div className="flex items-center justify-between sm:col-span-2 sm:block sm:text-right print:block print:text-right">
                                                <span className="text-[10px] font-bold text-muted-foreground uppercase sm:hidden print:hidden">
                                                    Price
                                                </span>
                                                <span className="font-medium">
                                                    {formatNumber(detail.price)}
                                                </span>
                                            </div>

                                            <div className="flex items-center justify-between sm:col-span-1 sm:block sm:text-center print:block print:text-center">
                                                <span className="text-[10px] font-bold text-muted-foreground uppercase sm:hidden print:hidden">
                                                    Disc
                                                </span>
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
                                                    <span className="text-zinc-400">
                                                        -
                                                    </span>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between border-t pt-3 sm:col-span-2 sm:block sm:border-0 sm:pt-0 sm:text-right print:block print:text-right">
                                                <span className="text-[10px] font-bold text-zinc-900 uppercase sm:hidden dark:text-zinc-100 print:hidden">
                                                    Subtotal
                                                </span>
                                                <span className="text-lg font-black text-blue-700 sm:text-sm dark:text-blue-300">
                                                    {formatNumber(detail.total)}
                                                </span>
                                            </div>

                                            {/* Mobile: Note (if exists) */}
                                            {detail.notes && (
                                                <div className="mt-1 rounded bg-muted/30 p-2 text-xs text-muted-foreground italic sm:hidden">
                                                    📝 {detail.notes}
                                                </div>
                                            )}
                                        </div>
                                    ),
                                )}
                            </div>
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
                                Internal Notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex-1 pt-4">
                            {transaction.notes ? (
                                <p className="border-l-2 border-blue-200 py-1 pl-4 text-sm leading-relaxed whitespace-pre-line text-muted-foreground italic">
                                    "{transaction.notes}"
                                </p>
                            ) : (
                                <div className="flex h-full flex-col items-center justify-center py-4 text-muted-foreground opacity-30">
                                    <AlertCircle className="mb-1 h-6 w-6" />
                                    <p className="text-xs italic">
                                        No notes added
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Financial Summary Card */}
                    <Card className="border-blue-200 bg-blue-50/20 shadow-sm dark:border-blue-800 dark:bg-blue-900/10">
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
                                    Invoice Discount (
                                    {transaction.discount_percent || 0}%)
                                </span>
                                <span className="font-bold text-red-600">
                                    -{formatNumber(transaction.discount)}
                                </span>
                            </div>

                            <Separator className="border-dashed" />

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground italic underline decoration-dotted">
                                    Adjustment
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
                                    PPN / Tax
                                </span>
                                <span className="font-bold">
                                    {formatNumber(transaction.tax_amount)}
                                </span>
                            </div>

                            <div className="pt-2">
                                <div className="flex items-center justify-between rounded-lg bg-blue-600 p-4 text-white shadow-lg shadow-blue-500/20">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] font-black tracking-widest text-blue-100/70 uppercase">
                                            Grand Total
                                        </span>
                                        <span className="text-xs font-medium text-blue-100 italic">
                                            Net Amount Payable
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

                {/* Print Footer Elements (Signature) */}
                <div className="mt-12 hidden grid-cols-3 gap-8 text-center text-xs print:grid">
                    <div className="space-y-16">
                        <p className="font-bold tracking-wider uppercase">
                            Authorized By
                        </p>
                        <div className="mx-auto w-2/3 border-b border-black" />
                        <p>
                            ( {transaction.user?.name || '________________'} )
                        </p>
                    </div>
                    <div className="space-y-16">
                        <p className="font-bold tracking-wider uppercase">
                            Origin Sign
                        </p>
                        <div className="mx-auto w-2/3 border-b border-black" />
                        <p>
                            ( {transaction.sender?.name || '________________'} )
                        </p>
                    </div>
                    <div className="space-y-16">
                        <p className="font-bold tracking-wider uppercase">
                            Received By
                        </p>
                        <div className="mx-auto w-2/3 border-b border-black" />
                        <p>
                            ( {transaction.receiver?.name || '________________'}{' '}
                            )
                        </p>
                    </div>
                </div>
            </div>

            <style>{`
                @media print {
                    body { background: white !important; }
                    .print\\:hidden { display: none !important; }
                    .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                    .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
                    .border-blue-100, .border-blue-200 { border-color: #e5e7eb !important; }
                    .bg-blue-600 { background-color: #000 !important; color: white !important; -webkit-print-color-adjust: exact; }
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

function SideStatus({
    submitted,
    needsSync,
    jubelioLocation,
    isFromJubelio,
    role,
}: {
    submitted: boolean;
    needsSync: boolean;
    jubelioLocation?: string;
    isFromJubelio?: boolean;
    role: 'sender' | 'receiver';
}) {
    if (!jubelioLocation) return null;

    if (submitted) {
        return (
            <div className="mt-1 flex flex-col gap-1">
                <Badge className="w-fit gap-1 border-green-500/20 bg-green-500/10 text-[9px] font-bold tracking-tighter text-green-600 uppercase hover:bg-green-500/20">
                    <CheckCircle2 className="h-2.5 w-2.5" />{' '}
                    {isFromJubelio
                        ? 'Tersinkron (Sistem)'
                        : 'Tersinkron ke Jubelio'}
                </Badge>
                <p className="ml-1 text-[10px] text-muted-foreground italic">
                    Lok: {jubelioLocation}
                </p>
            </div>
        );
    }

    if (needsSync) {
        return (
            <div className="mt-1 flex flex-col gap-1">
                <Badge
                    variant="outline"
                    className={cn(
                        'w-fit animate-pulse gap-1 text-[9px] font-bold tracking-tighter uppercase',
                        role === 'sender'
                            ? 'border-zinc-500/20 bg-zinc-500/10 text-zinc-500'
                            : 'border-blue-500/20 bg-blue-500/10 text-blue-500',
                    )}
                >
                    <div
                        className={cn(
                            'h-1.5 w-1.5 rounded-full',
                            role === 'sender' ? 'bg-zinc-500' : 'bg-blue-500',
                        )}
                    />
                    Menunggu Sinkron
                </Badge>
                <p className="ml-1 text-[10px] font-medium text-muted-foreground italic">
                    Target: {jubelioLocation}
                </p>
            </div>
        );
    }

    return (
        <div className="mt-1 flex flex-col gap-1 opacity-50">
            <Badge
                variant="secondary"
                className="w-fit gap-1 text-[9px] font-bold tracking-tighter uppercase"
            >
                <Info className="h-2.5 w-2.5" /> Mapping Aktif
            </Badge>
            <p className="ml-1 text-[10px] text-muted-foreground italic">
                Terhubung ke: {jubelioLocation}
            </p>
        </div>
    );
}
