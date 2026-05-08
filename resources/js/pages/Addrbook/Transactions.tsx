import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    ArrowDownLeft,
    MoveHorizontal,
    Calendar,
    Filter,
    Search,
    Download,
    X,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Addrbook {
    id: number;
    name: string;
    type: number;
    type_slug: string;
}

interface Transaction {
    id: number;
    date: string;
    type: number;
    invoice_number: string;
    description: string | null;
    total: string;
    grand_total: string;
    total_items: string;
    sender_balance: string;
    receiver_balance: string;
    sender?: Addrbook;
    receiver?: Addrbook;
    created_at: string;
}

interface PaginatedTransactions {
    data: Transaction[];
    links: any[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    addrbook: Addrbook;
    transactions: PaginatedTransactions;
    transactionTypes: { id: number; name: string }[];
    filters: {
        from?: string;
        to?: string;
        type?: string;
        order_date?: string;
    };
}

export default function AddrbookTransactions({
    addrbook,
    transactions,
    transactionTypes,
    filters,
}: Props) {
    const [fromDate, setFromDate] = useState(filters.from || '');
    const [toDate, setToDate] = useState(filters.to || '');
    const [type, setType] = useState(filters.type || '');
    const [orderDate, setOrderDate] = useState(filters.order_date || 'date');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Address Book', href: '/addrbook' },
        { title: addrbook.name, href: `/addrbook/${addrbook.id}` },
        { title: 'Transactions', href: '#' },
    ];

    const getTransactionTypeLabel = (typeId: number) => {
        return transactionTypes.find((t) => t.id === typeId)?.name || 'Other';
    };

