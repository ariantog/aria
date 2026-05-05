import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import {
    ChevronDown,
    ChevronUp,
    Command,
    Clock,
    CheckCircle2,
    AlertCircle,
    Copy,
    Search,
    X,
} from 'lucide-react';
import Pagination from '@/components/Pagination';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jubelio Orders',
        href: '/jubelio',
    },
];

interface JubelioOrder {
    id: number;
    jubelio_order_id: string;
    invoice: string;
    type: string;
    source: number;
    order_status: string;
    run_count: number;
    status: number;
    error_type: number | null;
    payload: string;
    error: string;
    created_at: string;
    updated_at: string;
    user?: {
        name: string;
    };
}

interface Props {
    orders: {
        data: JubelioOrder[];
        links: any[];
        meta: {
            current_page: number;
            from: number;
            last_page: number;
            path: string;
            per_page: number;
            to: number;
            total: number;
        };
    };
    stats: {
        pending: number;
        error: number;
        success: number;
        warning: number;
    };
    filters: {
        status?: string;
        invoice?: string;
    };
}

export default function JubelioIndex({ orders, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.invoice || '');

    const handleFilter = (status?: string) => {
        router.get(
            '/jubelio',
            { status, invoice: search },
            { preserveState: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilter(filters.status);
    };

    const clearFilters = () => {
        setSearch('');
        router.get('/jubelio');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jubelio Orders" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <h1 className="flex items-center gap-2 text-2xl font-bold">
                        <Command className="h-6 w-6" />
                        Jubelio Orders
                    </h1>

                    <form
                        onSubmit={handleSearch}
                        className="flex items-center gap-2"
                    >
                        <div className="relative">
                            <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-sidebar-foreground/50" />
                            <Input
                                placeholder="Search Invoice..."
                                className="h-9 w-64 pl-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <Button type="submit" size="sm">
                            Search
                        </Button>
                        {(filters.status || filters.invoice) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="gap-1"
                            >
                                <X className="h-4 w-4" /> Clear
                            </Button>
                        )}
                    </form>
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatCard
                        title="Pending"
                        value={stats.pending}
                        icon={Clock}
                        colorClass={cn(
                            'cursor-pointer border-blue-500 bg-blue-500/10 text-blue-500 hover:bg-blue-500/20',
                            !filters.status && 'ring-2 ring-blue-500',
                        )}
                        onClick={() => handleFilter('pending')}
                    />
                    <StatCard
                        title="Success"
                        value={stats.success}
                        icon={CheckCircle2}
                        colorClass={cn(
                            'cursor-pointer border-green-500 bg-green-500/10 text-green-500 hover:bg-green-500/20',
                            filters.status === 'success' &&
                                'ring-2 ring-green-500',
                        )}
                        onClick={() => handleFilter('success')}
                    />
                    <StatCard
                        title="Duplicate"
                        value={stats.warning}
                        icon={Copy}
                        colorClass={cn(
                            'cursor-pointer border-yellow-500 bg-yellow-500/10 text-yellow-500 hover:bg-yellow-500/20',
                            filters.status === 'warning' &&
                                'ring-2 ring-yellow-500',
                        )}
                        onClick={() => handleFilter('warning')}
                    />
                    <StatCard
                        title="Error SKU"
                        value={stats.error}
                        icon={AlertCircle}
                        colorClass={cn(
                            'cursor-pointer border-red-500 bg-red-500/10 text-red-500 hover:bg-red-500/20',
                            filters.status === 'error' && 'ring-2 ring-red-500',
                        )}
                        onClick={() => handleFilter('error')}
                    />
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-sidebar-accent/50 font-semibold text-sidebar-foreground uppercase">
                                <tr>
                                    <th className="px-6 py-4">Date</th>
                                    <th className="px-6 py-4">Source</th>
                                    <th className="px-6 py-4">Invoice</th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="px-6 py-4 text-center">
                                        Sync Status
                                    </th>
                                    <th className="px-6 py-4 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border">
                                {orders.data.map((order) => (
                                    <OrderRow key={order.id} order={order} />
                                ))}
                                {orders.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-8 text-center text-sidebar-foreground/50 italic"
                                        >
                                            No Jubelio orders found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4">
                    <Pagination links={orders.links} />
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({
    title,
    value,
    icon: Icon,
    colorClass,
    onClick,
}: {
    title: string;
    value: number;
    icon: any;
    colorClass: string;
    onClick?: () => void;
}) {
    return (
        <div
            onClick={onClick}
            className={cn(
                'flex items-center justify-between rounded-lg border-l-4 bg-sidebar p-4 shadow-sm transition-all',
                colorClass,
            )}
        >
            <div>
                <p className="text-[10px] font-semibold tracking-wider uppercase opacity-70 md:text-xs">
                    {title}
                </p>
                <p className="text-xl font-bold md:text-2xl">
                    {value.toLocaleString()}
                </p>
            </div>
            <Icon className="h-6 w-6 opacity-20 md:h-8 md:w-8" />
        </div>
    );
}

function OrderRow({ order }: { order: JubelioOrder }) {
    const [isOpen, setIsOpen] = useState(false);
    const payloadData = (() => {
        try {
            return JSON.parse(order.payload);
        } catch (e) {
            return {};
        }
    })();
    const payloadDate =
        payloadData.transaction_date || payloadData.created_date;

    return (
        <>
            <tr className="border-b border-sidebar-border/50 transition-colors hover:bg-sidebar-accent/30">
                <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-1.5">
                            <span className="w-8 text-[9px] font-bold text-sidebar-foreground/40 uppercase">
                                Sync:
                            </span>
                            <span className="text-[10px] font-bold text-sidebar-foreground">
                                {new Date(order.updated_at).toLocaleString(
                                    'id-ID',
                                    {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    },
                                )}
                            </span>
                        </div>
                        {payloadDate && (
                            <div className="flex items-center gap-1.5">
                                <span className="w-8 text-[9px] font-bold text-sidebar-foreground/40 uppercase">
                                    Trans:
                                </span>
                                <span className="text-[10px] font-medium text-sidebar-foreground/60">
                                    {new Date(payloadDate).toLocaleString(
                                        'id-ID',
                                        {
                                            day: '2-digit',
                                            month: 'short',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        },
                                    )}
                                </span>
                            </div>
                        )}
                    </div>
                </td>
                <td className="px-6 py-4">
                    {order.source === 1 ? (
                        <Badge
                            className="border-blue-500/20 bg-blue-500/10 text-[10px] text-blue-500 hover:bg-blue-500/20"
                            variant="outline"
                        >
                            Jubelio
                        </Badge>
                    ) : (
                        <Badge
                            className="border-yellow-500/20 bg-yellow-500/10 text-[10px] text-yellow-500 hover:bg-yellow-500/20"
                            variant="outline"
                        >
                            Aria
                        </Badge>
                    )}
                </td>
                <td className="px-6 py-4 font-medium">
                    <Link
                        href={`/jubelio/${order.id}`}
                        className="text-blue-500 hover:underline"
                    >
                        {order.invoice}
                    </Link>
                    <div className="mt-0.5 text-[10px] text-sidebar-foreground/50">
                        {order.order_status}
                    </div>
                </td>
                <td className="px-6 py-4">
                    <span className="rounded bg-sidebar-accent px-1.5 py-0.5 text-[10px] font-bold uppercase">
                        {order.type}
                    </span>
                </td>
                <td className="px-6 py-4 text-center">
                    <SyncStatusBadge
                        status={order.status}
                        errorType={order.error_type}
                        executeBy={order.user?.name}
                    />
                </td>
                <td className="px-6 py-4 text-right">
                    <button
                        onClick={() => setIsOpen(!isOpen)}
                        className="inline-flex items-center gap-1 rounded-md bg-sidebar-accent px-3 py-1.5 text-[10px] font-bold uppercase transition-colors hover:bg-sidebar-accent/70"
                    >
                        Payload{' '}
                        {isOpen ? (
                            <ChevronUp className="h-3 w-3" />
                        ) : (
                            <ChevronDown className="h-3 w-3" />
                        )}
                    </button>
                </td>
            </tr>
            {isOpen && (
                <tr className="bg-sidebar-accent/5">
                    <td colSpan={6} className="px-6 py-4">
                        <div className="space-y-4">
                            <div className="max-w-full overflow-x-auto rounded-lg border border-sidebar-border bg-black/40 p-4 font-mono text-[11px]">
                                <pre>{formatPayload(order.payload)}</pre>
                            </div>

                            {((order.status === 1 && order.error_type === 1) ||
                                (order.status === 2 &&
                                    order.error_type === 2)) &&
                                order.error && (
                                    <div
                                        className={cn(
                                            'rounded-lg border p-4 text-xs',
                                            order.error_type === 2
                                                ? 'border-yellow-500/30 bg-yellow-500/10 text-yellow-400'
                                                : 'border-red-500/30 bg-red-500/10 text-red-400',
                                        )}
                                    >
                                        <p className="mb-1 flex items-center gap-1 font-bold">
                                            <AlertCircle className="h-3 w-3" />{' '}
                                            Info:
                                        </p>
                                        <pre className="font-sans whitespace-pre-wrap">
                                            {order.error}
                                        </pre>
                                    </div>
                                )}
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

function SyncStatusBadge({
    status,
    errorType,
    executeBy,
}: {
    status: number;
    errorType: number | null;
    executeBy?: string;
}) {
    // Success State (status 2, error_type 10)
    if (status === 2 && errorType === 10) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-green-500/20 bg-green-500/10 px-2.5 py-0.5 text-[10px] font-bold text-green-500 uppercase">
                <CheckCircle2 className="h-3 w-3" />
                Success {executeBy ? `(${executeBy})` : ''}
            </span>
        );
    }

    // Duplicate State (status 2, error_type 2)
    if (status === 2 && errorType === 2) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-yellow-500/20 bg-yellow-500/10 px-2.5 py-0.5 text-[10px] font-bold text-yellow-500 uppercase">
                <Copy className="h-3 w-3" />
                Duplicate
            </span>
        );
    }

    // Error SKU State (status 1, error_type 1)
    if (status === 1 && errorType === 1) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-red-500/20 bg-red-500/10 px-2.5 py-0.5 text-[10px] font-bold text-red-500 uppercase">
                <AlertCircle className="h-3 w-3" />
                Error SKU
            </span>
        );
    }

    // Pending State (status 0)
    if (status === 0) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-blue-500/20 bg-blue-500/10 px-2.5 py-0.5 text-[10px] font-bold text-blue-500 uppercase">
                <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500" />
                Pending
            </span>
        );
    }

    return (
        <Badge variant="outline" className="text-[10px]">
            Status {status}:{errorType}
        </Badge>
    );
}

function formatPayload(payload: string) {
    try {
        const obj = JSON.parse(payload);
        return JSON.stringify(obj, null, 2);
    } catch (e) {
        return payload;
    }
}
