import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { ArrowLeft, Filter, BookOpen } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Partial/Pagination';
import { useState, FormEvent } from 'react';

interface Transaction {
    id: number;
    date: string;
    invoice_number: string | null;
    reference_number: string | null;
    type: number;
    total: number;
    sender_id: number | null;
    receiver_id: number | null;
    sender_balance: number;
    receiver_balance: number;
}

interface Account {
    id: number;
    name: string;
    description: string | null;
    operation?: { id: number; name: string };
    stat?: { balance: number };
}

interface Props {
    account: Account;
    transactions: {
        data: Transaction[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: {
        from: string;
        to: string;
    };
}

export default function LedgerIndex({ account, transactions, filters }: Props) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const handleFilter = (e: FormEvent) => {
        e.preventDefault();
        router.get(`/journals/account-list/${account.id}/ledger`, { from, to }, { preserveState: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Journals', href: '/journals/operations' },
        { title: 'Account List', href: '/journals/account-list' },
        { title: `Ledger: ${account.name}`, href: `/journals/account-list/${account.id}/ledger` },
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ledger - ${account.name}`} />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div className="flex items-center gap-4">
                        <Link href="/journals/account-list">
                            <Button variant="outline" size="icon" className="h-10 w-10">
                                <ArrowLeft className="h-5 w-5" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                                Daftar Operasi / Ledger: {account.name}
                            </h2>
                            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                Operation: {account.operation?.name || 'Uncategorized'} | Current Balance: <span className="font-semibold text-zinc-700 dark:text-zinc-300">{formatCurrency(account.stat?.balance || 0)}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mb-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm p-4">
                    <form onSubmit={handleFilter} className="flex flex-col sm:flex-row gap-4 items-end">
                        <div className="space-y-2 w-full sm:w-auto">
                            <Label htmlFor="from">From Date</Label>
                            <Input type="date" id="from" value={from} onChange={(e) => setFrom(e.target.value)} className="bg-zinc-50 dark:bg-zinc-900" />
                        </div>
                        <div className="space-y-2 w-full sm:w-auto">
                            <Label htmlFor="to">To Date</Label>
                            <Input type="date" id="to" value={to} onChange={(e) => setTo(e.target.value)} className="bg-zinc-50 dark:bg-zinc-900" />
                        </div>
                        <Button type="submit" className="w-full sm:w-auto bg-zinc-800 text-white hover:bg-zinc-700 gap-2">
                            <Filter className="h-4 w-4" />
                            Filter Ledger
                        </Button>
                    </form>
                </div>

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Date</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Invoice / Ref</th>
                                    <th className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Debit (In)</th>
                                    <th className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Credit (Out)</th>
                                    <th className="px-6 py-4 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Recorded Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                {transactions.data.map((trx) => {
                                    const isReceiver = trx.receiver_id === account.id;
                                    const debit = isReceiver ? trx.total : 0;
                                    const credit = !isReceiver ? trx.total : 0;
                                    const recordedBalance = isReceiver ? trx.receiver_balance : trx.sender_balance;

                                    return (
                                        <tr key={trx.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-300">
                                                {new Date(trx.date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-zinc-900 dark:text-zinc-300">
                                                <div className="font-semibold">{trx.invoice_number || 'N/A'}</div>
                                                <div className="text-xs text-zinc-500">{trx.reference_number}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-green-600 dark:text-green-500">
                                                {debit > 0 ? formatCurrency(debit) : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-red-600 dark:text-red-500">
                                                {credit > 0 ? formatCurrency(credit) : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                                {formatCurrency(recordedBalance)}
                                            </td>
                                        </tr>
                                    );
                                })}
                                {transactions.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                            No transactions found for this period.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {transactions.links && transactions.links.length > 3 && (
                        <div className="bg-zinc-50/30 dark:bg-zinc-900/30 px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                            <Pagination links={transactions.links} from={transactions.from} to={transactions.to} total={transactions.total} label="transactions" />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
