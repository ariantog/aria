
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { BreadcrumbItem } from '@/types';
import itemRoutes from '@/routes/items';
import { FilePen, Package, History, ArrowLeft, ArrowUpRight, ArrowDownLeft, MoveHorizontal } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/pagination';
import { cn } from "@/lib/utils";

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
            case 1: return 'Buy';
            case 2: return 'Sell';
            case 3: return 'Move';
            case 15: return 'Return';
            case 16: return 'Production';
            case 17: return 'Ret. Supplier';
            default: return 'Other';
        }
    };

    const getTransactionTypeColor = (type: number) => {
        switch (type) {
            case 1: return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'; // Buy
            case 2: return 'bg-blue-500/10 text-blue-500 border-blue-500/20'; // Sell
            case 3: return 'bg-amber-500/10 text-amber-500 border-amber-500/20'; // Move
            case 15: return 'bg-purple-500/10 text-purple-500 border-purple-500/20'; // Return
            case 16: return 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20'; // Production
            case 17: return 'bg-rose-500/10 text-rose-500 border-rose-500/20'; // Ret. Supplier
            default: return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    };

    const getTransactionIcon = (type: number) => {
        switch (type) {
            case 1: return <ArrowDownLeft className="w-3 h-3 mr-1" />;
            case 2: return <ArrowUpRight className="w-3 h-3 mr-1" />;
            case 3: return <MoveHorizontal className="w-3 h-3 mr-1" />;
            default: return null;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transactions: ${item.name}`} />

            <div className="flex-1 flex flex-col h-full bg-[#0A0A0A] min-h-screen text-gray-300 font-sans antialiased">
                <div className="flex-1 p-8">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <Link href={`/items/${item.id}`} className="text-gray-500 hover:text-white transition-colors">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="text-zinc-600 font-mono text-sm">#{item.code}</span>
                            </div>
                            <h1 className="text-2xl font-bold text-white mb-1">Transaction History</h1>
                            <p className="text-gray-500 text-sm">
                                Full history for <span className="text-blue-400">{item.name}</span>
                            </p>
                        </div>
                    </div>

                    {/* Tabs Navigation (Simulated for consistency with Show.tsx) */}
                    <div className="flex border-b border-gray-800 mb-8 overflow-x-auto">
                        <Link
                            href={`/items/${item.id}`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white"
                        >
                            Detail
                        </Link>
                        <Link
                            href={`/items/${item.id}/transactions`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 text-blue-500 border-blue-500"
                        >
                            Transaction
                        </Link>
                        <Link
                            href={`/items/${item.id}/stats`}
                            className="px-6 py-4 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-white"
                        >
                            Stats
                        </Link>
                    </div>

                    {/* Transactions Table */}
                    <div className="bg-[#111] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-[#161616] border-b border-gray-800">
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Date</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Type</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Invoice</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Price</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Sender / Source</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Receiver / Destination</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest">Description</th>
                                        <th className="px-6 py-4 text-[10px] uppercase font-bold text-gray-500 tracking-widest text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-800/50">
                                    {transactions.data.length > 0 ? (
                                        transactions.data.map((td) => (
                                            <tr key={td.id} className="hover:bg-white/[0.02] transition-colors group">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-300">
                                                        {new Date(td.date).toLocaleDateString('id-ID', {
                                                            day: '2-digit',
                                                            month: 'short',
                                                            year: 'numeric'
                                                        })}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge className={cn("px-2 py-0.5 text-[10px] uppercase font-bold border", getTransactionTypeColor(td.transaction_type))}>
                                                        {getTransactionIcon(td.transaction_type)}
                                                        {getTransactionTypeLabel(td.transaction_type)}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link
                                                        href={`/transactions/${td.transaction_id}`}
                                                        className="text-sm font-mono text-blue-400 hover:text-blue-300 hover:underline"
                                                    >
                                                        {td.transaction.invoice_number}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className="text-sm font-medium text-gray-400">
                                                        {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(td.price))}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-300">
                                                            {td.transaction.sender?.name || '-'}
                                                        </span>
                                                        {td.transaction.sender && (
                                                            <span className="text-[10px] text-gray-600 uppercase font-bold">ID: {td.transaction.sender.id}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-300">
                                                            {td.transaction.receiver?.name || '-'}
                                                        </span>
                                                        {td.transaction.receiver && (
                                                            <span className="text-[10px] text-gray-600 uppercase font-bold">ID: {td.transaction.receiver.id}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <p className="text-xs text-gray-500 max-w-[200px] truncate" title={td.notes || td.transaction.description || ''}>
                                                        {td.notes || td.transaction.description || '-'}
                                                    </p>
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <span className={cn(
                                                        "text-sm font-bold font-mono",
                                                        [2, 17].includes(td.transaction_type) ? "text-rose-400" : "text-emerald-400"
                                                    )}>
                                                        {[2, 17].includes(td.transaction_type) ? '-' : '+'}{parseFloat(td.quantity).toLocaleString()}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={8} className="px-6 py-12 text-center text-gray-500 italic">
                                                No transactions found for this item.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="px-6 py-4 bg-[#161616] border-t border-gray-800">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-gray-500">
                                    Showing <span className="text-white">{transactions.data.length}</span> of <span className="text-white">{transactions.total}</span> transactions
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
