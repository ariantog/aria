import { router } from '@inertiajs/react';
import { Search, X, Filter, Calendar as CalendarIcon } from 'lucide-react';
import { useState, useEffect } from 'react';
import { AsyncCombobox } from '@/components/AsyncCombobox';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

interface Props {
    filters: {
        from?: string;
        to?: string;
        kode?: string;
        customer?: string;
        potong_id?: string | number;
        jahit_id?: string | number;
        serial?: string;
        surat_jalan_potong?: string;
        warna?: string;
        [key: string]: any;
    };
}

export default function ProduksiFilter({ filters }: Props) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [kode, setKode] = useState(filters.kode || '');
    const [customer, setCustomer] = useState(filters.customer || '');

    // Advanced Filters
    const [serial, setSerial] = useState(filters.serial || '');
    const [sjp, setSjp] = useState(filters.surat_jalan_potong || '');
    const [warna, setWarna] = useState(filters.warna || '');
    const [selectedPotong, setSelectedPotong] = useState<any>(null);
    const [selectedJahit, setSelectedJahit] = useState<any>(null);

    // Initial load for worker objects if IDs exist in filters
    useEffect(() => {
        const fetchWorker = async (id: any, type: string) => {
            if (!id) return;
            try {
                const response = await fetch(
                    `/produksi/workers/lookup?type=${type}&search=${id}`,
                );
                const data = await response.json();
                const found = data.find(
                    (w: any) => w.id.toString() === id.toString(),
                );
                if (found) {
                    if (type === 'potong') setSelectedPotong(found);
                    if (type === 'jahit') setSelectedJahit(found);
                }
            } catch (error) {
                console.error('Failed to fetch worker for filter:', error);
            }
        };

        if (filters.potong_id) fetchWorker(filters.potong_id, 'potong');
        if (filters.jahit_id) fetchWorker(filters.jahit_id, 'jahit');
    }, []);

    const applyFilters = (newFilters?: any) => {
        const params: any = {
            from,
            to,
            kode,
            customer,
            serial,
            surat_jalan_potong: sjp,
            warna,
            potong_id: selectedPotong?.id || null,
            jahit_id: selectedJahit?.id || null,
            ...(newFilters || {}),
        };

        // Clean up empty values
        Object.keys(params).forEach((key) => {
            if (
                params[key] === null ||
                params[key] === undefined ||
                params[key] === ''
            ) {
                delete params[key];
            }
        });

        router.get('/produksi', params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    // Auto-apply for text inputs (Debounced)
    useEffect(() => {
        const timer = setTimeout(() => {
            if (kode !== (filters.kode || '')) applyFilters({ kode });
        }, 500);
        return () => clearTimeout(timer);
    }, [kode]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (customer !== (filters.customer || ''))
                applyFilters({ customer });
        }, 500);
        return () => clearTimeout(timer);
    }, [customer]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (serial !== (filters.serial || '')) applyFilters({ serial });
        }, 500);
        return () => clearTimeout(timer);
    }, [serial]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (sjp !== (filters.surat_jalan_potong || ''))
                applyFilters({ surat_jalan_potong: sjp });
        }, 500);
        return () => clearTimeout(timer);
    }, [sjp]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (warna !== (filters.warna || '')) applyFilters({ warna });
        }, 500);
        return () => clearTimeout(timer);
    }, [warna]);

    const handleReset = () => {
        setFrom('');
        setTo('');
        setKode('');
        setCustomer('');
        setSerial('');
        setSjp('');
        setWarna('');
        setSelectedPotong(null);
        setSelectedJahit(null);
        router.get('/produksi', {}, { preserveState: true, replace: true });
    };

    const hasFilters =
        from ||
        to ||
        kode ||
        customer ||
        serial ||
        sjp ||
        warna ||
        selectedPotong ||
        selectedJahit;

    return (
        <div className="mb-6 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div className="flex flex-wrap items-end gap-3">
                {/* Date Range */}
                <div className="flex min-w-[300px] gap-2">
                    <div className="flex-1 space-y-1.5">
                        <label className="ml-1 text-xs font-medium text-zinc-500">
                            Date From
                        </label>
                        <div className="relative">
                            <CalendarIcon className="absolute top-2.5 left-3 h-4 w-4 text-zinc-400" />
                            <Input
                                type="date"
                                className="h-9 border-zinc-200 bg-zinc-50 pl-9 dark:border-zinc-700 dark:bg-zinc-800/50"
                                value={from}
                                onChange={(e) => {
                                    setFrom(e.target.value);
                                    applyFilters({ from: e.target.value });
                                }}
                            />
                        </div>
                    </div>
                    <div className="flex-1 space-y-1.5">
                        <label className="ml-1 text-xs font-medium text-zinc-500">
                            Date To
                        </label>
                        <Input
                            type="date"
                            className="h-9 border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50"
                            value={to}
                            onChange={(e) => {
                                setTo(e.target.value);
                                applyFilters({ to: e.target.value });
                            }}
                        />
                    </div>
                </div>

                {/* Kode */}
                <div className="min-w-[150px] flex-1 space-y-1.5">
                    <label className="ml-1 text-xs font-medium text-zinc-500">
                        Kode
                    </label>
                    <div className="relative">
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-zinc-400" />
                        <Input
                            placeholder="Search kode..."
                            className="h-9 border-zinc-200 bg-zinc-50 pl-9 font-medium dark:border-zinc-700 dark:bg-zinc-800/50"
                            value={kode}
                            onChange={(e) => setKode(e.target.value)}
                        />
                    </div>
                </div>

                {/* Customer */}
                <div className="min-w-[150px] flex-1 space-y-1.5">
                    <label className="ml-1 text-xs font-medium text-zinc-500">
                        Customer
                    </label>
                    <Input
                        placeholder="Search customer..."
                        className="h-9 border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50"
                        value={customer}
                        onChange={(e) => setCustomer(e.target.value)}
                    />
                </div>

                {/* More Filters Dropdown */}
                <div className="shrink-0">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-9 border-zinc-200 bg-zinc-50 font-medium dark:border-zinc-700 dark:bg-zinc-800/50"
                            >
                                <Filter className="mr-2 h-4 w-4" /> More Filters
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-[500px] border-zinc-200 p-4 shadow-2xl dark:border-zinc-800"
                        >
                            <DropdownMenuLabel className="px-0">
                                Advanced Filters
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator className="my-2" />
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="ml-1 text-xs font-medium text-zinc-500">
                                        Potong Worker
                                    </label>
                                    <AsyncCombobox
                                        endpoint="/produksi/workers/lookup"
                                        additionalParams={{ type: 'potong' }}
                                        value={selectedPotong}
                                        onChange={(val) => {
                                            setSelectedPotong(val);
                                            applyFilters({
                                                potong_id: val?.id || null,
                                            });
                                        }}
                                        placeholder="Search worker..."
                                        className="h-9 rounded-md"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="ml-1 text-xs font-medium text-zinc-500">
                                        Jahit Worker
                                    </label>
                                    <AsyncCombobox
                                        endpoint="/produksi/workers/lookup"
                                        additionalParams={{ type: 'jahit' }}
                                        value={selectedJahit}
                                        onChange={(val) => {
                                            setSelectedJahit(val);
                                            applyFilters({
                                                jahit_id: val?.id || null,
                                            });
                                        }}
                                        placeholder="Search worker..."
                                        className="h-9 rounded-md"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="ml-1 text-xs font-medium text-zinc-500">
                                        Kitir (Serial)
                                    </label>
                                    <Input
                                        placeholder="Kitir serial..."
                                        className="h-9"
                                        value={serial}
                                        onChange={(e) =>
                                            setSerial(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="ml-1 text-xs font-medium text-zinc-500">
                                        SJP (Surat Jalan)
                                    </label>
                                    <Input
                                        placeholder="SJP number..."
                                        className="h-9"
                                        value={sjp}
                                        onChange={(e) => setSjp(e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="ml-1 text-xs font-medium text-zinc-500">
                                        Warna
                                    </label>
                                    <Input
                                        placeholder="Color..."
                                        className="h-9"
                                        value={warna}
                                        onChange={(e) =>
                                            setWarna(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Clear Actions */}
                {hasFilters && (
                    <div className="shrink-0 pb-0.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            className="h-9 px-3 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/10"
                        >
                            <X className="mr-2 h-4 w-4" /> Clear
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
