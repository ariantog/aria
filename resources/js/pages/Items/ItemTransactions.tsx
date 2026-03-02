import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Pagination from '@/components/Partial/Pagination';
import { ArrowLeft, Eye, Package, History } from 'lucide-react';
import { cn } from "@/lib/utils";

interface Transaction {
    id: number;
    date: string;
    invoice_number: string;
    type: number;
    sender?: { name: string };
    receiver?: { name: string };
}

interface TransactionDetail {
    id: number;
    transaction_id: number;
    date: string;
    transaction_type: number;
    quantity: number;
    transaction: Transaction;
}

interface Item {
    id: number;
    name: string;
    code: string;
    type: number;
    group?: { id: number; name: string };
}

interface Props {
    item: Item;
    transactions: {
        data: TransactionDetail[];
        links: any[];
        meta?: any;
        from: number;
        to: number;
        total: number;
    };
}

export default function ItemTransactions({ item, transactions }: Props) {
    const getBaseUrl = (type: number) => {
        return type === 2 ? '/assetlancar' : '/items';
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: item.type === 2 ? 'Assets' : 'Items', href: getBaseUrl(item.type) },
        { title: item.name, href: `${getBaseUrl(item.type)}/${item.id}` },
        { title: 'Transactions', href: '#' },
    ];

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: '2-digit' });
    };

    const getTypeLabel = (type: number) => {
        const labels: Record<number, { label: string, className: string }> = {
            1: { label: 'Buy', className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
            2: { label: 'Sell', className: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
            3: { label: 'Move', className: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
            6: { label: 'Transfer', className: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
            9: { label: 'Cash In', className: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
            10: { label: 'Cash Out', className: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
            12: { label: 'Adjust', className: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' },
            15: { label: 'Return', className: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
            16: { label: 'Production', className: 'bg-slate-500/10 text-slate-400 border-slate-500/20' },
            17: { label: 'Ret. Supplier', className: 'bg-orange-500/10 text-orange-400 border-orange-500/20' },
        };
        const config = labels[type] || { label: 'Unknown', className: '' };
        return <Badge variant="outline" className={cn("uppercase text-[10px]", config.className)}>{config.label}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transactions: ${item.name}`} />

            <div className="p-4 sm:p-6 lg:p-8 bg-black min-h-screen text-zinc-100">
                {/* Header */}
                <div className="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            className="border-zinc-800 bg-zinc-900/50 hover:bg-zinc-800 text-zinc-400 h-10 w-10"
                            onClick={() => (item.group ? router.get(`/items-group/${item.group.id}`) : router.get(`${getBaseUrl(item.type)}/${item.id}`))}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <h1 className="text-2xl font-bold tracking-tight text-white">{item.code} - {item.name}</h1>
                                {item.group && <Badge variant="secondary" className="bg-zinc-800 text-zinc-400 hover:bg-zinc-700">{item.group.name}</Badge>}
                            </div>
                            <p className="text-zinc-500 flex items-center gap-2">
                                <History className="h-4 w-4" /> Transaction History
                            </p>
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-zinc-800 bg-zinc-900/50 overflow-hidden shadow-sm">
                    <Table>
                        <TableHeader className="bg-zinc-900/80 border-b border-zinc-800">
                            <TableRow className="hover:bg-transparent border-zinc-800">
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6">Date</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6">Type</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6">Invoice</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6">Sender</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6">Receiver</TableHead>
                                <TableHead className="text-zinc-500 font-bold tracking-wider uppercase text-[10px] px-6 text-right">Qty</TableHead>
                                <TableHead className="w-[50px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-zinc-800/50">
                            {transactions.data.length > 0 ? (
                                transactions.data.map((detail) => (
                                    <TableRow key={detail.id} className="hover:bg-zinc-800/40 border-zinc-800/50 transition-colors">
                                        <TableCell className="px-6 py-4 text-zinc-400 tabular-nums">
                                            {formatDate(detail.date)}
                                        </TableCell>
                                        <TableCell className="px-6 py-4">
                                            {getTypeLabel(detail.transaction_type)}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 font-mono text-[11px]">
                                            <Link href={`/transactions/${detail.transaction_id}`} className="text-blue-500 hover:underline">
                                                {detail.transaction?.invoice_number || detail.transaction_id}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-zinc-300">
                                            {detail.transaction?.sender?.name || '-'}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-zinc-300">
                                            {detail.transaction?.receiver?.name || '-'}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-bold text-white tabular-nums">
                                            {detail.quantity}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8 text-zinc-500 hover:text-white"
                                                onClick={() => router.get(`/transactions/${detail.transaction_id}`)}
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={7} className="h-64 text-center">
                                        <div className="flex flex-col items-center gap-2 text-zinc-600">
                                            <Package className="h-10 w-10 opacity-20" />
                                            <p>No transaction history found for this item.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    <div className="border-t border-zinc-800 px-6 py-4">
                        <Pagination
                            links={transactions.links}
                            from={transactions.from}
                            to={transactions.to}
                            total={transactions.total}
                            label="transactions"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
