import { Head, Link, router } from '@inertiajs/react';
import {
    Search,
    Plus,
    Eye,
    MoreHorizontal,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    Trash2,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import FilterTransaction from '@/components/Partial/Filter/FilterTransaction';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import transactionsRoutes from '@/routes/transactions';

interface Transaction {
    id: number;
    date: string;
    invoice_number: string;
    type: number;
    description: string;
    notes: string;
    grand_total: number;
    total_items: number;
    sender_balance: number;
    receiver_balance: number;
    status: number;
    sender?: {
        id: number;
        name: string;
        type_slug: string;
    };
    receiver?: {
        id: number;
        name: string;
        type_slug: string;
    };
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
    can: {
        create_transaction: boolean;
        delete_transaction: boolean;
        [key: string]: boolean;
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
    { id: 7, name: 'Cash Out' },
    { id: 8, name: 'Use' },
    { id: 9, name: 'Cash In' },
    { id: 12, name: 'Adjust' },
    { id: 15, name: 'Return' },
    { id: 16, name: 'Production' },
    { id: 17, name: 'Ret. Supplier' },
    { id: 18, name: 'Depreciation' },
];

export default function Index({
    transactions: paginatedTransactions,
    filters,
    can,
}: Props) {
    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = String(date.getFullYear()).slice(-2);
        return `${day}/${month}/${year}`;
    };

    const getTypeLabel = (type: number) => {
        const getBadge = (label: string, colorClass: string, dotColor: string) => (
            <div className={`flex items-center gap-1.5 text-[13px] font-medium whitespace-nowrap ${colorClass}`}>
                <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${dotColor}`} />
                {label}
            </div>
        );

        switch (type) {
            case 1:
                return getBadge('Buy', 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-500');
            case 2:
                return getBadge('Sell', 'text-blue-600 dark:text-blue-400', 'bg-blue-500');
            case 3:
                return getBadge('Move', 'text-amber-600 dark:text-amber-400', 'bg-amber-500');
            case 6:
                return getBadge('Transfer', 'text-cyan-600 dark:text-cyan-400', 'bg-cyan-500');
            case 7:
                return getBadge('Cash Out', 'text-rose-600 dark:text-rose-400', 'bg-rose-500');
            case 8:
                return getBadge('Use', 'text-yellow-600 dark:text-yellow-400', 'bg-yellow-500');
            case 9:
                return getBadge('Cash In', 'text-purple-600 dark:text-purple-400', 'bg-purple-500');
            case 12:
                return getBadge('Adjust', 'text-indigo-600 dark:text-indigo-400', 'bg-indigo-500');
            case 15:
                return getBadge('Return', 'text-rose-600 dark:text-rose-400', 'bg-rose-500');
            case 16:
                return getBadge('Production', 'text-slate-600 dark:text-slate-400', 'bg-slate-500');
            case 17:
                return getBadge('Ret. Supplier', 'text-orange-600 dark:text-orange-400', 'bg-orange-500');
            case 18:
                return getBadge('Depreciation', 'text-zinc-600 dark:text-zinc-400', 'bg-zinc-500');
            default:
                return getBadge('Unknown', 'text-zinc-600 dark:text-zinc-400', 'bg-zinc-500');
        }
    };

    const handleSort = (column: string) => {
        const direction =
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        const mergedFilters = { ...filters, sort: column, direction };
        const cleanFilters = Object.keys(mergedFilters).reduce(
            (acc: any, key) => {
                const value = mergedFilters[key as keyof typeof mergedFilters];
                if (value !== null && value !== undefined && value !== '') {
                    acc[key] = value;
                }
                return acc;
            },
            {},
        );

        router.get('/transactions', cleanFilters, {
            preserveState: true,
            replace: true,
        });
    };

    const handleDelete = (id: number) => {
        router.delete(`/transactions/${id}`);
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (filters.sort !== column)
            return <ArrowUpDown className="ml-2 h-4 w-4 text-zinc-400" />;
        return filters.direction === 'asc' ? (
            <ArrowUp className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" />
        ) : (
            <ArrowDown className="ml-2 h-4 w-4 text-zinc-900 dark:text-zinc-100" />
        );
    };

    const links = paginatedTransactions.meta
        ? paginatedTransactions.meta.links
        : paginatedTransactions.links;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-2 overflow-x-auto rounded-xl p-2 sm:p-4">
                {/* Header */}
                <div className="mb-4 flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Transactions
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Manage and track your buy, sell, and transfer
                            transactions.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {can.delete_transaction && (
                            <Link href="/transactions/deleted">
                                <Button
                                    variant="outline"
                                    className="flex items-center gap-2 border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:border-rose-900/50 dark:text-rose-400"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    View Deleted
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Reusable Filters */}
                <FilterTransaction
                    baseUrl="/transactions"
                    filters={filters}
                    typeOptions={typeOptions}
                />

                {/* Data Table */}
                <div className="overflow-hidden border bg-white text-[13px] shadow-sm dark:bg-zinc-900">
                    <Table
                        wrapperClassName="max-h-[60vh] md:max-h-[calc(100vh-280px)] overflow-auto"
                        className="border-separate border-spacing-0"
                    >
                        <TableHeader className="bg-zinc-50 dark:bg-zinc-900">
                            <TableRow>
                                <TableHead
                                    onClick={() => handleSort('date')}
                                    className="sticky top-0 z-20 cursor-pointer whitespace-nowrap border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                >
                                    <div className="flex items-center">
                                        Date <SortIcon column="date" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    onClick={() => handleSort('invoice_number')}
                                    className="sticky top-0 z-20 w-[110px] cursor-pointer border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                >
                                    <div className="flex items-center">
                                        Invoice{' '}
                                        <SortIcon column="invoice_number" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    onClick={() => handleSort('type')}
                                    className="sticky top-0 z-20 w-[60px] cursor-pointer border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                >
                                    <div className="flex items-center">
                                        Type <SortIcon column="type" />
                                    </div>
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 max-w-[150px] border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase dark:bg-zinc-900">
                                    Description
                                </TableHead>
                                <TableHead
                                    onClick={() => handleSort('grand_total')}
                                    className="sticky top-0 z-20 cursor-pointer border-b bg-zinc-50 py-3 px-2 text-right text-[13px] uppercase transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                >
                                    <div className="flex items-center justify-end whitespace-nowrap">
                                        Grand Total{' '}
                                        <SortIcon column="grand_total" />
                                    </div>
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-right text-[13px] uppercase dark:bg-zinc-900">
                                    Items
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase dark:bg-zinc-900">Sender</TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-right text-[13px] uppercase dark:bg-zinc-900">
                                    Sender Bal
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase dark:bg-zinc-900">Receiver</TableHead>
                                <TableHead className="sticky top-0 z-20 border-b bg-zinc-50 py-3 px-2 text-right text-[13px] uppercase dark:bg-zinc-900">
                                    Receiver Bal
                                </TableHead>
                                <TableHead className="sticky top-0 z-20 w-[40px] border-b bg-zinc-50 py-3 px-2 text-[13px] uppercase dark:bg-zinc-900"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginatedTransactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={11}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100">
                                                <Search className="h-4 w-4 text-zinc-400" />
                                            </div>
                                            <p>No transactions found.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                paginatedTransactions.data.map(
                                    (transaction) => (
                                        <TableRow
                                            key={transaction.id}
                                            className="transition-colors hover:bg-zinc-100/50 dark:hover:bg-zinc-800/50"
                                        >
                                            <TableCell className="whitespace-nowrap py-3 px-2 text-[13px] text-zinc-500 tabular-nums">
                                                {formatDate(transaction.date)}
                                            </TableCell>
                                            <TableCell className="min-w-[110px] py-3 px-2 font-mono text-[13px] whitespace-normal break-words">
                                                <Link
                                                    href={transactionsRoutes.show.url(
                                                        {
                                                            transaction:
                                                                transaction.id,
                                                        },
                                                    )}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {transaction.invoice_number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="min-w-[60px] py-3 px-2 text-[13px] whitespace-normal break-words">
                                                {getTypeLabel(transaction.type)}
                                            </TableCell>
                                            <TableCell className="max-w-[150px] py-3 px-2 text-[13px] text-zinc-500 whitespace-normal break-words leading-tight">
                                                {transaction.description ||
                                                    transaction.notes ||
                                                    '-'}
                                            </TableCell>
                                            <TableCell className="py-3 px-2 text-right text-[13px] font-bold text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {Number(
                                                    transaction.grand_total,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="py-3 px-2 text-right text-[13px] text-zinc-500 tabular-nums">
                                                {Number(
                                                    transaction.total_items,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="min-w-[100px] max-w-[150px] whitespace-normal break-words py-3 px-2 text-[13px] leading-tight">
                                                {transaction.sender ? (
                                                    <Link
                                                        href={`/${transaction.sender.type_slug}/${transaction.sender.id}`}
                                                        className="text-blue-600 hover:underline dark:text-blue-500"
                                                    >
                                                        {transaction.sender.name}
                                                    </Link>
                                                ) : (
                                                    '-'
                                                )}
                                            </TableCell>
                                            <TableCell className="py-3 px-2 text-right text-[13px] font-medium text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {can.bank_hidden_balance && transaction.sender?.type_slug === 'bank'
                                                    ? '0'
                                                    : Number(
                                                        transaction.sender_balance || 0,
                                                    ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="min-w-[100px] max-w-[150px] whitespace-normal break-words py-3 px-2 text-[13px] leading-tight">
                                                {transaction.receiver ? (
                                                    <Link
                                                        href={`/${transaction.receiver.type_slug}/${transaction.receiver.id}`}
                                                        className="text-blue-600 hover:underline dark:text-blue-500"
                                                    >
                                                        {transaction.receiver.name}
                                                    </Link>
                                                ) : (
                                                    '-'
                                                )}
                                            </TableCell>
                                            <TableCell className="py-3 px-2 text-right text-[13px] font-medium text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {can.bank_hidden_balance && transaction.receiver?.type_slug === 'bank'
                                                    ? '0'
                                                    : Number(
                                                        transaction.receiver_balance || 0,
                                                    ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="py-3 px-2">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            className="h-6 w-6 p-0"
                                                        >
                                                            <span className="sr-only">
                                                                Open menu
                                                            </span>
                                                            <MoreHorizontal className="h-3 w-3" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>
                                                            Actions
                                                        </DropdownMenuLabel>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                router.visit(
                                                                    transactionsRoutes.show.url(
                                                                        {
                                                                            transaction:
                                                                                transaction.id,
                                                                        },
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Eye className="mr-2 h-4 w-4" />{' '}
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        {can.delete_transaction && (
                                                            <ConfirmDialog
                                                                onConfirm={() =>
                                                                    handleDelete(
                                                                        transaction.id,
                                                                    )
                                                                }
                                                                title="Hapus Transaksi"
                                                                description="Apakah Anda yakin ingin menghapus transaksi ini? Transaksi akan dipindahkan ke daftar hapus dan dampak stok/saldo akan dibatalkan."
                                                                trigger={
                                                                    <DropdownMenuItem
                                                                        className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:text-rose-400 dark:focus:bg-rose-900/20"
                                                                        onSelect={(
                                                                            e,
                                                                        ) =>
                                                                            e.preventDefault()
                                                                        }
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />{' '}
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                }
                                                            />
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ),
                                )
                            )}
                        </TableBody>
                    </Table>
                    {/* Pagination */}
                    <div className="border-t bg-zinc-50/50 p-4 dark:bg-zinc-900/50">
                        {paginatedTransactions.links && (
                            <Pagination
                                links={
                                    paginatedTransactions.links ||
                                    paginatedTransactions.meta?.links
                                }
                                from={
                                    (paginatedTransactions as any).from ||
                                    paginatedTransactions.meta?.from
                                }
                                to={
                                    (paginatedTransactions as any).to ||
                                    paginatedTransactions.meta?.to
                                }
                                total={
                                    (paginatedTransactions as any).total ||
                                    paginatedTransactions.meta?.total
                                }
                                label="transactions"
                            />
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
