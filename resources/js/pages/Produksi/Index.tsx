import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    Plus,
    UserCircle,
    Scissors,
    Pencil,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import ProduksiFilter from '@/components/Partial/Filter/ProduksiFilter';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Produksi {
    id: number;
    serial: string;
    temp_name: string;
    quantity: number;
    customer: string | null;
    warna: string | null;
    potong_date: string;
    surat_jalan_potong: string | null;
    jahit_date: string | null;
    qc_date: string | null;
    potong: {
        id: number;
        name: string;
    } | null;
    jahit: {
        id: number;
        name: string;
    } | null;
    qc: {
        id: number;
        name: string;
    } | null;
    size: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    produksis: {
        data: Produksi[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    jahitList: { id: number; name: string }[];
    filters: any;
    can: {
        create_produksi: boolean;
        edit_produksi: boolean;
        delete_produksi: boolean;
        setor_produksi: boolean;
    };
}

export default function ProduksiIndex({
    produksis,
    jahitList,
    filters,
    can,
}: Props) {
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [selectedProduksi, setSelectedProduksi] = useState<Produksi | null>(
        null,
    );
    const [selectedWorker, setSelectedWorker] = useState<any | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '/produksi' },
    ];

    const handleAssignClick = (p: Produksi) => {
        setSelectedProduksi(p);
        setSelectedWorker(null);
        setIsAssignModalOpen(true);
    };

    const handleSaveAssignment = () => {
        if (selectedProduksi && selectedWorker) {
            router.patch(
                `/produksi/${selectedProduksi.id}/jahit`,
                { jahit_id: selectedWorker.id },
                {
                    preserveScroll: true,
                    onSuccess: () => setIsAssignModalOpen(false),
                },
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Production" />

            <div className="flex h-full flex-1 flex-col gap-2 overflow-x-auto rounded-xl p-2 sm:p-4">
                {/* Header */}
                <div className="mb-4 flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                            Produksi
                        </h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            List of production records at the cutting stage.
                        </p>
                    </div>
                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        {can.create_produksi && (
                            <Button
                                asChild
                                className="w-full gap-2 bg-blue-600 text-white shadow-sm hover:bg-blue-700 sm:w-auto"
                            >
                                <Link href="/produksi/create">
                                    <Plus className="h-4 w-4" />
                                    New Production Entry
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <ProduksiFilter filters={filters} />

                {/* Table Card */}
                <div className="overflow-hidden border bg-white text-[11px] shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="max-h-[60vh] overflow-auto md:max-h-[calc(100vh-280px)]">
                        <table className="w-full border-separate border-spacing-0 text-left">
                            <thead className="bg-zinc-50 dark:bg-zinc-900">
                                <tr>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Kitir
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Kode
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Jumlah
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        SJP
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Potong
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Size
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Warna
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Customer
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-center text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Jahit
                                    </th>
                                    <th className="sticky top-0 z-20 border-b bg-zinc-50 px-2 py-3 text-center text-[11px] font-bold tracking-wider text-zinc-500 uppercase whitespace-nowrap dark:bg-zinc-900">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-800/50">
                                {produksis.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className="sticky left-0 z-10 border-r bg-white px-2 py-1 font-mono text-[11px] whitespace-nowrap text-blue-600 dark:bg-zinc-900 dark:text-blue-400">
                                            {p.serial}
                                        </td>
                                        <td className="px-2 py-1 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-100 px-2.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:border-blue-800/50 dark:bg-blue-900/40 dark:text-blue-300">
                                                    {p.temp_name}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-2 py-1 whitespace-nowrap">
                                            <div className="flex items-center gap-1.5 text-[11px] font-bold text-zinc-900 tabular-nums dark:text-zinc-100">
                                                {p.quantity}
                                            </div>
                                        </td>
                                        <td className="px-2 py-1 text-[11px] whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                            {p.surat_jalan_potong || '-'}
                                        </td>
                                        <td className="px-2 py-1 whitespace-nowrap">
                                            <div className="flex flex-col items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-800 dark:bg-zinc-800/50">
                                                <span className="mb-0.5 text-[9px] font-medium whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                                    {p.potong_date
                                                        ? new Date(
                                                              p.potong_date,
                                                          ).toLocaleDateString(
                                                              'id-ID',
                                                              {
                                                                  day: '2-digit',
                                                                  month: 'short',
                                                                  year: 'numeric',
                                                              },
                                                          )
                                                        : '-'}
                                                </span>
                                                {p.potong && (
                                                    <span
                                                        className="w-full max-w-[100px] truncate rounded border border-zinc-100 bg-white px-1.5 py-0.5 text-center text-[9px] font-bold text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                                                        title={p.potong.name}
                                                    >
                                                        {p.potong.name}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-2 py-1 whitespace-nowrap">
                                            <Badge
                                                variant="outline"
                                                className="bg-zinc-50 font-mono text-[9px] px-1 py-0 dark:bg-zinc-800/50"
                                            >
                                                {p.size?.name || '-'}
                                            </Badge>
                                        </td>
                                        <td className="px-2 py-1 text-[11px] font-bold whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                            {p.warna || '-'}
                                        </td>
                                        <td className="px-2 py-1 text-[11px] font-bold whitespace-normal break-words max-w-[150px] leading-tight text-zinc-600 dark:text-zinc-100">
                                            {p.customer || '-'}
                                        </td>
                                        <td className="px-2 py-1 text-center whitespace-nowrap">
                                            {p.jahit_date ? (
                                                <div className="flex flex-col items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-800 dark:bg-zinc-800/50">
                                                    <span className="mb-0.5 text-[9px] font-medium whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                                        {new Date(
                                                            p.jahit_date,
                                                        ).toLocaleDateString(
                                                            'id-ID',
                                                            {
                                                                day: '2-digit',
                                                                month: 'short',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </span>
                                                    {p.jahit && (
                                                        <span
                                                            className="w-full max-w-[100px] truncate rounded border border-zinc-100 bg-white px-1.5 py-0.5 text-center text-[9px] font-bold text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                                                            title={p.jahit.name}
                                                        >
                                                            {p.jahit.name}
                                                        </span>
                                                    )}
                                                </div>
                                            ) : can.setor_produksi ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-6 gap-1 border-emerald-200 px-2 text-[9px] font-bold text-emerald-700 shadow-sm hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                                    onClick={() =>
                                                        handleAssignClick(p)
                                                    }
                                                >
                                                    <Scissors className="h-3 w-3" />
                                                    Assign
                                                </Button>
                                            ) : (
                                                <span className="text-[11px] text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-2 py-1 text-center whitespace-nowrap">
                                            <div className="flex items-center justify-center gap-1">
                                                {can.edit_produksi && (
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        asChild
                                                        title="Edit"
                                                        className="h-6 w-6 rounded-md border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                                    >
                                                        <Link
                                                            href={`/produksi/${p.id}/edit`}
                                                        >
                                                            <Pencil className="h-3 w-3" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can.setor_produksi &&
                                                    p.jahit_date && (
                                                        <Button
                                                            size="icon"
                                                            variant="destructive"
                                                            title="Setor ke Jahit"
                                                            className="h-6 w-6 rounded-md bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                                            onClick={() =>
                                                                router.patch(
                                                                    `/produksi/${p.id}/setor`,
                                                                )
                                                            }
                                                        >
                                                            <Send className="h-3 w-3" />
                                                        </Button>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {produksis.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                        >
                                            No production records found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {produksis.links && produksis.links.length > 3 && (
                    <div className="border-t border-zinc-200 bg-zinc-50/30 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/30">
                        <Pagination
                            links={produksis.links}
                            from={produksis.from}
                            to={produksis.to}
                            total={produksis.total}
                            label="records"
                        />
                    </div>
                )}
            </div>

            {/* Assign Jahit Modal */}
            <Dialog
                open={isAssignModalOpen}
                onOpenChange={setIsAssignModalOpen}
            >
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle className="text-xl">
                            Assign Jahit Worker
                        </DialogTitle>
                        <DialogDescription className="mt-2 text-zinc-500 dark:text-zinc-400">
                            Select a worker to assign to the{' '}
                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                Jahit
                            </span>{' '}
                            stage.
                            {selectedProduksi && (
                                <div className="mt-4 flex flex-col gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-left dark:border-zinc-800 dark:bg-zinc-800/50">
                                    <span className="text-xs font-bold tracking-wider text-zinc-400 uppercase dark:text-zinc-500">
                                        Selected Item
                                    </span>
                                    <span className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {selectedProduksi.temp_name}
                                    </span>
                                    <span className="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                        Kitir: {selectedProduksi.serial}
                                    </span>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <div className="space-y-3">
                            <label
                                htmlFor="worker"
                                className="block text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                            >
                                Worker Name
                            </label>
                            <AsyncCombobox
                                endpoint="/produksi/workers/lookup"
                                additionalParams={{ type: 'jahit' }}
                                placeholder="Search worker..."
                                value={selectedWorker}
                                onChange={(worker) => setSelectedWorker(worker)}
                                className="w-full"
                            />
                        </div>
                    </div>
                    <DialogFooter className="mt-2 gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            className="font-semibold"
                            onClick={() => setIsAssignModalOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="bg-emerald-600 px-6 font-bold text-white hover:bg-emerald-700"
                            onClick={handleSaveAssignment}
                            disabled={!selectedWorker}
                        >
                            Assign Worker
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
