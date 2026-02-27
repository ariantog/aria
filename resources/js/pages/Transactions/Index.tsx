import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/Partial/Pagination';
import FilterTransaction from '@/components/Partial/Filter/FilterTransaction';
import transactionsRoutes from '@/routes/transactions';
import { Search, Plus, Eye, MoreHorizontal, ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

interface Transaction {
    id: number;
    date: string;
    invoice_number: string;
    type: number;
    grand_total: number;
    total_items: number;
    sender_balance: number;
    receiver_balance: number;
    status: number;
    sender?: { name: string };
    receiver?: { name: string };
}

interface Props {
    transactions: {
        data: Transaction[];
        links: any[];
        meta?: any;
    };
    filters: {
        from: string;
        to: string;
        sort: string;
        direction: 'asc' | 'desc';
        type?: string;
        invoice_number?: string;
        min_total?: string;
        max_total?: string;
    };
}

const breadcrumbs = [
    { title: 'Transactions', href: '/transactions' },
    { title: 'List', href: '/transactions' },
];

const typeOptions = [
    { id: 1, name: 'Buy' },
    { id: 2, name: 'Sell' },
    { id: 3, name: 'Move' },
    { id: 6, name: 'Transfer' },
    { id: 9, name: 'Cash In' },
    { id: 10, name: 'Cash Out' },
    { id: 12, name: 'Adjust' },
    { id: 15, name: 'Return' },
    { id: 16, name: 'Production' },
    { id: 17, name: 'Ret. Supplier' },
];

export default function Index({ transactions: paginatedTransactions, filters }: Props) {
    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = String(date.getFullYear()).slice(-2);
        return `${day}/${month}/${year}`;
    };

    const getTypeLabel = (type: number) => {
        switch (type) {
            case 1: return <Badge className="bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200">Buy</Badge>;
            case 2: return <Badge className="bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 border-blue-200">Sell</Badge>;
            case 3: return <Badge className="bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200">Move</Badge>;
            case 6: return <Badge className="bg-cyan-100 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400 border-cyan-200 uppercase text-[10px]">Transfer</Badge>;
            case 12: return <Badge className="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 border-indigo-200 uppercase text-[10px]">Adjust</Badge>;
            case 15: return <Badge className="bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200">Return</Badge>;
            case 16: return <Badge className="bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400 border-slate-200 uppercase text-[10px]">Production</Badge>;
            case 17: return <Badge className="bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400 border-orange-200 uppercase text-[10px]">Ret. Supplier</Badge>;
            case 9: return <Badge className="bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400 border-purple-200 uppercase text-[10px]">Cash In</Badge>;
            case 10: return <Badge className="bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 uppercase text-[10px]">Cash Out</Badge>;
            default: return <Badge variant="outline">Unknown</Badge>;
        }
    };

    const handleSort = (column: string) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

        const mergedFilters = { ...filters, sort: column, direction };
        const cleanFilters = Object.keys(mergedFilters).reduce((acc: any, key) => {
            const value = mergedFilters[key as keyof typeof mergedFilters];
            if (value !== null && value !== undefined && value !== '') {
                acc[key] = value;
            }
            return acc;
        }, {});

        router.get('/transactions', cleanFilters, {
            preserveState: true,
            replace: true,
        });
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (filters.sort !== column) return <ArrowUpDown className="ml-2 h-4 w-4 text-zinc-400" />;
        return filters.direction === 'asc' ?
            <ArrowUp className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" /> :
            <ArrowDown className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" />;
    };

    const links = paginatedTransactions.meta ? paginatedTransactions.meta.links : paginatedTransactions.links;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />
            <div className="p-4 sm:p-6 lg:p-8">

                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Transactions</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Manage and track your buy, sell, and transfer transactions.
                        </p>
                    </div>
                </div>

                {/* Reusable Filters */}
                <FilterTransaction baseUrl="/transactions" filters={filters} typeOptions={typeOptions} />

                {/* Data Table */}
                <div className="bg-white dark:bg-zinc-900 border rounded-xl shadow-sm overflow-hidden text-sm">
                    <Table>
                        <TableHeader className="bg-zinc-50/50 dark:bg-zinc-900/50">
                            <TableRow>
                                <TableHead onClick={() => handleSort('date')} className="cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors bg-blue-50/10">
                                    <div className="flex items-center">Date <SortIcon column="date" /></div>
                                </TableHead>
                                <TableHead onClick={() => handleSort('type')} className="cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                    <div className="flex items-center">Type <SortIcon column="type" /></div>
                                </TableHead>
                                <TableHead onClick={() => handleSort('invoice_number')} className="cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                    <div className="flex items-center">Invoice <SortIcon column="invoice_number" /></div>
                                </TableHead>
                                <TableHead onClick={() => handleSort('grand_total')} className="text-right cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                    <div className="flex items-center justify-end">Grand Total <SortIcon column="grand_total" /></div>
                                </TableHead>
                                <TableHead className="text-right">Total Items</TableHead>
                                <TableHead>Sender</TableHead>
                                <TableHead className="text-right">Sender Bal</TableHead>
                                <TableHead>Receiver</TableHead>
                                <TableHead className="text-right border-r">Receiver Bal</TableHead>
                                <TableHead className="w-[50px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginatedTransactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-center h-48 text-muted-foreground">
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="h-10 w-10 rounded-full bg-zinc-100 flex items-center justify-center">
                                                <Search className="h-5 w-5 text-zinc-400" />
                                            </div>
                                            <p>No transactions found.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                paginatedTransactions.data.map((transaction) => (
                                    <TableRow key={transaction.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors">
                                        <TableCell className="text-zinc-500 tabular-nums whitespace-nowrap">
                                            {formatDate(transaction.date)}
                                        </TableCell>
                                        <TableCell>
                                            {getTypeLabel(transaction.type)}
                                        </TableCell>
                                        <TableCell className="font-mono text-[10px]">
                                            <Link href={transactionsRoutes.show.url({ transaction: transaction.id })} className="text-blue-600 hover:underline">
                                                {transaction.invoice_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                            {Number(transaction.grand_total).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right text-zinc-500 tabular-nums">
                                            {Number(transaction.total_items).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-zinc-700 dark:text-zinc-300 truncate max-w-[120px]">
                                            {transaction.sender?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-right text-zinc-500 italic tabular-nums">
                                            {Number(transaction.sender_balance || 0).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-zinc-700 dark:text-zinc-300 truncate max-w-[120px]">
                                            {transaction.receiver?.name || '-'}
                                        </TableCell>
                                        <TableCell className="text-right text-zinc-500 italic tabular-nums border-r">
                                            {Number(transaction.receiver_balance || 0).toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0">
                                                        <span className="sr-only">Open menu</span>
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                    <DropdownMenuItem onClick={() => router.visit(transactionsRoutes.show.url({ transaction: transaction.id }))}>
                                                        <Eye className="mr-2 h-4 w-4" /> View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    <div className="p-4 border-t bg-zinc-50/50 dark:bg-zinc-900/50">
                        {paginatedTransactions.links && (
                            <Pagination
                                links={paginatedTransactions.links || (paginatedTransactions.meta?.links)}
                                from={(paginatedTransactions as any).from || (paginatedTransactions.meta?.from)}
                                to={(paginatedTransactions as any).to || (paginatedTransactions.meta?.to)}
                                total={(paginatedTransactions as any).total || (paginatedTransactions.meta?.total)}
                                label="transactions"
                            />
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