    const getTransactionTypeColor = (typeId: number) => {
        switch (typeId) {
            case 1:
                return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'; // Buy
            case 2:
                return 'bg-blue-500/10 text-blue-500 border-blue-500/20'; // Sell
            case 3:
                return 'bg-amber-500/10 text-amber-500 border-amber-500/20'; // Move
            case 15:
                return 'bg-purple-500/10 text-purple-500 border-purple-500/20'; // Return
            case 16:
                return 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20'; // Production
            case 17:
                return 'bg-rose-500/10 text-rose-500 border-rose-500/20'; // Ret. Supplier
            default:
                return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    };

    const getTransactionIcon = (typeId: number) => {
        switch (typeId) {
            case 1:
                return <ArrowDownLeft className="mr-1 h-3 w-3" />;
            case 2:
                return <ArrowUpRight className="mr-1 h-3 w-3" />;
            case 3:
                return <MoveHorizontal className="mr-1 h-3 w-3" />;
            default:
                return null;
        }
    };

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            `/${addrbook.type_slug}/${addrbook.id}/transactions`,
            {
                from: fromDate,
                to: toDate,
                type: type,
                order_date: orderDate,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setFromDate('');
        setToDate('');
        setType('');
        setOrderDate('date');
        router.get(`/${addrbook.type_slug}/${addrbook.id}/transactions`);
    };

    const formatBalanceColor = (balance: string | number) => {
        const val = Number(balance);
        if (val > 0) return 'text-emerald-400';
        if (val < 0) return 'text-rose-400';
        return 'text-zinc-500';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transactions: ${addrbook.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <div className="mb-2 flex items-center gap-2">
                                <Link
                                    href={`/addrbook/${addrbook.id}`}
                                    className="text-gray-500 transition-colors hover:text-white"
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="font-mono text-sm text-zinc-600">
                                    #{addrbook.id}
                                </span>
                            </div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Transaction History
                            </h1>
                            <p className="text-sm text-gray-500">
                                Full history for{' '}
                                <span className="text-blue-400">
                                    {addrbook.name}
                                </span>
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <Button className="border-0 bg-emerald-600 text-white hover:bg-emerald-500">
                                <Download className="mr-2 h-4 w-4" />
                                Download CSV
                            </Button>
                        </div>
                    </div>

                    {/* Tabs Navigation */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800">
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/transactions`}
                            className="border-b-2 border-blue-500 px-6 py-4 text-sm font-medium whitespace-nowrap text-blue-500 transition-all"
                        >
                            Transaction
                        </Link>
                        {addrbook.type === 2 && ( // TYPE_WAREHOUSE
                            <Link
                                href={`/${addrbook.type_slug}/${addrbook.id}/items`}
                                className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                            >
                                Items
                            </Link>
                        )}
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/stats`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/${addrbook.type_slug}/${addrbook.id}/item-sales`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 transition-all hover:text-white"
                        >
                            Item Sale
                        </Link>
                    </div>

                    {/* Filters */}
                    <div className="mb-8 rounded-xl border border-gray-800 bg-[#111] p-4">
                        <form
                            onSubmit={handleFilter}
                            className="grid grid-cols-1 items-end gap-4 md:grid-cols-5"
                        >
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    From Date
                                </label>
                                <div className="relative">
                                    <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                    <input
                                        type="date"
                                        value={fromDate}
                                        onChange={(e) =>
                                            setFromDate(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-800 bg-[#161616] py-2 pr-4 pl-10 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    To Date
                                </label>
                                <div className="relative">
                                    <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                    <input
                                        type="date"
                                        value={toDate}
                                        onChange={(e) =>
                                            setToDate(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-800 bg-[#161616] py-2 pr-4 pl-10 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Type
                                </label>
                                <select
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">All Types</option>
                                    {transactionTypes.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[10px] font-bold text-gray-500 uppercase">
                                    Order By
                                </label>
                                <select
                                    value={orderDate}
                                    onChange={(e) =>
                                        setOrderDate(e.target.value)
                                    }
                                    className="w-full rounded-lg border border-gray-800 bg-[#161616] px-4 py-2 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="date">
                                        Transaction Date
                                    </option>
                                    <option value="created_at">
                                        Created At
                                    </option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    className="flex-1 border-0 bg-blue-600 text-white hover:bg-blue-500"
                                >
                                    <Search className="mr-2 h-4 w-4" />
                                    Search
                                </Button>
                                <Button
                                    type="button"
                                    onClick={clearFilters}
                                    variant="outline"
                                    className="border-gray-800 text-gray-400 hover:text-white"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Transactions Table */}
                    <div className="overflow-hidden rounded-2xl border border-gray-800 bg-[#111] shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-gray-800 bg-[#161616]">
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Date
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Type
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Invoice
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Items
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Sender
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Sender Bal
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Receiver
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Receiver Bal
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {transactions.data.length > 0 ? (
                                        transactions.data.map((t) => (
                                            <tr
                                                key={t.id}
                                                className="group transition-colors hover:bg-white/[0.02]"
                                            >
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-300">
                                                            {new Date(
                                                                t.date,
                                                            ).toLocaleDateString(
                                                                'id-ID',
                                                                {
                                                                    day: '2-digit',
                                                                    month: 'short',
                                                                    year: 'numeric',
                                                                },
                                                            )}
                                                        </span>
                                                        <span className="text-[10px] text-gray-600">
                                                            At:{' '}
                                                            {new Date(
                                                                t.created_at,
                                                            ).toLocaleTimeString(
                                                                'id-ID',
                                                                {
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge
                                                        className={cn(
                                                            'border px-2 py-0.5 text-[10px] font-bold uppercase',
                                                            getTransactionTypeColor(
                                                                t.type,
                                                            ),
                                                        )}
                                                    >
                                                        {getTransactionIcon(
                                                            t.type,
                                                        )}
                                                        {getTransactionTypeLabel(
                                                            t.type,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link
                                                        href={`/transactions/${t.id}`}
                                                        className="font-mono text-sm text-blue-400 hover:text-blue-300 hover:underline"
                                                    >
                                                        {t.invoice_number}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="font-mono text-xs text-gray-400">
                                                        {parseFloat(
                                                            t.total_items,
                                                        ).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span
                                                        className={cn(
                                                            'text-[11px] leading-tight font-medium',
                                                            t.sender?.id ===
                                                                addrbook.id
                                                                ? 'font-bold text-blue-400'
                                                                : 'text-gray-400',
                                                        )}
                                                    >
                                                        {t.sender?.name || '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-xs font-bold',
                                                            formatBalanceColor(
                                                                t.sender_balance,
                                                            ),
                                                        )}
                                                    >
                                                        {new Intl.NumberFormat(
                                                            'id-ID',
                                                        ).format(
                                                            Number(
                                                                t.sender_balance,
                                                            ),
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span
                                                        className={cn(
                                                            'text-[11px] leading-tight font-medium',
                                                            t.receiver?.id ===
                                                                addrbook.id
                                                                ? 'font-bold text-blue-400'
                                                                : 'text-gray-400',
                                                        )}
                                                    >
                                                        {t.receiver?.name ||
                                                            '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-xs font-bold',
                                                            formatBalanceColor(
                                                                t.receiver_balance,
                                                            ),
                                                        )}
                                                    >
                                                        {new Intl.NumberFormat(
                                                            'id-ID',
                                                        ).format(
                                                            Number(
                                                                t.receiver_balance,
                                                            ),
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="font-mono text-sm font-bold text-gray-200">
                                                        {new Intl.NumberFormat(
                                                            'id-ID',
                                                            {
                                                                style: 'currency',
                                                                currency: 'IDR',
                                                                maximumFractionDigits: 0,
                                                            },
                                                        ).format(
                                                            Number(
                                                                t.grand_total,
                                                            ),
                                                        )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="px-6 py-12 text-center text-gray-500 italic"
                                            >
                                                No transactions found for this
                                                contact.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-t border-gray-800 bg-[#161616] px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-gray-500">
                                    Showing{' '}
                                    <span className="text-white">
                                        {transactions.data.length}
                                    </span>{' '}
                                    of{' '}
                                    <span className="text-white">
                                        {transactions.total}
                                    </span>{' '}
                                    transactions
                                </p>
                                <Pagination links={transactions.links} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
