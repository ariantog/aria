import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { ChevronDown, ChevronUp, Command, Clock, CheckCircle2, AlertCircle, Copy, Search, X } from 'lucide-react';
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
        router.get('/jubelio', { status, invoice: search }, { preserveState: true });
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
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold flex items-center gap-2">
                        <Command className="h-6 w-6" />
                        Jubelio Orders
                    </h1>

                    <form onSubmit={handleSearch} className="flex items-center gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-sidebar-foreground/50" />
                            <Input 
                                placeholder="Search Invoice..." 
                                className="pl-9 w-64 h-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <Button type="submit" size="sm">Search</Button>
                        {(filters.status || filters.invoice) && (
                            <Button type="button" variant="ghost" size="sm" onClick={clearFilters} className="gap-1">
                                <X className="h-4 w-4" /> Clear
                            </Button>
                        )}
                    </form>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StatCard 
                        title="Pending" 
                        value={stats.pending} 
                        icon={Clock} 
                        colorClass={cn("border-blue-500 text-blue-500 bg-blue-500/10 cursor-pointer hover:bg-blue-500/20", !filters.status && "ring-2 ring-blue-500")}
                        onClick={() => handleFilter('pending')}
                    />
                    <StatCard 
                        title="Success" 
                        value={stats.success} 
                        icon={CheckCircle2} 
                        colorClass={cn("border-green-500 text-green-500 bg-green-500/10 cursor-pointer hover:bg-green-500/20", filters.status === 'success' && "ring-2 ring-green-500")}
                        onClick={() => handleFilter('success')}
                    />
                    <StatCard 
                        title="Duplicate" 
                        value={stats.warning} 
                        icon={Copy} 
                        colorClass={cn("border-yellow-500 text-yellow-500 bg-yellow-500/10 cursor-pointer hover:bg-yellow-500/20", filters.status === 'warning' && "ring-2 ring-yellow-500")}
                        onClick={() => handleFilter('warning')}
                    />
                    <StatCard 
                        title="Error SKU" 
                        value={stats.error} 
                        icon={AlertCircle} 
                        colorClass={cn("border-red-500 text-red-500 bg-red-500/10 cursor-pointer hover:bg-red-500/20", filters.status === 'error' && "ring-2 ring-red-500")}
                        onClick={() => handleFilter('error')}
                    />
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-sidebar-accent/50 text-sidebar-foreground uppercase font-semibold">
                                <tr>
                                    <th className="px-6 py-4">Date</th>
                                    <th className="px-6 py-4">Source</th>
                                    <th className="px-6 py-4">Invoice</th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="px-6 py-4 text-center">Sync Status</th>
                                    <th className="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border">
                                {orders.data.map((order) => (
                                    <OrderRow key={order.id} order={order} />
                                ))}
                                {orders.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-sidebar-foreground/50 italic">
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

function StatCard({ title, value, icon: Icon, colorClass, onClick }: { title: string; value: number; icon: any; colorClass: string; onClick?: () => void }) {
    return (
        <div 
            onClick={onClick}
            className={cn("p-4 border-l-4 rounded-lg shadow-sm bg-sidebar flex items-center justify-between transition-all", colorClass)}
        >
            <div>
                <p className="text-[10px] md:text-xs font-semibold uppercase tracking-wider opacity-70">{title}</p>
                <p className="text-xl md:text-2xl font-bold">{value.toLocaleString()}</p>
            </div>
            <Icon className="h-6 w-6 md:h-8 md:w-8 opacity-20" />
        </div>
    );
}

function OrderRow({ order }: { order: JubelioOrder }) {
    const [isOpen, setIsOpen] = useState(false);
    const payloadData = (() => {
        try { return JSON.parse(order.payload); } catch (e) { return {}; }
    })();
    const payloadDate = payloadData.transaction_date || payloadData.created_date;

    return (
        <>
            <tr className="hover:bg-sidebar-accent/30 transition-colors border-b border-sidebar-border/50">
                <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-1.5">
                            <span className="text-[9px] font-bold uppercase text-sidebar-foreground/40 w-8">Sync:</span>
                            <span className="text-[10px] font-bold text-sidebar-foreground">
                                {new Date(order.updated_at).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </span>
                        </div>
                        {payloadDate && (
                            <div className="flex items-center gap-1.5">
                                <span className="text-[9px] font-bold uppercase text-sidebar-foreground/40 w-8">Trans:</span>
                                <span className="text-[10px] font-medium text-sidebar-foreground/60">
                                    {new Date(payloadDate).toLocaleString('id-ID', {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </span>
                            </div>
                        )}
                    </div>
                </td>
                <td className="px-6 py-4">
                    {order.source === 1 ? (
                        <Badge className="bg-blue-500/10 text-blue-500 border-blue-500/20 hover:bg-blue-500/20 text-[10px]" variant="outline">Jubelio</Badge>
                    ) : (
                        <Badge className="bg-yellow-500/10 text-yellow-500 border-yellow-500/20 hover:bg-yellow-500/20 text-[10px]" variant="outline">Aria</Badge>
                    )}
                </td>
                <td className="px-6 py-4 font-medium">
                    <Link href={`/jubelio/${order.id}`} className="hover:underline text-blue-500">
                        {order.invoice}
                    </Link>
                    <div className="text-[10px] text-sidebar-foreground/50 mt-0.5">{order.order_status}</div>
                </td>
                <td className="px-6 py-4">
                    <span className="text-[10px] font-bold uppercase py-0.5 px-1.5 bg-sidebar-accent rounded">{order.type}</span>
                </td>
                <td className="px-6 py-4 text-center">
                    <SyncStatusBadge status={order.status} errorType={order.error_type} executeBy={order.user?.name} />
                </td>
                <td className="px-6 py-4 text-right">
                    <button 
                        onClick={() => setIsOpen(!isOpen)}
                        className="inline-flex items-center gap-1 text-[10px] font-bold uppercase px-3 py-1.5 rounded-md bg-sidebar-accent hover:bg-sidebar-accent/70 transition-colors"
                    >
                        Payload {isOpen ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                    </button>
                </td>
            </tr>
            {isOpen && (
                <tr className="bg-sidebar-accent/5">
                    <td colSpan={6} className="px-6 py-4">
                        <div className="space-y-4">
                            <div className="p-4 rounded-lg bg-black/40 font-mono text-[11px] overflow-x-auto max-w-full border border-sidebar-border">
                                <pre>{formatPayload(order.payload)}</pre>
                            </div>
                            
                            {((order.status === 1 && order.error_type === 1) || (order.status === 2 && order.error_type === 2)) && order.error && (
                                <div className={cn(
                                    "p-4 rounded-lg border text-xs",
                                    order.error_type === 2 
                                        ? "bg-yellow-500/10 border-yellow-500/30 text-yellow-400" 
                                        : "bg-red-500/10 border-red-500/30 text-red-400"
                                )}>
                                    <p className="font-bold mb-1 flex items-center gap-1">
                                        <AlertCircle className="h-3 w-3" /> Info:
                                    </p>
                                    <pre className="whitespace-pre-wrap font-sans">{order.error}</pre>
                                </div>
                            )}
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

function SyncStatusBadge({ status, errorType, executeBy }: { status: number; errorType: number | null; executeBy?: string }) {
    // Success State (status 2, error_type 10)
    if (status === 2 && errorType === 10) {
        return (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-500 border border-green-500/20">
                <CheckCircle2 className="h-3 w-3" />
                Success {executeBy ? `(${executeBy})` : ''}
            </span>
        );
    }

    // Duplicate State (status 2, error_type 2)
    if (status === 2 && errorType === 2) {
        return (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-500 border border-yellow-500/20">
                <Copy className="h-3 w-3" />
                Duplicate
            </span>
        );
    }

    // Error SKU State (status 1, error_type 1)
    if (status === 1 && errorType === 1) {
        return (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-red-500/10 text-red-500 border border-red-500/20">
                <AlertCircle className="h-3 w-3" />
                Error SKU
            </span>
        );
    }

    // Pending State (status 0)
    if (status === 0) {
        return (
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-500/10 text-blue-500 border border-blue-500/20">
                <div className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" />
                Pending
            </span>
        );
    }

    return <Badge variant="outline" className="text-[10px]">Status {status}:{errorType}</Badge>;
}

function formatPayload(payload: string) {
    try {
        const obj = JSON.parse(payload);
        return JSON.stringify(obj, null, 2);
    } catch (e) {
        return payload;
    }
}
