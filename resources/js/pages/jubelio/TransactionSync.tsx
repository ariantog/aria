import { Head, Link, router } from '@inertiajs/react';
import { Command, Search, X, CheckCircle2, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Transaction Sync',
        href: '/jubelio-transaction/sync',
    },
];

interface Transaction {
    id: number;
    date: string;
    invoice_number: string;
    type: number;
    type_name: string;
    description: string;
    sync_hide: string;
    sync_cek: string;
    a_submit_by: number | null;
    b_submit_by: number | null;
    sender?: { name: string };
    receiver?: { name: string };
}

interface Props {
    transactions: {
        data: Transaction[];
        links: any[];
        meta: any;
    };
    types: Record<number, string>;
    filters: {
        date?: string;
        invoice?: string;
        type?: string;
        display?: string;
    };
}

export default function TransactionSync({
    transactions,
    types,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.invoice || '');
    const [date, setDate] = useState(filters.date || '');
    const [type, setType] = useState(filters.type || 'all');
    const [display, setDisplay] = useState(filters.display || 'N');

    const handleFilter = () => {
        router.get(
            '/jubelio-transaction/sync',
            {
                invoice: search,
                date,
                type: type === 'all' ? '' : type,
                display,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setDate('');
        setType('all');
        setDisplay('N');
        router.get('/jubelio-transaction/sync');
    };

    const toggleVisibility = (id: number) => {
        router.patch(
            `/jubelio-transaction/${id}/sync-display`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transaction Sync" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <h1 className="flex items-center gap-2 text-2xl font-bold">
                        <Command className="h-6 w-6" />
                        Transaction Sync
                    </h1>

                    <div className="flex flex-wrap items-end gap-2">
                        <div className="flex flex-col gap-1">
                            <label className="ml-1 text-[10px] font-bold uppercase opacity-50">
                                Date
                            </label>
                            <Input
                                type="date"
                                className="h-9 w-40"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="ml-1 text-[10px] font-bold uppercase opacity-50">
                                Invoice
                            </label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-sidebar-foreground/50" />
                                <Input
                                    placeholder="Search Invoice..."
                                    className="h-9 w-48 pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="ml-1 text-[10px] font-bold uppercase opacity-50">
                                Type
                            </label>
                            <Select value={type} onValueChange={setType}>
                                <SelectTrigger className="h-9 w-40">
                                    <SelectValue placeholder="All Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Types
                                    </SelectItem>
                                    {Object.entries(types).map(([id, name]) => (
                                        <SelectItem key={id} value={id}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="ml-1 text-[10px] font-bold uppercase opacity-50">
                                Display
                            </label>
                            <Select value={display} onValueChange={setDisplay}>
                                <SelectTrigger className="h-9 w-24">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="N">Show</SelectItem>
                                    <SelectItem value="Y">Hidden</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={handleFilter} size="sm">
                            Filter
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="gap-1"
                        >
                            <X className="h-4 w-4" /> Clear
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-sidebar-accent/50 font-semibold text-sidebar-foreground uppercase">
                                <tr>
                                    <th className="px-6 py-4">Date</th>
                                    <th className="max-w-[150px] px-6 py-4">
                                        Invoice
                                    </th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="max-w-[150px] px-6 py-4">
                                        Description
                                    </th>
                                    <th className="px-6 py-4">Sender</th>
                                    <th className="px-6 py-4">Receiver</th>
                                    <th className="px-6 py-4 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border">
                                {transactions.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b border-sidebar-border/50 transition-colors hover:bg-sidebar-accent/30"
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex flex-col">
                                                <span className="font-bold text-sidebar-foreground">
                                                    {new Date(
                                                        item.date,
                                                    ).toLocaleDateString(
                                                        'id-ID',
                                                        {
                                                            day: '2-digit',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        },
                                                    )}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="max-w-[150px] px-6 py-4 font-bold break-words whitespace-normal text-blue-500">
                                            <Link
                                                href={`/jubelio-transaction/${item.id}/detail-sync`}
                                                className="hover:underline"
                                            >
                                                {item.invoice_number}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4 text-[10px] font-bold whitespace-nowrap uppercase">
                                            <Badge variant="outline">
                                                {item.type_name}
                                            </Badge>
                                        </td>
                                        <td className="max-w-[150px] px-6 py-4 text-[10px] leading-tight break-words whitespace-normal text-sidebar-foreground/60 italic">
                                            {item.description}
                                        </td>
                                        <td className="px-6 py-4">
                                            <SideStatus
                                                name={
                                                    item.sender?.name ||
                                                    'Unknown'
                                                }
                                                submitted={!!item.a_submit_by}
                                                needsSync={
                                                    item.sync_cek === 'S' ||
                                                    item.sync_cek === 'B'
                                                }
                                                role="sender"
                                            />
                                        </td>
                                        <td className="px-6 py-4">
                                            <SideStatus
                                                name={
                                                    item.receiver?.name ||
                                                    'Unknown'
                                                }
                                                submitted={!!item.b_submit_by}
                                                needsSync={
                                                    item.sync_cek === 'R' ||
                                                    item.sync_cek === 'B'
                                                }
                                                role="receiver"
                                            />
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Button
                                                variant={
                                                    item.sync_hide === 'N'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                size="sm"
                                                className="h-8 text-[10px] font-bold uppercase"
                                                onClick={() =>
                                                    toggleVisibility(item.id)
                                                }
                                            >
                                                {item.sync_hide === 'N' ? (
                                                    <>
                                                        <EyeOff className="mr-1 h-3 w-3" />{' '}
                                                        Hide
                                                    </>
                                                ) : (
                                                    <>
                                                        <Eye className="mr-1 h-3 w-3" />{' '}
                                                        Show
                                                    </>
                                                )}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {transactions.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-6 py-8 text-center font-medium text-sidebar-foreground/50 italic"
                                        >
                                            No transactions pending
                                            synchronization.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4">
                    <Pagination links={transactions.links} />
                </div>
            </div>
        </AppLayout>
    );
}

function SideStatus({
    name,
    submitted,
    needsSync,
    role,
}: {
    name: string;
    submitted: boolean;
    needsSync: boolean;
    role: 'sender' | 'receiver';
}) {
    if (!needsSync) {
        return (
            <Badge
                variant="secondary"
                className="gap-1 border-none bg-zinc-100 text-[10px] text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
            >
                <CheckCircle2 className="h-3 w-3" /> {name}
            </Badge>
        );
    }

    if (submitted) {
        return (
            <Badge className="gap-1 border-green-500/20 bg-green-500/10 text-[10px] text-green-500 hover:bg-green-500/20">
                <CheckCircle2 className="h-3 w-3" /> {name}
            </Badge>
        );
    }

    if (role === 'sender') {
        return (
            <Badge
                variant="outline"
                className="animate-pulse gap-1 border-zinc-500/20 bg-zinc-500/10 text-[10px] text-zinc-500"
            >
                <div className="h-1.5 w-1.5 rounded-full bg-zinc-500" /> {name}
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="animate-pulse gap-1 border-blue-500/20 bg-blue-500/10 text-[10px] text-blue-500"
        >
            <div className="h-1.5 w-1.5 rounded-full bg-blue-500" /> {name}
        </Badge>
    );
}
