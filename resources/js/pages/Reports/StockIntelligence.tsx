import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Filter, X, Clock, Warehouse, ChevronLeft, ChevronRight, TrendingUp, CheckCircle, Settings2, Save, Search } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

interface StockItem {
    item_id: number;
    item_name: string;
    performance_level: string;
    performance_key: string;
    score: number;
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
    };
    filters: {
        days: string | null;
        performance: string | null;
        search: string | null;
    };
}

export default function StockIntelligence({ data, stats, settings, filters }: Props) {
    const [days, setDays] = useState(filters.days || '');
    const [search, setSearch] = useState(filters.search || '');
    const [isSettingsOpen, setIsSettingsOpen] = useState(false);

    const { data: form, setData, post, processing, reset } = useForm({
        gap_weight: settings.gap_weight,
        sale_weight: settings.sale_weight,
        max_gap: settings.max_gap,
        max_days: settings.max_days,
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
        router.get('/reports/stock-intelligence', {
            performance: filters.performance,
            days: days || null,
            search: search || null,
        }, { preserveState: true });
    };

    const resetFilter = () => {
        setDays('');
        setSearch('');
        router.get('/reports/stock-intelligence');
    };

    const saveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        post('/reports/stock-settings', {
            onSuccess: () => setIsSettingsOpen(false),
        });
    };

    const getPerformanceBadge = (key: string, label: string) => {
        switch (key) {
            case 'elite': return <Badge className="bg-emerald-500 hover:bg-emerald-600 text-white border-none">{label}</Badge>;
            case 'good': return <Badge className="bg-blue-500 hover:bg-blue-600 text-white border-none">{label}</Badge>;
            case 'active': return <Badge className="bg-cyan-500 hover:bg-cyan-600 text-white border-none">{label}</Badge>;
            case 'lagging': return <Badge variant="secondary" className="bg-amber-100 text-amber-700 hover:bg-amber-100 border-amber-200">{label}</Badge>;
            case 'stagnant': return <Badge variant="outline" className="text-orange-600 border-orange-200 bg-orange-50">{label}</Badge>;
            case 'deadstock': return <Badge variant="destructive" className="bg-rose-500 text-white border-none">{label}</Badge>;
            case 'critical': return <Badge variant="destructive" className="bg-zinc-800 text-white border-none">{label}</Badge>;
            default: return <Badge variant="outline">{label}</Badge>;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock Intelligence" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Stock Intelligence & Performance</h1>
                        <p className="text-zinc-500 dark:text-zinc-400 text-sm italic">Weighted Calculation: {settings.gap_weight * 100}% Gap, {settings.sale_weight * 100}% Sale History (Max: {settings.max_days}d)</p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Search & Quick Filter Bar */}
                        <div className="flex items-center gap-2 bg-white dark:bg-zinc-900 p-1 rounded-lg border border-zinc-200 dark:border-zinc-800 shadow-sm">
                            <Input 
                                placeholder="Cari item..." 
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 w-[200px] border-none shadow-none focus-visible:ring-0 bg-transparent"
                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                            />
                            <div className="h-4 w-px bg-zinc-200 dark:bg-zinc-800" />
                            <Input 
                                type="number"
                                placeholder="Hari"
                                value={days}
                                onChange={(e) => setDays(e.target.value)}
                                className="h-9 w-[70px] border-none shadow-none focus-visible:ring-0 bg-transparent"
                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                            />
                            <Button size="icon" variant="ghost" onClick={handleFilter} className="h-8 w-8">
                                <Search className="h-4 w-4" />
                            </Button>
                            <Button size="icon" variant="ghost" onClick={resetFilter} className="h-8 w-8 text-zinc-400 hover:text-rose-500">
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        {/* Settings Modal */}
                        <Dialog open={isSettingsOpen} onOpenChange={setIsSettingsOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="icon" className="h-11 w-11 rounded-lg dark:border-zinc-800">
                                    <Settings2 className="h-5 w-5" />
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-[425px]">
                                <form onSubmit={saveSettings}>
                                    <DialogHeader>
                                        <DialogTitle>Stock Algorithm Settings</DialogTitle>
                                        <DialogDescription>
                                            Sesuaikan bobot dan nilai maksimal untuk perhitungan skor performa stok.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="gap_weight">Bobot Gap (0.0 - 1.0)</Label>
                                                <Input 
                                                    id="gap_weight" 
                                                    type="number" 
                                                    step="0.1" 
                                                    value={form.gap_weight} 
                                                    onChange={e => setData('gap_weight', parseFloat(e.target.value))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="sale_weight">Bobot Sale (0.0 - 1.0)</Label>
                                                <Input 
                                                    id="sale_weight" 
                                                    type="number" 
                                                    step="0.1" 
                                                    value={form.sale_weight} 
                                                    onChange={e => setData('sale_weight', parseFloat(e.target.value))}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="max_gap">Max Gap (Hari)</Label>
                                                <Input 
                                                    id="max_gap" 
                                                    type="number" 
                                                    value={form.max_gap} 
                                                    onChange={e => setData('max_gap', parseInt(e.target.value))}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="max_days">Max Days (Hari)</Label>
                                                <Input 
                                                    id="max_days" 
                                                    type="number" 
                                                    value={form.max_days} 
                                                    onChange={e => setData('max_days', parseInt(e.target.value))}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            <Save className="h-4 w-4 mr-2" /> Simpan Konfigurasi
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* Performance Tabs */}
                <div className="flex flex-wrap gap-2">
                    {performanceTabs.map((tab) => {
                        const isActive = (filters.performance || 'all') === tab.key;
                        const count = stats[tab.key] || 0;
                        const queryParams: any = { ...filters, performance: tab.key === 'all' ? null : tab.key, page: null };
                        
                        return (
                            <Link
                                key={tab.key}
                                href={`/reports/stock-intelligence?${new URLSearchParams(Object.fromEntries(Object.entries(queryParams).filter(([_, v]) => v != null))).toString()}`}
                                preserveState
                                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all border ${
                                    isActive 
                                        ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:border-zinc-100 shadow-sm' 
                                        : 'bg-white text-zinc-600 border-zinc-200 hover:border-zinc-300 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800 dark:hover:border-zinc-700'
                                }`}
                            >
                                {tab.label}
                                <span className={`px-1.5 py-0.5 rounded-md text-[10px] ${
                                    isActive 
                                        ? 'bg-zinc-700 text-zinc-100 dark:bg-zinc-300 dark:text-zinc-800' 
                                        : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500'
                                }`}>
                                    {count}
                                </span>
                            </Link>
                        );
                    })}
                </div>

                {/* Data Table */}
                <Card className="overflow-hidden border-zinc-200 dark:border-zinc-800 shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-zinc-50/50 dark:bg-zinc-900/50">
                                <TableHead className="w-[280px] dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Item Info</TableHead>
                                <TableHead className="text-center dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Score</TableHead>
                                <TableHead className="dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Performance</TableHead>
                                <TableHead className="dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Current Warehouse</TableHead>
                                <TableHead className="dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Best Performance</TableHead>
                                <TableHead className="text-center dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Gap Days</TableHead>
                                <TableHead className="text-right dark:text-zinc-400 font-bold uppercase text-[11px] tracking-wider">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.data.length > 0 ? (
                                data.data.map((item) => (
                                    <TableRow key={`${item.item_id}-${item.current_warehouse.name}`} className="dark:hover:bg-zinc-900/50">
                                        <TableCell>
                                            <div className="font-bold text-zinc-900 dark:text-zinc-100 leading-tight">{item.item_name}</div>
                                            <div className="text-[10px] text-zinc-400 mt-1 uppercase">ID: {item.item_id}</div>
                                        </TableCell>
                                        <TableCell className="text-center font-mono font-bold text-lg dark:text-zinc-100">
                                            {item.score.toFixed(4)}
                                        </TableCell>
                                        <TableCell>
                                            {getPerformanceBadge(item.performance_key, item.performance_level)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300 text-sm">
                                                    <Warehouse className="h-3.5 w-3.5" />
                                                    {item.current_warehouse.name}
                                                </div>
                                                <div className="text-[10px] text-zinc-500 font-medium">
                                                    Last Sale: {item.current_warehouse.last_sale}
                                                </div>
                                                <div className="flex items-center gap-2 text-xs mt-0.5">
                                                    <span className="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded font-bold text-zinc-600 dark:text-zinc-400">QTY: {item.current_warehouse.qty}</span>
                                                    <span className="text-zinc-500 flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        {item.current_warehouse.days_ago === 'NEVER SOLD' ? 'Never' : `${item.current_warehouse.days_ago}d`}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {item.best_performing_warehouse ? (
                                                <div className="flex flex-col gap-1">
                                                    <div className="font-medium text-emerald-700 dark:text-emerald-400 text-sm italic leading-tight">
                                                        {item.best_performing_warehouse.name}
                                                    </div>
                                                    <div className="text-[10px] text-zinc-500">
                                                        Last Sale: {item.best_performing_warehouse.last_sale}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-[11px] mt-0.5">
                                                        <span className="text-emerald-600 font-bold uppercase">Stok: {item.best_performing_warehouse.qty}</span>
                                                        <span className="text-zinc-400">({item.best_performing_warehouse.days_ago}d ago)</span>
                                                    </div>
                                                </div>
                                            ) : '-'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className={`font-mono font-bold text-base ${
                                                item.gap_days === 'NEVER SOLD' ? 'text-zinc-400' : 
                                                (Number(item.gap_days) > 30 ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100')
                                            }`}>
                                                {item.gap_days === 'NEVER SOLD' ? '-' : `+${item.gap_days}`}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end">
                                                {item.best_performing_warehouse && item.best_performing_warehouse.name === item.current_warehouse.name ? (
                                                    <div className="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-900/20 px-2.5 py-1.5 rounded-md border border-emerald-100 dark:border-emerald-800/30 shadow-sm">
                                                        <CheckCircle className="h-3.5 w-3.5" />
                                                        Gudang Terbaik
                                                    </div>
                                                ) : (
                                                    <Button variant="outline" size="sm" asChild className="h-8 border-zinc-200 dark:border-zinc-800 shadow-sm">
                                                        <Link href={`/reports/rebalance-detail?item_id=${item.item_id}&warehouse_id=${item.current_warehouse.id}`}>
                                                            <TrendingUp className="h-3.5 w-3.5 mr-1.5 text-blue-500" />
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
                                    <TableCell colSpan={7} className="h-32 text-center text-zinc-500 italic">Data kosong.</TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </Card>

                {/* Pagination */}
                {data.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4">
                        <p className="text-xs text-zinc-500 font-medium">Halaman {data.current_page} dari {data.last_page} ({data.total} item)</p>
                        <div className="flex gap-1">
                            {data.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-1.5 rounded-md text-xs font-bold border transition-all ${
                                        link.active 
                                            ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-100 dark:text-zinc-900' 
                                            : 'bg-white text-zinc-600 border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800'
                                    } ${!link.url && 'opacity-30 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
