import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Command, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface JubelioOrder {
    id: number;
    jubelio_order_id: string;
    invoice: string;
    type: string;
    order_status: string;
    run_count: number;
    status: number;
    payload: string;
    error: string;
    created_at: string;
    user?: {
        name: string;
    };
    trx?: {
        id: number;
        invoice_number: string;
    };
}

interface Props {
    order: JubelioOrder;
}

export default function JubelioShow({ order }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Jubelio Orders',
            href: '/jubelio',
        },
        {
            title: `Order #${order.invoice}`,
            href: `/jubelio/${order.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Jubelio Order #${order.invoice}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/jubelio">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                        </Button>
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <Command className="h-6 w-6" />
                            Order #{order.invoice}
                        </h1>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-1">
                        <div className="rounded-xl border border-sidebar-border bg-sidebar p-6 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold">
                                Order Details
                            </h2>
                            <dl className="space-y-4">
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Jubelio Order ID
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {order.jubelio_order_id}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Type
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        <Badge variant="outline">
                                            {order.type}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Order Status
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        <Badge variant="secondary">
                                            {order.order_status}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Sync Status
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        <SyncStatusBadge
                                            status={order.status}
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Run Count
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {order.run_count}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Executed By
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {order.user?.name || 'System'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm text-sidebar-foreground/50">
                                        Date
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {new Date(
                                            order.created_at,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {order.trx && (
                            <div className="rounded-xl border border-blue-500/30 bg-blue-500/5 p-6 shadow-sm">
                                <h2 className="mb-4 text-lg font-semibold text-blue-400">
                                    Linked Transaction
                                </h2>
                                <p className="mb-4 text-sm text-blue-300/70">
                                    This order is linked to an internal
                                    transaction.
                                </p>
                                <Button
                                    className="w-full gap-2"
                                    variant="outline"
                                    asChild
                                >
                                    <Link
                                        href={`/transactions/${order.trx.id}`}
                                    >
                                        View Transaction{' '}
                                        <ExternalLink className="h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6 md:col-span-2">
                        <div className="rounded-xl border border-sidebar-border bg-sidebar p-6 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold">
                                Payload
                            </h2>
                            <div className="overflow-x-auto rounded-lg bg-black/40 p-4 font-mono text-xs">
                                <pre>{formatPayload(order.payload)}</pre>
                            </div>
                        </div>

                        {order.error && (
                            <div className="rounded-xl border border-red-900/30 bg-red-900/10 p-6 shadow-sm">
                                <h2 className="mb-4 text-lg font-semibold text-red-400">
                                    Error Details
                                </h2>
                                <div className="rounded-lg border border-red-900/30 bg-red-900/20 p-4 font-mono text-xs text-red-300">
                                    <pre className="whitespace-pre-wrap">
                                        {order.error}
                                    </pre>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function SyncStatusBadge({ status }: { status: number }) {
    const config: any = {
        0: { label: 'Pending', variant: 'secondary' },
        1: { label: 'Success', variant: 'default' },
        2: { label: 'Failed', variant: 'destructive' },
    };

    const { label, variant } = config[status] || {
        label: 'Unknown',
        variant: 'outline',
    };
    return <Badge variant={variant}>{label}</Badge>;
}

function formatPayload(payload: string) {
    try {
        const obj = JSON.parse(payload);
        return JSON.stringify(obj, null, 2);
    } catch (e) {
        return payload;
    }
}
