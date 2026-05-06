import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/Partial/Pagination';
import { RotateCcw, Trash2, ArrowLeft, Search } from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';

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
    deleted_at: string;
    sender?: { name: string };
    receiver?: { name: string };
}

interface Props {
    transactions: {
        data: Transaction[];
        links: any[];
        meta?: any;
    };
}

const breadcrumbs = [
    { title: 'Transactions', href: '/transactions' },
    { title: 'Deleted', href: '/transactions/deleted' },
];

export default function DeletedIndex({
    transactions: paginatedTransactions,
}: Props) {
    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = String(date.getFullYear()).slice(-2);
        return `${day}/${month}/${year}`;
    };

    const formatDateTime = (dateString: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const handleRestore = (id: number) => {
        router.post(`/transactions/deleted/${id}/restore`);
    };

    const getTypeLabel = (type: number) => {
        switch (type) {
            case 1:
                return (
                    <Badge className="border-emerald-200 bg-emerald-100 text-center whitespace-normal text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        Beli
                    </Badge>
                );
            case 2:
                return (
                    <Badge className="border-blue-200 bg-blue-100 text-center whitespace-normal text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                        Jual
                    </Badge>
                );
            case 3:
                return (
                    <Badge className="border-amber-200 bg-amber-100 text-center whitespace-normal text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                        Pindah
                    </Badge>
                );
            case 6:
                return (
                    <Badge className="border-cyan-200 bg-cyan-100 text-center text-[10px] whitespace-normal text-cyan-700 uppercase dark:bg-cyan-500/10 dark:text-cyan-400">
                        Transfer
                    </Badge>
                );
            case 12:
                return (
                    <Badge className="border-indigo-200 bg-indigo-100 text-center text-[10px] whitespace-normal text-indigo-700 uppercase dark:bg-indigo-500/10 dark:text-indigo-400">
                        Penyesuaian
                    </Badge>
                );
            case 15:
                return (
                    <Badge className="border-rose-200 bg-rose-100 text-center whitespace-normal text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                        Retur
                    </Badge>
                );
            case 16:
                return (
                    <Badge className="border-slate-200 bg-slate-100 text-center text-[10px] whitespace-normal text-slate-700 uppercase dark:bg-slate-500/10 dark:text-slate-400">
                        Produksi
                    </Badge>
                );
            case 17:
                return (
                    <Badge className="border-orange-200 bg-orange-100 text-center text-[10px] whitespace-normal text-orange-700 uppercase dark:bg-orange-500/10 dark:text-orange-400">
                        Ret. Supplier
                    </Badge>
                );
            case 9:
                return (
                    <Badge className="border-purple-200 bg-purple-100 text-center text-[10px] whitespace-normal text-purple-700 uppercase dark:bg-purple-500/10 dark:text-purple-400">
                        Kas Masuk
                    </Badge>
                );
            case 10:
                return (
                    <Badge className="border-rose-200 bg-rose-100 text-center text-[10px] whitespace-normal text-rose-700 uppercase dark:bg-rose-500/10 dark:text-rose-400">
                        Kas Keluar
                    </Badge>
                );
            default:
                return (
                    <Badge
                        variant="outline"
                        className="text-center whitespace-normal"
                    >
                        Tidak Diketahui
                    </Badge>
                );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transaksi Terhapus" />
            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Transaksi Terhapus
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Daftar transaksi yang telah dihapus. Anda dapat
                            memulihkannya di sini.
                        </p>
                    </div>
                    <Link href="/transactions">
                        <Button
                            variant="outline"
                            className="flex items-center gap-2"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Kembali ke Transaksi
                        </Button>
                    </Link>
                </div>

                {/* Data Table */}
                <div className="overflow-hidden rounded-xl border bg-white text-sm shadow-sm dark:bg-zinc-900">
                    <Table>
                        <TableHeader className="bg-zinc-50/50 dark:bg-zinc-900/50">
                            <TableRow>
                                <TableHead className="bg-blue-50/10 whitespace-nowrap">
                                    Timeline
                                </TableHead>
                                <TableHead className="w-[80px]">Tipe</TableHead>
                                <TableHead className="w-[120px]">
                                    Invoice
                                </TableHead>
                                <TableHead className="max-w-[150px]">
                                    Deskripsi
                                </TableHead>
                                <TableHead className="text-right">
                                    Total Akhir
                                </TableHead>
                                <TableHead className="text-right">
                                    Total Item
                                </TableHead>
                                <TableHead>Pengirim</TableHead>
                                <TableHead className="text-right">
                                    Saldo Pengirim
                                </TableHead>
                                <TableHead>Penerima</TableHead>
                                <TableHead className="border-r text-right">
                                    Saldo Penerima
                                </TableHead>
                                <TableHead className="w-[100px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginatedTransactions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={12}
                                        className="h-48 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100">
                                                <Search className="h-5 w-5 text-zinc-400" />
                                            </div>
                                            <p>
                                                Tidak ada transaksi terhapus
                                                ditemukan.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                paginatedTransactions.data.map(
                                    (transaction) => (
                                        <TableRow
                                            key={transaction.id}
                                            className="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50"
                                        >
                                            <TableCell className="py-1 whitespace-nowrap tabular-nums">
                                                <Link
                                                    href={`/transactions/deleted/${transaction.id}`}
                                                    className="flex flex-col gap-0.5 hover:opacity-80"
                                                >
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="w-8 text-[10px] font-black tracking-tighter text-blue-500 uppercase opacity-70">
                                                            Trans:
                                                        </span>
                                                        <span className="text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                                            {formatDate(
                                                                transaction.date,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="w-8 text-[10px] font-black tracking-tighter text-red-500 uppercase opacity-70">
                                                            Hps:
                                                        </span>
                                                        <span className="text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                                            {formatDate(
                                                                transaction.deleted_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                </Link>
                                            </TableCell>
                                            <TableCell className="min-w-[80px] py-1 break-words whitespace-normal">
                                                {getTypeLabel(transaction.type)}
                                            </TableCell>
                                            <TableCell className="min-w-[120px] py-1 font-mono text-[10px] break-words whitespace-normal">
                                                <Link
                                                    href={`/transactions/deleted/${transaction.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {transaction.invoice_number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="max-w-[150px] py-1 text-[10px] break-words whitespace-normal text-zinc-500">
                                                {transaction.description ||
                                                    transaction.notes ||
                                                    '-'}
                                            </TableCell>
                                            <TableCell className="text-right font-bold text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {Number(
                                                    transaction.grand_total,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right text-zinc-500 tabular-nums">
                                                {Number(
                                                    transaction.total_items,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="max-w-[120px] truncate text-zinc-700 dark:text-zinc-300">
                                                {transaction.sender?.name ||
                                                    '-'}
                                            </TableCell>
                                            <TableCell className="text-right text-zinc-500 italic tabular-nums">
                                                {Number(
                                                    transaction.sender_balance ||
                                                        0,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="max-w-[120px] truncate text-zinc-700 dark:text-zinc-300">
                                                {transaction.receiver?.name ||
                                                    '-'}
                                            </TableCell>
                                            <TableCell className="border-r text-right text-zinc-500 italic tabular-nums">
                                                {Number(
                                                    transaction.receiver_balance ||
                                                        0,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                <ConfirmDialog
                                                    onConfirm={() =>
                                                        handleRestore(
                                                            transaction.id,
                                                        )
                                                    }
                                                    title="Pulihkan Transaksi"
                                                    description="Apakah Anda yakin ingin memulihkan transaksi ini? Transaksi akan dikembalikan ke daftar aktif."
                                                    confirmText="Pulihkan"
                                                    destructive={false}
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="flex items-center gap-2 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/20"
                                                        >
                                                            <RotateCcw className="h-4 w-4" />
                                                            Pulihkan
                                                        </Button>
                                                    }
                                                />
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
                                label="transaksi terhapus"
                            />
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
