import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Calendar, Plus, UserCircle, Scissors, Pencil, Send } from 'lucide-react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import { AsyncCombobox } from '@/components/AsyncCombobox';

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
}

export default function ProduksiIndex({ produksis, jahitList }: Props) {
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [selectedProduksi, setSelectedProduksi] = useState<Produksi | null>(null);
    const [selectedWorker, setSelectedWorker] = useState<any | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '#' },
        { title: 'Potong', href: '#' },
        { title: 'Entries', href: '/produksi' },
    ];

    const handleAssignClick = (p: Produksi) => {
        setSelectedProduksi(p);
        setSelectedWorker(null);
        setIsAssignModalOpen(true);
    };

    const handleSaveAssignment = () => {
        if (selectedProduksi && selectedWorker) {
            router.patch(`/produksi/${selectedProduksi.id}/jahit`, { jahit_id: selectedWorker.id }, {
                preserveScroll: true,
                onSuccess: () => setIsAssignModalOpen(false)
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Potong Production" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Potong Production</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">List of production records at the cutting stage.</p>
                    </div>
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <Button asChild className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white shadow-sm gap-2">
                            <Link href="/produksi/create">
                                <Plus className="h-4 w-4" />
                                New Production Entry
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Kitir</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Kode</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Jumlah</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">SJP</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Potong</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Size</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Warna</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Customer</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-center">Jahit</th>
                                    <th className="px-6 py-4 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                {produksis.data.map((p) => (
                                    <tr key={p.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 dark:text-blue-400">
                                            {p.serial}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <div className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-100 border border-blue-200 rounded-md dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800/50">
                                                    {p.temp_name}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center gap-1.5 text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                {p.quantity}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400">
                                            {p.surat_jalan_potong || '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex flex-col items-center justify-center p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded-md border border-zinc-200 dark:border-zinc-800">
                                                <span className="text-[11px] font-medium text-zinc-500 dark:text-zinc-400 mb-0.5 whitespace-nowrap">
                                                    {p.potong_date ? new Date(p.potong_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'}
                                                </span>
                                                {p.potong && (
                                                    <span className="text-xs font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded shadow-sm border border-zinc-100 dark:border-zinc-800 w-full text-center truncate max-w-[100px]" title={p.potong.name}>
                                                        {p.potong.name}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge variant="outline" className="font-mono bg-zinc-50 dark:bg-zinc-800/50">
                                                {p.size?.name || '-'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400 font-bold">
                                            {p.warna || '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-100 font-bold">
                                            {p.customer || '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-center">
                                            {p.jahit_date ? (
                                                <div className="flex flex-col items-center justify-center p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded-md border border-zinc-200 dark:border-zinc-800">
                                                    <span className="text-[11px] font-medium text-zinc-500 dark:text-zinc-400 mb-0.5 whitespace-nowrap">
                                                        {new Date(p.jahit_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                                                    </span>
                                                    {p.jahit && (
                                                        <span className="text-xs font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded shadow-sm border border-zinc-100 dark:border-zinc-800 w-full text-center truncate max-w-[100px]" title={p.jahit.name}>
                                                            {p.jahit.name}
                                                        </span>
                                                    )}
                                                </div>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-8 px-4 border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-bold gap-1.5 shadow-sm"
                                                    onClick={() => handleAssignClick(p)}
                                                >
                                                    <Scissors className="h-3.5 w-3.5" />
                                                    Assign
                                                </Button>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-center">
                                            <div className="flex items-center justify-center gap-1.5">
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    asChild
                                                    title="Edit"
                                                    className="h-8 w-8 rounded-md border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                >
                                                    <Link href={`/produksi/${p.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {p.jahit_date && (
                                                    <Button
                                                        size="icon"
                                                        variant="destructive"
                                                        title="Setor ke Jahit"
                                                        className="h-8 w-8 rounded-md shadow-sm bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700 text-white"
                                                        onClick={() => router.patch(`/produksi/${p.id}/setor`)}
                                                    >
                                                        <Send className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {produksis.data.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
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
                    <div className="bg-zinc-50/30 dark:bg-zinc-900/30 px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                        <Pagination links={produksis.links} from={produksis.from} to={produksis.to} total={produksis.total} label="records" />
                    </div>
                )}
            </div>

            {/* Assign Jahit Modal */}
            <Dialog open={isAssignModalOpen} onOpenChange={setIsAssignModalOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle className="text-xl">Assign Jahit Worker</DialogTitle>
                        <DialogDescription className="text-zinc-500 dark:text-zinc-400 mt-2">
                            Select a worker to assign to the <span className="font-semibold text-emerald-600 dark:text-emerald-400">Jahit</span> stage.
                            {selectedProduksi && (
                                <div className="mt-4 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-800 flex flex-col gap-1 text-left">
                                    <span className="text-xs uppercase font-bold text-zinc-400 dark:text-zinc-500 tracking-wider">Selected Item</span>
                                    <span className="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">{selectedProduksi.temp_name}</span>
                                    <span className="text-xs font-mono text-zinc-500 dark:text-zinc-400">Kitir: {selectedProduksi.serial}</span>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <div className="space-y-3">
                            <label htmlFor="worker" className="text-sm font-semibold text-zinc-900 dark:text-zinc-100 block">
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
                    <DialogFooter className="gap-2 sm:gap-0 mt-2">
                        <Button variant="outline" className="font-semibold" onClick={() => setIsAssignModalOpen(false)}>Cancel</Button>
                        <Button
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6"
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
