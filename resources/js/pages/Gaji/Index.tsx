import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Eye, Edit, Trash2, Printer, Search } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { useState } from 'react';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';

export default function Index({ gajiList, gajiPerBank, bulanSelect, yearSelect, filters, auth }: any) {
    const [search, setSearch] = useState(filters.karyawan || '');
    const [bulan, setBulan] = useState(bulanSelect.toString());
    const [tahun, setTahun] = useState(yearSelect.toString());

    const isSuperAdmin = auth?.roles?.includes('superadmin');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Gaji Bulanan', href: '/gaji' },
    ];

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get('/gaji', { bulan, tahun, karyawan: search }, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this Gaji record?')) {
            router.delete(`/gaji/${id}`);
        }
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'decimal',
            minimumFractionDigits: 0,
        }).format(value);
    };

    // Calculate Grand Total for all banks
    const grandTotalBank = gajiPerBank.reduce((sum: number, item: any) => sum + Number(item.total_gaji), 0);

    const monthNames = [
        "Januari", "Februari", "Maret", "April", "Mei", "Juni",
        "Juli", "Agustus", "September", "Oktober", "November", "Desember"
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gaji Bulanan" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">Gaji Bulanan Karyawan</h1>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-lg border shadow-sm dark:bg-gray-800 dark:border-gray-700 flex flex-wrap gap-4 items-end">
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Bulan</label>
                        <Select value={bulan} onValueChange={(v) => { setBulan(v); setTimeout(() => router.get('/gaji', { bulan: v, tahun, karyawan: search }), 100); }}>
                            <SelectTrigger className="w-[150px]">
                                <SelectValue placeholder="Bulan" />
                            </SelectTrigger>
                            <SelectContent>
                                {monthNames.map((m, i) => (
                                    <SelectItem key={i} value={(i + 1).toString()}>{m}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Tahun</label>
                        <Input
                            type="number"
                            className="w-[120px]"
                            value={tahun}
                            onChange={(e) => setTahun(e.target.value)}
                            onBlur={handleFilter}
                        />
                    </div>

                    <form onSubmit={handleFilter} className="flex gap-2 flex-1">
                        <div className="space-y-2 flex-1 max-w-xs">
                            <label className="text-sm font-medium">Cari Karyawan</label>
                            <Input
                                placeholder="Nama Karyawan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <div className="pb-0 self-end">
                            <Button type="submit" variant="secondary">
                                <Search className="h-4 w-4 mr-2" /> Cari
                            </Button>
                        </div>
                    </form>
                </div>

                {isSuperAdmin && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Card className="bg-blue-50/50">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm text-muted-foreground uppercase">Grand Total Gaji</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-700">{formatCurrency(grandTotalBank)}</div>
                            </CardContent>
                        </Card>
                        {gajiPerBank.map((bank: any) => (
                            <Card key={bank.bank_id}>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm text-muted-foreground uppercase">{bank.bank?.name || 'Kas Tunai'}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-gray-800 dark:text-gray-100">{formatCurrency(bank.total_gaji)}</div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Card className="flex-1">
                    <CardContent className="p-0">
                        <div className="relative w-full overflow-auto">
                            <table className="w-full caption-bottom text-sm">
                                <thead className="bg-gray-100 dark:bg-gray-800">
                                    <tr className="border-b transition-colors hover:bg-muted/50">
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Nama</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Bank</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">HK (Rp/Hari)</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Bulanan</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Sanksi & Potongan</th>
                                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground uppercase text-xs">Total Gaji</th>
                                        {isSuperAdmin && (
                                            <th className="h-12 px-4 text-center align-middle font-medium text-muted-foreground uppercase text-xs">Aksi</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {gajiList?.data?.length > 0 ? (
                                        gajiList.data.map((item: any) => (
                                            <tr key={item.id} className="border-b transition-colors hover:bg-muted/50">
                                                <td className="p-4 align-middle font-medium">
                                                    <Link href={`/karyawan/${item.karyawan_id}`} className="text-blue-600 hover:underline">
                                                        {item.karyawan?.nama}
                                                    </Link>
                                                </td>
                                                <td className="p-4 align-middle">{item.bank_single?.name || '-'}</td>
                                                <td className="p-4 align-middle">{formatCurrency(item.harian)}</td>
                                                <td className="p-4 align-middle">{formatCurrency(item.bulanan)}</td>
                                                <td className="p-4 align-middle text-red-600">
                                                    {formatCurrency((item.total_potongan || 0) + (item.sanksi || 0))}
                                                </td>
                                                <td className="p-4 align-middle font-bold text-green-700">
                                                    {formatCurrency(item.total_gaji)}
                                                </td>
                                                {isSuperAdmin && (
                                                    <td className="p-4 align-middle text-center">
                                                        <div className="flex items-center justify-center gap-2">
                                                            <Button variant="outline" size="sm" asChild>
                                                                <a href={`/gaji/cetak/${item.id}`} target="_blank">
                                                                    <Printer className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                            <Button variant="destructive" size="icon" onClick={() => handleDelete(item.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={isSuperAdmin ? 7 : 6} className="p-4 text-center text-muted-foreground">
                                                Belum ada data gaji pada bulan/tahun ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {gajiList?.links && gajiList.last_page > 1 && (
                            <div className="py-4 flex justify-center px-4 border-t">
                                <Pagination>
                                    <PaginationContent>
                                        {gajiList.links.map((link: any, index: number) => {
                                            if (link.url === null) {
                                                return (
                                                    <PaginationItem key={index}>
                                                        <span className="px-3 py-2 opacity-50 cursor-not-allowed text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    </PaginationItem>
                                                );
                                            }

                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationLink
                                                        href={link.url}
                                                        isActive={link.active}
                                                        size="sm"
                                                    >
                                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    </PaginationLink>
                                                </PaginationItem>
                                            );
                                        })}
                                    </PaginationContent>
                                </Pagination>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
