import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import {
    ArrowLeft,
    Box,
    CheckCircle2,
    AlertTriangle,
    Send,
    X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface TransactionDetail {
    id: number;
    item_id: number;
    quantity: number;
    total: number;
    item: {
        id: number;
        code: string;
        name: string;
        jubelio_item_id: number | null;
        image_url?: string;
    };
}

interface Transaction {
    id: number;
    date: string;
    invoice_number: string;
    type: number;
    total: number;
    total_items: number;
    description: string;
    a_submit_by: number | null;
    b_submit_by: number | null;
    a_reference_id: string | null;
    b_reference_id: string | null;
    item_with_jubelio_count: number;
    sender?: { id: number; name: string };
    receiver?: { id: number; name: string };
    details: TransactionDetail[];
    submit_by_a?: { username: string };
    submit_by_b?: { username: string };
}

interface Props {
    data: Transaction;
    can_sync: boolean;
    JubelioA: string | null;
    JubelioB: string | null;
    adJustTypeA: number;
    adJustTypeB: number;
    whA: number;
    whB: number;
    whAName: string;
    whBName: string;
}

export default function DetailSync({
    data,
    can_sync,
    JubelioA,
    JubelioB,
    adJustTypeA,
    adJustTypeB,
    whA,
    whB,
    whAName,
    whBName,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Transaction Sync',
            href: '/jubelio-transaction/sync',
        },
        {
            title: `Sync Detail #${data.invoice_number}`,
            href: `/jubelio-transaction/${data.id}/detail-sync`,
        },
    ];

    const handleAdjust = (side: number, whType: number, adjustType: number) => {
        if (
            !confirm(
                `Are you sure you want to adjust stock for ${side === 1 ? whAName : whBName}?`,
            )
        )
            return;

        router.post(`/jubelio-transaction/${data.id}/adjust-stok`, {
            side,
            whType,
            adjustType,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Sync Detail #${data.invoice_number}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/jubelio-transaction/sync">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold">
                            Transaction Detail #{data.id}
                        </h1>
                    </div>
                </div>

                {!can_sync && (
                    <div className="flex items-center gap-3 rounded-xl border border-blue-500/20 bg-blue-500/5 p-6 text-blue-600 shadow-sm">
                        <CheckCircle2 className="h-6 w-6 shrink-0" />
                        <div>
                            <h3 className="font-bold">Transaksi Otomatis</h3>
                            <p className="text-sm opacity-80">
                                Transaksi ini dikirimkan secara otomatis oleh
                                sistem (Cron/Jubelio). Sinkronisasi manual tidak
                                diperlukan dan tidak dapat dilakukan untuk data
                                ini.
                            </p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-1">
                        <div className="rounded-xl border border-sidebar-border bg-sidebar p-6 shadow-sm">
                            <div className="mb-6 flex items-start justify-between">
                                <div>
                                    <p className="mb-1 text-xs font-bold uppercase opacity-40">
                                        Invoice
                                    </p>
                                    <p className="text-lg font-bold">
                                        {data.invoice_number}
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="mb-1 text-xs font-bold uppercase opacity-40">
                                        Date
                                    </p>
                                    <p className="text-sm font-bold">
                                        {new Date(data.date).toLocaleDateString(
                                            'id-ID',
                                            {
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                            },
                                        )}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="mb-1 text-xs font-bold uppercase opacity-40">
                                        Total
                                    </p>
                                    <p className="text-lg font-bold text-blue-500">
                                        Rp {Number(data.total).toLocaleString()}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-4 border-t border-sidebar-border/50 pt-4">
                                <div>
                                    <p className="mb-1 text-[10px] font-bold uppercase opacity-40">
                                        From
                                    </p>
                                    <p className="text-sm font-medium">
                                        {data.sender?.name || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="mb-1 text-[10px] font-bold uppercase opacity-40">
                                        To
                                    </p>
                                    <p className="text-sm font-medium">
                                        {data.receiver?.name || '-'}
                                    </p>
                                </div>
                                {data.description && (
                                    <div>
                                        <p className="mb-1 text-[10px] font-bold uppercase opacity-40">
                                            Note
                                        </p>
                                        <p className="text-xs italic opacity-70">
                                            {data.description}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {can_sync && (
                            <div className="space-y-4">
                                <SyncCard
                                    title="Sender (Side A)"
                                    whName={whAName || data.sender?.name || '-'}
                                    jubName={JubelioA}
                                    type={adJustTypeA}
                                    qty={data.total_items}
                                    submittedBy={data.submit_by_a?.username}
                                    referenceId={data.a_reference_id}
                                    onSync={() =>
                                        handleAdjust(1, whA, adJustTypeA)
                                    }
                                    needsSync={adJustTypeA > 0}
                                    disabled={data.item_with_jubelio_count > 0}
                                    role="sender"
                                />
                                <SyncCard
                                    title="Receiver (Side B)"
                                    whName={
                                        whBName || data.receiver?.name || '-'
                                    }
                                    jubName={JubelioB}
                                    type={adJustTypeB}
                                    qty={data.total_items}
                                    submittedBy={data.submit_by_b?.username}
                                    referenceId={data.b_reference_id}
                                    onSync={() =>
                                        handleAdjust(2, whB, adJustTypeB)
                                    }
                                    needsSync={adJustTypeB > 0}
                                    disabled={data.item_with_jubelio_count > 0}
                                    role="receiver"
                                />
                            </div>
                        )}
                    </div>

                    <div className="md:col-span-2">
                        {data.item_with_jubelio_count > 0 && (
                            <div className="mb-6 flex gap-3 rounded-xl border border-red-500/30 bg-red-500/5 p-6 text-red-500 shadow-sm">
                                <AlertTriangle className="h-5 w-5 shrink-0" />
                                <div>
                                    <h3 className="mb-1 font-bold">
                                        Mapping Item Hilang
                                    </h3>
                                    <p className="text-sm opacity-80">
                                        Ada {data.item_with_jubelio_count} item
                                        dalam transaksi ini yang belum terhubung
                                        ke Jubelio. Anda harus menghubungkannya
                                        di menu Item sebelum melakukan sinkron.
                                    </p>
                                </div>
                            </div>
                        )}
                        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-sidebar-accent/50 text-[10px] font-semibold text-sidebar-foreground uppercase">
                                    <tr>
                                        <th className="px-6 py-4">Item</th>
                                        <th className="px-6 py-4">Code</th>
                                        <th className="px-6 py-4 text-center">
                                            Qty
                                        </th>
                                        <th className="px-6 py-4 text-right">
                                            Jubelio ID
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border">
                                    {data.details.map((detail) => (
                                        <tr
                                            key={detail.id}
                                            className="transition-colors hover:bg-sidebar-accent/30"
                                        >
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded bg-sidebar-accent">
                                                        <Box className="h-5 w-5 opacity-20" />
                                                    </div>
                                                    <span className="text-xs font-medium">
                                                        {detail.item.name}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 font-mono text-[11px] opacity-70">
                                                {detail.item.code}
                                            </td>
                                            <td className="px-6 py-4 text-center font-bold">
                                                {detail.quantity}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {detail.item.jubelio_item_id ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-green-500/20 bg-green-500/10 font-mono text-green-500"
                                                    >
                                                        {
                                                            detail.item
                                                                .jubelio_item_id
                                                        }
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-red-500/20 bg-red-500/10 text-red-500"
                                                    >
                                                        Missing
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface SyncCardProps {
    title: string;
    whName: string;
    jubName: string | null;
    type: number;
    qty: number;
    submittedBy?: string;
    referenceId?: string | null;
    onSync: () => void;
    needsSync: boolean;
    disabled?: boolean;
    role: 'sender' | 'receiver';
}

function SyncCard({
    title,
    whName,
    jubName,
    type,
    qty,
    submittedBy,
    referenceId,
    onSync,
    needsSync,
    disabled,
    role,
}: SyncCardProps) {
    const isSubmitted = !!submittedBy;
    const isDeduct = type === 2;

    if (!needsSync) {
        return (
            <div className="rounded-xl border border-sidebar-border/50 bg-sidebar-accent/5 p-6 opacity-50 shadow-sm">
                <h3 className="mb-4 flex items-center gap-2 text-xs font-bold uppercase opacity-40">
                    <CheckCircle2 className="h-4 w-4" /> {title}
                </h3>
                <div className="flex items-center justify-between">
                    <span className="text-xs font-medium">{whName}</span>
                    <Badge
                        variant="secondary"
                        className="text-[9px] font-bold uppercase"
                    >
                        System Completed
                    </Badge>
                </div>
            </div>
        );
    }

    const theme = isSubmitted ? 'green' : role === 'sender' ? 'zinc' : 'blue';

    return (
        <div
            className={cn(
                'rounded-xl border p-6 shadow-sm transition-all',
                theme === 'green' && 'border-green-500/30 bg-green-500/5',
                theme === 'blue' && 'border-blue-500/30 bg-blue-500/5',
                theme === 'zinc' && 'border-zinc-500/30 bg-zinc-500/5',
            )}
        >
            <div className="mb-4 flex items-center justify-between">
                <h3
                    className={cn(
                        'flex items-center gap-2 font-bold',
                        theme === 'green' && 'text-green-500',
                        theme === 'blue' && 'text-blue-500',
                        theme === 'zinc' && 'text-zinc-500',
                    )}
                >
                    {isSubmitted ? (
                        <CheckCircle2 className="h-5 w-5" />
                    ) : (
                        <Send className="h-5 w-5" />
                    )}
                    {title}
                </h3>
                {isSubmitted && (
                    <Badge
                        variant="outline"
                        className="border-green-500/30 text-[9px] text-green-500 uppercase"
                    >
                        Synced
                    </Badge>
                )}
            </div>

            <div className="mb-6 space-y-3">
                <div className="flex justify-between text-xs">
                    <span className="text-[9px] font-bold uppercase opacity-50">
                        Internal WH
                    </span>
                    <span className="font-medium">{whName}</span>
                </div>
                <div className="flex justify-between text-xs">
                    <span className="text-[9px] font-bold uppercase opacity-50">
                        Jubelio WH
                    </span>
                    <span className="ml-4 text-right font-medium">
                        {jubName || 'Not Linked'}
                    </span>
                </div>
                <div className="flex justify-between border-t border-sidebar-border/20 pt-2 text-xs">
                    <span className="text-[9px] font-bold uppercase opacity-50">
                        Adjustment
                    </span>
                    <span
                        className={cn(
                            'font-bold',
                            isDeduct ? 'text-red-500' : 'text-green-500',
                        )}
                    >
                        {isDeduct ? '-' : '+'}
                        {qty} Items
                    </span>
                </div>
            </div>

            {isSubmitted ? (
                <div className="text-[10px] opacity-60">
                    <p>
                        Synced by{' '}
                        <span className="font-bold">{submittedBy}</span>
                    </p>
                    <p className="mt-0.5 font-mono">
                        Ref: {referenceId || 'N/A'}
                    </p>
                </div>
            ) : (
                <Button
                    className="h-9 w-full text-xs font-bold uppercase"
                    disabled={!jubName || disabled}
                    onClick={onSync}
                >
                    Push to Jubelio
                </Button>
            )}
        </div>
    );
}
