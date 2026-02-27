import React, { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
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
    RefreshCw
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { BreadcrumbItem } from '@/types';

import transactions from '@/routes/transactions';
import items from '@/routes/items';
import addrbook from '@/routes/addrbook';

interface Props {
    transaction: any;
    config: {
        sender_label: string;
        receiver_label: string;
        type_slug: string;
    };
    auth: any;
}

export default function Show({ transaction, config, auth }: Props) {
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
        if (confirm('Are you absolutely sure you want to delete this transaction? This action cannot be undone.')) {
            destroy(transactions.destroy.url({ transaction: transaction.id }));
        }
    };

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat('id-ID').format(num);
    };

    const formatDate = (date: string) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    };

    // Helper to determine status badge
    const getStatusBadge = () => {
        const statuses: Record<number, { label: string, color: string }> = {
            1: { label: 'Pending', color: 'bg-yellow-100 text-yellow-800' },
            2: { label: 'Completed', color: 'bg-green-100 text-green-800' },
            3: { label: 'Cancelled', color: 'bg-red-100 text-red-800' },
        };
        const status = statuses[transaction.status] || { label: 'Unknown', color: 'bg-gray-100 text-gray-800' };
        return <Badge className={`${status.color} border-none font-semibold px-3`}>{status.label}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transaction #${transaction.invoice_number}`} />

            <div className="p-6 space-y-6">
                {/* Top Action Bar */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={transactions.index.url()}>
                            <Button variant="outline" size="icon" className="h-9 w-9">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Detail Transaction</h1>
                            <p className="text-muted-foreground text-sm flex items-center gap-2">
                                <FileText className="h-3.5 w-3.5 text-blue-500" />
                                Invoice #{transaction.invoice_number}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={() => window.print()} className="gap-2">
                            <Printer className="h-4 w-4" />
                            Print
                        </Button>

                        <Button variant="destructive" onClick={handleDelete} className="gap-2">
                            <Trash2 className="h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Primary Info Cards */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Summary Card */}
                    <Card className="shadow-sm overflow-hidden border-blue-100 dark:border-blue-900">
                        <div className="h-2 bg-blue-600 w-full" />
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Grand Total</CardTitle>
                            <div className="flex items-baseline gap-1 mt-1">
                                <span className="text-3xl font-black text-blue-600 dark:text-blue-400">IDR</span>
                                <span className="text-4xl font-black tracking-tighter tabular-nums">{formatNumber(Math.abs(transaction.grand_total))}</span>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="flex justify-between items-center bg-muted/50 p-3 rounded-lg border border-dashed">
                                <div className="text-sm font-medium">Status</div>
                                {getStatusBadge()}
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground flex items-center gap-1.5"><Calendar className="h-3.5 w-3.5" /> Date</span>
                                    <span className="font-semibold">{formatDate(transaction.date)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground flex items-center gap-1.5"><Info className="h-3.5 w-3.5" /> Type</span>
                                    <Badge variant="secondary" className="capitalize">{config.type_slug}</Badge>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground flex items-center gap-1.5"><Tag className="h-3.5 w-3.5" /> Submit Source</span>
                                    {transaction.submit_type === 2 ? (
                                        <Badge variant="outline" className="bg-amber-500/10 text-amber-500 border-amber-500/20 gap-1 font-bold">
                                            <RefreshCw className="h-3 w-3" /> cron jubelio
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="bg-blue-500/10 text-blue-500 border-blue-500/20 gap-1 font-bold">
                                            <MousePointer2 className="h-3 w-3" /> aria submit
                                        </Badge>
                                    )}
                                </div>
                                {transaction.user && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground flex items-center gap-1.5"><UserIcon className="h-3.5 w-3.5" /> Created By</span>
                                        <span className="font-medium underline decoration-blue-500/30">{transaction.user.name}</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sender Info */}
                    <Card className="shadow-sm">
                        <CardHeader className="pb-3 border-b flex flex-row items-center justify-between space-y-0 bg-muted/20">
                            <div>
                                <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">{config.sender_label} (From)</CardTitle>
                                <CardDescription className="text-xs">Origin of these items</CardDescription>
                            </div>
                            <Package className="h-4 w-4 text-blue-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.sender ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 font-bold shrink-0">
                                            {transaction.sender.name.charAt(0)}
                                        </div>
                                        <div>
                                            <p className="font-bold text-lg leading-tight">{transaction.sender.name}</p>
                                            <p className="text-xs text-muted-foreground font-mono mt-1">ID: {transaction.sender.code}</p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="bg-muted/30 p-2 rounded text-center">
                                            <p className="text-[10px] uppercase font-bold text-muted-foreground mb-0.5">Type</p>
                                            <p className="text-xs font-semibold">{transaction.sender.type_name}</p>
                                        </div>
                                        <Link
                                            href={addrbook.type.show.url({ type: transaction.sender.type_slug, addrbook: transaction.sender.id })}
                                            className="group p-2 rounded border border-dashed hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 flex flex-col items-center justify-center transition-all"
                                        >
                                            <p className="text-[10px] uppercase font-bold text-muted-foreground group-hover:text-blue-500 mb-0.5 text-center w-full">View Details</p>
                                            <ExternalLink className="h-3 w-3 group-hover:text-blue-500 translate-y-0 group-hover:-translate-y-0.5 transition-transform" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="h-8 w-8 opacity-20 mb-2" />
                                    <p className="text-sm font-medium italic">No sender info</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Receiver Info */}
                    <Card className="shadow-sm">
                        <CardHeader className="pb-3 border-b flex flex-row items-center justify-between space-y-0 bg-muted/20">
                            <div>
                                <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">{config.receiver_label} (To)</CardTitle>
                                <CardDescription className="text-xs">Destination of these items</CardDescription>
                            </div>
                            <ArrowRight className="h-4 w-4 text-green-500 opacity-50" />
                        </CardHeader>
                        <CardContent className="pt-6">
                            {transaction.receiver ? (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <div className="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 font-bold shrink-0">
                                            {transaction.receiver.name.charAt(0)}
                                        </div>
                                        <div>
                                            <p className="font-bold text-lg leading-tight">{transaction.receiver.name}</p>
                                            <p className="text-xs text-muted-foreground font-mono mt-1">ID: {transaction.receiver.code}</p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="bg-muted/30 p-2 rounded text-center">
                                            <p className="text-[10px] uppercase font-bold text-muted-foreground mb-0.5">Type</p>
                                            <p className="text-xs font-semibold">{transaction.receiver.type_name}</p>
                                        </div>
                                        <Link
                                            href={addrbook.type.show.url({ type: transaction.receiver.type_slug, addrbook: transaction.receiver.id })}
                                            className="group p-2 rounded border border-dashed hover:border-green-400 hover:bg-green-50 dark:hover:bg-green-900/10 flex flex-col items-center justify-center transition-all"
                                        >
                                            <p className="text-[10px] uppercase font-bold text-muted-foreground group-hover:text-green-500 mb-0.5 text-center w-full">View Details</p>
                                            <ExternalLink className="h-3 w-3 group-hover:text-green-500 translate-y-0 group-hover:-translate-y-0.5 transition-transform" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                                    <AlertCircle className="h-8 w-8 opacity-20 mb-2" />
                                    <p className="text-sm font-medium italic">No receiver info</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Items Section */}
                <Card className="shadow-md border-none print:shadow-none">
                    <CardHeader className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4">
                        <div>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Package className="h-5 w-5 text-blue-600" />
                                Items List
                            </CardTitle>
                            <CardDescription>Requested items in this transaction ({transaction.total_items})</CardDescription>
                        </div>

                        {/* Column Controls */}
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-2 bg-muted/40 rounded-full border border-dashed print:hidden">
                            <span className="text-[10px] uppercase font-black text-muted-foreground tracking-widest mr-[-8px]">View:</span>
                            <div className="flex items-center space-x-2">
                                <Checkbox id="img" checked={showImage} onCheckedChange={() => setShowImage(!showImage)} />
                                <label htmlFor="img" className="text-xs font-bold leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">Image</label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox id="bc" checked={showBarcode} onCheckedChange={() => setShowBarcode(!showBarcode)} />
                                <label htmlFor="bc" className="text-xs font-bold leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">Barcode</label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox id="sku" checked={showSku} onCheckedChange={() => setShowSku(!showSku)} />
                                <label htmlFor="sku" className="text-xs font-bold leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">SKU</label>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left border-collapse">
                                <thead className="text-xs text-muted-foreground uppercase bg-muted/50 border-y tabular-nums">
                                    <tr>
                                        {showImage && <th className="px-4 py-3 font-black text-center w-20">Img</th>}
                                        {showBarcode && <th className="px-4 py-3 font-black">Barcode</th>}
                                        {showSku && <th className="px-4 py-3 font-black">SKU</th>}
                                        <th className="px-4 py-3 font-black">Item Name</th>
                                        <th className="px-4 py-3 font-black text-center">Qty</th>
                                        <th className="px-4 py-3 font-black text-right">Price</th>
                                        <th className="px-4 py-3 font-black text-center">Disc(%)</th>
                                        <th className="px-4 py-3 font-black text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transaction.details.map((detail: any, idx: number) => (
                                        <tr key={idx} className="border-b transition-colors hover:bg-muted/20 group tabular-nums">
                                            {showImage && (
                                                <td className="px-4 py-3 text-center">
                                                    <div className="relative h-12 w-12 rounded overflow-hidden border shadow-sm mx-auto bg-white group-hover:scale-105 transition-transform duration-200 flex items-center justify-center">
                                                        {detail.item?.image_url ? (
                                                            <img src={detail.item.image_url} alt={detail.item?.name} className="h-full w-full object-cover" />
                                                        ) : (
                                                            <Package className="h-6 w-6 text-muted-foreground/30" />
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                            {showBarcode && (
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    <Link href={items.show.url({ item: detail.item?.id })} className="text-blue-600 hover:underline">
                                                        {detail.item?.id}
                                                    </Link>
                                                </td>
                                            )}
                                            {showSku && (
                                                <td className="px-4 py-3 font-mono text-xs text-muted-foreground italic">
                                                    {detail.item?.code || '-'}
                                                </td>
                                            )}
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="font-bold text-gray-900 dark:text-gray-100">{detail.item?.name}</span>
                                                    <span className="text-[10px] text-muted-foreground leading-tight mt-0.5 line-clamp-1 italic">{detail.notes || detail.item?.description}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="inline-flex items-center justify-center px-2 py-1 rounded bg-muted font-black text-xs">
                                                    {detail.quantity}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {formatNumber(detail.price)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {detail.discount > 0 ? (
                                                    <Badge variant="outline" className="text-[10px] font-bold py-0 h-5 border-dashed border-red-300 bg-red-50 text-red-600">
                                                        -{formatNumber(detail.discount)}
                                                    </Badge>
                                                ) : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <span className="font-black text-blue-700 dark:text-blue-300">
                                                    {formatNumber(detail.total)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Footer Totals & Summary */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Notes/Discription Card */}
                    <Card className="shadow-sm h-full flex flex-col">
                        <CardHeader className="py-4 border-b bg-muted/10">
                            <CardTitle className="text-sm font-bold flex items-center gap-2">
                                <FileText className="h-4 w-4 opacity-50" />
                                Internal Notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4 flex-1">
                            {transaction.notes ? (
                                <p className="text-sm text-muted-foreground whitespace-pre-line leading-relaxed italic border-l-2 border-blue-200 pl-4 py-1">
                                    "{transaction.notes}"
                                </p>
                            ) : (
                                <div className="h-full flex flex-col items-center justify-center text-muted-foreground opacity-30 py-4">
                                    <AlertCircle className="h-6 w-6 mb-1" />
                                    <p className="text-xs italic">No notes added</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Financial Summary Card */}
                    <Card className="shadow-sm border-blue-200 dark:border-blue-800 bg-blue-50/20 dark:bg-blue-900/10">
                        <CardContent className="p-6 space-y-3 tabular-nums">
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-muted-foreground">Subtotal</span>
                                <span className="font-bold">{formatNumber(transaction.total)}</span>
                            </div>

                            <div className="flex justify-between items-center text-sm">
                                <span className="text-muted-foreground">Invoice Discount ({transaction.discount_percent || 0}%)</span>
                                <span className="font-bold text-red-600">-{formatNumber(transaction.discount)}</span>
                            </div>

                            <Separator className="border-dashed" />

                            <div className="flex justify-between items-center text-sm">
                                <span className="text-muted-foreground italic underline decoration-dotted">Adjustment</span>
                                <span className={`font-bold ${transaction.adjustment < 0 ? 'text-red-500' : 'text-green-500'}`}>
                                    {transaction.adjustment > 0 ? '+' : ''}{formatNumber(transaction.adjustment)}
                                </span>
                            </div>

                            <div className="flex justify-between items-center text-sm">
                                <span className="text-muted-foreground">PPN / Tax</span>
                                <span className="font-bold">{formatNumber(transaction.tax_amount)}</span>
                            </div>

                            <div className="pt-2">
                                <div className="bg-blue-600 rounded-lg p-4 flex justify-between items-center text-white shadow-lg shadow-blue-500/20">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] uppercase font-black tracking-widest text-blue-100/70">Grand Total</span>
                                        <span className="text-xs font-medium text-blue-100 italic">Net Amount Payable</span>
                                    </div>
                                    <span className="text-2xl font-black">IDR {formatNumber(Math.abs(transaction.grand_total))}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Print Footer Elements (Signature) */}
                <div className="hidden print:grid grid-cols-3 gap-8 mt-12 text-center text-xs">
                    <div className="space-y-16">
                        <p className="font-bold uppercase tracking-wider">Authorized By</p>
                        <div className="border-b border-black w-2/3 mx-auto" />
                        <p>( {transaction.user?.name || '________________'} )</p>
                    </div>
                    <div className="space-y-16">
                        <p className="font-bold uppercase tracking-wider">Origin Sign</p>
                        <div className="border-b border-black w-2/3 mx-auto" />
                        <p>( {transaction.sender?.name || '________________'} )</p>
                    </div>
                    <div className="space-y-16">
                        <p className="font-bold uppercase tracking-wider">Received By</p>
                        <div className="border-b border-black w-2/3 mx-auto" />
                        <p>( {transaction.receiver?.name || '________________'} )</p>
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
