import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Filter, X, Clock, Calendar, Warehouse, Plus } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';

interface StagnantItem {
    item_id: number;
    item_name: string;
    item_code: string;
    warehouse_id: number;
    warehouse_name: string;
    current_stock: number | string;
    last_sold_at: string | null;
    status: string;
    warning: string;
    color: string;
    potential_count: number;
}

interface Props {
    items: {
        data: StagnantItem[];
        links: any[];
    };
    filters: {
        search: string | null;
        stagnancy: string | null;
    };
}

export default function StockIntelligence({ items, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [stagnancy, setStagnancy] = useState(filters.stagnancy || 'default');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '#' },
        { title: 'Stock Intelligence', href: '/reports/stock-intelligence' },
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports/stock-intelligence', { search, stagnancy }, { preserveState: true });
    };

    const handleClear = () => {
        setSearch('');
        setStagnancy('default');
        router.get('/reports/stock-intelligence');
    };

    const getBadgeClass = (color: string) => {
        switch (color) {
            case 'rose': return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 border-rose-200';
            case 'zinc': return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 border-zinc-200';
            case 'amber': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 border-amber-200';
            default: return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 border-blue-200';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock Intelligence" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Stock Intelligence & Rebalancing</h1>
                    <p className="text-zinc-500 dark:text-zinc-400">
                        Analisis barang macet dan rekomendasi pemindahan ke gudang dengan permintaan tinggi.
                    </p>
                </div>

                <Card>
                    <CardHeader className="p-4 sm:p-6 pb-4 sm:pb-4">
                        <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-1.5 w-full md:w-[300px]">
                                <Label htmlFor="search">Cari Item</Label>
                                <Input 
                                    id="search"
                                    placeholder="Nama atau kode barang..." 
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1.5 w-[200px]">
                                <Label htmlFor="stagnancy">Kriteria Stagnasi</Label>
                                <Select value={stagnancy} onValueChange={setStagnancy}>
                                    <SelectTrigger id="stagnancy">
                                        <SelectValue placeholder="Pilih Kriteria" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="default">Semua Bermasalah (> 7 Hari)</SelectItem>
                                        <SelectItem value="never">Belum Pernah Terjual</SelectItem>
                                        <SelectItem value="7">Hanya 7 - 30 Hari</SelectItem>
                                        <SelectItem value="30">Hanya 30 - 90 Hari</SelectItem>
                                        <SelectItem value="90">Hanya > 90 Hari (Deadstock)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button type="button" variant="outline" onClick={handleClear}>
                                    <X className="mr-2 h-4 w-4" />
                                    Bersihkan
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                </Card>

                <div className="rounded-md border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-zinc-50 dark:bg-zinc-900/50">
                                <TableHead className="w-[250px]">Item Details</TableHead>
                                <TableHead>Current Location</TableHead>
                                <TableHead className="text-center">Last Sale</TableHead>
                                <TableHead className="text-center">Stock</TableHead>
                                <TableHead className="w-[180px]">Status</TableHead>
                                <TableHead className="w-[250px]">Smart Suggestion</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="h-24 text-center">Tidak ada data stagnant.</TableCell>
                                </TableRow>
                            ) : (
                                items.data.map((item, idx) => (
                                    <TableRow key={`${item.item_id}-${item.warehouse_id}-${idx}`} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors">
                                        <TableCell>
                                            <div className="font-bold text-sm">{item.item_name}</div>
                                            <div className="text-[10px] text-zinc-500 font-mono uppercase">{item.item_code}</div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2 text-xs font-semibold">
                                                <Warehouse className="h-3.5 w-3.5 text-zinc-400" />
                                                {item.warehouse_name}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {item.last_sold_at ? (
                                                <div className="flex flex-col items-center">
                                                    <div className="flex items-center gap-1 text-xs font-mono font-bold text-rose-600">
                                                        <Calendar className="h-3 w-3" />
                                                        {item.last_sold_at}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-[10px] font-bold text-zinc-400 italic">NEVER SOLD</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className="text-sm font-mono font-bold text-blue-600">
                                                {item.current_stock}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase ${getBadgeClass(item.color)}`}>
                                                <Clock className="h-3 w-3" />
                                                {item.status}
                                            </div>
                                            <p className="text-[10px] mt-1 text-zinc-500 leading-tight">
                                                {item.warning}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {item.potential_count > 0 ? (
                                                <div className="flex items-center gap-3">
                                                    <Button 
                                                        size="sm" 
                                                        variant="outline"
                                                        className="h-9 px-3 border-emerald-200 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-all gap-2 font-bold shadow-sm"
                                                        onClick={() => router.get('/reports/rebalance-detail', {
                                                            item_id: item.item_id,
                                                            warehouse_id: item.warehouse_id
                                                        })}
                                                    >
                                                        <Plus className="h-4 w-4" />
                                                        Rebalance
                                                    </Button>
                                                    <div className="flex flex-col">
                                                        <span className="text-[10px] font-black text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-1.5 py-0.5 rounded-md border border-emerald-100 dark:border-emerald-800">
                                                            {item.potential_count} Gudang
                                                        </span>
                                                        <span className="text-[8px] text-zinc-400 uppercase font-bold mt-0.5">Potensial</span>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="text-[10px] text-zinc-400 italic bg-zinc-50 dark:bg-zinc-900/50 py-2 px-3 rounded-md border border-dashed text-center">
                                                    Permintaan di gudang lain rendah
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="mt-4">
                    <Pagination links={items.links} />
                </div>
            </div>
        </AppLayout>
    );
}
