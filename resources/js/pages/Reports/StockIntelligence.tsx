import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Filter,
    X,
    Clock,
    Warehouse,
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    CheckCircle,
    Settings2,
    Save,
    Search,
    AlertCircle,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface StockItem {
    item_id: number;
    item_name: string;
    performance_level: string;
    performance_key: string;
    score: number;
    previous_score: number | null;
    gap_days: number | string;
    current_warehouse: {
        id: number;
        name: string;
        qty: number;
        last_sale: string;
        days_ago: number | string;
    };
    best_performing_warehouse: {
        id: number;
        name: string;
        last_sale: string;
        days_ago: number;
        qty: number;
    } | null;
    audit_reference_date: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedResponse {
    data: StockItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    data: PaginatedResponse;
    stats: Record<string, number>;
    settings: {
        gap_weight: number;
        sale_weight: number;
        max_gap: number;
        max_days: number;
        total_rows: number;
        generate_days: string[];
    };
    reportInfo: {
        generet_at: string;
        type: string;
        generet_by: string;
        next_run: string | null;
        last_update_days_ago: string;
    } | null;
    reportHistory: { id: number; label: string }[];
    currentReportId: number | null;
    filters: {
        performance: string | null;
        search: string | null;
    };
}

export default function StockIntelligence({
    data,
    stats,
    settings,
    reportInfo,
    reportHistory,
    currentReportId,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isSettingsOpen, setIsSettingsOpen] = useState(false);
    const [isGenerateDialogOpen, setIsGenerateDialogOpen] = useState(false);

    const {
        data: form,
        setData,
        post,
        processing,
        reset,
    } = useForm({
        gap_weight: settings.gap_weight,
        sale_weight: settings.sale_weight,
        max_gap: settings.max_gap,
        max_days: settings.max_days,
        total_rows: settings.total_rows,
        generate_days: settings.generate_days || [],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Stock Intelligence', href: '/reports/stock-intelligence' },
    ];

    const performanceTabs = [
        { key: 'all', label: 'Semua' },
        { key: 'elite', label: '1. Elite' },
        { key: 'good', label: '2. Good' },
        { key: 'active', label: '3. Active' },
        { key: 'lagging', label: '4. Lagging' },
        { key: 'stagnant', label: '5. Stagnant' },
        { key: 'deadstock', label: '6. Deadstock' },
        { key: 'critical', label: '7. Critical' },
    ];

    const handleFilter = () => {
        router.get(
            '/reports/stock-intelligence',
            {
                performance: filters.performance,
                search: search || null,
                report_id: currentReportId,
            },
            { preserveState: true },
        );
    };

    const resetFilter = () => {
        setSearch('');
        router.get('/reports/stock-intelligence', {
            report_id: currentReportId,
        });
    };

    const handleGenerate = () => {
        setIsGenerateDialogOpen(true);
    };

    const confirmGenerate = () => {
        setIsGenerateDialogOpen(false);
        router.post(
            '/reports/stock-intelligence/generate',
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const saveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        post('/reports/stock-settings', {
            onSuccess: () => setIsSettingsOpen(false),
        });
    };

    const resetToDefault = () => {
        if (
            confirm(
                'Apakah Anda yakin ingin mengembalikan semua pengaturan algoritma ke nilai default?',
            )
        ) {
            router.post(
                '/reports/stock-settings/reset',
                {},
                {
                    onSuccess: () => {
                        setIsSettingsOpen(false);
                        // Reset local form state to default values
                        setData({
                            gap_weight: 0.2,
                            sale_weight: 0.8,
                            max_gap: 90,
                            max_days: 90,
                            total_rows: 1000,
                            generate_days: [
                                'Monday',
                                'Tuesday',
                                'Wednesday',
                                'Thursday',
                                'Friday',
                                'Saturday',
                                'Sunday',
                            ],
                        });
                    },
                },
            );
        }
    };

    const getPerformanceBadge = (key: string, label: string) => {
        switch (key) {
            case 'elite':
                return (
                    <Badge className="border-none bg-emerald-500 text-white hover:bg-emerald-600">
                        {label}
                    </Badge>
                );
            case 'good':
                return (
                    <Badge className="border-none bg-blue-500 text-white hover:bg-blue-600">
                        {label}
                    </Badge>
                );
            case 'active':
                return (
                    <Badge className="border-none bg-cyan-500 text-white hover:bg-cyan-600">
                        {label}
                    </Badge>
                );
            case 'lagging':
                return (
                    <Badge
                        variant="secondary"
                        className="border-amber-200 bg-amber-100 text-amber-700 hover:bg-amber-100"
                    >
                        {label}
                    </Badge>
                );
            case 'stagnant':
                return (
                    <Badge
                        variant="outline"
                        className="border-orange-200 bg-orange-50 text-orange-600"
                    >
                        {label}
                    </Badge>
                );
            case 'deadstock':
                return (
                    <Badge
                        variant="destructive"
                        className="border-none bg-rose-500 text-white"
                    >
                        {label}
                    </Badge>
                );
            case 'critical':
                return (
                    <Badge
                        variant="destructive"
                        className="border-none bg-zinc-800 text-white"
                    >
                        {label}
                    </Badge>
                );
            default:
                return <Badge variant="outline">{label}</Badge>;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock Intelligence" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-zinc-900 uppercase dark:text-zinc-100">
                            Stock Intelligence
                        </h1>
                        <p className="mt-1 text-sm font-medium text-zinc-500 italic dark:text-zinc-400">
                            Weighted Algorithm:{' '}
                            <span className="font-bold text-zinc-900 dark:text-zinc-100">
                                {settings.gap_weight * 100}% Gap
                            </span>{' '}
                            &{' '}
                            <span className="font-bold text-zinc-900 dark:text-zinc-100">
                                {settings.sale_weight * 100}% Sale History
                            </span>{' '}
                            (Max: {settings.max_days}d)
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Search & Quick Filter Bar */}
                        <div className="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <Input
                                placeholder="Cari item..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 w-[280px] border-none bg-transparent font-medium shadow-none focus-visible:ring-0"
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && handleFilter()
                                }
                            />
                            <Button
                                size="icon"
                                variant="ghost"
                                onClick={handleFilter}
                                className="h-8 w-8"
                            >
                                <Search className="h-4 w-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                onClick={resetFilter}
                                className="h-8 w-8 text-zinc-400 hover:text-rose-500"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        {/* Manual Generate Button */}
                        <Button
                            onClick={handleGenerate}
                            disabled={processing}
                            className="flex h-11 gap-2 rounded-lg bg-emerald-600 px-6 font-bold text-white shadow-lg transition-all hover:scale-105 hover:bg-emerald-700 active:scale-95"
                        >
                            <TrendingUp className="h-5 w-5" />
                            Generate Laporan Hari Ini
                        </Button>

                        {/* Manual Generate Alert Dialog (Using Dialog Component) */}
                        <Dialog
                            open={isGenerateDialogOpen}
                            onOpenChange={setIsGenerateDialogOpen}
                        >
                            <DialogContent className="overflow-hidden border-none p-0 shadow-2xl sm:max-w-[450px]">
                                <div className="flex items-center gap-4 bg-emerald-600 p-6">
                                    <div className="rounded-full bg-white/20 p-3">
                                        <AlertCircle className="h-8 w-8 text-white" />
                                    </div>
                                    <div>
                                        <DialogTitle className="text-xl font-black tracking-tight text-white uppercase">
                                            Konfirmasi Generate
                                        </DialogTitle>
                                        <p className="mt-1 text-xs font-medium tracking-widest text-emerald-100 uppercase">
                                            Manual Action Required
                                        </p>
                                    </div>
                                </div>
                                <div className="p-6">
                                    <DialogDescription className="text-sm leading-relaxed font-medium text-zinc-600 dark:text-zinc-400">
                                        Apakah Anda yakin ingin melakukan{' '}
                                        <span className="font-bold text-zinc-900 italic dark:text-zinc-100">
                                            Generate Laporan Stock Intelligence
                                        </span>{' '}
                                        untuk hari ini?
                                        <br />
                                        <br />
                                        Sistem akan menghitung ulang seluruh
                                        skor performa stok berdasarkan parameter
                                        algoritma yang aktif. Proses ini
                                        membutuhkan sumber daya server yang
                                        cukup intensif.
                                    </DialogDescription>
                                </div>
                                <DialogFooter className="flex-row justify-end gap-2 border-t bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                                    <Button
                                        variant="ghost"
                                        onClick={() =>
                                            setIsGenerateDialogOpen(false)
                                        }
                                        className="text-[11px] font-bold tracking-widest text-zinc-500 uppercase hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        onClick={confirmGenerate}
                                        disabled={processing}
                                        className="bg-emerald-600 px-6 text-[11px] font-bold tracking-widest text-white uppercase shadow-md hover:bg-emerald-700"
                                    >
                                        {processing
                                            ? 'Sedang Memproses...'
                                            : 'Lanjutkan Generate'}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        {/* Settings Modal */}
                        <Dialog
                            open={isSettingsOpen}
                            onOpenChange={setIsSettingsOpen}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="h-11 w-11 rounded-lg bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                                >
                                    <Settings2 className="h-5 w-5" />
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-[425px]">
                                <form onSubmit={saveSettings}>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Stock Algorithm Settings
                                        </DialogTitle>
                                        <DialogDescription>
                                            Sesuaikan bobot dan nilai maksimal
                                            untuk perhitungan skor performa
                                            stok.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="gap_weight">
                                                    Bobot Gap (0.0 - 1.0)
                                                </Label>
                                                <Input
                                                    id="gap_weight"
                                                    type="number"
                                                    step="0.1"
                                                    value={form.gap_weight}
                                                    onChange={(e) =>
                                                        setData(
                                                            'gap_weight',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="sale_weight">
                                                    Bobot Sale (0.0 - 1.0)
                                                </Label>
                                                <Input
                                                    id="sale_weight"
                                                    type="number"
                                                    step="0.1"
                                                    value={form.sale_weight}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sale_weight',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="max_days">
                                                    Max Days (Hari)
                                                </Label>
                                                <Input
                                                    id="max_days"
                                                    type="number"
                                                    value={form.max_days}
                                                    onChange={(e) =>
                                                        setData(
                                                            'max_days',
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="total_rows">
                                                    Total Data (Baris)
                                                </Label>
                                                <Input
                                                    id="total_rows"
                                                    type="number"
                                                    min="100"
                                                    max="10000"
                                                    value={form.total_rows}
                                                    onChange={(e) =>
                                                        setData(
                                                            'total_rows',
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-3">
                                            <Label>Hari Generet Laporan</Label>
                                            <div className="flex flex-wrap gap-2">
                                                {[
                                                    'Senin',
                                                    'Selasa',
                                                    'Rabu',
                                                    'Kamis',
                                                    'Jumat',
                                                    'Sabtu',
                                                    'Minggu',
                                                ].map((day) => {
                                                    const isSelected =
                                                        form.generate_days.includes(
                                                            day,
                                                        );
                                                    return (
                                                        <Button
                                                            key={day}
                                                            type="button"
                                                            variant={
                                                                isSelected
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            className={`h-8 px-3 text-[11px] font-bold uppercase transition-all ${
                                                                isSelected
                                                                    ? 'scale-105 bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                                                                    : 'border-zinc-200 bg-transparent text-zinc-500 dark:border-zinc-800'
                                                            }`}
                                                            onClick={() => {
                                                                const nextDays =
                                                                    isSelected
                                                                        ? form.generate_days.filter(
                                                                              (
                                                                                  d,
                                                                              ) =>
                                                                                  d !==
                                                                                  day,
                                                                          )
                                                                        : [
                                                                              ...form.generate_days,
                                                                              day,
                                                                          ];
                                                                setData(
                                                                    'generate_days',
                                                                    nextDays,
                                                                );
                                                            }}
                                                        >
                                                            {day}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                            <p className="text-[10px] font-medium text-zinc-400 italic">
                                                * Cron hanya akan berjalan pada
                                                hari-hari yang dipilih.
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter className="flex-col gap-2 border-t pt-2 sm:flex-row dark:border-zinc-800">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={resetToDefault}
                                            className="text-zinc-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-950/20"
                                        >
                                            Reset to Default
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="sm:flex-1"
                                        >
                                            <Save className="mr-2 h-4 w-4" />{' '}
                                            Simpan Konfigurasi
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* History Selector & Info Banner */}
                <div className="flex flex-col gap-4">
                    <div className="scrollbar-hide flex items-center gap-3 overflow-x-auto pb-2">
                        <div className="flex shrink-0 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-100 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900">
                            <Clock className="h-4 w-4 text-zinc-500" />
                            <span className="text-xs font-bold tracking-wider text-zinc-500 uppercase">
                                Riwayat:
                            </span>
                        </div>
                        {reportHistory.map((report) => (
                            <Link
                                key={report.id}
                                href={`/reports/stock-intelligence?report_id=${report.id}`}
                                className={`shrink-0 rounded-lg border px-4 py-2 text-xs font-bold transition-all ${
                                    currentReportId === report.id
                                        ? 'border-zinc-900 bg-zinc-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400'
                                }`}
                            >
                                {report.label}
                            </Link>
                        ))}
                    </div>

                    {/* Prominent Report Status Banner */}
                    {reportInfo && (
                        <div className="flex flex-col justify-between gap-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl md:flex-row md:items-center dark:border-zinc-200 dark:bg-zinc-100">
                            <div className="flex items-center gap-5">
                                <div className="rounded-xl bg-zinc-800 p-4 shadow-inner dark:bg-zinc-200">
                                    <Clock className="h-8 w-8 text-zinc-100 dark:text-zinc-900" />
                                </div>
                                <div>
                                    <div className="mb-1 text-[10px] font-black tracking-[0.2em] text-zinc-400 uppercase dark:text-zinc-500">
                                        Terakhir Diperbarui
                                    </div>
                                    <div className="flex items-baseline gap-3 font-mono text-3xl leading-none font-black tracking-tight text-white tabular-nums dark:text-zinc-950">
                                        {reportInfo.generet_at}
                                        <span className="text-sm font-bold text-zinc-500 dark:text-zinc-400">
                                            ({reportInfo.last_update_days_ago})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center gap-6 text-right">
                                {reportInfo.next_run && (
                                    <div className="flex flex-col items-end">
                                        <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-black tracking-widest text-emerald-500 uppercase dark:text-emerald-600">
                                            <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                                            Next Run
                                        </div>
                                        <div className="font-mono text-lg leading-none font-bold text-white dark:text-zinc-950">
                                            {reportInfo.next_run}
                                        </div>
                                    </div>
                                )}
                                <div className="flex hidden flex-col items-end text-right md:flex">
                                    <div className="mb-1.5 text-[10px] font-black tracking-widest text-zinc-500 uppercase">
                                        Metode
                                    </div>
                                    <Badge
                                        className={`rounded-full border-none px-4 py-1 text-xs font-black uppercase shadow-lg ${
                                            reportInfo.type === 'cron'
                                                ? 'bg-blue-500 text-white hover:bg-blue-500'
                                                : 'bg-amber-500 text-white hover:bg-amber-500'
                                        }`}
                                    >
                                        {reportInfo.type}
                                    </Badge>
                                </div>
                                <div className="hidden h-12 w-px bg-zinc-800 opacity-50 md:block dark:bg-zinc-300" />
                                <div className="flex flex-col">
                                    <span className="mb-1 text-[10px] font-black tracking-widest text-zinc-500 uppercase">
                                        Oleh
                                    </span>
                                    <span className="text-lg font-black tracking-tight text-white uppercase dark:text-zinc-950">
                                        {reportInfo.generet_by}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Performance Tabs */}
                <div className="flex flex-wrap gap-2">
                    {performanceTabs.map((tab) => {
                        const isActive = filters.performance === tab.key;
                        const count = stats[tab.key] || 0;
                        const queryParams: any = {
                            ...filters,
                            performance: tab.key,
                            page: null,
                            report_id: currentReportId,
                        };

                        return (
                            <Link
                                key={tab.key}
                                href={`/reports/stock-intelligence?${new URLSearchParams(Object.fromEntries(Object.entries(queryParams).filter(([_, v]) => v != null))).toString()}`}
                                preserveState
                                className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                                    isActive
                                        ? 'border-zinc-900 bg-zinc-900 text-white shadow-sm dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-700'
                                }`}
                            >
                                {tab.label}
                                <span
                                    className={`rounded-md px-1.5 py-0.5 text-[10px] ${
                                        isActive
                                            ? 'bg-zinc-700 text-zinc-100 dark:bg-zinc-300 dark:text-zinc-800'
                                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500'
                                    }`}
                                >
                                    {count}
                                </span>
                            </Link>
                        );
                    })}
                </div>

                {/* Data Table */}
                <Card className="overflow-hidden border-zinc-200 shadow-sm dark:border-zinc-800">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <TableHead className="w-[280px] text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Item Info
                                </TableHead>
                                <TableHead className="text-center text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Score
                                </TableHead>
                                <TableHead className="text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Performance
                                </TableHead>
                                <TableHead className="text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Current Warehouse
                                </TableHead>
                                <TableHead className="text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Best Performance
                                </TableHead>
                                <TableHead className="text-center text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Gap Days
                                </TableHead>
                                <TableHead className="text-right text-[11px] font-bold tracking-wider uppercase dark:text-zinc-400">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.data.length > 0 ? (
                                data.data.map((item) => (
                                    <TableRow
                                        key={`${item.item_id}-${item.current_warehouse.name}`}
                                        className="dark:hover:bg-zinc-900/50"
                                    >
                                        <TableCell>
                                            <div className="leading-tight font-bold text-zinc-900 dark:text-zinc-100">
                                                {item.item_name}
                                            </div>
                                            <div className="mt-1 text-[10px] text-zinc-400 uppercase">
                                                ID: {item.item_id}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className="flex flex-col items-center justify-center gap-1">
                                                <div className="font-mono text-xl font-black dark:text-zinc-100">
                                                    {item.score.toFixed(4)}
                                                </div>
                                                {item.previous_score !==
                                                    null && (
                                                    <div className="flex scale-90 items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-100 px-2 py-0.5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                                                        {item.score >
                                                        item.previous_score ? (
                                                            <>
                                                                <TrendingUp className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                                                <span className="text-[10px] font-black text-emerald-600 dark:text-emerald-400">
                                                                    +
                                                                    {(
                                                                        item.score -
                                                                        item.previous_score
                                                                    ).toFixed(
                                                                        4,
                                                                    )}
                                                                </span>
                                                            </>
                                                        ) : item.score <
                                                          item.previous_score ? (
                                                            <>
                                                                <TrendingUp className="h-3 w-3 rotate-180 text-rose-600 dark:text-rose-400" />
                                                                <span className="text-[10px] font-black text-rose-600 dark:text-rose-400">
                                                                    {(
                                                                        item.score -
                                                                        item.previous_score
                                                                    ).toFixed(
                                                                        4,
                                                                    )}
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <span className="text-[10px] font-black tracking-tighter text-zinc-400 uppercase">
                                                                No Change
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {getPerformanceBadge(
                                                item.performance_key,
                                                item.performance_level,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                                    <Warehouse className="h-3.5 w-3.5" />
                                                    {
                                                        item.current_warehouse
                                                            .name
                                                    }
                                                </div>
                                                <div className="text-[10px] font-medium text-zinc-500">
                                                    Last Sale:{' '}
                                                    {
                                                        item.current_warehouse
                                                            .last_sale
                                                    }
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-2 text-xs">
                                                    <span className="rounded bg-zinc-100 px-1.5 py-0.5 font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                        QTY:{' '}
                                                        {
                                                            item
                                                                .current_warehouse
                                                                .qty
                                                        }
                                                    </span>
                                                    <span className="flex items-center gap-1 text-zinc-500">
                                                        <Clock className="h-3 w-3" />
                                                        {item.current_warehouse
                                                            .days_ago ===
                                                        'NEVER SOLD'
                                                            ? 'Never'
                                                            : `${item.current_warehouse.days_ago}d`}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {item.best_performing_warehouse ? (
                                                <div className="flex flex-col gap-1">
                                                    <div className="text-sm leading-tight font-medium text-emerald-700 italic dark:text-emerald-400">
                                                        {
                                                            item
                                                                .best_performing_warehouse
                                                                .name
                                                        }
                                                    </div>
                                                    <div className="text-[10px] text-zinc-500">
                                                        Last Sale:{' '}
                                                        {
                                                            item
                                                                .best_performing_warehouse
                                                                .last_sale
                                                        }
                                                    </div>
                                                    <div className="mt-0.5 flex items-center gap-2 text-[11px]">
                                                        <span className="font-bold text-emerald-600 uppercase">
                                                            Stok:{' '}
                                                            {
                                                                item
                                                                    .best_performing_warehouse
                                                                    .qty
                                                            }
                                                        </span>
                                                        <span className="text-zinc-400">
                                                            (
                                                            {
                                                                item
                                                                    .best_performing_warehouse
                                                                    .days_ago
                                                            }
                                                            d ago)
                                                        </span>
                                                    </div>
                                                </div>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div
                                                className={`font-mono text-base font-bold ${
                                                    item.gap_days ===
                                                    'NEVER SOLD'
                                                        ? 'text-zinc-400'
                                                        : Number(
                                                                item.gap_days,
                                                            ) > 30
                                                          ? 'text-rose-600'
                                                          : 'text-zinc-900 dark:text-zinc-100'
                                                }`}
                                            >
                                                {item.gap_days === 'NEVER SOLD'
                                                    ? '-'
                                                    : `+${item.gap_days}`}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end">
                                                {item.best_performing_warehouse &&
                                                item.best_performing_warehouse
                                                    .name ===
                                                    item.current_warehouse
                                                        .name ? (
                                                    <div className="flex items-center gap-1.5 rounded-md border border-emerald-100 bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-600 uppercase shadow-sm dark:border-emerald-800/30 dark:bg-emerald-900/20 dark:text-emerald-400">
                                                        <CheckCircle className="h-3.5 w-3.5" />
                                                        Gudang Terbaik
                                                    </div>
                                                ) : (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        className="h-8 border-zinc-200 shadow-sm dark:border-zinc-800"
                                                    >
                                                        <Link
                                                            href={`/reports/rebalance-detail?item_id=${item.item_id}&warehouse_id=${item.current_warehouse.id}`}
                                                        >
                                                            <TrendingUp className="mr-1.5 h-3.5 w-3.5 text-blue-500" />
                                                            Rebalance
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-32 text-center text-zinc-500 italic"
                                    >
                                        Data kosong.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </Card>

                {/* Pagination */}
                {data.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between">
                        <p className="text-xs font-medium text-zinc-500">
                            Halaman {data.current_page} dari {data.last_page} (
                            {data.total} item)
                        </p>
                        <div className="flex gap-1">
                            {data.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={
                                        link.url
                                            ? `${link.url}&report_id=${currentReportId || ''}`
                                            : '#'
                                    }
                                    preserveState
                                    className={`rounded-md border px-3 py-1.5 text-xs font-bold transition-all ${
                                        link.active
                                            ? 'border-zinc-900 bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                            : 'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900'
                                    } ${!link.url && 'cursor-not-allowed opacity-30'}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
