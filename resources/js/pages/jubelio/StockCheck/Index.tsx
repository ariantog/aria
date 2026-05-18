import { Head, Link, router } from '@inertiajs/react';
import {
    Command,
    Clock,
    CheckCircle2,
    AlertCircle,
    Plus,
    Search,
    X,
    Trash2,
    ExternalLink,
    PauseCircle,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengecekan Stok',
        href: '/jubelio-stock-checks',
    },
];

interface StockCheck {
    id: number;
    page_tracking: number;
    status: string;
    discrepancies_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    stockChecks: {
        data: StockCheck[];
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
    activeJob: StockCheck | null;
}

export default function Index({ stockChecks, activeJob }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Apakah Anda yakin ingin menghapus data pengecekan ini?')) {
            router.delete(`/jubelio-stock-checks/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengecekan Stok Jubelio" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <h1 className="flex items-center gap-2 text-2xl font-bold">
                        <Command className="h-6 w-6" />
                        Pengecekan Stok Jubelio
                    </h1>

                    <Button asChild>
                        <Link href="/jubelio-stock-checks/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Buat Pengecekan Baru
                        </Link>
                    </Button>
                </div>

                {activeJob && (
                    <div className="flex items-center justify-between rounded-lg border border-blue-500/30 bg-blue-500/10 p-4 text-blue-700 dark:text-blue-400">
                        <div className="flex items-center gap-3">
                            <Clock className="h-5 w-5 animate-pulse" />
                            <div>
                                <p className="font-bold">Pengecekan Sedang Aktif (ID: {activeJob.id})</p>
                                <p className="text-sm opacity-80">
                                    Status: <span className="uppercase">{activeJob.status}</span> | 
                                    Halaman Terakhir: {activeJob.page_tracking}
                                </p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/jubelio-stock-checks/${activeJob.id}`}>
                                Pantau Detail
                            </Link>
                        </Button>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-sidebar-border bg-sidebar shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-sidebar-accent/50 font-semibold text-sidebar-foreground uppercase">
                                <tr>
                                    <th className="px-6 py-4">ID</th>
                                    <th className="px-6 py-4">Dibuat Pada</th>
                                    <th className="px-6 py-4 text-center">Halaman</th>
                                    <th className="px-6 py-4 text-center">Ketidakcocokan</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border">
                                {stockChecks.data.map((job) => (
                                    <tr key={job.id} className="border-b border-sidebar-border/50 transition-colors hover:bg-sidebar-accent/30">
                                        <td className="px-6 py-4 font-mono font-bold">
                                            #{job.id}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {new Date(job.created_at).toLocaleString('id-ID', {
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {job.page_tracking}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <Badge variant={job.discrepancies_count > 0 ? "destructive" : "outline"}>
                                                {job.discrepancies_count}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <StatusBadge status={job.status} />
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="ghost" size="icon" asChild title="Lihat Detail">
                                                    <Link href={`/jubelio-stock-checks/${job.id}`}>
                                                        <ExternalLink className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon" 
                                                    onClick={() => handleDelete(job.id)}
                                                    className="text-red-500 hover:bg-red-500/10 hover:text-red-600"
                                                    title="Hapus"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {stockChecks.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-sidebar-foreground/50 italic">
                                            Belum ada data pengecekan stok.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4">
                    <Pagination links={stockChecks.links} />
                </div>
            </div>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    switch (status) {
        case 'completed':
            return (
                <Badge className="bg-green-500/10 text-green-500 hover:bg-green-500/20 uppercase" variant="outline">
                    <CheckCircle2 className="mr-1 h-3 w-3" /> Completed
                </Badge>
            );
        case 'processing':
            return (
                <Badge className="bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 uppercase" variant="outline">
                    <Clock className="mr-1 h-3 w-3 animate-spin" /> Processing
                </Badge>
            );
        case 'stopped':
            return (
                <Badge className="bg-yellow-500/10 text-yellow-500 hover:bg-yellow-500/20 uppercase" variant="outline">
                    <PauseCircle className="mr-1 h-3 w-3" /> Stopped (200)
                </Badge>
            );
        default:
            return (
                <Badge className="bg-gray-500/10 text-gray-500 hover:bg-gray-500/20 uppercase" variant="outline">
                    {status}
                </Badge>
            );
    }
}
