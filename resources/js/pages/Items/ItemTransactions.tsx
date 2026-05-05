import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import itemRoutes from '@/routes/items';
import {
    FilePen,
    Package,
    History,
    ArrowLeft,
    ArrowUpRight,
    ArrowDownLeft,
    MoveHorizontal,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/pagination';
import { cn } from '@/lib/utils';

interface Addrbook {
    id: number;
    name: string;
    type: number;
}

interface Transaction {
    id: number;
    date: string;
    type: number;
    invoice_number: string;
    description: string | null;
    sender?: Addrbook;
    receiver?: Addrbook;
}

interface TransactionDetail {
    id: number;
    transaction_id: number;
    date: string;
    transaction_type: number;
    quantity: string;
    price: string;
    total: string;
    notes: string | null;
    transaction: Transaction;
}

interface PaginatedTransactions {
    data: TransactionDetail[];
    links: any[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Item {
    id: number;
    pcode: string;
    code: string;
    name: string;
    group?: {
        id: number;
        name: string;
    };
}

interface Props {
    item: Item;
    transactions: PaginatedTransactions;
}

export default function ItemTransactions({ item, transactions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Items', href: '/items' },
        { title: item.name, href: `/items/${item.id}` },
        { title: 'Transactions', href: '#' },
    ];

    const getTransactionTypeLabel = (type: number) => {
        switch (type) {
            case 1:
                return 'Buy';
            case 2:
                return 'Sell';
            case 3:
                return 'Move';
            case 15:
                return 'Return';
            case 16:
                return 'Production';
            case 17:
                return 'Ret. Supplier';
            default:
                return 'Other';
        }
    };

    const getTransactionTypeColor = (type: number) => {
        switch (type) {
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

    const getTransactionIcon = (type: number) => {
        switch (type) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transactions: ${item.name}`} />

            <div className="flex h-full min-h-screen flex-1 flex-col bg-[#0A0A0A] font-sans text-gray-300 antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <div className="mb-2 flex items-center gap-2">
                                <Link
                                    href={`/items/${item.id}`}
                                    className="text-gray-500 transition-colors hover:text-white"
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="font-mono text-sm text-zinc-600">
                                    #{item.code}
                                </span>
                            </div>
                            <h1 className="mb-1 text-2xl font-bold text-white">
                                Transaction History
                            </h1>
                            <p className="text-sm text-gray-500">
                                Full history for{' '}
                                <span className="text-blue-400">
                                    {item.name}
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Tabs Navigation (Simulated for consistency with Show.tsx) */}
                    <div className="mb-8 flex overflow-x-auto border-b border-gray-800">
                        <Link
                            href={`/items/${item.id}`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium text-gray-500 transition-all hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/items/${item.id}/transactions`}
                            className="border-b-2 border-blue-500 px-6 py-4 text-sm font-medium text-blue-500 transition-all"
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/items/${item.id}/stats`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium text-gray-500 transition-all hover:text-white"
                        >
                            Stats
                        </Link>
                        <Link
                            href={`/items/${item.id}/jubelio`}
                            className="border-b-2 border-transparent px-6 py-4 text-sm font-medium text-gray-500 transition-all hover:text-white"
                        >
                            Jubelio
                        </Link>
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
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Price
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Sender / Source
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Receiver / Destination
                                        </th>
                                        <th className="px-6 py-4 text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Description
                                        </th>
                                        <th className="px-6 py-4 text-right text-[10px] font-bold tracking-widest text-gray-500 uppercase">
                                            Qty
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {transactions.data.length > 0 ? (
                                        transactions.data.map((td) => (
                                            <tr
                                                key={td.id}
                                                className="group transition-colors hover:bg-white/[0.02]"
                                            >
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-300">
                                                        {new Date(
                                                            td.date,
                                                        ).toLocaleDateString(
                                                            'id-ID',
                                                            {
                                                                day: '2-digit',
                                                                month: 'short',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge
                                                        className={cn(
                                                            'border px-2 py-0.5 text-[10px] font-bold uppercase',
                                                            getTransactionTypeColor(
                                                                td.transaction_type,
                                                            ),
                                                        )}
                                                    >
                                                        {getTransactionIcon(
                                                            td.transaction_type,
                                                        )}
                                                        {getTransactionTypeLabel(
                                                            td.transaction_type,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link
                                                        href={`/transactions/${td.transaction_id}`}
                                                        className="font-mono text-sm text-blue-400 hover:text-blue-300 hover:underline"
                                                    >
                                                        {
                                                            td.transaction
                                                                .invoice_number
                                                        }
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-400">
                                                        {new Intl.NumberFormat(
                                                            'id-ID',
                                                            {
                                                                style: 'currency',
                                                                currency: 'IDR',
                                                                maximumFractionDigits: 0,
                                                            },
                                                        ).format(
                                                            Number(td.price),
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-300">
                                                            {td.transaction
                                                                .sender?.name ||
                                                                '-'}
                                                        </span>
                                                        {td.transaction
                                                            .sender && (
                                                            <span className="text-[10px] font-bold text-gray-600 uppercase">
                                                                ID:{' '}
                                                                {
                                                                    td
                                                                        .transaction
                                                                        .sender
                                                                        .id
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-300">
                                                            {td.transaction
                                                                .receiver
                                                                ?.name || '-'}
                                                        </span>
                                                        {td.transaction
                                                            .receiver && (
                                                            <span className="text-[10px] font-bold text-gray-600 uppercase">
                                                                ID:{' '}
                                                                {
                                                                    td
                                                                        .transaction
                                                                        .receiver
                                                                        .id
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <p
                                                        className="max-w-[200px] truncate text-xs text-gray-500"
                                                        title={
                                                            td.notes ||
                                                            td.transaction
                                                                .description ||
                                                            ''
                                                        }
                                                    >
                                                        {td.notes ||
                                                            td.transaction
                                                                .description ||
                                                            '-'}
                                                    </p>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-sm font-bold',
                                                            [2, 17].includes(
                                                                td.transaction_type,
                                                            )
                                                                ? 'text-rose-400'
                                                                : 'text-emerald-400',
                                                        )}
                                                    >
                                                        {[2, 17].includes(
                                                            td.transaction_type,
                                                        )
                                                            ? '-'
                                                            : '+'}
                                                        {parseFloat(
                                                            td.quantity,
                                                        ).toLocaleString()}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-6 py-12 text-center text-gray-500 italic"
                                            >
                                                No transactions found for this
                                                item.
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
