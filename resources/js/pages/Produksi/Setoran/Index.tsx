import { useState, FormEvent } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Calendar, CheckCircle2, Search, Filter, RefreshCcw, ArrowLeft, ArrowRight, X } from 'lucide-react';
import Pagination from '@/components/Partial/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import FormInput from '@/components/Partial/Form/FormInput';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';

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
    setor_date: string | null;
    qc_date: string | null;
    status: number;
    invoice: string | null;
    item_id: number | null;
    potong: { id: number; name: string; } | null;
    jahit: { id: number; name: string; } | null;
    qc: { id: number; name: string; } | null;
    size: { id: number; name: string; } | null;
    item: { id: number; name: string; code: string; item_code: string; } | null;
}

interface Props {
    produksis: {
        data: Produksi[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: any;
    jahitList: any[];
    potongList: any[];
    statusList: any[];
    statusGudang: number;
    statusBoth: number;
}

export default function SetoranIndex({ produksis, filters, jahitList, potongList, statusList, statusGudang, statusBoth }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Produksi', href: '#' },
        { title: 'Setoran', href: '/produksi/setoran' },
    ];

    const [showFilters, setShowFilters] = useState(false);

    // Filter Form
    const { data: filterData, setData: setFilterData, get: submitFilters, reset: resetFilters } = useForm({
        from: filters?.from || '',
        to: filters?.to || '',
        potong_id: filters?.potong_id || '',
        jahit_id: filters?.jahit_id || '',
        customer: filters?.customer || '',
        warna: filters?.warna || '',
        kode: filters?.kode || '',
        surat_jalan_potong: filters?.surat_jalan_potong || '',
        serial: filters?.serial || '',
        invoice: filters?.invoice || '',
        status: filters?.status || '',
    });

    const handleFilter = (e: FormEvent) => {
        e.preventDefault();
        submitFilters('/produksi/setoran', { preserveState: true });
    };

    const handleClearFilters = () => {
        router.get('/produksi/setoran');
    };

    // Update Item Kode Form
    const [updateModalOpen, setUpdateModalOpen] = useState(false);
    const [selectedProduksi, setSelectedProduksi] = useState<Produksi | null>(null);
    const { data: updateData, setData: setUpdateData, patch: submitUpdate, processing: updating, reset: resetUpdate } = useForm({
        item_id: '',
    });

    const openUpdateModal = (p: Produksi) => {
        setSelectedProduksi(p);
        resetUpdate();
        setUpdateModalOpen(true);
    };

    const handleUpdateSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!selectedProduksi || !updateData.item_id) return;
        submitUpdate(`/produksi/setoran/${selectedProduksi.id}/edit-item`, {
            preserveScroll: true,
            onSuccess: () => {
                setUpdateModalOpen(false);
                toast.success('Kode otomatis diupdate.');
            },
            onError: (err) => {
                const errMsg = err.item_id || err.error || 'Gagal update kode item.';
                toast.error(errMsg);
                console.error(err);
            }
        });
    };

    // To Gudang Form
    const [gudangModalOpen, setGudangModalOpen] = useState(false);
    const { data: gudangData, setData: setGudangData, patch: submitGudang, processing: gudangding, reset: resetGudang } = useForm({
        invoice: '',
    });

    const openGudangModal = (p: Produksi) => {
        setSelectedProduksi(p);
        setGudangData('invoice', p.invoice || '');
        setGudangModalOpen(true);
    };

    const handleGudangSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!selectedProduksi || !gudangData.invoice) return;
        submitGudang(`/produksi/setoran/${selectedProduksi.id}/gudang`, {
            preserveScroll: true,
            onSuccess: () => {
                setGudangModalOpen(false);
                toast.success('Berhasil masuk Gudang.');
            },
            onError: (err) => {
                const errMsg = err.invoice || err.error || 'Gagal pindah ke gudang.';
                toast.error(errMsg);
                console.error(err);
            }
        });
    };

    const getRowColor = (status: number) => {
        if (status === statusGudang) return 'bg-teal-100 dark:bg-teal-900/30 hover:bg-teal-200 dark:hover:bg-teal-800/40';
        if (status === statusBoth) return 'bg-lime-200 dark:bg-lime-900/30 hover:bg-lime-300 dark:hover:bg-lime-800/40';
        return 'hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setoran Production" />

            <div className="p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Setoran Production</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage and filter completed production records.</p>
                    </div>
                    <Button onClick={() => setShowFilters(!showFilters)} variant="outline" className="gap-2">
                        <Filter className="h-4 w-4" />
                        {showFilters ? 'Hide Filters' : 'Show Filters'}
                    </Button>
                </div>

                {/* Filters */}
                {showFilters && (
                    <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 mb-6 shadow-sm">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <FormInput id="from" label="From Date" type="date" value={filterData.from} onChange={e => setFilterData('from', e.target.value)} />
                            <FormInput id="to" label="To Date" type="date" value={filterData.to} onChange={e => setFilterData('to', e.target.value)} />

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="potong_id">Potong</Label>
                                <select id="potong_id" className="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2 dark:border-zinc-800 dark:bg-zinc-950 dark:ring-offset-zinc-950 dark:focus-visible:ring-zinc-300" value={filterData.potong_id} onChange={e => setFilterData('potong_id', e.target.value)}>
                                    <option value="">All</option>
                                    {potongList.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
                                </select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="jahit_id">Jahit</Label>
                                <select id="jahit_id" className="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2 dark:border-zinc-800 dark:bg-zinc-950 dark:ring-offset-zinc-950 dark:focus-visible:ring-zinc-300" value={filterData.jahit_id} onChange={e => setFilterData('jahit_id', e.target.value)}>
                                    <option value="">All</option>
                                    {jahitList.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
                                </select>
                            </div>

                            <FormInput id="customer" label="Customer" value={filterData.customer} onChange={e => setFilterData('customer', e.target.value)} />
                            <FormInput id="warna" label="Warna" value={filterData.warna} onChange={e => setFilterData('warna', e.target.value)} />
                            <FormInput id="kode" label="Kode" value={filterData.kode} onChange={e => setFilterData('kode', e.target.value)} />
                            <FormInput id="sjp" label="Surat Jalan Potong" value={filterData.surat_jalan_potong} onChange={e => setFilterData('surat_jalan_potong', e.target.value)} />

                            <FormInput id="serial" label="Serial" value={filterData.serial} onChange={e => setFilterData('serial', e.target.value)} />
                            <FormInput id="invoice" label="Invoice" value={filterData.invoice} onChange={e => setFilterData('invoice', e.target.value)} />

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                <select id="status" className="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2 dark:border-zinc-800 dark:bg-zinc-950 dark:ring-offset-zinc-950 dark:focus-visible:ring-zinc-300" value={filterData.status} onChange={e => setFilterData('status', e.target.value)}>
                                    <option value="">All</option>
                                    {statusList.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>

                            <div className="col-span-full flex justify-end gap-2 mt-2">
                                <Button type="button" variant="outline" onClick={handleClearFilters}>Clear</Button>
                                <Button type="submit" className="gap-2"><Search className="h-4 w-4" /> Search</Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Table Card */}
                <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead className="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Serial</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Kode</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Potong</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase whitespace-nowrap">SJP</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Jumlah</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Size</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Warna</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Costumer</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">Jahit</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase">QC</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase whitespace-nowrap">Invoice</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200 dark:divide-zinc-800">
                                {produksis.data.map((p) => {
                                    const isGudangOrBoth = p.status === statusGudang || p.status === statusBoth;

                                    return (
                                        <tr key={p.id} className={`transition-colors ${getRowColor(p.status)}`}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-bold text-blue-600 dark:text-blue-400">
                                                <Link href={`/produksi/setoran/${p.id}/edit`} className="hover:underline">
                                                    {p.serial}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {isGudangOrBoth ? (
                                                    <div className="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                        {p.item?.item_code || p.temp_name}
                                                    </div>
                                                ) : (
                                                    p.item_id ? (
                                                        <div className="text-sm font-bold text-green-600 dark:text-green-500 bg-green-50 dark:bg-green-900/30 px-2 py-0.5 rounded-md inline-block">
                                                            {p.item?.item_code}
                                                        </div>
                                                    ) : (
                                                        <button 
                                                            onClick={() => openUpdateModal(p)} 
                                                            className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-100 border border-blue-200 rounded-md hover:bg-blue-200 hover:text-blue-800 transition-colors dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800/50 dark:hover:bg-blue-800/60"
                                                        >
                                                            {p.temp_name}
                                                        </button>
                                                    )
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-center">
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
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                {p.surat_jalan_potong || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-black text-zinc-900 dark:text-zinc-100">
                                                {p.quantity}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <Badge variant="outline" className="w-fit font-mono text-[10px] bg-white dark:bg-zinc-900">
                                                    {p.size?.name || '-'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs font-bold text-zinc-600 dark:text-zinc-300">
                                                {p.warna || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100 font-bold">
                                                {p.customer || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-center">
                                                <div className="flex flex-col items-center justify-center p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded-md border border-zinc-200 dark:border-zinc-800">
                                                    <span className="text-[11px] font-medium text-zinc-500 dark:text-zinc-400 mb-0.5 whitespace-nowrap">
                                                        {p.jahit_date ? new Date(p.jahit_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'}
                                                    </span>
                                                    {p.jahit && (
                                                        <span className="text-xs font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded shadow-sm border border-zinc-100 dark:border-zinc-800 w-full text-center truncate max-w-[100px]" title={p.jahit.name}>
                                                            {p.jahit.name}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-center text-sm">
                                                {p.qc ? (
                                                    <Badge variant="secondary" className="font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                        {p.qc.name}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-zinc-400 dark:text-zinc-600 font-medium">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {isGudangOrBoth ? (
                                                    <span className="text-sm font-bold text-blue-600 dark:text-blue-400">
                                                        {p.invoice}
                                                    </span>
                                                ) : (
                                                    p.item_id ? (
                                                        <Button variant="default" size="sm" onClick={() => openGudangModal(p)} className="h-7 text-xs bg-zinc-800 hover:bg-zinc-700 text-white shadow-sm">
                                                            To Gudang
                                                        </Button>
                                                    ) : (
                                                        <span className="text-xs text-zinc-400 italic">Belum ada item</span>
                                                    )
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                                {produksis.data.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-900">
                                            No records found. Adjust your filters to see more results.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {produksis.links && produksis.links.length > 3 && (
                    <div className="bg-zinc-50/50 dark:bg-zinc-900/50 px-6 py-4 border border-t-0 rounded-b-xl border-zinc-200 dark:border-zinc-800">
                        <Pagination links={produksis.links} from={produksis.from} to={produksis.to} total={produksis.total} label="records" />
                    </div>
                )}
            </div>

            {/* UPdate Kode Modal */}
            <Dialog open={updateModalOpen} onOpenChange={setUpdateModalOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <form onSubmit={handleUpdateSubmit}>
                        <DialogHeader>
                            <DialogTitle>Update Kode Item</DialogTitle>
                            <DialogDescription>
                                Set true Item for Serial <span className="font-bold text-zinc-900">{selectedProduksi?.serial}</span>. Ini akan mengupdate semua kitir dengan id asli yang sama.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Select Item</Label>
                                <AsyncCombobox
                                    endpoint="/items?json=true"
                                    placeholder="Search item..."
                                    onChange={(item) => setUpdateData('item_id', item?.id?.toString() || '')}
                                    renderItem={(item: any) => (
                                        <div className="flex flex-col text-xs">
                                            <span className="font-bold">#{item.id} - {item.name}</span>
                                            <span className="text-muted-foreground">{item.code}</span>
                                        </div>
                                    )}
                                    className="w-full"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setUpdateModalOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={updating || !updateData.item_id}>Save Changes</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Pindah Gudang Modal */}
            <Dialog open={gudangModalOpen} onOpenChange={setGudangModalOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <form onSubmit={handleGudangSubmit}>
                        <DialogHeader>
                            <DialogTitle>Pindah Ke Gudang</DialogTitle>
                            <DialogDescription>
                                Masukkan nomor Invoice/Transaksi untuk Serial <span className="font-bold text-zinc-900">{selectedProduksi?.serial}</span>.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <FormInput
                                id="invoice"
                                label="Invoice Number"
                                value={gudangData.invoice}
                                onChange={e => setGudangData('invoice', e.target.value)}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setGudangModalOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={gudangding || !gudangData.invoice}>Pindah Gudang</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
